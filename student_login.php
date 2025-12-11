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

// Function to log activity
function logActivity($pdo, $user_id, $user_type, $action, $details) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, user_type, action, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $user_type, $action, $details]);
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

// ✅ Handle new password change if required
if (isset($_SESSION['requires_password_change']) && $_SESSION['requires_password_change'] == 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $error = 'Password must contain at least one special character.';
    } else {
        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE students SET password = ?, requires_password_change = 0 WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            $_SESSION['requires_password_change'] = 0;
            logActivity($pdo, $_SESSION['user_id'], 'student', 'password_change', 'Student changed their password successfully');

            // Audit log the password change using project helper
            try {
                if (function_exists('logAudit')) {
                    $ip_address = getClientIP();
                    logAudit($pdo, $_SESSION['user_id'], $_SESSION['name'] ?? 'Student', 'student', 'password_change', 'Student changed password after first login', 'medium', 'Account Management', $ip_address);
                } else {
                    // Fallback direct insert
                    $ip_address = getClientIP();
                    $auditStmt = $pdo->prepare(
                        "INSERT INTO audit_logs (user_id, user_name, role, action, action_category, details, severity, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $auditStmt->execute([
                        $_SESSION['user_id'],
                        $_SESSION['name'] ?? 'Student',
                        'student',
                        'password_change',
                        'Account Management',
                        'Student changed password after first login',
                        'medium',
                        $ip_address
                    ]);
                }
            } catch (Exception $e) {
                error_log("Audit log error: " . $e->getMessage());
            }

            header("Location: Student/student_dashboard.php");
            exit();
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}

