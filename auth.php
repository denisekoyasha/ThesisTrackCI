<?php
session_start();
require_once 'db/db.php';

// Session timeout configuration (seconds). Change as needed.
$DEFAULT_SESSION_TIMEOUT = 1800; // 2 minutes for smoke testing  (120=2 minutes) (1800=30 minutes)

/**
 * Enforce session timeout. If session is expired, destroys it and redirects
 * to a role-specific login page with ?timeout=1. If not expired, update last activity.
 */
function enforceSessionTimeout($timeoutSeconds = null) {
    global $pdo, $DEFAULT_SESSION_TIMEOUT;
    if (is_null($timeoutSeconds)) $timeoutSeconds = $DEFAULT_SESSION_TIMEOUT;

    // Ensure session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeoutSeconds)) {
        // Log the timeout if possible
        try {
            if (isset($pdo)) {
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, user_type, action, details) VALUES (?, ?, ?, ?)");
                $log_stmt->execute([
                    $_SESSION['user_id'] ?? null,
                    $_SESSION['role'] ?? null,
                    'session_timeout',
                    'Session expired due to inactivity'
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to log session timeout: " . $e->getMessage());
        }
        // Capture identifying info before destroying session
        $expired_user_id = $_SESSION['user_id'] ?? null;
        $expired_role = $_SESSION['role'] ?? null;

        // Destroy session
        session_unset();
        session_destroy();

        // Choose redirect target based on previous role if available
        $role = $expired_role;
        if ($role === 'advisor') {
            $redirect = '../advisor_login.php?timeout=1';
        } elseif ($role === 'coordinator') {
            $redirect = '../coordinator_login.php?timeout=1';
        } else {
            // default to student login
            $redirect = '../student_login.php?timeout=1';
        }

        // If AJAX request, return 401 instead
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['session_expired' => true]);
            exit;
        } else {
            header('Location: ' . $redirect);
            exit();
        }
    }

    // Update last activity
    $_SESSION['LAST_ACTIVITY'] = time();
}

function requireRole($allowedRoles) {
    // Enforce timeout first
    enforceSessionTimeout();

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: student_login.php');
        exit();
    }

    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect to role-specific login if access denied
        header('Location: student_login.php?error=access_denied');
        exit();
    }
}

?>
