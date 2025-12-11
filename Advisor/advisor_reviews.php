<?php
require_once __DIR__ . '/../auth.php';

// Turn off error display but log them
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if we're in an API call to prevent HTML output
$is_api_call = isset($_GET['get_thesis_report']) && $_GET['get_thesis_report'] === 'true';

if ($is_api_call) {
    // For API calls, ensure no HTML output
    ini_set('display_errors', 0);
}

// Enforce session timeout and require advisor role
requireRole(['advisor']);

require_once '../db/db.php';

// Get the logged-in advisor's ID
$advisor_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Advisor';

$profile_picture = '../images/default-user.png';
$error_message = '';
$success_message = '';

// Handle notification actions and begin_review only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['mark_notification_read', 'mark_all_notifications_read', 'begin_review'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'mark_notification_read') {
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                // Mark single notification as read
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = 'advisor'");
                $stmt->execute([$notification_id, $advisor_id]);
                echo json_encode(['success' => true]);
            }
            
        } elseif ($_POST['action'] === 'mark_all_notifications_read') {
            // Mark all notifications as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'advisor'");
            $stmt->execute([$advisor_id]);
            echo json_encode(['success' => true]);
            } elseif ($_POST['action'] === 'begin_review') {
                // Begin review action: set chapter to under_review and assign reviewer (transactional)
                $chapter_id = $_POST['chapter_id'] ?? null;
                if (empty($chapter_id)) {
                    echo json_encode(['success' => false, 'error' => 'Missing chapter_id']);
                    exit();
                }

                try {
                    // Verify that the chapter belongs to a group advised by this advisor
                    $verify_sql = "
                        SELECT c.*, g.advisor_id, g.title as group_title
                        FROM chapters c
                        JOIN groups g ON c.group_id = g.id
                        WHERE c.id = ? AND g.advisor_id = ?
                    ";
                    $verify_stmt = $pdo->prepare($verify_sql);
                    $verify_stmt->execute([$chapter_id, $advisor_id]);
                    $chapter = $verify_stmt->fetch();

                    if (!$chapter) {
                        echo json_encode(['success' => false, 'error' => "Chapter not found or you don't have permission to review it."]);
                        exit();
                    }

                    // If verification passes, just return success so the client can open the modal.
                    echo json_encode(['success' => true, 'message' => 'Permission verified']);
                } catch (Exception $e) {
                    error_log('Begin review verify error: ' . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Server error verifying permission']);
                }
        }
    } catch (Exception $e) {
        error_log("Notification action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
    exit();
}

// Process form submission for chapter review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $chapter_id = $_POST['chapter_id'] ?? null;
    $status = $_POST['status'] ?? '';
    $score = $_POST['score'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    // Detect AJAX request
    $is_ajax_post = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    try {
        // Validate inputs
        if (empty($chapter_id) || empty($status)) {
            throw new Exception("Required fields are missing.");
        }
        
        if (!empty($score) && ($score < 0 || $score > 100)) {
            throw new Exception("Score must be between 0 and 100.");
        }

        // Verify that the chapter belongs to a group advised by this advisor
        $verify_sql = "
            SELECT c.*, g.advisor_id, g.title as group_title
            FROM chapters c 
            JOIN groups g ON c.group_id = g.id 
            WHERE c.id = ? AND g.advisor_id = ?
        ";
        $verify_stmt = $pdo->prepare($verify_sql);
        $verify_stmt->execute([$chapter_id, $advisor_id]);
        $chapter = $verify_stmt->fetch();
        
        if (!$chapter) {
            throw new Exception("Chapter not found or you don't have permission to review it.");
        }
        
        // Update chapter status with score and feedback
        $update_sql = "
            UPDATE chapters 
            SET status = ?, 
                review_score = ?,
                advisor_feedback = ?,
                last_reviewed_date = NOW(),
                reviewer_id = ?,
                reviewer_type = 'advisor',
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            $status, 
            $score ?: null, 
            $feedback, 
            $advisor_id, 
            $chapter_id
        ]);
        
        // Add comment if feedback is provided
        if (!empty($feedback)) {
            $comment_sql = "
                INSERT INTO chapter_comments 
                (chapter_id, commenter_id, commenter_type, comment, created_at, updated_at)
                VALUES (?, ?, 'advisor', ?, NOW(), NOW())
            ";
            $comment_stmt = $pdo->prepare($comment_sql);
            $comment_stmt->execute([$chapter_id, $advisor_id, $feedback]);
        }
        
        // Create notification for students
        // Notify only students who are members of this chapter's group
        // Prepare notification message
        $notification_message = "Your chapter {$chapter['chapter_number']} has been reviewed by your advisor. Status: " . ucfirst(str_replace('_', ' ', $status));

        /*
         * Insert notifications for distinct students in the group, but avoid duplicates
         * by inserting only when a similar notification (same user, group, title, message, type)
         * does not already exist. We use LEFT JOIN to find absence in notifications.
         */
        $student_notification_sql = "
            INSERT INTO notifications
            (user_id, user_type, group_id, title, message, type, is_read, created_at)
            SELECT DISTINCT
                s.id,
                'student',
                gm.group_id,
                'Chapter Reviewed',
                ?,
                'info',
                0,
                NOW()
            FROM group_members gm
            JOIN students s ON gm.student_id = s.id
            LEFT JOIN notifications n ON n.user_id = s.id
                AND n.user_type = 'student'
                AND n.group_id = gm.group_id
                AND n.title = 'Chapter Reviewed'
                AND n.message = ?
                AND n.type = 'info'
            WHERE gm.group_id = ?
                AND n.id IS NULL
        ";

        $notification_stmt = $pdo->prepare($student_notification_sql);
        $notification_stmt->execute([$notification_message, $notification_message, $chapter['group_id']]);
        
        // Log the activity
        $activity_sql = "
            INSERT INTO activity_logs 
            (user_id, user_type, action, details, created_at)
            VALUES (?, 'advisor', 'chapter_review', ?, NOW())
        ";
        $activity_stmt = $pdo->prepare($activity_sql);
        $activity_details = "Reviewed chapter {$chapter_id} ({$chapter['group_title']}) - Status: {$status}" . ($score ? " Score: {$score}" : "");
        $activity_stmt->execute([$advisor_id, $activity_details]);

        // Audit logging for chapter review
        try {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

            // Prepare review details and severity
            $review_details = "Reviewed chapter {$chapter['chapter_number']} for group {$chapter['group_title']}. Status: {$status}" .
                ($score ? " Score: {$score}." : '') .
                (!empty($feedback) ? " Feedback: " . substr($feedback, 0, 1000) : '');

            $severity = 'high';

            if (function_exists('logAudit')) {
                // Use the project's helper (expects $pdo first)
                logAudit($pdo, $advisor_id, $user_name, 'advisor', 'chapter_review', $review_details, $severity, 'Chapter Management', $ip_address);
            } else {
                // Fallback direct insert into audit_logs
                $auditStmt = $pdo->prepare(
                    "INSERT INTO audit_logs (user_id, user_name, role, action, action_category, details, severity, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $auditStmt->execute([
                    $advisor_id,
                    $user_name,
                    'advisor',
                    'chapter_review',
                    'Chapter Management',
                    $review_details,
                    $severity,
                    $ip_address
                ]);
            }
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }

        $success_message = "Chapter review submitted successfully!";

        if ($is_ajax_post) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $success_message]);
            exit();
        }
        
    } catch (Exception $e) {
        error_log("Review process error: " . $e->getMessage());
        $error_message = "Error submitting review: " . $e->getMessage();
        if ($is_ajax_post) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}

