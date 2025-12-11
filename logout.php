<?php
session_start();

// Attempt to log the logout action if user is a coordinator (or any role)
// Include DB connection if available
require_once __DIR__ . '/db/db.php';

$ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
	try {
		if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
			$user_id = $_SESSION['user_id'];
			$user_name = $_SESSION['name'] ?? null;
			$role = $_SESSION['role'];

			// Avoid duplicate inserts: check if a logout record already exists for this user+IP in the last 5 seconds
			$checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM audit_logs WHERE user_id = ? AND action = 'logout' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 5 SECOND)");
			$checkStmt->execute([$user_id, $ip_address]);
			$count = $checkStmt->fetch()['cnt'] ?? 0;

			if ($count == 0) {
				// Insert into audit_logs (use prepared statement directly to avoid coupling)
				$stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, user_name, role, action, action_category, details, severity, ip_address, created_at) VALUES (?, ?, ?, 'logout', 'System Access', ?, 'low', ?, NOW())");
				$details = ($user_name ? $user_name . ' logged out' : 'User logged out');
				$stmt->execute([$user_id, $user_name, $role, $details, $ip_address]);
			}
		}
	} catch (Exception $e) {
		// Don't block logout on audit failures
		error_log('Logout audit log error: ' . $e->getMessage());
	}

// Destroy session and redirect
session_destroy();
header('Location: portal.php?success=logout');
exit();
?>
