<?php
require_once __DIR__ . '/../auth.php';
requireRole(['coordinator']);
require_once __DIR__ . '/../db/db.php';

// Add PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path to your autoloader

// Coordinator session verification
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, profile_picture FROM coordinators WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $coordinator = $stmt->fetch();
    
    if (!$coordinator) {
        header('Location: ../login.php');
        exit();
    }
    
    $user_name = $coordinator['name'];
    
    // Check if profile picture exists
    $profile_picture = '../images/default-user.png';
    if (!empty($coordinator['profile_picture'])) {
        $imagePath = '../uploads/profile_pictures/' . $coordinator['profile_picture'];
        if (file_exists($imagePath)) {
            $profile_picture = $imagePath;
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}

// Common stopwords to ignore in similarity calculations
$stopwords = [
    'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
    'from', 'as', 'is', 'was', 'are', 'be', 'been', 'being', 'have', 'has', 'had', 'do',
    'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can',
    'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
    'what', 'which', 'who', 'when', 'where', 'why', 'how', 'all', 'each', 'every',
    'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only',
    'same', 'so', 'than', 'too', 'very', 'just', 'about', 'above', 'after', 'again',
    'against', 'before', 'between', 'down', 'during', 'further', 'into', 'through',
    'under', 'until', 'up', 'while', 'using', 'used', 'use', 'uses'
];

// Simple stemming function - reduces words to base form
function simpleStem($word) {
    $word = strtolower($word);
    
    // Remove common suffixes
    $suffixes = ['ing', 'ed', 'ly', 'tion', 'sion', 'ment', 'ness', 'able', 'ible', 'ful', 'less', 'ous', 'ive', 'ity', 'er', 'est', 's', 'es'];
    
    foreach ($suffixes as $suffix) {
        if (strlen($word) > strlen($suffix) + 2 && substr($word, -strlen($suffix)) === $suffix) {
            return substr($word, 0, -strlen($suffix));
        }
    }
    
    return $word;
}

// Preprocess text: lowercase, remove punctuation, split into words
function preprocessText($text) {
    global $stopwords;
    
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s]/', ' ', $text);
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    
    // Remove stopwords and stem remaining words
    $filtered = [];
    foreach ($words as $word) {
        if (!in_array($word, $stopwords) && strlen($word) > 2) {
            $filtered[] = simpleStem($word);
        }
    }
    
    return $filtered;
}

// Calculate weighted word similarity (0-100)
function calculateWeightedWordSimilarity($str1, $str2) {
    $words1 = preprocessText($str1);
    $words2 = preprocessText($str2);
    
    if (empty($words1) || empty($words2)) return 0;
    
    // Count word frequencies
    $freq1 = array_count_values($words1);
    $freq2 = array_count_values($words2);
    
    // Find common words and calculate weighted overlap
    $commonWords = array_intersect_key($freq1, $freq2);
    $commonScore = 0;
    
    foreach ($commonWords as $word => $count) {
        $weight = min($freq1[$word], $freq2[$word]);
        $commonScore += $weight;
    }
    
    $totalWords = max(count($words1), count($words2));
    $similarity = ($commonScore / $totalWords) * 100;
    
    return round($similarity, 2);
}

// Check for consecutive word matches (at least 3 consecutive words)
function checkConsecutiveMatches($str1, $str2) {
    $words1 = preg_split('/\s+/', strtolower(trim($str1)), -1, PREG_SPLIT_NO_EMPTY);
    $words2 = preg_split('/\s+/', strtolower(trim($str2)), -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($words1) || empty($words2)) return 0;
    
    $maxConsecutive = 0;
    
    // Check for consecutive word matches
    for ($i = 0; $i < count($words1); $i++) {
        for ($j = 0; $j < count($words2); $j++) {
            $consecutive = 0;
            $k = 0;
            
            while ($i + $k < count($words1) && $j + $k < count($words2) && 
                   $words1[$i + $k] === $words2[$j + $k]) {
                $consecutive++;
                $k++;
            }
            
            if ($consecutive > $maxConsecutive) {
                $maxConsecutive = $consecutive;
            }
        }
    }
    
    // If 3+ consecutive words match, calculate percentage
    if ($maxConsecutive >= 3) {
        $totalWords = max(count($words1), count($words2));
        return round(($maxConsecutive / $totalWords) * 100, 2);
    }
    
    return 0;
}

// Check if 70% of unique keywords are shared
function checkKeywordOverlap($str1, $str2) {
    $words1 = preprocessText($str1);
    $words2 = preprocessText($str2);
    
    if (empty($words1) || empty($words2)) return 0;
    
    $unique1 = array_unique($words1);
    $unique2 = array_unique($words2);
    
    $commonKeywords = array_intersect($unique1, $unique2);
    $totalUnique = max(count($unique1), count($unique2));
    
    $overlap = (count($commonKeywords) / $totalUnique) * 100;
    
    return $overlap >= 70 ? round($overlap, 2) : 0;
}

// Calculate Levenshtein similarity percentage (for character-level matching)
function calculateLevenshteinSimilarity($str1, $str2) {
    $distance = levenshteinDistance(strtolower($str1), strtolower($str2));
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100, 2);
}