// Handle viewing chapter file
if (isset($_GET['action']) && $_GET['action'] === 'view_chapter' && isset($_GET['chapter_id'])) {
    $chapter_id = $_GET['chapter_id'];
    
    try {
        // Verify the chapter belongs to advisor's group
        $sql = "
            SELECT c.*, g.advisor_id 
            FROM chapters c 
            JOIN groups g ON c.group_id = g.id 
            WHERE c.id = ? AND g.advisor_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$chapter_id, $advisor_id]);
        $chapter = $stmt->fetch();
        
        if (!$chapter) {
            throw new Exception("Chapter not found or access denied.");
        }
        
        // Update chapter status to 'under_review' when viewed
        $update_sql = "UPDATE chapters SET status = 'under_review' WHERE id = ? AND status = 'uploaded'";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$chapter_id]);
        
        // Try to find the file using several common path formats
        $file_found = false;
        $file_path = '';

        // Collector of attempted candidates for debugging
        $attempts = [];

        // Helper to check and set the file path if exists
        $tryPath = function($p) use (&$file_found, &$file_path, &$attempts) {
            if ($file_found) return;
            if (empty($p)) return;
            // Normalize slashes and prepare candidate paths
            $norm = str_replace('\\', '/', $p);
            $candidates = [$p, $norm, realpath($p), realpath($norm)];
            // If path looks like a relative path from project root, check __DIR__/.. prefix
            $candidates[] = __DIR__ . '/../' . ltrim($p, '/\\');
            $candidates[] = __DIR__ . '/../' . ltrim($norm, '/\\');
            // Also try document root prefix (useful if stored path is relative to web root)
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($p, '/\\');
                $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . ltrim($norm, '/\\');
            }
            $candidates[] = __DIR__ . '/' . ltrim($p, '/\\');
            foreach ($candidates as $cand) {
                if (empty($cand)) continue;
                // Try to resolve realpath for each candidate
                $resolved = realpath($cand) ?: $cand;
                $exists = file_exists($resolved);
                $readable = is_readable($resolved);
                $attempts[] = ['candidate' => $cand, 'resolved' => $resolved, 'exists' => $exists, 'readable' => $readable];
                if ($exists && $readable) {
                    $file_path = $resolved;
                    $file_found = true;
                    return;
                }
            }
        };

        // 1) Check explicit file_path field
        if (!empty($chapter['file_path'])) {
            $tryPath($chapter['file_path']);
        }

        // 2) Check filename in uploads/chapters (relative and absolute)
        if (!$file_found && !empty($chapter['filename'])) {
            $tryPath('../uploads/chapters/' . $chapter['filename']);
            $tryPath(__DIR__ . '/../uploads/chapters/' . $chapter['filename']);
        }

        // 3) As a last resort, attempt to locate by filename or original_filename in uploads folders
        if (!$file_found) {
            $searchNames = [];
            if (!empty($chapter['filename'])) $searchNames[] = $chapter['filename'];
            if (!empty($chapter['original_filename'])) $searchNames[] = $chapter['original_filename'];

            $uploadDirs = [
                realpath(__DIR__ . '/../uploads/chapters'),
                realpath(__DIR__ . '/../uploads'),
            ];

            foreach ($uploadDirs as $dir) {
                if (!$dir || !is_dir($dir)) continue;
                // Recursive iterate but limit depth to avoid long scans
                $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                foreach ($rii as $fileInfo) {
                    if ($fileInfo->isDir()) continue;
                    $fname = $fileInfo->getFilename();
                    foreach ($searchNames as $search) {
                        if (empty($search)) continue;
                        // match exact filename or partial match of original filename
                        if (strcasecmp($fname, $search) === 0 || stripos($fname, $search) !== false) {
                            $tryPath($fileInfo->getPathname());
                            break 2; // stop searching once found
                        }
                    }
                }
            }
        }

    if ($file_found && is_readable($file_path)) {
            // Determine mime type if possible
            $mime = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $detected = @mime_content_type($file_path);
                if ($detected) $mime = $detected;
            } else {
                // Fallback by extension
                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                $map = [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'txt' => 'text/plain',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png'
                ];
                if (isset($map[$ext])) $mime = $map[$ext];
            }

            // Set headers
            header('Content-Type: ' . $mime);
            // Use original filename for download/inline display
            $safeName = $chapter['original_filename'] ? basename($chapter['original_filename']) : basename($file_path);
            header('Content-Disposition: inline; filename="' . $safeName . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: private, max-age=0, must-revalidate');

            readfile($file_path);
            exit;
        } else {
            // Log attempts for debugging
            error_log('View chapter - file not found. Chapter ID: ' . $chapter_id . ' Stored path: ' . $chapter['file_path']);
            foreach ($attempts as $a) {
                error_log('Tried: ' . $a['candidate'] . ' -> resolved: ' . $a['resolved'] . ' exists:' . ($a['exists'] ? '1' : '0') . ' readable:' . ($a['readable'] ? '1' : '0'));
            }

            // Show a more helpful error message including attempted paths
            header('Content-Type: text/html');
            echo '<html><body style="font-family: Arial, sans-serif; padding: 20px;">';
            echo '<div style="max-width: 800px; margin: 20px auto;">';
            echo '<h2 style="color: #e74c3c;">File Not Found</h2>';
            echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
            echo '<p>The chapter file could not be located. This could be due to:</p>';
            echo '<ul style="text-align: left;">';
            echo '<li>File was moved or deleted</li>';
            echo '<li>Incorrect file path in database</li>';
            echo '<li>File permission issues</li>';
            echo '</ul>';
            echo '</div>';
            echo '<p><strong>File information:</strong></p>';
            echo '<p>Filename: ' . htmlspecialchars($chapter['filename']) . '</p>';
            echo '<p>Original: ' . htmlspecialchars($chapter['original_filename']) . '</p>';
            echo '<p>Stored Path: ' . htmlspecialchars($chapter['file_path']) . '</p>';

            echo '<h3>Attempted locations</h3>';
            echo '<table style="width:100%; border-collapse: collapse;">';
            echo '<tr><th style="text-align:left; padding:6px; border-bottom:1px solid #ddd">Candidate</th><th style="text-align:left; padding:6px; border-bottom:1px solid #ddd">Resolved</th><th style="padding:6px; border-bottom:1px solid #ddd">Exists</th><th style="padding:6px; border-bottom:1px solid #ddd">Readable</th></tr>';
            foreach ($attempts as $a) {
                echo '<tr>';
                echo '<td style="padding:6px; vertical-align:top;">' . htmlspecialchars($a['candidate']) . '</td>';
                echo '<td style="padding:6px; vertical-align:top;">' . htmlspecialchars($a['resolved']) . '</td>';
                echo '<td style="padding:6px; vertical-align:top;">' . ($a['exists'] ? 'Yes' : 'No') . '</td>';
                echo '<td style="padding:6px; vertical-align:top;">' . ($a['readable'] ? 'Yes' : 'No') . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            echo '<div style="margin-top:16px;">';
            echo '<button onclick="window.close()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Close Window</button>';
            echo '</div>';
            echo '</div>';
            echo '</body></html>';
            exit;
        }
        
    } catch (Exception $e) {
        header('Content-Type: text/html');
        echo '<html><body style="font-family: Arial, sans-serif; padding: 20px;">';
        echo '<h2 style="color: #e74c3c;">Error</h2>';
        echo '<p>Error accessing chapter: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<button onclick="window.close()" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Close Window</button>';
        echo '</body></html>';
        exit;
    }
}

