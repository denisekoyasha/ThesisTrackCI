<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

$advisor_id = $_SESSION['user_id'];

// Accept POST
$comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : null;
$feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

if (empty($comment_id) || $feedback === null) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    // Ensure the comment belongs to a chapter that's under this advisor
    $sql = "
        UPDATE chapter_comments cc
        JOIN chapters c ON cc.chapter_id = c.id
        JOIN groups g ON c.group_id = g.id
        SET cc.comment = ?, cc.updated_at = NOW()
        WHERE cc.id = ? AND cc.commenter_type = 'advisor' AND g.advisor_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$feedback, $comment_id, $advisor_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Feedback updated']);
        exit();
    } else {
        // Could be not found or not permitted
        echo json_encode(['success' => false, 'message' => 'No matching feedback found or not permitted']);
        exit();
    }
} catch (Exception $e) {
    error_log('Error updating feedback: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit();
}
