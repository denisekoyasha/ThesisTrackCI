<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);

// Get the logged-in advisor's ID and name
$advisor_id = $_SESSION['user_id'];
$user_name = 'Advisor';

$profile_picture = '../images/default-user.png'; // Default image

// Improved referrer handling - store the previous page URL
if (!isset($_SESSION['previous_page'])) {
    // Store current page as previous page for the first time
    $_SESSION['previous_page'] = 'advisor_dashboard.php'; // Default fallback
}

// Store the current page as previous page when navigating to settings
if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== $_SERVER['REQUEST_URI']) {
    $referrer = $_SERVER['HTTP_REFERER'];
    // Only store if it's from our domain and not the current page
    if (strpos($referrer, $_SERVER['HTTP_HOST']) !== false) {
        $_SESSION['previous_page'] = $referrer;
    }
}

// Alternative: Use URL parameter to specify return page
if (isset($_GET['from']) && !empty($_GET['from'])) {
    $allowed_pages = ['advisor_feedback.php', 'advisor_group.php', 'advisor_reviews.php', 'advisor_thesis-group.php', 'advisor_student_management.php'];
    $from_page = $_GET['from'];
    if (in_array($from_page, $allowed_pages)) {
        $_SESSION['previous_page'] = $from_page;
    }
}

// Handle password change
$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    try {
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'All password fields are required!';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New passwords do not match!';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'New password must be at least 6 characters long!';
            $message_type = 'error';
        } else {
            // Get current password from database
            $stmt = $pdo->prepare("SELECT password FROM advisors WHERE id = ?");
            $stmt->execute([$advisor_id]);
            $advisor = $stmt->fetch();
            
            if ($advisor && password_verify($current_password, $advisor['password'])) {
                // Current password is correct, update to new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $pdo->prepare("UPDATE advisors SET password = ?, requires_password_change = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->execute([$hashed_password, $advisor_id]);
                
                // Store message and preserve previous page for redirect
                $preserved_previous_page = $_SESSION['previous_page'] ?? 'advisor_dashboard.php';
                $_SESSION['password_change_message'] = 'Password changed successfully!';
                $_SESSION['password_change_type'] = 'success';
                $_SESSION['previous_page'] = $preserved_previous_page; // Preserve for next load
                
                header('Location: ' . $_SERVER['PHP_SELF'] . '?from=' . urlencode($preserved_previous_page));
                exit();
                
            } else {
                $message = 'Current password is incorrect!';
                $message_type = 'error';
            }
        }
    } catch (PDOException $e) {
        error_log("Database error during password change: " . $e->getMessage());
        $message = 'An error occurred while changing password. Please try again.';
        $message_type = 'error';
    }
}

// Check for session messages from redirect
if (isset($_SESSION['password_change_message'])) {
    $message = $_SESSION['password_change_message'];
    $message_type = $_SESSION['password_change_type'];
    unset($_SESSION['password_change_message']);
    unset($_SESSION['password_change_type']);
}

try {
    // Get advisor details including profile picture
    $stmt = $pdo->prepare("SELECT first_name, last_name, profile_picture FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor = $stmt->fetch();
    
    $user_name = ($advisor['first_name'] && $advisor['last_name']) ? $advisor['first_name'] . ' ' . $advisor['last_name'] : 'Advisor';
    
    // Check if profile picture exists and is valid
    if (!empty($advisor['profile_picture'])) {
        $relative_path = $advisor['profile_picture'];
        $absolute_path = __DIR__ . '/../' . $relative_path;
        
        if (file_exists($absolute_path) && is_readable($absolute_path)) {
            $profile_picture = '../' . $relative_path;
        } else {
            error_log("Profile image not found: " . $absolute_path);
        }
    }

} catch (PDOException $e) {
    error_log("Database error fetching advisor details: " . $e->getMessage());
    $user_name = 'Advisor';
    $profile_picture = '../images/default-user.png';
}

// Get the previous page for the back button
$previous_page = $_SESSION['previous_page'] ?? 'advisor_dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <title>Settings - ThesisTrack</title>
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <!-- start of Version 10 changes -->
    <!-- changed code start, purpose: Link to external CSS file matching student settings structure -->
    <link rel="stylesheet" href="../CSS/advisor_settings.css">
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <!-- changed code end, Version 10 -->
</head>
<body data-previous-page="<?php echo htmlspecialchars($previous_page); ?>">
    <div class="app-container">
        <!-- start of Version 10 changes -->
        <!-- changed code start, purpose: Match student settings HTML structure exactly -->
        <!-- Main Content -->
        <div class="content-wrapper">
        <button class="back-btn" id="backButton">
            <i class="fas fa-arrow-left"></i> Back
        </button>

            <div class="main-content">
                <div class="page-header">
                    <h1 class="page-title">Settings</h1>
                    <p class="page-subtitle">Manage your account preferences and review workflow settings</p>
                </div>

                <!-- Change Password Section -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i>
                        Change Password
                    </h2>
                    
                    <!-- Display message if any -->
                    <?php if (!empty($message)): ?>
                        <div class="message <?php echo $message_type; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form id="passwordForm" method="POST" action="">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label class="form-label" for="currentPassword">Current Password</label>
                            <input type="password" id="currentPassword" name="current_password" class="form-input" placeholder="Enter your current password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="new_password" class="form-input" placeholder="Enter your new password" required>
                            <small class="form-help">Password must be at least 6 characters long</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-input" placeholder="Confirm your new password" required>
                        </div>
                        <button type="submit" class="save-btn">
                            <i class="fas fa-save"></i>
                            Save Password
                        </button>
                    </form>
                </div>

                <!-- Notification Preferences Section -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-bell"></i>
                        Notification Preferences
                    </h2>
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">New Chapter Submissions</div>
                            <div class="toggle-description">Get notified when students submit new chapters for review</div>
                        </div>
                        <div class="toggle-switch active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">Student Feedback Requests</div>
                            <div class="toggle-description">Receive alerts when students request feedback or send messages</div>
                        </div>
                        <div class="toggle-switch active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">System Announcements</div>
                            <div class="toggle-description">Stay updated with important system announcements and updates</div>
                        </div>
                        <div class="toggle-switch active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                </div>

                               <!-- Review Workflow Preferences Section -->
                <div class="settings-section">
                    <h2 class="section-title">
                        <i class="fas fa-tasks"></i>
                        Review Workflow Preferences
                    </h2>
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <div class="toggle-title">Automatic Reminders</div>
                            <div class="toggle-description">Enable automatic reminders for pending chapter reviews</div>
                        </div>
                        <div class="toggle-switch" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    
                    <!-- Reminder customization with slider -->
                    <div class="reminder-customization">
                        <div class="form-group">
                            <label class="form-label" for="reminderSlider">
                                <span id="reminderLabel">Remind after 7 days</span>
                            </label>
                            <div class="slider-container">
                                <input type="range" id="reminderSlider" class="reminder-slider" min="1" max="30" value="7">
                                <div class="slider-track"></div>
                                <div class="slider-fill"></div>
                            </div>
                            <div class="quick-select-buttons">
                                <button type="button" class="quick-btn" data-value="4">4 days</button>
                                <button type="button" class="quick-btn active" data-value="7">7 days</button>
                                <button type="button" class="quick-btn" data-value="10">10 days</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- changed code end, Version 10 -->
    </div>
  
    <script src="../JS/advisor_settings.js"></script>
</body>
    <script src="../JS/session_timeout.js"></script>
</html>