// ✅ Handle login
if (!isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !isset($_POST['clear_attempts'])) {
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
                try {
                    logAudit($pdo, 0, $email, 'student', 'login_failed', 'Rate-limited login attempt', 'low', 'System Access', $ip_address);
                } catch (Exception $e) {
                    error_log("Audit log error: " . $e->getMessage());
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
                $stmt->execute([$email]);
                $student = $stmt->fetch();

                if (!$student) {
                    $error = 'No user found with that email.';
                    logLoginAttempt($pdo, $email, $ip_address, false);
                    try {
                        logAudit($pdo, 0, $email, 'student', 'login_failed', 'Invalid email address', 'low', 'System Access', $ip_address);
                    } catch (Exception $e) {
                        error_log("Audit log error: " . $e->getMessage());
                    }

                    $remaining_attempts = getRemainingAttempts($pdo, $email, $ip_address);
                    if ($remaining_attempts > 0) {
                        $error .= " You have {$remaining_attempts} attempt(s) remaining.";
                    }
                } elseif (password_verify($password, $student['password'])) {
                    // Successful login - clear any previous failed attempts for this email
                    clearFailedAttempts($pdo, $email, $ip_address);
                    
                    $_SESSION['user_id'] = $student['id'];
                    $_SESSION['role'] = 'student';
                    $_SESSION['student_id'] = $student['student_id'];
                    $_SESSION['name'] = $student['first_name'] . ' ' . $student['last_name'];
                    $_SESSION['email'] = $student['email'];
                    $_SESSION['course'] = $student['course'];
                    $_SESSION['section'] = $student['section'];
                    $_SESSION['year_level'] = $student['year_level'];
                    $_SESSION['profile_picture'] = $student['profile_picture'];
                    $_SESSION['requires_password_change'] = $student['requires_password_change'];

                    $updateStmt = $pdo->prepare("UPDATE students SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$student['id']]);

                    logLoginAttempt($pdo, $email, $ip_address, true);
                    try {
                        logAudit($pdo, $student['id'], $student['first_name'] . ' ' . $student['last_name'], 'student', 'login', 'Student logged in successfully', 'low', 'System Access', $ip_address);
                    } catch (Exception $e) {
                        error_log("Audit log error: " . $e->getMessage());
                    }
                    logActivity($pdo, $student['id'], 'student', 'login', 'Student logged in successfully from IP: ' . $ip_address);

                    if ($student['requires_password_change']) {
                        // Stay on this page to show password change form
                    } else {
                        header("Location: Student/student_dashboard.php");
                        exit();
                    }
                } else {
                    $error = 'Incorrect password.';
                    logLoginAttempt($pdo, $email, $ip_address, false);
                    try {
                        logAudit($pdo, $student['id'], $student['first_name'] . ' ' . $student['last_name'], 'student', 'login_failed', 'Incorrect password', 'low', 'System Access', $ip_address);
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
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// ✅ Redirect if already logged in and no password change required
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student' && (!isset($_SESSION['requires_password_change']) || $_SESSION['requires_password_change'] == 0)) {
    header('Location: Student/student_dashboard.php');
    exit();
}

if (isset($_GET['success']) && $_GET['success'] === 'logout') {
    $success = 'You have been logged out successfully.';
}

// Clear any existing session when arriving at login page (only if not timeout)
if (!isset($_GET['timeout'])) {
    // Don't destroy session if we're in the middle of password change
    if (!isset($_SESSION['requires_password_change']) || $_SESSION['requires_password_change'] != 1) {
        session_unset();
        session_destroy();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ThesisTrack</title>
    <link rel="stylesheet" href="CSS/login.css">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ThesisTrack</h1>
            <p>College of Information and Communication Technology</p>
        </div>
        <div class="user-type-indicator">
            <i class="fas fa-user-graduate"></i>
            <h3>Student Portal</h3>
            <p>Submits and tracks thesis proposals, views feedback, and communicates with advisors.</p>
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

       <!-- ✅ Change Password Form -->
        <?php if (isset($_SESSION['requires_password_change']) && $_SESSION['requires_password_change'] == 1): ?>
            <form method="POST" action="" id="passwordChangeForm">
                <h3>Change Your Temporary Password</h3>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required>
                    <!-- Added password strength indicator -->
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-bar">
                            <div class="strength-bar-fill" id="strengthBar"></div>
                        </div>
                        <p class="strength-text" id="strengthText"></p>
                    </div>
                    <ul class="password-requirements" id="passwordRequirements">
                        <li id="req-length">At least 8 characters</li>
                        <li id="req-uppercase">One uppercase letter</li>
                        <li id="req-lowercase">One lowercase letter</li>
                        <li id="req-number">One number</li>
                        <li id="req-special">One special character</li>
                    </ul>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                    <!-- Added password match indicator -->
                    <p class="password-match" id="passwordMatch" style="display: none;"></p>
                </div>

                <button type="submit" class="btn-login">Update Password</button>
            </form>

        <!-- ✅ Regular Login Form -->
        <?php else: ?>
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
        <?php endif; ?>

        <div class="demo-accounts">
            <p style="margin-top: 1rem; font-size: 0.9rem; color: #666;">
                <strong>Note:</strong> Professor and Student accounts are now created through the system by the Coordinator and Professors respectively.
            </p>
        </div>

        <div class="links">
            <a href="portal.php">← Back to Home</a>
        </div>
    </div>

    <script src="JS/login.js"></script>
    <!-- Added password validation script for student login -->
    <script>
        // Password strength validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const passwordMatch = document.getElementById('passwordMatch');
        const form = document.getElementById('passwordChangeForm');

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                passwordStrength.style.display = password.length > 0 ? 'block' : 'none';

                // Check requirements
                const requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password)
                };

                // Update requirement indicators
                document.getElementById('req-length').className = requirements.length ? 'valid' : '';
                document.getElementById('req-uppercase').className = requirements.uppercase ? 'valid' : '';
                document.getElementById('req-lowercase').className = requirements.lowercase ? 'valid' : '';
                document.getElementById('req-number').className = requirements.number ? 'valid' : '';
                document.getElementById('req-special').className = requirements.special ? 'valid' : '';

                // Calculate strength
                const metRequirements = Object.values(requirements).filter(Boolean).length;
                let strength = 0;
                let strengthLabel = '';
                let strengthColor = '';

                if (metRequirements <= 2) {
                    strength = 20;
                    strengthLabel = 'Weak';
                    strengthColor = '#ff4444';
                } else if (metRequirements === 3) {
                    strength = 40;
                    strengthLabel = 'Fair';
                    strengthColor = '#ff8800';
                } else if (metRequirements === 4) {
                    strength = 70;
                    strengthLabel = 'Good';
                    strengthColor = '#ffbb00';
                } else if (metRequirements === 5) {
                    strength = 100;
                    strengthLabel = 'Strong';
                    strengthColor = '#00cc66';
                }

                strengthBar.style.width = strength + '%';
                strengthBar.style.backgroundColor = strengthColor;
                strengthText.textContent = strengthLabel;
                strengthText.style.color = strengthColor;

                // Check password match
                if (confirmPasswordInput.value) {
                    checkPasswordMatch();
                }
            });
        }

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }

        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length > 0) {
                passwordMatch.style.display = 'block';
                if (password === confirmPassword) {
                    passwordMatch.textContent = '✓ Passwords match';
                    passwordMatch.style.color = '#00cc66';
                } else {
                    passwordMatch.textContent = '✗ Passwords do not match';
                    passwordMatch.style.color = '#ff4444';
                }
            } else {
                passwordMatch.style.display = 'none';
            }
        }

        // Form validation
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }

                // Check all requirements
                if (password.length < 8 || 
                    !/[A-Z]/.test(password) || 
                    !/[a-z]/.test(password) || 
                    !/[0-9]/.test(password) || 
                    !/[^A-Za-z0-9]/.test(password)) {
                    e.preventDefault();
                    alert('Password does not meet all requirements!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
