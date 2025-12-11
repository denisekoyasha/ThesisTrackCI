<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

// enforceSessionTimeout will return a 401 JSON response for AJAX if the session is expired
try {
    // Call enforcement but do not redirect; auth.php handles AJAX 401 itself
    enforceSessionTimeout();
    echo json_encode(['success' => true]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
?>
