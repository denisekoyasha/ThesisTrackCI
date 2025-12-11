<?php
require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';
$user_section = $_SESSION['section'] ?? null;

// In your dashboard PHP code where you fetch the profile picture:
$profile_picture = '../images/default-user.png'; // Default image

try {
    // Get student's profile picture path
    $stmt = $pdo->prepare("SELECT profile_picture FROM students WHERE id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    // Verify and set profile picture if exists
    if (!empty($student['profile_picture'])) {
        $relative_path = $student['profile_picture'];
        $absolute_path = dirname(__DIR__) . '/' . $relative_path;
        
        // Check if file exists and is readable
        if (file_exists($absolute_path) && is_readable($absolute_path)) {
            $profile_picture = '../' . $relative_path;
        }
    }
} catch (PDOException $e) {
    error_log("Database error fetching profile picture: " . $e->getMessage());
}

//Get user's group information
$userGroup = null;
if (isset($pdo)) {
    // Get group and advisor name
    $groupQuery = $pdo->prepare("
        SELECT g.*, 
               CONCAT(a.first_name, ' ', a.middle_name, ' ', a.last_name) AS advisor_name
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        LEFT JOIN advisors a ON g.advisor_id = a.id
        WHERE gm.student_id = ?
    ");
    $groupQuery->execute([$user_id]);
    $userGroup = $groupQuery->fetch(PDO::FETCH_ASSOC);
}

// Get group members
$groupMembers = [];
if ($userGroup && isset($pdo)) {
    $membersQuery = $pdo->prepare("
        SELECT 
            CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS name,
            s.email, 
            gm.role_in_group
        FROM students s
        JOIN group_members gm ON s.id = gm.student_id
        WHERE gm.group_id = ?
    ");
    $membersQuery->execute([$userGroup['id']]);
    $groupMembers = $membersQuery->fetchAll(PDO::FETCH_ASSOC);
}

// Get chapters and advisor feedback for the group (only advisor comments)
$chapters = [];
if ($userGroup && isset($pdo)) {
    $chaptersQuery = $pdo->prepare(
        "SELECT c.*, 
                cc.comment AS advisor_comment, 
                cc.created_at AS advisor_comment_date,
                cc.commenter_id AS advisor_id_from_comment,
                c.review_score AS review_score,
                c.advisor_feedback AS advisor_feedback_text,
                a.first_name AS advisor_first_name,
                a.middle_name AS advisor_middle_name,
                a.last_name AS advisor_last_name
         FROM chapters c
         LEFT JOIN chapter_comments cc ON cc.chapter_id = c.id AND cc.commenter_type = 'advisor'
         LEFT JOIN groups g ON g.id = c.group_id
         LEFT JOIN advisors a ON g.advisor_id = a.id
         WHERE c.group_id = ?
         ORDER BY c.chapter_number, cc.created_at DESC"
    );
    $chaptersQuery->execute([$userGroup['id']]);
    $chapters = $chaptersQuery->fetchAll(PDO::FETCH_ASSOC);
}


// Calculate progress
$totalChapters = 5;
$completedChapters = 0;
foreach ($chapters as $chapter) {
    if ($chapter['status'] === 'approved') {
        $completedChapters++;
    }
}
$progressPercentage = ($totalChapters > 0) ? ($completedChapters / $totalChapters) * 100 : 0;

// Chapter names mapping (used for display)
$chapterNames = [
    1 => 'Introduction',
    2 => 'Review of Related Literature',
    3 => 'Methodology',
    4 => 'Results and Discussion',
    5 => 'Summary, Conclusion, and Recommendation'
];

// Fetch notifications
$notifications = [];
$unread_notifications_count = 0;

try {
    // Get notifications
    $notificationsQuery = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $notificationsQuery->execute([$user_id]);
    $notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Count unread notifications
    $unreadQuery = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $unreadQuery->execute([$user_id]);
    $unread_result = $unreadQuery->fetch(PDO::FETCH_ASSOC);
    $unread_notifications_count = $unread_result['unread_count'] ?? 0;

} catch (PDOException $e) {
    error_log("Database error fetching notifications: " . $e->getMessage());
}

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'mark_as_read':
                if (isset($_POST['notification_id'])) {
                    // Mark single notification as read
                    $markReadStmt = $pdo->prepare("
                        UPDATE notifications 
                        SET is_read = 1 
                        WHERE id = ? AND user_id = ?
                    ");
                    $markReadStmt->execute([$_POST['notification_id'], $user_id]);
                    echo json_encode(['success' => true]);
                }
                break;

            case 'mark_all_read':
                // Mark all notifications as read for this user
                $markAllReadStmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE user_id = ? AND is_read = 0
                ");
                $markAllReadStmt->execute([$user_id]);
                echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
                break;

            case 'get_notifications':
                // Return updated notifications
                $notificationsQuery = $pdo->prepare("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $notificationsQuery->execute([$user_id]);
                $updated_notifications = $notificationsQuery->fetchAll(PDO::FETCH_ASSOC);
                
                $unreadQuery = $pdo->prepare("
                    SELECT COUNT(*) as unread_count 
                    FROM notifications 
                    WHERE user_id = ? AND is_read = 0
                ");
                $unreadQuery->execute([$user_id]);
                $unread_result = $unreadQuery->fetch(PDO::FETCH_ASSOC);
                $updated_unread_count = $unread_result['unread_count'] ?? 0;
                
                echo json_encode([
                    'success' => true,
                    'notifications' => $updated_notifications,
                    'unread_count' => $updated_unread_count
                ]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("Notification action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <title>ThesisTrack</title>
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../CSS/student_feedback.css">
</head>
<body>


    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" 
         class="sidebar-avatar" 
         alt="Profile Picture"
         id="sidebarProfileImage" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Student</span>
            </div>
           <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="nav-item" data-tab="dashboard">
                <i class="fas fa-chart-bar"></i> Dashboard
            </a>
            <a href="student_chap-upload.php" class="nav-item" data-tab="upload">
                <i class="fas fa-folder"></i> Chapter Uploads
            </a>
            <a href="student_feedback.php" class="nav-item active" data-tab="feedback">
                <i class="fas fa-comments"></i> Feedback
            </a>
            <a href="student_kanban-progress.php" class="nav-item" data-tab="kanban">
                <i class="fas fa-clipboard-list"></i> Chapter Progress
            </a>
           <a href="#" id="logoutBtn" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i> Logout
           </a>
        </nav>
        
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

        </aside>
           <!-- End Sidebar -->

    <div class="content-wrapper">
        <!-- Start Header -->
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
                <img src="<?php echo htmlspecialchars($profile_picture); ?>"
     alt="User Avatar"
     class="user-avatar"
     id="userAvatar"
     tabindex="0" />
        <div class="dropdown-menu" id="userDropdown">
          <!-- start of Version 10 changes -->
          <!-- changed code start, purpose: update settings link to point to student_settings.php -->
          <a href="student_settings.php" class="dropdown-item">
            <i class="fas fa-cog"></i> Settings
          </a>
          <!-- changed code end, Version 10 -->
          <!-- end of Version 10 changes -->
         <a href="#" class="dropdown-item" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> Logout
         </a>
        </div>
      </div>
    </div>
        </header>
        <!-- End Header -->


        <main class="main-content">
            <!-- <header class="main-header">
                <h1>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <div class="header-actions">
                    <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                </div>
            </header> -->

            <!-- Feedback Tab -->
            <div id="feedback" class="tab-content">
                <div class="card">
                    <button class="togglebtn" onclick="toggleSidebar()">☰</button>
                   <h3 style="color: #1a202c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        Chapter Feedback from Advisor
                   </h3>

                    <?php if (!empty($chapters)): ?>
                        <?php
                        // Filter chapters that have advisor feedback (from chapter_comments or advisor_feedback fields)
                        $feedbackChapters = array_filter($chapters, function($c) {
                            return !empty($c['advisor_comment']) || !empty($c['advisor_feedback_text']) || !empty($c['advisor_feedback']);
                        });
                        // Sort by advisor comment date (if present), else fall back to chapter updated_at/created_at descending
                        usort($feedbackChapters, function($a, $b) {
                            $aDate = isset($a['advisor_comment_date']) && !empty($a['advisor_comment_date']) ? $a['advisor_comment_date'] : (isset($a['updated_at']) && !empty($a['updated_at']) ? $a['updated_at'] : $a['created_at']);
                            $bDate = isset($b['advisor_comment_date']) && !empty($b['advisor_comment_date']) ? $b['advisor_comment_date'] : (isset($b['updated_at']) && !empty($b['updated_at']) ? $b['updated_at'] : $b['created_at']);
                            return strtotime($bDate) - strtotime($aDate);
                        });
                        ?>
                        <?php if (!empty($feedbackChapters)): ?>
                            <?php
                                // Group feedback items by chapter_number
                                $grouped = [];
                                foreach ($feedbackChapters as $item) {
                                    $num = $item['chapter_number'] ?? 0;
                                    if (!isset($grouped[$num])) $grouped[$num] = [];
                                    $grouped[$num][] = $item;
                                }
                            ?>
                            <?php foreach ($grouped as $chapterNum => $items): ?>
                                <?php
                                    // Pick header info from the most recent item
                                    $latest = $items[0];
                                    // Determine advisor name
                                    $advisorName = trim((($latest['advisor_first_name'] ?? '') . ' ' . ($latest['advisor_middle_name'] ?? '') . ' ' . ($latest['advisor_last_name'] ?? '')));
                                    if (empty($advisorName)) {
                                        $advisorName = $userGroup['advisor_name'] ?? 'Advisor';
                                    }
                                    $displayScore = $latest['review_score'] ?? $latest['score'] ?? null;
                                ?>
                                <div class="feedback-item">
                                    <div class="feedback-header">
                                        <div>
                                            <strong><?php echo htmlspecialchars($advisorName); ?></strong>
                                            <div class="feedback-chapter">Chapter <?php echo htmlspecialchars($chapterNum); ?>: <?php echo htmlspecialchars($chapterNames[$chapterNum] ?? 'N/A'); ?></div>
                                        </div>
                                        <div style="text-align:right;">
                                            <?php if (!is_null($displayScore) && $displayScore !== ''): ?>
                                                <div style="background: <?php echo ($displayScore < 80 && $displayScore >= 60) ? '#ed8936' : (($displayScore < 60) ? '#f56565' : '#48bb78'); ?>; color: white; padding: 0.25rem 0.6rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem;">
                                                    <?php echo htmlspecialchars($displayScore); ?>/100
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="comment-history">
                                        <?php foreach ($items as $it): ?>
                                            <?php
                                                $commentText = $it['advisor_comment'] ?? null;
                                                $commentDate = $it['advisor_comment_date'] ?? null;
                                                $source = !empty($it['advisor_comment']) ? 'Comment' : (!empty($it['advisor_feedback_text']) || !empty($it['advisor_feedback']) ? 'Advisor Note' : '');
                                                // If there's no explicit comment but there is advisor_feedback, show it as a single note
                                                if (empty($commentText) && !empty($it['advisor_feedback_text'])) {
                                                    $commentText = $it['advisor_feedback_text'];
                                                    $commentDate = $it['updated_at'] ?? $it['created_at'];
                                                } elseif (empty($commentText) && !empty($it['advisor_feedback'])) {
                                                    $commentText = $it['advisor_feedback'];
                                                    $commentDate = $it['updated_at'] ?? $it['created_at'];
                                                }
                                            ?>
                                            <?php if (!empty($commentText)): ?>
                                                <div class="comment-card">
                                                    <div class="comment-meta">
                                                        <span><?php echo htmlspecialchars($source ?: 'Advisor'); ?></span>
                                                        &nbsp;•&nbsp;
                                                        <span><?php echo date('M j, Y g:i A', strtotime($commentDate)); ?></span>
                                                    </div>
                                                    <div class="comment-body">
                                                        <p style="margin:0; color:#2d3748; line-height:1.6;"><?php echo nl2br(htmlspecialchars($commentText)); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No feedback received yet.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No group chapters found to display feedback.</p>
                    <?php endif; ?>
                </div>
            </div>

 
        </main>
    </div>

</body>
</html>
    <script src="../JS/student_feedback.js"></script>
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="../JS/session_timeout.js"></script>


</body>
</html>
