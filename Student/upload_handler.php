<?php
require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Function to create notification (optionally attach to a group)
function createNotification($pdo, $user_id, $user_type, $title, $message, $type = 'info', $group_id = null) {
    try {
        if ($group_id !== null) {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, group_id, user_type, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$user_id, $group_id, $user_type, $title, $message, $type]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO notifications (user_id, user_type, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$user_id, $user_type, $title, $message, $type]);
        }

        // Return inserted ID for debugging/confirmation
        $lastId = $pdo->lastInsertId();
        if ($lastId) {
            return (int)$lastId;
        }

        return true;
    } catch (PDOException $e) {
        error_log("Notification creation error: " . $e->getMessage());
        // Log statement error info if available
        if (isset($stmt) && is_object($stmt)) {
            error_log("Notification statement error info: " . json_encode($stmt->errorInfo()));
        }
        return false;
    }
}

// Function to notify advisor about chapter upload
// Function to notify advisor about chapter upload
function notifyAdvisorAboutUpload($pdo, $group_id, $chapter_number, $chapter_name) {
    try {
        // Get advisor ID and group title for this group
        $stmt = $pdo->prepare("
            SELECT advisor_id, title FROM groups WHERE id = ?
        ");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        
        if ($group && $group['advisor_id']) {
            $advisor_id = $group['advisor_id'];
            $group_title = $group['title'] ?? 'Group ' . $group_id;
            $title = "New Chapter Uploaded";
            // Use lowercase chapter name for message body to match examples
            $message = "Group {$group_title} uploaded Chapter {$chapter_number}: " . strtolower($chapter_name) . " for your review.";
            $type = 'info';
            
            // Attach group_id so notifications match other parts of the app
            return createNotification($pdo, $advisor_id, 'advisor', $title, $message, $type, $group_id);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Advisor notification error: " . $e->getMessage());
        return false;
    }
}

// Function to notify all coordinators about chapter upload
function notifyCoordinatorsAboutUpload($pdo, $group_id, $chapter_number, $chapter_name) {
    try {
        // Get group title
        $stmt = $pdo->prepare("SELECT title FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);
        $group = $stmt->fetch();
        $group_title = $group['title'] ?? 'Group ' . $group_id;

        // Fetch all coordinators
        $coordStmt = $pdo->prepare("SELECT id FROM coordinators");
        $coordStmt->execute();
        $coordinators = $coordStmt->fetchAll();

        $title = 'New Chapter Uploaded';
        $message = "Group {$group_title} uploaded Chapter {$chapter_number}: " . strtolower($chapter_name) . ".";

        // Use INSERT...SELECT to create coordinator notifications and avoid duplicates
        $coordSql = "
            INSERT INTO notifications (user_id, group_id, user_type, title, message, type, is_read, created_at)
            SELECT c.id, ?, 'coordinator', ?, ?, 'info', 0, NOW()
            FROM coordinators c
            LEFT JOIN notifications n ON n.user_id = c.id
                AND n.user_type = 'coordinator'
                AND n.group_id = ?
                AND n.title = ?
                AND n.message = ?
            WHERE n.id IS NULL
        ";

        $coordStmt = $pdo->prepare($coordSql);
        $coordStmt->execute([$group_id, $title, $message, $group_id, $title, $message]);

        return ($coordStmt->rowCount() > 0);
    } catch (PDOException $e) {
        error_log("Coordinator notification error: " . $e->getMessage());
        return false;
    }
}

$user_id = $_SESSION['user_id'];
$chapter_number = $_POST['chapter_number'] ?? null;
$file = $_FILES['file'] ?? null;

// Validate inputs
if (!$chapter_number || !$file || $file['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'Invalid file or chapter number';
    if ($file && $file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file selected';
                break;
            default:
                $error_message = 'Upload error occurred';
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

try {
    // Get user's group and student name
    $groupQuery = $pdo->prepare("
        SELECT g.id, 
               CONCAT(s.first_name, ' ', s.last_name) as student_name 
        FROM groups g 
        JOIN group_members gm ON g.id = gm.group_id 
        JOIN students s ON gm.student_id = s.id 
        WHERE gm.student_id = ?
    ");
    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);

    if (!$userGroup) {
        echo json_encode(['success' => false, 'message' => 'No group found for this student']);
        exit();
    }

    $group_id = $userGroup['id'];
    $student_name = $userGroup['student_name'];

    // Validate file type and size
    $max_file_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_file_size) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
        exit();
    }

    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['pdf', 'doc', 'docx'];

    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Only PDF and Word documents (.pdf, .doc, .docx) are allowed']);
        exit();
    }

    // Create upload directory if it doesn't exist
    $upload_dir = dirname(__DIR__) . '/uploads/chapters/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("Failed to create upload directory: " . $upload_dir);
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    if (!is_writable($upload_dir)) {
        error_log("Upload directory is not writable: " . $upload_dir);
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit();
    }

    // Generate unique filename
    $original_filename = $file['name'];
    $safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);
    $filename = 'chapter_' . $chapter_number . '_group_' . $group_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
        exit();
    }

    // Define chapter names
    $chapter_names = [
        1 => 'Introduction',
        2 => 'Review of Related Literature',
        3 => 'Methodology',
        4 => 'Results and Discussion',
        5 => 'Summary, Conclusion, and Recommendation'
    ];

    $chapter_name = $chapter_names[$chapter_number] ?? 'Chapter ' . $chapter_number;

    // Save to database
    $pdo->beginTransaction();

    try {
        // START of Version 9 changes
        // version 9 : Changed logic to maintain upload history instead of overwriting previous uploads
        
        // Check if chapter already exists and mark old versions as not current
        $checkStmt = $pdo->prepare("SELECT id, file_path FROM chapters WHERE group_id = ? AND chapter_number = ? AND is_current = 1");
        $checkStmt->execute([$group_id, $chapter_number]);
        $existingChapter = $checkStmt->fetch();

        $version = 1; // Default version for new chapters
        
        if ($existingChapter) {
            // Get the highest version number for this chapter
            $versionStmt = $pdo->prepare("SELECT MAX(version) as max_version FROM chapters WHERE group_id = ? AND chapter_number = ?");
            $versionStmt->execute([$group_id, $chapter_number]);
            $versionResult = $versionStmt->fetch();
            $version = ($versionResult['max_version'] ?? 0) + 1;
            
            // Mark existing current chapter as not current
            $updateCurrentStmt = $pdo->prepare("UPDATE chapters SET is_current = 0 WHERE id = ?");
            $updateCurrentStmt->execute([$existingChapter['id']]);
        }

        // Insert new chapter version (always insert, never update to maintain history)
        $insertStmt = $pdo->prepare("INSERT INTO chapters (group_id, chapter_number, version, chapter_name, filename, original_filename, file_path, file_size, file_type, status, is_current, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'uploaded', 1, NOW())");
        $insertStmt->execute([
            $group_id, 
            $chapter_number,
            $version,
            $chapter_name, 
            $filename, 
            $original_filename, 
            'uploads/chapters/' . $filename, 
            $file['size'], 
            $file['type']
        ]);

        $newChapterId = $pdo->lastInsertId();
        
        // If there was a previous version, link it
        if ($existingChapter) {
            $linkStmt = $pdo->prepare("UPDATE chapters SET replaced_by = ? WHERE id = ?");
            $linkStmt->execute([$newChapterId, $existingChapter['id']]);
        }
        // END of Version 9 changes

        // CREATE NOTIFICATIONS AFTER SUCCESSFUL UPLOAD
        // Capture notification results to include in the web response for easier debugging
        $notifResults = [
            'advisor' => false,
            'coordinators' => false,
            'student' => false
        ];

        try {
            // Notify advisor about the new upload
            $advisorResult = notifyAdvisorAboutUpload($pdo, $group_id, $chapter_number, $chapter_name);
            $notifResults['advisor'] = ($advisorResult !== false && $advisorResult !== 0);
            if ($notifResults['advisor']) {
                error_log("Notification created for advisor about chapter upload");
            } else {
                error_log("Failed to create notification for advisor");
            }

            // Notify coordinators about the new upload
            $coordsResult = notifyCoordinatorsAboutUpload($pdo, $group_id, $chapter_number, $chapter_name);
            $notifResults['coordinators'] = ($coordsResult !== false && $coordsResult !== 0);
            if ($notifResults['coordinators']) {
                error_log("Notification(s) created for coordinators about chapter upload");
            } else {
                error_log("Failed to create notification for coordinators");
            }

            // Create notification for student (attach to group)
            $studentResult = createNotification($pdo, $user_id, 'student', 
                "Chapter Uploaded", 
                "Your Chapter {$chapter_number}: {$chapter_name} has been uploaded successfully and is pending advisor review.", 
                'success',
                $group_id
            );
            $notifResults['student'] = ($studentResult !== false && $studentResult !== 0);
            if ($notifResults['student']) {
                error_log("Notification created for student about chapter upload");
            } else {
                error_log("Failed to create notification for student");
            }
        } catch (Exception $notificationError) {
            // Don't let notification errors break the upload process
            error_log("Notification error (non-fatal): " . $notificationError->getMessage());
        }

        // debug logging disabled in production

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Chapter uploaded successfully',
            'filename' => $original_filename,
            'chapter_name' => $chapter_name,
            'version' => $version, // version 9 : Added version info to response
            'file_path' => 'uploads/chapters/' . $filename,
            'download_url' => '../uploads/chapters/' . $filename,
            // debug flags to help identify whether notifications were created
            'debug_notifications' => $notifResults
        ]);

    } catch (PDOException $e) {
        $pdo->rollback();
        // Delete uploaded file if database save fails
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        error_log("Database error in upload_handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }

} catch (Exception $e) {
    error_log("General error in upload_handler: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during upload']);
}
?>
