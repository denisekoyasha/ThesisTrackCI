<?php
// Add this at the very top to catch all output
ob_start();

require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);

require_once __DIR__ . '/../db/db.php';

// Set headers first to prevent any output
header('Content-Type: application/json');

try {
    // Advisor is authenticated via requireRole
    $advisor_id = $_SESSION['user_id'];

    $advisor_id = $_SESSION['user_id'];

    // Check if file was uploaded
    if (!isset($_FILES['profileImage']) || $_FILES['profileImage']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['profileImage'];

    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Only JPG, PNG, and GIF images are allowed');
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('Image must be less than 2MB');
    }

    // Create upload directory if it doesn't exist (using absolute path)
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'advisor_' . $advisor_id . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;

    // After successful upload:
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save file. Check directory permissions.');
    }

    // Store relative path in database (without leading ../)
    $dbPath = 'uploads/profiles/' . $filename;
    
    // Update database
    $stmt = $conn->prepare("UPDATE advisors SET profile_picture = ? WHERE id = ?");
    if (!$stmt) {
        unlink($filePath); // Clean up file
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $dbPath, $advisor_id);
    
    if (!$stmt->execute()) {
        unlink($filePath); // Clean up file if DB update fails
        throw new Exception('Database update failed: ' . $stmt->error);
    }

    // Clear any output buffer
    ob_end_clean();
    
    // Return success with relative path
    echo json_encode([
        'success' => true, 
        'filePath' => $dbPath,
        'message' => 'Profile picture updated successfully'
    ]);
    exit();

} catch (Exception $e) {
    // Clear any output buffer
    ob_end_clean();
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}
?>
