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

$user_id = $_SESSION['user_id'];
$chapter_number = $_POST['chapter_number'] ?? null;

// Validate inputs
if (!$chapter_number) {
    echo json_encode(['success' => false, 'message' => 'Chapter number is required']);
    exit();
}

try {
    // Get user's group
    $groupQuery = $pdo->prepare("SELECT g.id FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE gm.student_id = ?");
    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);

    if (!$userGroup) {
        echo json_encode(['success' => false, 'message' => 'No group found for this student']);
        exit();
    }

    $group_id = $userGroup['id'];

    // Get chapter file info
    $chapterQuery = $pdo->prepare("SELECT id, file_path, original_filename FROM chapters WHERE group_id = ? AND chapter_number = ?");
    $chapterQuery->execute([$group_id, $chapter_number]);
    $chapter = $chapterQuery->fetch(PDO::FETCH_ASSOC);

    if (!$chapter) {
        echo json_encode(['success' => false, 'message' => 'Chapter not found']);
        exit();
    }

    $pdo->beginTransaction();

    try {
        // Delete file from filesystem
        if ($chapter['file_path']) {
            $file_path = dirname(__DIR__) . '/' . $chapter['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // Delete from database
        $deleteStmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
        $deleteStmt->execute([$chapter['id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Chapter deleted successfully',
            'filename' => $chapter['original_filename']
        ]);

    } catch (PDOException $e) {
        $pdo->rollback();
        error_log("Database error in delete_handler: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }

} catch (Exception $e) {
    error_log("General error in delete_handler: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during deletion']);
}
?>
