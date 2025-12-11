<?php
require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php';

$student_id = $_SESSION['user_id'];

// Validate file upload
if (empty($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit();
}

$file = $_FILES['profile_picture'];

// Validate file type and size
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 2 * 1024 * 1024; // 2MB

if (!in_array($file['type'], $allowed_types)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit();
}

if ($file['size'] > $max_size) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit();
}

// Set up upload directory - use absolute server path
$upload_dir = dirname(__DIR__) . '/uploads/student_profiles/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit();
    }
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $student_id . '_' . time() . '.' . $file_ext;
$destination = $upload_dir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
    exit();
}

// Update database with relative path
try {
    $relative_path = 'uploads/student_profiles/' . $filename;
    
    $stmt = $pdo->prepare("UPDATE students SET profile_picture = ? WHERE id = ?");
    $stmt->execute([$relative_path, $student_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated',
        'image_path' => $relative_path
    ]);
    
} catch (PDOException $e) {
    unlink($destination); // Clean up
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