function levenshteinDistance($str1, $str2) {
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    
    if ($len1 == 0) return $len2;
    if ($len2 == 0) return $len1;
    
    $d = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));
    
    for ($i = 0; $i <= $len1; $i++) $d[$i][0] = $i;
    for ($j = 0; $j <= $len2; $j++) $d[0][$j] = $j;
    
    for ($i = 1; $i <= $len1; $i++) {
        for ($j = 1; $j <= $len2; $j++) {
            $cost = ($str1[$i - 1] == $str2[$j - 1]) ? 0 : 1;
            $d[$i][$j] = min(
                $d[$i - 1][$j] + 1,
                $d[$i][$j - 1] + 1,
                $d[$i - 1][$j - 1] + $cost
            );
        }
    }
    
    return $d[$len1][$len2];
}

function calculateCombinedSimilarity($str1, $str2) {
    $weightedWordSim = calculateWeightedWordSimilarity($str1, $str2);
    $consecutiveMatch = checkConsecutiveMatches($str1, $str2);
    $keywordOverlap = checkKeywordOverlap($str1, $str2);
    $levenshteinSim = calculateLevenshteinSimilarity($str1, $str2);
    
    // Prioritize detection methods in order of reliability
    if ($consecutiveMatch >= 30) {
        return $consecutiveMatch; // 3+ consecutive words is strong indicator
    }
    
    if ($keywordOverlap > 0) {
        return $keywordOverlap; // 70%+ keyword overlap is strong indicator
    }
    
    if ($weightedWordSim >= 60) {
        return $weightedWordSim; // Weighted word similarity
    }
    
    return $levenshteinSim; // Fallback to character-level matching
}

function clusterDuplicates($thesis_data, $duplicates) {
    $clusters = [];
    $visited = [];
    $clusterCounter = 0;
    
    foreach ($thesis_data as $item) {
        $id = $item['id'];
        
        if (isset($visited[$id])) {
            continue;
        }
        
        // Start a new cluster
        $cluster = [];
        $queue = [$id];
        
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            
            if (isset($visited[$currentId])) {
                continue;
            }
            
            $visited[$currentId] = true;
            $cluster[] = $currentId;
            
            // Add all related duplicates to queue
            if (isset($duplicates[$currentId])) {
                foreach ($duplicates[$currentId] as $dup) {
                    if (!isset($visited[$dup['id']])) {
                        $queue[] = $dup['id'];
                    }
                }
            }
        }
        
        // Only create cluster if it has more than one item
        if (count($cluster) > 1) {
            $clusterCounter++;
            $clusters[$clusterCounter] = $cluster;
        }
    }
    
    return $clusters;
}

function extractCommonKeywords($titles) {
    if (empty($titles)) return [];
    
    global $stopwords;
    
    $allWords = [];
    foreach ($titles as $title) {
        $words = preprocessText($title);
        $allWords = array_merge($allWords, $words);
    }
    
    $wordFreq = array_count_values($allWords);
    arsort($wordFreq);
    
    // Return top 3-5 most common keywords
    return array_slice(array_keys($wordFreq), 0, 5);
}