// Get advisor details including profile picture and notifications
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_picture FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor = $stmt->fetch();
    
    $user_name = ($advisor['first_name'] && $advisor['last_name']) ? $advisor['first_name'] . ' ' . $advisor['last_name'] : 'Advisor';
    
    // Check if profile picture exists and is valid
    if (!empty($advisor['profile_picture'])) {
        $relative_path = $advisor['profile_picture'];
        $absolute_path = __DIR__ . '/../' . $relative_path;
        
        if (file_exists($absolute_path) && is_readable($absolute_path)) {
            $profile_picture = '../' . $relative_path;
        } else {
            error_log("Profile image not found: " . $absolute_path);
        }
    }

    // Get notifications for advisor
    $notifications_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications_stmt->execute([$advisor_id]);
    $notifications = $notifications_stmt->fetchAll();

    // Count unread notifications
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
    ");
    $unread_stmt->execute([$advisor_id]);
    $unread_result = $unread_stmt->fetch();
    $unread_notifications_count = $unread_result['unread_count'];

} catch (PDOException $e) {
    error_log("Database error fetching advisor details: " . $e->getMessage());
    $user_name = 'Advisor';
    $profile_picture = '../images/default-user.png';
    $notifications = [];
    $unread_notifications_count = 0;
}

// Fetch pending chapters for this advisor (statuses = 'pending', 'under_review', 'uploaded') with thesis title and version info
$pending_chapters = [];
try {
    // Only select true pending chapters here
    $sql = "
        SELECT 
            c.*, 
            g.title AS group_title,
            g.id AS group_id,
            sg.thesis_title,
            s.first_name, 
            s.last_name,
            s.student_id,
            (SELECT COUNT(*) FROM chapter_comments 
             WHERE chapter_id = c.id AND commenter_type = 'advisor') as has_feedback
        FROM chapters c
        JOIN groups g ON c.group_id = g.id
        LEFT JOIN student_groups sg ON g.id = sg.group_id
        JOIN group_members gm ON g.id = gm.group_id AND gm.role_in_group = 'leader'
        JOIN students s ON gm.student_id = s.id
        WHERE g.advisor_id = ? 
        AND c.status IN ('pending', 'under_review', 'uploaded')
        ORDER BY c.upload_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$advisor_id]);
    $pending_chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "Unable to fetch pending reviews. Please try again later.";
}

