<?php
session_start();
require_once 'db/db.php';

// Check if user is logged in and requires password change
if (!isset($_SESSION['user_id']) || !isset($_SESSION['requires_password_change']) || !$_SESSION['requires_password_change']) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        try {
            // Get current user data
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password'])) {
                // Update password and remove password change requirement
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $_SESSION['requires_password_change'] = 0;
                $success = 'Password changed successfully! Redirecting to dashboard...';
                
                // Redirect after 2 seconds
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '" . ($_SESSION['role'] === 'advisor' ? 'Advisor/advisor_dashboard.php' : 'Student/student_dashboard.php') . "';
                    }, 2000);
                </script>";
            } else {
                $error = 'Current password is incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="images/book-icon.ico">
    <link rel="stylesheet" href="CSS/login.css">
    <link rel="stylesheet" href="CSS/session_timeout.css">
    <title>Change Password - ThesisTrack</title>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ThesisTrack</h1>
            <p>College of Information and Communication Technology</p>
        </div>

        <div class="alert alert-info">
            <strong>Password Change Required</strong><br>
            For security reasons, you must change your password before accessing the system.
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <small>Password must be at least 6 characters long</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>

            <button type="submit" class="btn-login">Change Password</button>
        </form>

        <div class="links">
            <a href="logout.php">← Logout</a>
        </div>
    </div>
</body>
</html>
