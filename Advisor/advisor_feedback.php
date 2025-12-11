<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);

// Handle notification actions (mark as read, mark all read)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['notification_action'];
        
        if ($action === 'mark_as_read' && isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ? AND user_type = 'advisor'
            ");
            $stmt->execute([$notification_id, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
            ");
            $stmt->execute([$_SESSION['user_id']]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'get_notifications') {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? AND user_type = 'advisor' 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $notifications = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Notifications error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

// Get the logged-in advisor's ID and name
$advisor_id = $_SESSION['user_id'];
$user_name = 'Advisor';
$feedback_history = [];
$error_message = null;

$profile_picture = '../images/default-user.png'; // Default image

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

    // Fetch unread notifications count and recent notifications
    $notification_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notification_stmt->execute([$advisor_id]);
    $notifications = $notification_stmt->fetchAll();
    
    // Count unread notifications
    $unread_count_stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
    ");
    $unread_count_stmt->execute([$advisor_id]);
    $unread_notifications_count = $unread_count_stmt->fetch()['count'];

    // Fetch feedback history for this advisor
    $sql = "
        SELECT 
            cc.id AS comment_id,
            c.id AS chapter_id,
            c.version,
            c.review_score,
            c.chapter_number,
            c.chapter_name,
            c.status,
            g.title AS group_title,
            cc.comment,
            cc.created_at AS feedback_date,
            s.first_name,
            s.last_name
        FROM chapter_comments cc
        JOIN chapters c ON cc.chapter_id = c.id
        JOIN groups g ON c.group_id = g.id
        JOIN group_members gm ON g.id = gm.group_id
        JOIN students s ON gm.student_id = s.id
        WHERE g.advisor_id = ?
        AND cc.commenter_type = 'advisor'
        ORDER BY cc.created_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $advisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $feedback_history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (PDOException $e) {
    error_log("PDO Database error: " . $e->getMessage());
    $error_message = "Unable to fetch advisor details. Please try again later.";
} catch (mysqli_sql_exception $e) {
    error_log("MySQLi Database error: " . $e->getMessage());
    $error_message = "Unable to fetch feedback history. Please try again later.";
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    $error_message = "An unexpected error occurred. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_feedback.css">
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                <!-- ✅ FIXED: Changed all href links to data-tab system -->
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item" data-tab="students">
                    <i class="fas fa-user-graduate"></i> Student Management
                </a>
                <a href="advisor_thesis-group.php" class="nav-item" data-tab="students">
                    <i class="fas fa-users-rectangle"></i> Groups Management
                </a>
                <a href="advisor_reviews.php" class="nav-item" data-tab="reviews">
                    <i class="fas fa-tasks"></i> Feedback Management
                </a>
                <a href="advisor_feedback.php" class="nav-item active" data-tab="feedback">
                    <i class="fas fa-comments"></i> Feedback History
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <!-- Enhanced logout confirmation modal -->
                <div id="logoutModal" class="modal">
                    <div class="modal-content modal-centered">
                        <div class="modal-header">
                            <h3 class="modal-title">Confirm Logout</h3>
                            <button class="close-modal" onclick="closeLogoutModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="logout-confirmation">
                                <div class="logout-icon">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <p>Are you sure you want to logout from ThesisTrack?</p>
                                <p class="logout-note">You will need to login again to access your dashboard.</p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button class="btn-modal btn-cancel" id="cancelLogout" onclick="closeLogoutModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="btn-modal btn-danger" id="confirmLogout" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i> Yes, Logout
                            </button>
                        </div>
                    </div>
                </div>
                
            </nav>

        </aside>
        <!-- End Sidebar -->


        <!-- Start HEADER -->
        <div class="content-wrapper">
         <header class="blank-header">
             <div class="topbar-left">
    </div>
                <div class="topbar-right">
<!-- Notification Dropdown -->
                    <div class="notification-dropdown">
                        <button class="topbar-icon" title="Notifications" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="notification-badge" id="notificationBadge">
                                    <?php echo $unread_notifications_count > 9 ? '9+' : $unread_notifications_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="notification-menu" id="notificationMenu">
                            <div class="notification-header">
                                <h4>Notifications</h4>
                                <?php if ($unread_notifications_count > 0): ?>
                                    <button class="mark-all-read" id="markAllRead">Mark all as read</button>
                                <?php endif; ?>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                             data-id="<?php echo $notification['id']; ?>">
                                            <span class="notification-type type-<?php echo $notification['type'] ?? 'info'; ?>">
                                                <?php echo ucfirst($notification['type'] ?? 'info'); ?>
                                            </span>
                                            <div class="notification-title">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </div>
                                            <div class="notification-message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </div>
                                            <div class="notification-time">
                                                <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-notifications">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="#" class="view-all-notifications">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                <div class="user-info dropdown">
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar" class="user-avatar" id="userAvatar" tabindex="0" />
        <div class="dropdown-menu" id="userDropdown">
          <a href="advisor_settings.php" class="dropdown-item">
            <i class="fas fa-cog"></i> Settings
          </a>
          <a href="#" id="logoutLink" class="dropdown-item">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
           
        
        </div>
      </div>
    </div>
        </header>
        <!-- End HEADER -->

        <!-- Main Content -->
        <main class="main-content">
                      <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-comments"></i> Feedback History</h1>
                     <button class="togglebtn" onclick="toggleSidebar()">☰</button>
                    <p>Review all feedback provided to your thesis groups.</p>
                </div>

           <!-- Feedback History Tab -->
              <div id="feedback" class="tab-content">
                    <div class="card">
                        <h3>Thesis Group Feedback Review</h3>
                        
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                        <?php endif; ?>

                        <?php if (empty($feedback_history)): ?>
                            <div class="no-results">
                                <!-- <i class="fas fa-comment-slash"></i> -->
                                <p>No feedback history found.</p>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <?php foreach ($feedback_history as $feedback): ?>
                                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                            <div>
                                                <h4 style="color: #2d3748;">
                                                    <?php echo htmlspecialchars($feedback['group_title']); ?> - 
                                                    Chapter <?php echo htmlspecialchars($feedback['chapter_number']); ?>: 
                                                    <?php echo htmlspecialchars($feedback['chapter_name']); ?>
                                                </h4>
                                                <p style="color: #4a5568; font-size: 0.9rem;">
                                                    Submitted by: <?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?>
                                                </p>
                                                <div style="font-size: 0.85rem; color: #718096; margin-top:6px;">
                                                    Version: <?php echo htmlspecialchars($feedback['version'] ?? '1'); ?>
                                                    <?php if (!is_null($feedback['review_score']) && $feedback['review_score'] !== ''): ?>
                                                        &nbsp;•&nbsp; Score: <?php echo htmlspecialchars($feedback['review_score']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="background: <?php 
                                                    echo $feedback['status'] === 'approved' ? '#48bb78' : 
                                                           ($feedback['status'] === 'needs_revision' ? '#e53e3e' : '#ed8936'); 
                                                ?>; color: white; padding: 0.25rem 0.6rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $feedback['status'])); ?>
                                                </div>
                                                <div style="font-size: 0.8rem; color: #718096;">
                                                    Reviewed: <?php echo date('M d, Y', strtotime($feedback['feedback_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="background: #f7fafc; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                                            <p style="color: #4a5568; line-height: 1.6;">
                                                <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                                            </p>
                                        </div>
                                        <button class="btn-secondary btn-small" 
                                            id="editBtn-<?php echo $feedback['comment_id']; ?>"
                                            data-comment="<?php echo htmlspecialchars($feedback['comment'], ENT_QUOTES); ?>"
                                            onclick="editFeedback(<?php echo $feedback['comment_id']; ?>)">
                                            Edit Feedback
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Edit Feedback Modal -->
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Feedback</h3>
                            <span class="close" onclick="closeModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editFeedbackForm" action="process_feedback_edit.php" method="POST">
                                <input type="hidden" id="commentId" name="comment_id">
                                <div class="form-group">
                                    <label for="editFeedbackText">Feedback:</label>
                                    <textarea id="editFeedbackText" name="feedback" rows="6" required></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-primary" onclick="submitEdit()">Save Changes</button>
                            <button class="btn-secondary" onclick="closeModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>


    

     <script src="../JS/advisor_feedback.js"></script>

</body>
    <script src="../JS/session_timeout.js"></script>
</html>
