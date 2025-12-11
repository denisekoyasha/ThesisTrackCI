<?php
session_start();
require_once 'db/db.php';

$error = '';
$success = '';
$lockout_time = null;
$locked_email = null;

// Function to check login attempts and get lockout time
function isRateLimited($pdo, $email, $ip_address, &$lockout_time = null) {
    $time_limit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count, 
               MAX(attempted_at) as last_attempt
        FROM login_attempts 
        WHERE email = ? AND ip_address = ? AND attempted_at > ? AND success = 0
    ");
    $stmt->execute([$email, $ip_address, $time_limit]);
    $result = $stmt->fetch();
    
    if ($result['attempt_count'] >= 3) {
        $last_attempt = strtotime($result['last_attempt']);
        $lockout_time = $last_attempt + (5 * 60);
        return true;
    }
    
    return false;
}

// Function to clear failed login attempts for an email
function clearFailedAttempts($pdo, $email, $ip_address) {
    $stmt = $pdo->prepare("
        DELETE FROM login_attempts 
        WHERE email = ? AND ip_address = ? AND success = 0
    ");
    $stmt->execute([$email, $ip_address]);
    return $stmt->rowCount();
}

// Function to log login attempt
function logLoginAttempt($pdo, $email, $ip_address, $success) {
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address, success) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $ip_address, $success ? 1 : 0]);
}

// Function to log activity (kept for backward compatibility)
function logActivity($pdo, $user_id, $user_type, $action, $details) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, user_type, action, details, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$user_id, $user_type, $action, $details]);
    } catch (PDOException $e) {
        // If activity logging fails, log to PHP error log and continue
        error_log("Activity log error: " . $e->getMessage());
    }
}

// Function to log audit entries into audit_logs table
function logAudit($pdo, $user_id, $user_name, $role, $action, $details, $severity = 'low', $action_category = 'System Access', $ip_address = null) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, user_name, role, action, action_category, details, severity, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $user_id,
            $user_name,
            $role,
            $action,
            $action_category,
            $details,
            $severity,
            $ip_address
        ]);
    } catch (PDOException $e) {
        // If audit logging fails, write to PHP error log but don't stop login flow
        error_log("Audit log error: " . $e->getMessage());
    }
}

// Function to get client IP address
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Function to get remaining attempts
function getRemainingAttempts($pdo, $email, $ip_address) {
    $time_limit = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM login_attempts 
        WHERE email = ? AND ip_address = ? AND attempted_at > ? AND success = 0
    ");
    $stmt->execute([$email, $ip_address, $time_limit]);
    $result = $stmt->fetch();
    
    return 3 - $result['attempt_count'];
}

// Function to cleanup old login attempts
function cleanupOldAttempts($pdo) {
    $cleanup_time = date('Y-m-d H:i:s', strtotime('-1 day'));
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
    $stmt->execute([$cleanup_time]);
}

// Call cleanup
cleanupOldAttempts($pdo);

// ✅ Handle countdown completion - clear failed attempts
if (isset($_POST['clear_attempts']) && isset($_POST['email_to_clear'])) {
    $email_to_clear = sanitize($_POST['email_to_clear']);
    $ip_address = getClientIP();
    $cleared = clearFailedAttempts($pdo, $email_to_clear, $ip_address);
    
    // Return JSON response for AJAX call
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'cleared' => $cleared]);
    exit();
}

// ✅ Redirect if already logged in as coordinator
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'coordinator') {
    header('Location: Coordinator/coordinator_dashboard.php');
    exit();
}