// Compute exact pending-only count (statuses = 'pending','under_review','uploaded') for the badge
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM chapters c JOIN groups g ON c.group_id = g.id WHERE g.advisor_id = ? AND c.status IN ('pending','under_review','uploaded')");
    $countStmt->execute([$advisor_id]);
    $countRow = $countStmt->fetch();
    $pending_only_count = (int)($countRow['pending_count'] ?? 0);
} catch (Exception $e) {
    error_log("Error fetching pending count: " . $e->getMessage());
    $pending_only_count = count($pending_chapters);
}

// Fetch completed chapters (already reviewed) - statuses 'approved' and 'needs_revision'
$completed_chapters = [];
try {
    $completed_sql = "
        SELECT 
            c.*, 
            g.title AS group_title,
            g.id AS group_id,
            sg.thesis_title,
            s.first_name, 
            s.last_name,
            s.student_id
        FROM chapters c
        JOIN groups g ON c.group_id = g.id
        LEFT JOIN student_groups sg ON g.id = sg.group_id
        JOIN group_members gm ON g.id = gm.group_id AND gm.role_in_group = 'leader'
        JOIN students s ON gm.student_id = s.id
        WHERE g.advisor_id = ?
        AND c.status IN ('approved', 'needs_revision')
        ORDER BY c.last_reviewed_date DESC
    ";
    $compStmt = $pdo->prepare($completed_sql);
    $compStmt->execute([$advisor_id]);
    $completed_chapters = $compStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching completed chapters: " . $e->getMessage());
    $completed_chapters = [];
}

// Compute counts for reviewed statuses only
$status_counts = [
    'approved' => 0,
    'needs_revision' => 0,
];
try {
    $statusSql = "
        SELECT c.status, COUNT(*) as cnt
        FROM chapters c
        JOIN groups g ON c.group_id = g.id
        WHERE g.advisor_id = ?
          AND c.status IN ('approved', 'needs_revision')
        GROUP BY c.status
    ";
    $statusStmt = $pdo->prepare($statusSql);
    $statusStmt->execute([$advisor_id]);
    $rows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $s = $r['status'];
        if (isset($status_counts[$s])) $status_counts[$s] = (int)$r['cnt'];
    }
} catch (Exception $e) {
    error_log('Error fetching status counts: ' . $e->getMessage());
}

// ----------------------------- COMPREHENSIVE REPORT HANDLER -----------------------------

