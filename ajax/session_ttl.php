<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

// Let enforceSessionTimeout handle expiry. If it detects expiry and this is an AJAX call,
// it will emit a 401 JSON response and exit. If not expired, it will update LAST_ACTIVITY.
enforceSessionTimeout();

// If we get here, session is active. Compute remaining seconds.
$timeout = isset($DEFAULT_SESSION_TIMEOUT) ? $DEFAULT_SESSION_TIMEOUT : 1800;
$last = $_SESSION['LAST_ACTIVITY'] ?? time();
$elapsed = time() - $last;
$remaining = $timeout - $elapsed;
if ($remaining < 0) $remaining = 0;

echo json_encode(['success' => true, 'remaining_seconds' => (int)$remaining]);
exit();
?>
