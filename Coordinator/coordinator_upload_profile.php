<?php
ob_start();
require_once __DIR__ . '/../auth.php';
requireRole(['coordinator']);
require_once __DIR__ . '/../db/db.php';
header('Content-Type: application/json');

try {
    // Coordinator authenticated via requireRole

    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error', 400);
    }

    $file = $_FILES['profile_picture'];
    
    // Relative path from your PHP file to the uploads directory
    $relativeUploadDir = '/../uploads/profile_pictures/';
    
    // Full server path (for file operations)
    $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
    
    // Verify the directory exists
    if (!file_exists($uploadDir)) {
        throw new Exception('Upload directory does not exist', 500);
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable', 500);
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!array_key_exists($file['type'], $allowedTypes)) {
        throw new Exception('Only JPG, PNG or GIF images are allowed', 400);
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File size must be less than 2MB', 400);
    }

    $extension = $allowedTypes[$file['type']];
    $filename = 'coordinator_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    // Remove old file if exists
    $stmt = $pdo->prepare("SELECT profile_picture FROM coordinators WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($oldFile = $stmt->fetchColumn()) {
        @unlink($uploadDir . $oldFile);
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to save file. Check directory permissions.', 500);
    }

    $stmt = $pdo->prepare("UPDATE coordinators SET profile_picture = ? WHERE id = ?");
    $stmt->execute([$filename, $_SESSION['user_id']]);

    // Return the simple path you want to use
    echo json_encode([
        'success' => true,
        'filePath' => 'uploads/profile_pictures/' . $filename
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