// Handle all analysis report requests
if (isset($_GET['get_thesis_report']) && $_GET['get_thesis_report'] === 'true') {
    // Enable error reporting for debugging
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    // Turn off any potential HTML output
    @ob_clean();

    header('Content-Type: application/json');
    
    // Check authorization
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'advisor') {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Not authorized. Please log in again.'
        ]);
        exit();
    }

    $chapter_number = $_GET['chapter'] ?? null;
    $version = $_GET['version'] ?? null;
    $group_id = $_GET['group'] ?? null;

    // Validate required parameters
    if (!$chapter_number || !$version || !$group_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Missing parameters. Required: chapter, version, group'
        ]);
        exit();
    }

    try {
        // Verify the chapter belongs to advisor's group
        $verify_sql = "
            SELECT c.*, g.advisor_id
            FROM chapters c 
            JOIN groups g ON c.group_id = g.id 
            WHERE c.group_id = ? AND c.chapter_number = ? AND c.version = ? AND g.advisor_id = ?
        ";
        $verify_stmt = $pdo->prepare($verify_sql);
        $verify_stmt->execute([$group_id, $chapter_number, $version, $_SESSION['user_id']]);
        $chapter_verify = $verify_stmt->fetch();
        
        if (!$chapter_verify) {
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'error' => 'Chapter not found or you do not have permission to access this chapter.'
            ]);
            exit();
        }

        // Get complete chapter data with all reports
        $stmt = $pdo->prepare("
            SELECT 
                completeness_report, 
                ai_report, 
                citation_report,
                chapter_number, 
                version,
                completeness_score, 
                completeness_feedback, 
                ai_score, 
                ai_feedback,
                citation_score,
                citation_feedback,
                grammar_score,
                grammar_feedback,
                grammar_report,
                spelling_score,
                spelling_feedback,
                spelling_report,
                formatting_score,
                formatting_feedback,
                formatting_report,
                relevance_score,
                relevance_feedback,
                relevance_report
            FROM chapters 
            WHERE group_id = ? AND chapter_number = ? AND version = ?
        ");
        $stmt->execute([$group_id, $chapter_number, $version]);
        $chapter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$chapter) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Chapter analysis data not found']);
            exit();
        }

        // Build comprehensive response
        $response = [
            'success' => true,
            'chapter_number' => intval($chapter_number),
            'version' => intval($version),
            'group_id' => intval($group_id),
            'completeness_score' => $chapter['completeness_score'] ? floatval($chapter['completeness_score']) : 0,
            'ai_score' => $chapter['ai_score'] ? floatval($chapter['ai_score']) : 0,
            'citation_score' => $chapter['citation_score'] ? floatval($chapter['citation_score']) : 0,
            'grammar_score' => $chapter['grammar_score'] ? floatval($chapter['grammar_score']) : 0,
            'spelling_score' => $chapter['spelling_score'] ? floatval($chapter['spelling_score']) : 0,
            'formatting_score' => $chapter['formatting_score'] ? floatval($chapter['formatting_score']) : 0,
            'relevance_score' => $chapter['relevance_score'] ? floatval($chapter['relevance_score']) : 0,
            'completeness_feedback' => $chapter['completeness_feedback'] ?? '',
            'ai_feedback' => $chapter['ai_feedback'] ?? '',
            'citation_feedback' => $chapter['citation_feedback'] ?? '',
            'grammar_feedback' => $chapter['grammar_feedback'] ?? 'Grammar analysis not available',
            'spelling_feedback' => $chapter['spelling_feedback'] ?? 'Spelling analysis not available',
            'formatting_feedback' => $chapter['formatting_feedback'] ?? 'Formatting analysis not available',
            'relevance_feedback' => $chapter['relevance_feedback'] ?? 'Relevance analysis not available',
        ];

        // Process AI report
        if (!empty($chapter['ai_report'])) {
            $aiReport = json_decode($chapter['ai_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['ai_report'] = $aiReport;
                // Extract common AI analysis fields
                if (isset($aiReport['ai_analysis'])) {
                    $aiAnalysis = $aiReport['ai_analysis'];
                    $response['overall_ai_percentage'] = $aiAnalysis['overall_ai_percentage'] ?? $response['ai_score'];
                    $response['sentences_analyzed'] = $aiAnalysis['total_sentences_analyzed'] ?? 0;
                    $response['sentences_flagged'] = $aiAnalysis['sentences_flagged_as_ai'] ?? 0;
                    $response['analysis'] = $aiAnalysis['analysis'] ?? [];
                }
            }
        }

        // Process completeness report
        if (!empty($chapter['completeness_report'])) {
            $completenessReport = json_decode($chapter['completeness_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['completeness_report'] = $completenessReport;
                // Extract completeness analysis data
                if (isset($completenessReport['sections_analysis'])) {
                    $response['sections_analysis'] = $completenessReport['sections_analysis'];
                }
                if (isset($completenessReport['chapter_scores'])) {
                    $response['chapter_scores'] = $completenessReport['chapter_scores'];
                }
            }
        }

        // Process citation report
        if (!empty($chapter['citation_report'])) {
            $citationReport = json_decode($chapter['citation_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['citation_report'] = $citationReport;
                // Extract citation data
                if (isset($citationReport['total_citations'])) {
                    $response['total_citations'] = $citationReport['total_citations'];
                }
                if (isset($citationReport['correct_citations'])) {
                    $response['correct_citations'] = $citationReport['correct_citations'];
                }
                if (isset($citationReport['corrected_citations'])) {
                    $response['corrected_citations'] = $citationReport['corrected_citations'];
                }
                // Calculate citation score if not provided
                if (!$response['citation_score'] && $response['total_citations'] > 0) {
                    $response['citation_score'] = round(($response['correct_citations'] / $response['total_citations']) * 100);
                }
            }
        }

        // Process grammar report
        if (!empty($chapter['grammar_report'])) {
            $grammarReport = json_decode($chapter['grammar_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['grammar_report'] = $grammarReport;
                if (isset($grammarReport['grammar_analysis'])) {
                    $response['grammar_analysis'] = $grammarReport['grammar_analysis'];
                    $response['grammar_issues'] = $grammarReport['grammar_analysis']['grammar_issues'] ?? [];
                }
            }
        }

        // Process spelling report
        if (!empty($chapter['spelling_report'])) {
            $spellingReport = json_decode($chapter['spelling_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['spelling_report'] = $spellingReport;
                if (isset($spellingReport['spelling_analysis'])) {
                    $response['spelling_analysis'] = $spellingReport['spelling_analysis'];
                    // Extract spelling_issues to main level for easy access
                    $response['spelling_issues'] = $spellingReport['spelling_analysis']['spelling_issues'] ?? [];
                } else {
                    // If no nested structure, try to get spelling_issues directly
                    $response['spelling_issues'] = $spellingReport['spelling_issues'] ?? [];
                }
            }
        } else {
            // Ensure spelling_issues is always an array
            $response['spelling_issues'] = [];
        }

        // Process formatting report - COMPREHENSIVE FIX FOR ADVISOR SIDE
        if (!empty($chapter['formatting_report'])) {
            $formattingReport = json_decode($chapter['formatting_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['formatting_report'] = $formattingReport;
                
                // Extract comprehensive formatting analysis data
                if (isset($formattingReport['formatting_analysis'])) {
                    // Use the exact same structure as student side
                    $response['formatting_analysis'] = $formattingReport['formatting_analysis'];
                } else {
                    // Build comprehensive formatting analysis from main report
                    $response['formatting_analysis'] = [
                        'formatting_score' => $response['formatting_score'],
                        'overall_score' => $response['formatting_score'],
                        'formatting_feedback' => $response['formatting_feedback'],
                        'recommendations' => $formattingReport['recommendations'] ?? [],
                        'document_type' => $formattingReport['document_type'] ?? 'PDF',
                        
                        // Comprehensive formatting compliance data (like student side)
                        'formatting_compliance' => extractComplianceData($formattingReport),
                        
                        'summary_statistics' => $formattingReport['summary_statistics'] ?? [
                            'pages_analyzed' => $formattingReport['total_pages_analyzed'] ?? 0,
                            'pages_fully_compliant' => $formattingReport['pages_fully_compliant'] ?? 0,
                            'overall_assessment' => $formattingReport['overall_assessment'] ?? 'Good',
                            'priority_level' => $formattingReport['priority_level'] ?? 'Medium'
                        ],
                        
                        'page_by_page_analysis' => $formattingReport['page_by_page_analysis'] ?? [],
                        'total_pages_analyzed' => $formattingReport['total_pages_analyzed'] ?? 0
                    ];
                }
                
                // Ensure we have all the detailed compliance data
                if (isset($formattingReport['formatting_compliance'])) {
                    $response['formatting_analysis']['formatting_compliance'] = array_merge(
                        $response['formatting_analysis']['formatting_compliance'],
                        $formattingReport['formatting_compliance']
                    );
                }
                
                // Extract specific compliance sections if they exist at root level
                $complianceSections = ['margins', 'font_style', 'font_size', 'spacing', 'page_layout', 'three_lines_right', 'headers_footers', 'indentation'];
                foreach ($complianceSections as $section) {
                    if (isset($formattingReport[$section]) && !isset($response['formatting_analysis']['formatting_compliance'][$section])) {
                        $response['formatting_analysis']['formatting_compliance'][$section] = $formattingReport[$section];
                    }
                }
                
                // Ensure recommendations are available at root level for backward compatibility
                if (isset($formattingReport['recommendations'])) {
                    $response['formatting_recommendations'] = $formattingReport['recommendations'];
                }
                
                // DEBUG: Log what we found
                error_log("Formatting compliance sections found: " . implode(', ', array_keys($response['formatting_analysis']['formatting_compliance'])));
            }
        } else {
            // Minimal fallback - won't break anything
            $response['formatting_analysis'] = [
                'formatting_score' => $response['formatting_score'],
                'overall_score' => $response['formatting_score'],
                'formatting_feedback' => $response['formatting_feedback'],
                'recommendations' => [],
                'document_type' => 'PDF',
                'formatting_compliance' => [
                    'margins' => [],
                    'font_style' => [
                        'overall_font_usage' => [
                            'primary_font_compliance' => ['status' => 'unknown'],
                            'font_consistency' => 'unknown',
                            'fonts_detected' => []
                        ],
                        'page_analysis' => []
                    ],
                    'font_size' => [
                        'compliance' => 'unknown',
                        'primary_size' => 12,
                        'compliance_rate' => 0,
                        'detected_sizes' => [],
                        'overall_size_analysis' => [
                            'most_common_size' => 12,
                            'consistency_score' => 0
                        ]
                    ],
                    'spacing' => [],
                    'page_layout' => [],
                    'three_lines_right' => [],
                    'headers_footers' => null,
                    'indentation' => null
                ],
                'summary_statistics' => [
                    'pages_analyzed' => 0,
                    'pages_fully_compliant' => 0,
                    'overall_assessment' => 'No data',
                    'priority_level' => 'Unknown'
                ],
                'page_by_page_analysis' => [],
                'total_pages_analyzed' => 0
            ];
        }

        // Process relevance report
        if (!empty($chapter['relevance_report'])) {
            $relevanceReport = json_decode($chapter['relevance_report'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response['relevance_report'] = $relevanceReport;
            }
        }

        echo json_encode($response);
        exit();
        
    } catch (Exception $e) {
        error_log("Comprehensive report error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Server error: ' . $e->getMessage()
        ]);
        exit();
    }
}

/**
 * Extract compliance data from formatting report to match student side structure
 */
function extractComplianceData($formattingReport) {
    $compliance = [];
    
    // Extract all compliance sections that might exist
    $sections = [
        'margins', 'font_style', 'font_size', 'spacing', 
        'page_layout', 'three_lines_right', 'headers_footers', 'indentation'
    ];
    
    foreach ($sections as $section) {
        if (isset($formattingReport[$section])) {
            $compliance[$section] = $formattingReport[$section];
        } elseif (isset($formattingReport['formatting_compliance'][$section])) {
            $compliance[$section] = $formattingReport['formatting_compliance'][$section];
        } else {
            // Create proper structure for missing sections to prevent JavaScript errors
            switch($section) {
                case 'margins':
                    $compliance[$section] = [];
                    break;
                case 'font_style':
                    $compliance[$section] = [
                        'overall_font_usage' => [
                            'primary_font_compliance' => ['status' => 'unknown'],
                            'font_consistency' => 'unknown',
                            'fonts_detected' => []
                        ],
                        'page_analysis' => []
                    ];
                    break;
                case 'font_size':
                    $compliance[$section] = [
                        'compliance' => 'unknown',
                        'primary_size' => 12,
                        'compliance_rate' => 0,
                        'detected_sizes' => [],
                        'overall_size_analysis' => [
                            'most_common_size' => 12,
                            'consistency_score' => 0
                        ]
                    ];
                    break;
                case 'spacing':
                    $compliance[$section] = [];
                    break;
                case 'page_layout':
                    $compliance[$section] = [];
                    break;
                case 'three_lines_right':
                    $compliance[$section] = [];
                    break;
                default:
                    $compliance[$section] = null;
            }
        }
    }
    
    return $compliance;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_reviews.css">
    <link rel="stylesheet" href="../CSS/advisor_reviews_formatting.css">
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user">
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
                <span class="role-badge">Subject Advisor</span>
            </div>
            <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item" data-tab="students">
                    <i class="fas fa-user-graduate"></i> Student Management
                </a>
                <a href="advisor_thesis-group.php" class="nav-item" data-tab="students">
                    <i class="fas fa-users-rectangle"></i> Groups Management
                </a>
                <a href="advisor_reviews.php" class="nav-item active" data-tab="reviews">
                    <i class="fas fa-tasks"></i> Feedback Management
                </a>
                <a href="advisor_feedback.php" class="nav-item" data-tab="feedback">
                    <i class="fas fa-comments"></i> Feedback History
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <!-- Enhanced logout confirmation modal -->
                <div id="logoutModal" class="modal">
                    <div class="modal-content modal-centered">
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
                <div class="topbar-left">
                </div>
                <div class="topbar-right">
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
                                <h4>Notifications</h4>
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
                                        <p>No notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="advisor_notifications.php" class="view-all-notifications">
                                View All Notifications
                            </a>
                        </div>
                    </div>

                    <div class="user-info dropdown">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar" class="user-avatar" id="userAvatar" tabindex="0" />
                        <div class="dropdown-menu" id="userDropdown">
                            <a href="advisor_settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="#" id="logoutLink" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            <!-- End HEADER -->

            <!-- Main Content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-tasks"></i> Feedback Management</h1>
                    <p>Chapters awaiting your review and feedback</p>
                </div>
                <!-- End of Page Title -->

                <!-- Display Messages -->
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <!-- Pending Reviews Tab -->
                <div id="reviews" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3>Pending Chapter Reviews</h3>
                            <div class="pending-count">
                                <span class="count-badge"><?php echo htmlspecialchars($pending_only_count); ?> pending</span>
                            </div>
                        </div>

                        <?php if (empty($pending_chapters)): ?>
                            <div class="no-results">
                                <i class="fas fa-check-circle"></i>
                                <p>No pending reviews at this time.</p>
                            </div>
                        <?php else: ?>
                            <div class="chapters-list">
                                <?php foreach ($pending_chapters as $chapter): ?>
                                    <div class="chapter-item">
                                        <div class="chapter-header">
                                            <div class="chapter-info">
                                                <h4>
                                                    <?php echo htmlspecialchars($chapter['group_title']); ?> - 
                                                    Chapter <?php echo htmlspecialchars($chapter['chapter_number']); ?>: 
                                                    <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                                </h4>
                                                <div class="chapter-meta-grid">
                                                    <div class="meta-item">
                                                        <strong>Thesis Title:</strong> 
                                                        <?php echo !empty($chapter['thesis_title']) ? htmlspecialchars($chapter['thesis_title']) : 'No thesis title assigned'; ?>
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>Submitted by:</strong> 
                                                        <?php echo htmlspecialchars($chapter['first_name'] . ' ' . $chapter['last_name']); ?> 
                                                        (<?php echo htmlspecialchars($chapter['student_id']); ?>)
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>File:</strong> 
                                                        <?php echo htmlspecialchars($chapter['original_filename']); ?> 
                                                        (<?php echo round($chapter['file_size'] / 1024, 2); ?> KB)
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>Version:</strong> 
                                                        <span class="version-badge">Version <?php echo htmlspecialchars($chapter['version'] ?? '1'); ?></span>
                                                    </div>
                                                    <?php if ($chapter['has_feedback'] > 0): ?>
                                                        <div class="meta-item">
                                                            <span class="feedback-badge has-feedback">
                                                                <i class="fas fa-comment"></i> Feedback Given
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="meta-item">
                                                            <span class="feedback-badge no-feedback">
                                                                <i class="fas fa-comment-slash"></i> No Feedback
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="chapter-status">
                                                <div class="status-badge status-<?php echo str_replace('_', '-', $chapter['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $chapter['status'])); ?>
                                                </div>
                                                <div class="chapter-date">
                                                    Submitted: <?php echo date('M d, Y g:i A', strtotime($chapter['upload_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="chapter-actions">
                                            <?php $is_pending = in_array($chapter['status'], ['pending', 'under_review', 'uploaded']); ?>
                                            <button class="btn-primary btn-small <?php echo $is_pending ? '' : 'btn-disabled'; ?>" 
                                                data-chapter-id="<?php echo $chapter['id']; ?>" 
                                                data-status="<?php echo htmlspecialchars($chapter['status']); ?>" 
                                                aria-disabled="<?php echo $is_pending ? 'false' : 'true'; ?>" 
                                                title="<?php echo $is_pending ? 'Start review' : 'Review unavailable (already reviewed)'; ?>"
                                                onclick="reviewChapter(<?php echo $chapter['id']; ?>, '<?php echo htmlspecialchars(addslashes($chapter['chapter_name'])); ?>')">
                                                <i class="fas fa-edit"></i> Review Now
                                            </button>
                                            <button class="btn-secondary btn-small" 
                                                onclick="viewChapterFile(<?php echo $chapter['id']; ?>)">
                                                <i class="fas fa-eye"></i> View File
                                            </button>
                                          <button class="btn-primary btn-small" 
                                            onclick="setCurrentGroupId(<?php echo $chapter['group_id']; ?>); viewComprehensiveReport(<?php echo $chapter['chapter_number']; ?>, <?php echo $chapter['version']; ?>)">
                                            <i class="fas fa-chart-bar"></i> View Full Analysis Report
                                        </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Completed Reviews -->
                <div id="completed-reviews" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h3>Reviewed Chapters</h3>
                            <div class="completed-count">
                               
                                <div class="status-badges" style="display:inline-block; margin-left:12px;">
                                    <button type="button" class="status-pill approved" data-filter="approved">Approved: <?php echo $status_counts['approved']; ?></button>
                                    <button type="button" class="status-pill needs-revision" data-filter="needs_revision">Needs Revision: <?php echo $status_counts['needs_revision']; ?></button>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($completed_chapters)): ?>
                            <div class="no-results">
                                <i class="fas fa-check"></i>
                                <p>No completed reviews yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="chapters-list">
                                <?php foreach ($completed_chapters as $chapter): ?>
                                    <div class="chapter-item" data-status="<?php echo htmlspecialchars($chapter['status']); ?>">
                                        <div class="chapter-header">
                                            <div class="chapter-info">
                                                <h4>
                                                    <?php echo htmlspecialchars($chapter['group_title']); ?> - 
                                                    Chapter <?php echo htmlspecialchars($chapter['chapter_number']); ?>: 
                                                    <?php echo htmlspecialchars($chapter['chapter_name']); ?>
                                                </h4>
                                                <div class="chapter-meta-grid">
                                                    <div class="meta-item">
                                                        <strong>Thesis Title:</strong> 
                                                        <?php echo !empty($chapter['thesis_title']) ? htmlspecialchars($chapter['thesis_title']) : 'No thesis title assigned'; ?>
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>Submitted by:</strong> 
                                                        <?php echo htmlspecialchars($chapter['first_name'] . ' ' . $chapter['last_name']); ?> 
                                                        (<?php echo htmlspecialchars($chapter['student_id']); ?>)
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>File:</strong> 
                                                        <?php echo htmlspecialchars($chapter['original_filename']); ?> 
                                                        (<?php echo round($chapter['file_size'] / 1024, 2); ?> KB)
                                                    </div>
                                                    <div class="meta-item">
                                                        <strong>Version:</strong> 
                                                        <span class="version-badge">Version <?php echo htmlspecialchars($chapter['version'] ?? '1'); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="chapter-status">
                                                <div class="status-badge status-<?php echo str_replace('_', '-', $chapter['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $chapter['status'])); ?>
                                                </div>
                                                <div class="chapter-date">
                                                    Reviewed: <?php echo date('M d, Y g:i A', strtotime($chapter['last_reviewed_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="chapter-actions">                                       
                                            <button class="btn-secondary btn-small" 
                                                onclick="viewChapterFile(<?php echo $chapter['id']; ?>)">
                                                <i class="fas fa-eye"></i> View File
                                            </button>
                                            <button class="btn-primary btn-small" 
                                            onclick="setCurrentGroupId(<?php echo $chapter['group_id']; ?>); viewComprehensiveReport(<?php echo $chapter['chapter_number']; ?>, <?php echo $chapter['version']; ?>)">
                                            <i class="fas fa-chart-bar"></i> View Full Analysis Report
                                        </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Review Modal -->
                <div id="reviewModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="reviewTitle">Review Chapter</h3>
                            <button class="close">&times;</button>
                        </div>
                        <form id="reviewForm" method="POST">
                            <input type="hidden" name="action" value="submit_review">
                            <input type="hidden" id="chapterId" name="chapter_id">
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="scoreInput">Score (0-100):</label>
                                    <input type="number" id="scoreInput" name="score" min="0" max="100" placeholder="Enter score">
                                </div>
                                <div class="form-group">
                                    <label for="statusSelect">Status:</label>
                                    <select id="statusSelect" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="approved">Approved</option>
                                        <option value="needs_revision">Needs Revision</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="feedbackText">Feedback:</label>
                                    <textarea id="feedbackText" name="feedback" rows="6" placeholder="Provide detailed feedback..." required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn-primary">Submit Review</button>
                                <button type="button" class="btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="../JS/advisor_reviews.js"></script>
    <script src="../JS/session_timeout.js"></script>
    <script>
    // Set the current group ID for the analysis report
    function setCurrentGroupId(groupId) {
        window.currentGroupId = groupId;
        console.log('Group ID set to:', groupId);
    }
    
    // Initialize with first available group ID if any chapters exist
    <?php if (!empty($pending_chapters) && isset($pending_chapters[0]['group_id'])): ?>
        window.currentGroupId = <?php echo $pending_chapters[0]['group_id']; ?>;
    <?php else: ?>
        window.currentGroupId = null;
    <?php endif; ?>
    </script>
</body>
</html>