// ✅ Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !isset($_POST['clear_attempts'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $ip_address = getClientIP();

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Check if rate limited for THIS specific email
            if (isRateLimited($pdo, $email, $ip_address, $lockout_time)) {
                // Always show 5 minutes countdown regardless of actual time
                $error = 'Too many login attempts for email: ' . htmlspecialchars($email) . '. Please try again in <span id="countdown-timer">05:00</span> or try a different email.';
                $locked_email = $email;
                // Set lockout time to exactly 5 minutes from now for consistency
                $lockout_time = time() + 300;
                // Don't insert another failed attempt as the account is already rate limited.
                // Log an audit entry indicating a login attempt during lockout.
                try {
                    logAudit($pdo, 0, $email, 'coordinator', 'login_failed', 'Rate-limited login attempt', 'low', 'System Access', $ip_address);
                } catch (Exception $e) {
                    error_log("Audit log error: " . $e->getMessage());
                }
            } else {
                // ✅ Query from `coordinators` table
                $stmt = $pdo->prepare("SELECT * FROM coordinators WHERE email = ?");
                $stmt->execute([$email]);
                $coordinator = $stmt->fetch();

                if (!$coordinator) {
                    $error = 'No user found with that email.';
                    logLoginAttempt($pdo, $email, $ip_address, false);
                    try {
                        logAudit($pdo, 0, $email, 'coordinator', 'login_failed', 'Invalid email address', 'low', 'System Access', $ip_address);
                    } catch (Exception $e) {
                        error_log("Audit log error: " . $e->getMessage());
                    }

                    $remaining_attempts = getRemainingAttempts($pdo, $email, $ip_address);
                    if ($remaining_attempts > 0) {
                        $error .= " You have {$remaining_attempts} attempt(s) remaining.";
                    }
                } else {
                    // ✅ Verify hashed password
                    if (password_verify($password, $coordinator['password'])) {
                        // Successful login - clear any previous failed attempts for this email
                        clearFailedAttempts($pdo, $email, $ip_address);
                        
                        // ✅ Set session variables
                        $_SESSION['user_id'] = $coordinator['id'];
                        $_SESSION['role'] = 'coordinator'; 
                        // Some DB schemas use 'coordinator_id'; if not present, fall back to 'id'
                        $_SESSION['coordinator_id'] = isset($coordinator['coordinator_id']) ? $coordinator['coordinator_id'] : $coordinator['id'];
                        $_SESSION['name'] = $coordinator['first_name'] . ' ' . $coordinator['last_name'];
                        $_SESSION['email'] = $coordinator['email'];
                        $_SESSION['profile_picture'] = $coordinator['profile_picture'];

                        // ✅ Update last login
                        $updateStmt = $pdo->prepare("UPDATE coordinators SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$coordinator['id']]);

                        logLoginAttempt($pdo, $email, $ip_address, true);
                        // Audit log for successful login
                        try {
                            logAudit(
                                $pdo,
                                $coordinator['id'], 
                                $coordinator['first_name'] . ' ' . $coordinator['last_name'], 
                                'coordinator', 
                                'login', 
                                'Coordinator logged in successfully',
                                'low',
                                'System Access',
                                $ip_address
                            );
                        } catch (Exception $e) {
                            error_log("Audit log error: " . $e->getMessage());
                        }
                        // Preserve existing activity log call if needed
                        logActivity($pdo, $coordinator['id'], 'coordinator', 'login', 'Coordinator logged in successfully from IP: ' . $ip_address);

                        // ✅ Redirect to dashboard
                        header('Location: Coordinator/coordinator_dashboard.php');
                        exit();
                    } else {
                        $error = 'Incorrect password.';
                        logLoginAttempt($pdo, $email, $ip_address, false);
                        try {
                            logAudit(
                                $pdo,
                                $coordinator['id'], 
                                $coordinator['first_name'] . ' ' . $coordinator['last_name'], 
                                'coordinator', 
                                'login_failed', 
                                'Incorrect password',
                                'low',
                                'System Access',
                                $ip_address
                            );
                        } catch (Exception $e) {
                            error_log("Audit log error: " . $e->getMessage());
                        }

                        $remaining_attempts = getRemainingAttempts($pdo, $email, $ip_address);
                        if ($remaining_attempts > 0) {
                            $error .= " You have {$remaining_attempts} attempt(s) remaining.";
                        } else {
                            // User just reached the limit, set lockout time to exactly 5 minutes
                            $lockout_time = time() + 300;
                            $locked_email = $email;
                            $error = 'Too many login attempts for email: ' . htmlspecialchars($email) . '. Please try again in <span id="countdown-timer">05:00</span> or try a different email.';
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'logout') {
    $success = 'You have been logged out successfully.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="CSS/login.css">
    <title>Login - ThesisTrack</title>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ThesisTrack</h1>
            <p>College of Information and Communication Technology</p>
        </div>
        <div class="user-type-indicator">
           <i class="fas fa-user-tie"></i>
            <h3>Coordinator</h3>
            
       <p> Oversees the entire system, manages users and roles, tracks thesis titles and progress, and customizes document requirements.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" id="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Pass lockout data to JavaScript -->
        <?php if ($lockout_time && $locked_email): ?>
            <input type="hidden" id="lockout-time" value="<?php echo $lockout_time; ?>">
            <input type="hidden" id="locked-email" value="<?php echo htmlspecialchars($locked_email); ?>">
        <?php endif; ?>

        <form method="POST" action="" id="login-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required>
                    <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="btn-login" id="login-button">
                Sign In
            </button>
        </form>

        <div class="demo-accounts">
            <!-- Demo accounts can be enabled for testing -->
            <!-- 
            <h4><i class="fas fa-key"></i> Demo Accounts</h4>
            <div class="demo-account" onclick="fillCredentials('coordinator@cict.edu', 'coordinator123')">
                <strong>Coordinator:</strong> coordinator@cict.edu / coordinator123
            </div>
            -->
            <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                <strong>Note:</strong> Professor and Student accounts are now created through the system by the Coordinator and Professors respectively.
            </p>
        </div>

        <div class="links">
            <a href="portal.php">← Back to Home</a>
        </div>
    </div>

    <script src="JS/login.js"></script>
</body>
</html>
