<?php
require_once __DIR__ . '/../auth.php';
requireRole(['student']);
require_once __DIR__ . '/../db/db.php'; // Assuming this file exists and handles database connection

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

// Get user's group information

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

// Get chapters for the group
$chapters = [];
if ($userGroup && isset($pdo)) {
    // START of Version 9 changes
    // version 9 : Updated query to only fetch current chapter versions for kanban display
    $chaptersQuery = $pdo->prepare("
        SELECT *, 
               (SELECT COUNT(*) FROM chapters c2 WHERE c2.group_id = chapters.group_id AND c2.chapter_number = chapters.chapter_number) as total_versions
        FROM chapters
        WHERE group_id = ? AND is_current = 1
        ORDER BY chapter_number
    ");
    // END of Version 9 changes
    $chaptersQuery->execute([$userGroup['id']]);
    $chapters = $chaptersQuery->fetchAll(PDO::FETCH_ASSOC);
}

// START of Version 9 changes
// version 9 : Added missing chapter names array for kanban board display
$chapterNames = [
    1 => 'Introduction',
    2 => 'Review of Related Literature', 
    3 => 'Methodology',
    4 => 'Results and Discussion',
    5 => 'Summary, Conclusion, and Recommendation'
];
// END of Version 9 changes


// Calculate progress
$totalChapters = 5;
$completedChapters = 0;
foreach ($chapters as $chapter) {
    if ($chapter['status'] === 'approved') {
        $completedChapters++;
    }
}
$progressPercentage = ($totalChapters > 0) ? ($completedChapters / $totalChapters) * 100 : 0;

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
    <title>ThesisTrack</title>
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../CSS/student_kanban-progress.css">
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
            <a href="student_feedback.php" class="nav-item" data-tab="feedback">
                <i class="fas fa-comments"></i> Feedback
            </a>
            <a href="student_kanban-progress.php" class="nav-item active" data-tab="kanban">
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
        <!-- end of change for version 10 -->

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

             <!-- Kanban - Progress Tab -->
            <div id="kanban" class="tab-content">
                <div class="card">
                    <button class="togglebtn" onclick="toggleSidebar()">☰</button>
                    <h3 style="color: #1a202c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        Chapter Progress Board</h3>
                    <div class="kanban-board">
                        <?php
                        $kanbanColumns = [
                            'to_do' => ['title' => 'To Do', 'chapters' => []],
                            'in_progress' => ['title' => 'In Progress', 'chapters' => []],
                            'under_review' => ['title' => 'Under Review', 'chapters' => []],
                            'completed' => ['title' => 'Completed', 'chapters' => []],
                        ];

                        // Populate kanban columns with actual chapter data
                        foreach ($chapters as $chapter) {
                            $status = $chapter['status'];
                            $chapterTitle = $chapterNames[$chapter['chapter_number']] ?? 'N/A';
                            $cardData = [
                                'title' => "Chapter {$chapter['chapter_number']}: {$chapterTitle}",
                                'description' => htmlspecialchars($chapter['feedback'] ?? 'No specific task description.'),
                                'meta' => '', // This will be dynamically set
                                'priority_class' => '', // This will be dynamically set
                            ];

                            switch ($status) {
                                case 'not_submitted':
                                    $kanbanColumns['to_do']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Start working on this chapter.',
                                        'meta' => 'Due: TBD',
                                        'priority_class' => 'low',
                                    ]);
                                    break;
                                case 'pending': // version 9 : Added pending status handling
                                case 'in_progress':
                                    $kanbanColumns['in_progress']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Currently being drafted or revised.',
                                        'meta' => 'Last updated: ' . date('M j', strtotime($chapter['updated_at'] ?? $chapter['created_at'])),
                                        'priority_class' => 'medium',
                                    ]);
                                    break;
                                case 'uploaded': // version 9 : Fixed uploaded status handling with proper enum value
                                case 'under_review': // version 9 : Added under_review status handling
                                    $versionText = isset($chapter['total_versions']) && $chapter['total_versions'] > 1 
                                        ? " (v{$chapter['version']}, {$chapter['total_versions']} uploads)" 
                                        : " (v{$chapter['version']})";
                                    $kanbanColumns['under_review']['chapters'][] = array_merge($cardData, [
                                        'title' => $cardData['title'] . $versionText, // version 9 : Added version info to title
                                        'description' => 'Chapter uploaded and awaiting advisor review.',
                                        'meta' => 'Uploaded: ' . date('M j, Y g:i A', strtotime($chapter['upload_date'] ?? $chapter['created_at'])), // version 9 : Enhanced timestamp format
                                        'priority_class' => 'medium',
                                    ]);
                                    break;
                                case 'submitted': // Assuming 'submitted' means 'under review'
                                    $kanbanColumns['under_review']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Submitted for advisor review.',
                                        'meta' => 'Submitted: ' . date('M j', strtotime($chapter['created_at'])),
                                        'priority_class' => 'medium',
                                    ]);
                                    break;
                                case 'needs_revision':
                                    $kanbanColumns['in_progress']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Needs revision based on advisor feedback.',
                                        'meta' => 'Feedback received: ' . date('M j', strtotime($chapter['updated_at'] ?? $chapter['created_at'])),
                                        'priority_class' => 'high',
                                    ]);
                                    break;
                                case 'approved':
                                    $kanbanColumns['completed']['chapters'][] = array_merge($cardData, [
                                        'description' => 'Approved and finalized.',
                                        'meta' => 'Score: ' . htmlspecialchars($chapter['score'] ?? 'N/A') . '/100',
                                        'priority_class' => 'low',
                                    ]);
                                    break;
                            }
                        }

                        // Add placeholder chapters if not all 5 are present in DB
                        for ($i = 1; $i <= 5; $i++) {
                            $found = false;
                            foreach ($chapters as $chapter) {
                                if ($chapter['chapter_number'] == $i) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $chapterTitle = $chapterNames[$i] ?? 'N/A';
                                $kanbanColumns['to_do']['chapters'][] = [
                                    'title' => "Chapter {$i}: {$chapterTitle}",
                                    'description' => 'Not yet started.',
                                    'meta' => 'Due: TBD',
                                    'priority_class' => 'low',
                                ];
                            }
                        }

                        // Display kanban columns
                        foreach ($kanbanColumns as $columnId => $column):
                        ?>
                            <div class="kanban-column">
                                <h3><?php echo htmlspecialchars($column['title']); ?></h3>
                                <div class="kanban-cards">
                                    <?php foreach ($column['chapters'] as $card): ?>
                                        <div class="kanban-card">
                                            <h4><?php echo htmlspecialchars($card['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($card['description']); ?></p>
                                            <div class="card-meta">
                                                <span class="priority <?php echo htmlspecialchars($card['priority_class']); ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $card['priority_class']))); ?></span>
                                                <span><?php echo htmlspecialchars($card['meta']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

 
        </main>
    </div>


    <script src="../JS/student_kanban-progress.js"></script>
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="../JS/session_timeout.js"></script>
  
</body>
</html>