// Function to mark thesis as verified
function markThesisAsVerified($pdo, $thesisId, $coordinatorId, $notes = null) {
    try {
        // Check if verification record already exists
        $checkStmt = $pdo->prepare("SELECT id FROM thesis_verifications WHERE thesis_id = ?");
        $checkStmt->execute([$thesisId]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE thesis_verifications 
                SET verification_status = 'verified',
                    verified_at = NOW(),
                    verified_by = ?,
                    verification_notes = ?,
                    updated_at = NOW()
                WHERE thesis_id = ?
            ");
            return $stmt->execute([$coordinatorId, $notes, $thesisId]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO thesis_verifications 
                (thesis_id, verification_status, verified_at, verified_by, verification_notes)
                VALUES (?, 'verified', NOW(), ?, ?)
            ");
            return $stmt->execute([$thesisId, $coordinatorId, $notes]);
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        return false;
    }
}

// Function to send email to advisor about duplicate using PHPMailer
function sendDuplicateEmailToAdvisor($pdo, $advisorId, $thesisTitle, $groupName, $duplicateTitles, $coordinatorName) {
    try {
        // Get advisor email
        $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM advisors WHERE id = ?");
        $stmt->execute([$advisorId]);
        $advisor = $stmt->fetch();
        
        if (!$advisor || empty($advisor['email'])) {
            error_log("Advisor not found or no email for ID: " . $advisorId);
            return false;
        }
        
        $advisorEmail = $advisor['email'];
        $advisorName = $advisor['first_name'] . ' ' . $advisor['last_name'];
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'klarerivera25@gmail.com'; // Your Gmail address
        $mail->Password = 'bztg uiur xzho wslv'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('klarerivera25@gmail.com', 'ThesisTrack System');
        $mail->addAddress($advisorEmail, $advisorName); // Add a recipient
        $mail->addReplyTo('klarerivera25@gmail.com', $coordinatorName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Thesis Title Duplicate Alert - ' . $groupName;
        
        // Email body
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    line-height: 1.6; 
                    color: #333; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f9f9f9;
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: #ffffff; 
                    border-radius: 10px; 
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 30px 20px; 
                    text-align: center; 
                    color: white;
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 24px; 
                    font-weight: 600;
                }
                .content { 
                    padding: 30px; 
                }
                .alert-box { 
                    background: #fff3cd; 
                    border: 1px solid #ffeaa7; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0; 
                }
                .duplicate-list { 
                    background: #f8f9fa; 
                    border-radius: 6px; 
                    padding: 15px; 
                    margin: 15px 0; 
                }
                .action-required { 
                    background: #d1ecf1; 
                    border: 1px solid #bee5eb; 
                    border-radius: 8px; 
                    padding: 20px; 
                    margin: 20px 0;
                }
                .footer { 
                    margin-top: 30px; 
                    padding-top: 20px; 
                    border-top: 1px solid #e9ecef; 
                    font-size: 12px; 
                    color: #6c757d; 
                    text-align: center;
                }
                .btn { 
                    display: inline-block; 
                    padding: 10px 20px; 
                    background: #28a745; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px; 
                    margin: 10px 0; 
                }
                ul { 
                    margin: 10px 0; 
                    padding-left: 20px; 
                }
                li { 
                    margin-bottom: 8px; 
                }
                .similarity-badge {
                    background: #e9ecef;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 12px;
                    margin-left: 10px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>ThesisTrack - Duplicate Title Alert</h1>
                </div>
                <div class='content'>
                    <p>Dear <strong>Prof. " . htmlspecialchars($advisorName) . "</strong>,</p>
                    
                    <div class='alert-box'>
                        <h3>🚨 Important Notification</h3>
                        <p>The thesis title for group <strong>\"" . htmlspecialchars($groupName) . "\"</strong> has been flagged by our system as potentially similar to other titles.</p>
                    </div>
                    
                    <div class='duplicate-list'>
                        <h4>Title Details:</h4>
                        <p><strong>Group's Title:</strong><br>
                        <em>\"" . htmlspecialchars($thesisTitle) . "\"</em></p>
                        
                        <h4>Similar Titles Found:</h4>
                        <ul>
        ";
        
        foreach ($duplicateTitles as $dup) {
            $similarityColor = $dup['similarity'] >= 80 ? '#dc3545' : '#fd7e14';
            $emailBody .= "<li>\"" . htmlspecialchars($dup['title']) . "\" 
                <span class='similarity-badge' style='background: " . $similarityColor . "; color: white;'>" . $dup['similarity'] . "% similar</span>
            </li>";
        }
        
        $emailBody .= "
                        </ul>
                    </div>
                    
                    <div class='action-required'>
                        <h4>✅ Action Required:</h4>
                        <ul>
                            <li><strong>Review the thesis title</strong> with your student group</li>
                            <li><strong>Consider modifying the title</strong> to make it more distinctive if necessary</li>
                            <li><strong>Coordinate with other advisors</strong> if similar titles are intentional (e.g., related research topics)</li>
                            <li><strong>Contact the research coordinator</strong> if you believe this is a false positive</li>
                        </ul>
                    </div>
                    
                    <p>Please address this matter at your earliest convenience to ensure the uniqueness of your students' research work.</p>
                    
                    <p>Best regards,<br>
                    <strong>" . htmlspecialchars($coordinatorName) . "</strong><br>
                    Research Coordinator<br>
                    College of Information and Communication Technology<br>
                    ThesisTrack System</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from ThesisTrack System. Please do not reply to this email.</p>
                    <p>If you have questions, please contact the research coordinator directly.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $emailBody;
        
        // Alternative body for non-HTML mail clients
        $textBody = "Thesis Title Duplicate Alert\n\n";
        $textBody .= "Dear Prof. " . $advisorName . ",\n\n";
        $textBody .= "The thesis title for your group \"" . $groupName . "\" has been flagged as potentially similar to other titles.\n\n";
        $textBody .= "Your Group's Title: \"" . $thesisTitle . "\"\n\n";
        $textBody .= "Similar Titles Found:\n";
        foreach ($duplicateTitles as $dup) {
            $textBody .= "- \"" . $dup['title'] . "\" (" . $dup['similarity'] . "% similar)\n";
        }
        $textBody .= "\nAction Required:\n";
        $textBody .= "- Review the thesis title with your students\n";
        $textBody .= "- Consider modifying the title if necessary\n";
        $textBody .= "- Coordinate with other advisors if needed\n";
        $textBody .= "- Contact the research coordinator for questions\n\n";
        $textBody .= "Best regards,\n" . $coordinatorName . "\nResearch Coordinator";
        
        $mail->AltBody = $textBody;
        
        // Send email
        $mail->send();
        
        error_log("Duplicate email sent successfully to advisor: " . $advisorEmail);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer error: " . $e->getMessage());
        error_log("Failed to send duplicate email to advisor: " . $advisorEmail);
        return false;
    } catch (PDOException $e) {
        error_log("Database error in email function: " . $e->getMessage());
        return false;
    }
}

// Function to report duplicate to advisor
function reportDuplicateToAdvisor($pdo, $thesisId, $advisorId, $coordinatorId, $reason = null, $duplicateData = null) {
    try {
        // Get coordinator name
        $coordStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM coordinators WHERE id = ?");
        $coordStmt->execute([$coordinatorId]);
        $coordinator = $coordStmt->fetch();
        $coordinatorName = $coordinator['name'] ?? 'Research Coordinator';
        
        // Get thesis details for email
        $thesisStmt = $pdo->prepare("SELECT thesis_title, group_name FROM student_groups WHERE id = ?");
        $thesisStmt->execute([$thesisId]);
        $thesis = $thesisStmt->fetch();
        
        $emailStatus = 'not_sent';
        $emailError = '';
        
        // Send email to advisor
        if ($thesis && $duplicateData) {
            $emailSent = sendDuplicateEmailToAdvisor(
                $pdo, 
                $advisorId, 
                $thesis['thesis_title'], 
                $thesis['group_name'], 
                $duplicateData, 
                $coordinatorName
            );
            
            if ($emailSent) {
                $emailStatus = 'sent';
            } else {
                $emailStatus = 'failed';
                $emailError = 'Email sending failed but duplicate was recorded';
            }
        }
        
        // Check if verification record already exists
        $checkStmt = $pdo->prepare("SELECT id FROM thesis_verifications WHERE thesis_id = ?");
        $checkStmt->execute([$thesisId]);
        $existing = $checkStmt->fetch();
        
        $verificationNotes = $reason;
        if ($emailStatus === 'sent') {
            $verificationNotes .= " (Email sent to advisor)";
        } elseif ($emailStatus === 'failed') {
            $verificationNotes .= " (Email failed to send)";
        }
        
        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE thesis_verifications 
                SET verification_status = 'duplicate_reported',
                    verified_at = NOW(),
                    verified_by = ?,
                    verification_notes = ?,
                    updated_at = NOW()
                WHERE thesis_id = ?
            ");
            $result = $stmt->execute([$coordinatorId, $verificationNotes, $thesisId]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("
                INSERT INTO thesis_verifications 
                (thesis_id, verification_status, verified_at, verified_by, verification_notes)
                VALUES (?, 'duplicate_reported', NOW(), ?, ?)
            ");
            $result = $stmt->execute([$thesisId, $coordinatorId, $verificationNotes]);
        }
        
        return [
            'success' => $result,
            'email_sent' => $emailStatus === 'sent',
            'email_error' => $emailError
        ];
        
    } catch (PDOException $e) {
        error_log("Duplicate report error: " . $e->getMessage());
        return [
            'success' => false,
            'email_sent' => false,
            'email_error' => 'Database error'
        ];
    }
}

// Fetch all thesis groups with advisor information and verification status
$thesis_data = [];
try {
    $stmt = $pdo->query("
        SELECT 
            sg.id,
            sg.group_name,
            sg.thesis_title,
            sg.section,
            sg.course,
            sg.status,
            sg.advisor_id,
            CONCAT(a.first_name, ' ', a.last_name) AS advisor_name,
            tv.verification_status,
            tv.verified_at,
            tv.verification_notes,
            CONCAT(c.first_name, ' ', c.last_name) AS verified_by_name
        FROM student_groups sg
        LEFT JOIN advisors a ON sg.advisor_id = a.id
        LEFT JOIN thesis_verifications tv ON sg.id = tv.thesis_id
        LEFT JOIN coordinators c ON tv.verified_by = c.id
        ORDER BY sg.thesis_title ASC
    ");
    $thesis_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $thesis_data = [];
}

// Initialize threshold from session, POST, or default
if (isset($_SESSION['similarity_threshold'])) {
    $similarity_threshold = $_SESSION['similarity_threshold'];
} elseif (isset($_POST['similarity_threshold'])) {
    $similarity_threshold = (int)$_POST['similarity_threshold'];
    $_SESSION['similarity_threshold'] = $similarity_threshold;
} else {
    $similarity_threshold = 60;
    $_SESSION['similarity_threshold'] = $similarity_threshold;
}

$duplicates = [];

for ($i = 0; $i < count($thesis_data); $i++) {
    for ($j = $i + 1; $j < count($thesis_data); $j++) {
        $similarity = calculateCombinedSimilarity($thesis_data[$i]['thesis_title'], $thesis_data[$j]['thesis_title']);
        
        if ($similarity >= $similarity_threshold) {
            $id1 = $thesis_data[$i]['id'];
            $id2 = $thesis_data[$j]['id'];
            
            if (!isset($duplicates[$id1])) {
                $duplicates[$id1] = [];
            }
            if (!isset($duplicates[$id2])) {
                $duplicates[$id2] = [];
            }
            
            $duplicates[$id1][] = [
                'id' => $id2,
                'title' => $thesis_data[$j]['thesis_title'],
                'similarity' => $similarity
            ];
            $duplicates[$id2][] = [
                'id' => $id1,
                'title' => $thesis_data[$i]['thesis_title'],
                'similarity' => $similarity
            ];
        }
    }
}

$clusters = clusterDuplicates($thesis_data, $duplicates);

// Create lookup for thesis data by ID
$thesisById = [];
foreach ($thesis_data as $item) {
    $thesisById[$item['id']] = $item;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'export_excel') {
        // Set proper headers for CSV download
        $filename = 'thesis_titles_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Summary section
        fputcsv($output, ['THESIS TITLES DUPLICATE ANALYSIS REPORT']);
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['SUMMARY']);
        fputcsv($output, ['Total Thesis Titles', count($thesis_data)]);
        fputcsv($output, ['Duplicate Groups Found', count($clusters)]);
        fputcsv($output, ['Unique Titles', count($thesis_data) - count(array_keys($duplicates))]);
        fputcsv($output, ['Similarity Threshold', $similarity_threshold . '%']);
        fputcsv($output, []);
        fputcsv($output, []);
        
        // Grouped duplicates section
        if (!empty($clusters)) {
            fputcsv($output, ['DUPLICATE GROUPS']);
            fputcsv($output, []);
            
            foreach ($clusters as $groupNum => $clusterIds) {
                fputcsv($output, ['GROUP ' . $groupNum . ' - SIMILAR TITLES']);
                
                // Extract common keywords for this group
                $groupTitles = [];
                foreach ($clusterIds as $id) {
                    if (isset($thesisById[$id])) {
                        $groupTitles[] = $thesisById[$id]['thesis_title'];
                    }
                }
                $commonKeywords = extractCommonKeywords($groupTitles);
                
                fputcsv($output, ['Common Keywords: ' . implode(', ', $commonKeywords)]);
                fputcsv($output, []);
                
                // Headers for group
                fputcsv($output, ['Group Name', 'Thesis Title', 'Advisor Name', 'Section', 'Course', 'Status', 'Similarity %']);
                
                // Group items
                foreach ($clusterIds as $id) {
                    if (isset($thesisById[$id])) {
                        $row = $thesisById[$id];
                        
                        // Calculate average similarity for this item in the group
                        $avgSimilarity = 0;
                        if (isset($duplicates[$id])) {
                            $similarities = array_map(function($dup) { return $dup['similarity']; }, $duplicates[$id]);
                            $avgSimilarity = round(array_sum($similarities) / count($similarities), 2);
                        }
                        
                        fputcsv($output, [
                            $row['group_name'],
                            $row['thesis_title'],
                            $row['advisor_name'] ?? 'N/A',
                            $row['section'],
                            $row['course'],
                            $row['status'],
                            $avgSimilarity . '%'
                        ]);
                    }
                }
                
                fputcsv($output, []);
                fputcsv($output, []);
            }
        }
        
        // All titles section
        fputcsv($output, ['ALL THESIS TITLES']);
        fputcsv($output, ['Group Name', 'Thesis Title', 'Advisor Name', 'Section', 'Course', 'Status', 'Duplicate Status']);
        
        foreach ($thesis_data as $row) {
            $duplicate_status = isset($duplicates[$row['id']]) ? 'POSSIBLE DUPLICATE' : 'UNIQUE';
            
            fputcsv($output, [
                $row['group_name'],
                $row['thesis_title'],
                $row['advisor_name'] ?? 'N/A',
                $row['section'],
                $row['course'],
                $row['status'],
                $duplicate_status
            ]);
        }
        
        fclose($output);
        exit();
    }
    
    if ($_POST['action'] === 'get_duplicates_only') {
        $duplicates_only = array_filter($thesis_data, function($item) use ($duplicates) {
            return isset($duplicates[$item['id']]);
        });
        
        echo json_encode([
            'success' => true,
            'data' => array_values($duplicates_only),
            'duplicates' => $duplicates,
            'clusters' => $clusters
        ]);
        exit();
    }
    
    if ($_POST['action'] === 'update_threshold') {
        $new_threshold = (int)$_POST['threshold'];
        if ($new_threshold >= 0 && $new_threshold <= 100) {
            $_SESSION['similarity_threshold'] = $new_threshold;
            echo json_encode(['success' => true, 'threshold' => $new_threshold]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid threshold value']);
        }
        exit();
    }
    
    // Handle verification AJAX requests
    if ($_POST['action'] === 'verify_thesis') {
        $thesisId = (int)$_POST['thesis_id'];
        $coordinatorId = $_SESSION['user_id'];
        $notes = $_POST['notes'] ?? null;
        
        if (markThesisAsVerified($pdo, $thesisId, $coordinatorId, $notes)) {
            // Create notification
            createCoordinatorNotification($pdo, 
                "Thesis Verified", 
                "Thesis title has been verified as unique", 
                'success', 
                $thesisId
            );
            
            echo json_encode(['success' => true, 'message' => 'Thesis verified successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to verify thesis']);
        }
        exit();
    }
    
    if ($_POST['action'] === 'report_duplicate') {
        $thesisId = (int)$_POST['thesis_id'];
        $advisorId = (int)$_POST['advisor_id'];
        $coordinatorId = $_SESSION['user_id'];
        $reason = $_POST['reason'] ?? 'Potential duplicate title detected';
        
        // Parse duplicate data if provided
        $duplicateData = [];
        if (isset($_POST['duplicate_data']) && !empty($_POST['duplicate_data'])) {
            $duplicateData = json_decode($_POST['duplicate_data'], true);
        }
        
        $result = reportDuplicateToAdvisor($pdo, $thesisId, $advisorId, $coordinatorId, $reason, $duplicateData);
        
        if ($result['success']) {
            // Create notification
            createCoordinatorNotification($pdo, 
                "Duplicate Reported", 
                "Duplicate thesis title reported to advisor" . ($result['email_sent'] ? " via email" : ""), 
                $result['email_sent'] ? 'success' : 'warning', 
                $thesisId
            );
            
            $message = 'Duplicate reported to advisor successfully';
            if ($result['email_sent']) {
                $message .= ' and email sent';
            } elseif (!empty($result['email_error'])) {
                $message .= ' (but email failed to send)';
            }
            
            echo json_encode([
                'success' => true, 
                'message' => $message,
                'email_sent' => $result['email_sent']
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to report duplicate',
                'email_sent' => false
            ]);
        }
        exit();
    }
}

// Helper function to get profile picture
function getProfilePicture($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM coordinators WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['profile_picture'])) {
            $imagePath = '../uploads/profile_pictures/' . $result['profile_picture'];
            if (file_exists($imagePath)) {
                return $imagePath;
            }
        }
        return '../images/default-user.png';
    } catch (PDOException $e) {
        error_log("Profile picture error: " . $e->getMessage());
        return '../images/default-user.png';
    }
}

// Notification functions
function createCoordinatorNotification($pdo, $title, $message, $type = 'info', $group_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, type, is_read, group_id, created_at) 
            SELECT id, 'coordinator', ?, ?, ?, 0, ?, NOW() 
            FROM coordinators 
            WHERE status = 'active'
        ");
        $stmt->execute([$title, $message, $type, $group_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Fetch coordinator-specific notifications
try {
    $notification_stmt = $pdo->prepare("
        SELECT n.*, sg.group_name 
        FROM notifications n 
        LEFT JOIN student_groups sg ON n.group_id = sg.id 
        WHERE n.user_type = 'coordinator' 
        ORDER BY n.created_at DESC 
        LIMIT 10
    ");
    $notification_stmt->execute();
    $notifications = $notification_stmt->fetchAll();
    
    // Count unread notifications
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_type = 'coordinator' AND is_read = 0
    ");
    $unread_stmt->execute();
    $unread_result = $unread_stmt->fetch();
    $unread_notifications_count = $unread_result['unread_count'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());
    $notifications = [];
    $unread_notifications_count = 0;
}

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_as_read') {
        header('Content-Type: application/json');
        if (isset($_POST['notification_id'])) {
            // Mark single notification as read
            $notification_id = $_POST['notification_id'];
            try {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE id = ? AND user_type = 'coordinator'
                ");
                $stmt->execute([$notification_id]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log("Mark notification read error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            // Mark all as read
            try {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE user_type = 'coordinator' AND is_read = 0
                ");
                $stmt->execute();
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log("Mark all notifications read error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
        exit();
    }
    
    if ($_POST['action'] === 'get_notifications') {
        header('Content-Type: application/json');
        try {
            $notification_stmt = $pdo->prepare("
                SELECT n.*, sg.group_name 
                FROM notifications n 
                LEFT JOIN student_groups sg ON n.group_id = sg.id 
                WHERE n.user_type = 'coordinator' 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $notification_stmt->execute();
            $notifications = $notification_stmt->fetchAll();
            
            $unread_stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM notifications 
                WHERE user_type = 'coordinator' AND is_read = 0
            ");
            $unread_stmt->execute();
            $unread_result = $unread_stmt->fetch();
            $unread_count = $unread_result['unread_count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_thesis-titles-overview.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user">
                    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
                         class="sidebar-avatar" 
                         alt="User Avatar" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
                <span class="role-badge">Research Coordinator</span>
            </div>
            <!-- Sidebar Navigation -->
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item">
                    <i class="fas fa-chalkboard-teacher"></i> Advisor Management
                </a>
                <a href="coordinator_thesis-titles-overview.php" class="nav-item active">
                    <i class="fas fa-book"></i> Thesis Titles Overview
                </a>
                <a href="coordinator_document-control-panel.php" class="nav-item">
                    <i class="fas fa-book-open"></i> Document Control Panel
                </a>
                <a href="coordinator_audit_log.php" class="nav-item">
                    <i class="fas fa-history"></i> Audit Logs
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <!-- Enhanced logout confirmation modal -->
                <div id="logoutModal" class="modal">
                    <div class="logoutmodal-content modal-centered">
                        <div class="modal-header">
                            <h3 class="modal-title">Confirm Logout</h3>
                            <button class="close-modal" onclick="closeLogoutModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="logout-confirmation">
                                <div class="logout-icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <p>Are you sure you want to logout from ThesisTrack?</p>
                                <p class="logout-note">You will need to login again to access your dashboard.</p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button class="btn-modal btn-cancel" id="cancelLogout" onclick="closeLogoutModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="btn-modal btn-danger" id="confirmLogout" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i> Yes, Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>
        <!-- End Sidebar -->

        <!-- Start HEADER -->
        <div class="content-wrapper">
            <header class="blank-header">
                <div class="topbar-left"></div>
                <!-- Notification Dropdown -->
                <div class="notification-dropdown">
                    <button class="topbar-icon" title="Notifications" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($unread_notifications_count > 0): ?>
                            <span class="notification-badge" id="notificationBadge">
                                <?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-menu" id="notificationMenu">
                        <div class="notification-header">
                            <h4>System Monitoring</h4>
                            <?php if ($unread_notifications_count > 0): ?>
                                <button class="mark-all-read" id="markAllRead">Mark all as read</button>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                         data-id="<?php echo $notification['id']; ?>">
                                        <span class="notification-type type-<?php echo $notification['type'] ?? 'info'; ?>">
                                            <?php echo ucfirst($notification['type'] ?? 'info'); ?>
                                        </span>
                                        <?php if ($notification['group_name']): ?>
                                            <div class="notification-group">
                                                Group: <?php echo htmlspecialchars($notification['group_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="notification-title">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </div>
                                        <div class="notification-message">
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </div>
                                        <div class="notification-time">
                                            <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-notifications">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No system notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <a href="coordinator_notifications.php" class="view-all-notifications">
                            View All System Activities
                        </a>
                    </div>
                </div>
                <div class="user-info dropdown">
                    <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>?t=<?php echo time(); ?>" 
                         alt="User Avatar" 
                         class="user-avatar" 
                         id="userAvatar" />
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="coordinator_settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="#" id="logoutLink" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </header>
            <!-- End HEADER -->

            <!-- Start MAIN CONTENT -->
            <main class="main-content">
                <div class="page-header">
                    <h1><i class="fas fa-book"></i> Thesis Titles Overview</h1>
                    <p>Monitor and manage all thesis titles with smart duplicate detection</p>
                </div>

                <div class="content-card">
                    <!-- Toolbar -->
                    <div class="toolbar">
                        <div class="search-container">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search here..">
                        </div>
                        
                        <div class="toolbar-actions">
                            <!-- Added threshold adjustment control -->
                            <div class="threshold-control">
                                <label for="thresholdSlider">Similarity Threshold:</label>
                                <input type="range" id="thresholdSlider" min="0" max="100" value="<?php echo $similarity_threshold; ?>" step="5">
                                <span id="thresholdValue"><?php echo $similarity_threshold; ?>%</span>
                            </div>
                            
                            <button id="toggleDuplicatesBtn" class="btn btn-secondary">
                                <i class="fas fa-filter"></i> Show Duplicates Only
                            </button>
                            <button id="exportBtn" class="btn btn-primary">
                                <i class="fas fa-download"></i> Export to CSV
                            </button>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo count($thesis_data); ?></div>
                                <div class="stat-label">Total Titles</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon duplicate">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo count($clusters); ?></div>
                                <div class="stat-label">Duplicate Groups</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon unique">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo count($thesis_data) - count(array_keys($duplicates)); ?></div>
                                <div class="stat-label">Unique Titles</div>
                            </div>
                        </div>
                    </div>

                   <!-- Table -->
                        <div class="table-container">
                            <h2 class="section-title">
                                <i class="fas fa-list"></i> All Thesis Titles
                            </h2>
                            <table class="thesis-table" id="thesisTable">
                                <thead>
                                    <tr>
                                        <th>Group Name</th>
                                        <th>Thesis Title</th>
                                        <th>Advisor Name</th>
                                        <th>Section</th>
                                        <th>Course</th>
                                        <th>Status</th>
                                        <th>Verification Status</th>
                                        <th>Duplicate Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <?php if (!empty($thesis_data)): ?>
                                        <?php foreach ($thesis_data as $row): ?>
                                            <?php 
                                            $is_duplicate = isset($duplicates[$row['id']]);
                                            $verification_status = $row['verification_status'] ?? 'pending';
                                            $status_class = '';
                                            $status_text = '';
                                            
                                            switch ($verification_status) {
                                                case 'verified':
                                                    $status_class = 'verified-indicator';
                                                    $status_text = '<i class="fas fa-check-double"></i> Verified';
                                                    break;
                                                case 'duplicate_reported':
                                                    $status_class = 'reported-indicator';
                                                    $status_text = '<i class="fas fa-flag"></i> Reported';
                                                    break;
                                                default:
                                                    $status_class = 'pending-indicator';
                                                    $status_text = '<i class="fas fa-clock"></i> Pending';
                                                    break;
                                            }
                                            
                                            // Determine duplicate status text based on verification
                                            $duplicate_status_text = '';
                                            $duplicate_status_class = '';
                                            if ($verification_status === 'verified') {
                                                $duplicate_status_text = '<i class="fas fa-check-circle"></i> Verified as Unique';
                                                $duplicate_status_class = 'unique-indicator verified';
                                            } else {
                                                if ($is_duplicate) {
                                                    $duplicate_status_text = '<i class="fas fa-exclamation-triangle"></i> Possible Duplicate';
                                                    $duplicate_status_class = 'duplicate-indicator';
                                                } else {
                                                    $duplicate_status_text = '<i class="fas fa-check-circle"></i> Unique';
                                                    $duplicate_status_class = 'unique-indicator';
                                                }
                                            }
                                            ?>
                                            <tr class="<?php echo ($is_duplicate && $verification_status !== 'verified') ? 'duplicate-row' : ''; ?>" data-id="<?php echo $row['id']; ?>">
                                                <td><?php echo htmlspecialchars($row['group_name']); ?></td>
                                                <td class="title-cell <?php echo ($is_duplicate && $verification_status !== 'verified') ? 'duplicate-title' : ''; ?>">
                                                    <?php echo htmlspecialchars($row['thesis_title']); ?>
                                                    <?php if ($is_duplicate && $verification_status !== 'verified'): ?>
                                                        <span class="duplicate-badge" title="This title has duplicates">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                        </span>
                                                        <div class="duplicate-tooltip">
                                                            <strong>Possible Duplicates:</strong>
                                                            <ul>
                                                                <?php foreach ($duplicates[$row['id']] as $dup): ?>
                                                                    <li><?php echo htmlspecialchars($dup['title']); ?> <span class="similarity-score">(<?php echo $dup['similarity']; ?>% similar)</span></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['advisor_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['section']); ?></td>
                                                <td><?php echo htmlspecialchars($row['course']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="verification-status <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                    <!-- Removed the coordinator name and date display -->
                                                </td>
                                                <td>
                                                    <span class="<?php echo $duplicate_status_class; ?>">
                                                        <?php echo $duplicate_status_text; ?>
                                                    </span>
                                                </td>
                                                <td class="action-cell">
                                                    <?php if ($verification_status === 'pending'): ?>
                                                        <button class="btn-verify" 
                                                                data-thesis-id="<?php echo $row['id']; ?>"
                                                                data-thesis-title="<?php echo htmlspecialchars($row['thesis_title']); ?>"
                                                                data-group-name="<?php echo htmlspecialchars($row['group_name']); ?>"
                                                                data-advisor-name="<?php echo htmlspecialchars($row['advisor_name'] ?? 'N/A'); ?>"
                                                                data-advisor-id="<?php echo $row['advisor_id'] ?? ''; ?>"
                                                                data-is-duplicate="<?php echo $is_duplicate ? 'true' : 'false'; ?>">
                                                            <i class="fas fa-check-circle"></i> Verify
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn-verified" disabled>
                                                            <i class="fas fa-check-double"></i> 
                                                            <?php echo ucfirst(str_replace('_', ' ', $verification_status)); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="no-data">
                                                <i class="fas fa-info-circle"></i>
                                                No thesis data found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <!-- Pagination -->
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Showing <span id="startEntry">1</span> to <span id="endEntry"><?php echo min(10, count($thesis_data)); ?></span> of <span id="totalEntries"><?php echo count($thesis_data); ?></span> entries
                        </div>
                        <div class="pagination-controls">
                            <button id="prevBtn" class="pagination-btn" disabled>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <span id="pageInfo" class="page-info">Page 1</span>
                            <button id="nextBtn" class="pagination-btn" <?php echo count($thesis_data) <= 10 ? 'disabled' : ''; ?>>
                                <i class="fas fa-chevron-right"></i> Next
                            </button>
                        </div>
                    </div>

                    <!-- Grouped Duplicates Display -->
                    <div id="groupedDuplicatesContainer" class="grouped-duplicates-container">
                        <?php if (!empty($clusters)): ?>
                            <div class="duplicates-section">
                                <h2 class="section-title">
                                    <i class="fas fa-exclamation-triangle"></i> Duplicate Groups (<?php echo count($clusters); ?>)
                                </h2>
                                
                                <?php foreach ($clusters as $groupNum => $clusterIds): ?>
                                    <?php
                                        $groupTitles = [];
                                        foreach ($clusterIds as $id) {
                                            if (isset($thesisById[$id])) {
                                                $groupTitles[] = $thesisById[$id]['thesis_title'];
                                            }
                                        }
                                        $commonKeywords = extractCommonKeywords($groupTitles);
                                    ?>
                                    <div class="duplicate-group">
                                        <div class="group-header">
                                            <h3>
                                                <i class="fas fa-layer-group"></i> Duplicate Group #<?php echo $groupNum; ?>
                                            </h3>
                                            <div class="group-meta">
                                                <span class="group-count"><?php echo count($clusterIds); ?> titles</span>
                                                <?php if (!empty($commonKeywords)): ?>
                                                    <span class="group-keywords">
                                                        <i class="fas fa-tag"></i> Keywords: <?php echo htmlspecialchars(implode(', ', $commonKeywords)); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="group-table-wrapper">
                                            <table class="group-table">
                                                <thead>
                                                    <tr>
                                                        <th>Group Name</th>
                                                        <th>Thesis Title</th>
                                                        <th>Advisor Name</th>
                                                        <th>Section</th>
                                                        <th>Similarity %</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($clusterIds as $id): ?>
                                                        <?php if (isset($thesisById[$id])): ?>
                                                            <?php $row = $thesisById[$id]; ?>
                                                            <?php
                                                                $avgSimilarity = 0;
                                                                if (isset($duplicates[$id])) {
                                                                    $similarities = array_map(function($dup) { return $dup['similarity']; }, $duplicates[$id]);
                                                                    $avgSimilarity = round(array_sum($similarities) / count($similarities), 2);
                                                                }
                                                            ?>
                                                            <tr class="group-row">
                                                                <td><?php echo htmlspecialchars($row['group_name']); ?></td>
                                                                <td class="title-cell"><?php echo htmlspecialchars($row['thesis_title']); ?></td>
                                                                <td><?php echo htmlspecialchars($row['advisor_name'] ?? 'N/A'); ?></td>
                                                                <td><?php echo htmlspecialchars($row['section']); ?></td>
                                                                <td>
                                                                    <span class="similarity-badge" style="background-color: <?php echo $avgSimilarity >= 80 ? '#fed7d7' : '#feebc8'; ?>; color: <?php echo $avgSimilarity >= 80 ? '#742a2a' : '#7c2d12'; ?>;">
                                                                        <?php echo $avgSimilarity; ?>%
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-duplicates-message">
                                <i class="fas fa-check-circle"></i>
                                <h3>No duplicate groups found</h3>
                                <p>All thesis titles appear to be unique at the current similarity threshold.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
            <!-- End MAIN CONTENT -->

            <!-- Verification Modal -->
            <div id="verificationModal" class="vmodal">
                <div class="modal-content verification-modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-check-circle"></i> Verify Thesis Title</h3>
                        <button class="close-modal" onclick="closeVerificationModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="thesis-info">
                            <h4>Title Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Thesis Title:</label>
                                    <span id="modalThesisTitle" class="info-value"></span>
                                </div>
                                <div class="info-item">
                                    <label>Group Name:</label>
                                    <span id="modalGroupName" class="info-value"></span>
                                </div>
                                <div class="info-item">
                                    <label>Assigned Advisor:</label>
                                    <span id="modalAdvisorName" class="info-value"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div id="duplicateInfo" class="duplicate-info" style="display: none;">
                            <h4><i class="fas fa-exclamation-triangle"></i> Duplicate Detection</h4>
                            <div class="duplicate-list">
                                <p>The following similar titles were found:</p>
                                <ul id="duplicateList"></ul>
                            </div>
                        </div>
                        
                        <div class="verification-actions">
                            <div class="action-buttons">
                                <button class="btn btn-warning" id="informAdvisorBtn" onclick="informAdvisor()">
                                    <i class="fas fa-envelope"></i> Email Assigned Advisor
                                </button>
                                <button class="btn btn-success" id="verifyUniqueBtn" onclick="verifyAsUnique()">
                                    <i class="fas fa-check"></i> Verify as Unique
                                </button>
                            </div>
                            <div class="action-note">
                                <p><small>Note: Verifying as unique will mark this title as approved and remove it from duplicate checks.</small></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../JS/coordinator_thesis-titles-overview.js"></script>
    
</body>
</html>
