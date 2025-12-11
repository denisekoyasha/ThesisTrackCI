<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);

// Get the logged-in advisor's ID
$advisor_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Advisor';

$profile_picture = '../images/default-user.png'; // Default image

// Handle notification actions only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'mark_notification_read') {
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                // Mark single notification as read
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = 'advisor'");
                $stmt->execute([$notification_id, $advisor_id]);
                echo json_encode(['success' => true]);
            }
            
        } elseif ($_POST['action'] === 'mark_all_notifications_read') {
            // Mark all notifications as read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'advisor'");
            $stmt->execute([$advisor_id]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        error_log("Action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
    exit();
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

    // Get notifications for advisor
    $notifications_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications_stmt->execute([$advisor_id]);
    $notifications = $notifications_stmt->fetchAll();

    // Count unread notifications
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
    ");
    $unread_stmt->execute([$advisor_id]);
    $unread_result = $unread_stmt->fetch();
    $unread_notifications_count = $unread_result['unread_count'];

} catch (PDOException $e) {
    // Log the error and use default values
    error_log("Database error fetching advisor details: " . $e->getMessage());
    $user_name = 'Advisor';
    $profile_picture = '../images/default-user.png';
    $notifications = [];
    $unread_notifications_count = 0;
}

try {
    // Fetch all groups assigned to this advisor with course from student_groups
    $groups_query = "SELECT g.id, g.title, g.section, g.status, sg.course, sg.thesis_title
                    FROM groups g
                    LEFT JOIN student_groups sg ON sg.group_id = g.id
                    WHERE g.advisor_id = ?
                    ORDER BY g.section, g.title";
    $stmt = $conn->prepare($groups_query);
    $stmt->bind_param("i", $advisor_id);
    $stmt->execute();
    $groups_result = $stmt->get_result();

    // We'll store all group data in this array
    $groups_data = [];

    while ($group = $groups_result->fetch_assoc()) {
        $group_id = $group['id'];
        
        // Get members for this group
        $members_query = "SELECT s.id, s.first_name, s.last_name, gm.role_in_group
                        FROM group_members gm
                        JOIN students s ON gm.student_id = s.id
                        WHERE gm.group_id = ?
                        ORDER BY 
                        CASE gm.role_in_group
                            WHEN 'leader' THEN 0
                            ELSE 1
                        END,
                        s.last_name";
        $stmt_m = $conn->prepare($members_query);
        $stmt_m->bind_param("i", $group_id);
        $stmt_m->execute();
        $members_result = $stmt_m->get_result();
        
        $members = [];
        while ($member = $members_result->fetch_assoc()) {
            $members[] = $member;
        }
        
        // Get LATEST VERSION of chapters for this group - only show latest version per chapter
        // Also check if feedback exists for each chapter
        $chapters_query = "SELECT c1.*, 
                          (SELECT COUNT(*) FROM chapter_comments 
                           WHERE chapter_id = c1.id AND is_resolved = 0) as unresolved_comments,
                          (SELECT COUNT(*) FROM chapter_comments 
                           WHERE chapter_id = c1.id AND commenter_type = 'advisor') as has_feedback
                        FROM chapters c1
                        WHERE c1.group_id = ?
                        AND c1.version = (
                            SELECT MAX(c2.version) 
                            FROM chapters c2 
                            WHERE c2.group_id = c1.group_id 
                            AND c2.chapter_number = c1.chapter_number
                        )
                        ORDER BY c1.chapter_number";
        $stmt_c = $conn->prepare($chapters_query);
        $stmt_c->bind_param("i", $group_id);
        $stmt_c->execute();
        $chapters_result = $stmt_c->get_result();
        
        $chapters = [];
        $pending_review_count = 0;
        while ($chapter = $chapters_result->fetch_assoc()) {
            // Count chapters that need review (under_review or needs_revision)
            if ($chapter['status'] == 'under_review' || $chapter['status'] == 'needs_revision') {
                $pending_review_count++;
            }
            $chapters[] = $chapter;
        }
        
        // Store all data for this group
        $groups_data[] = [
            'group_info' => $group,
            'members' => $members,
            'chapters' => $chapters,
            'pending_review_count' => $pending_review_count
        ];
    }
} catch (Exception $e) {
    // Log the error and initialize empty groups data
    error_log("Error fetching groups data: " . $e->getMessage());
    $groups_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_group.css">
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
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item active" data-tab="groups">
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
                <a href="advisor_feedback.php" class="nav-item" data-tab="feedback">
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
                <div class="topbar-left"></div>
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
                            <a href="advisor_notifications.php" class="view-all-notifications">
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
                    <h1><i class="fas fa-users"></i> Groups Overview</h1>
                    <p>Monitor and review thesis progress for BSCS and BSIS student groups.</p>
                </div>
                <!-- End of Page Title -->

                <!-- Groups Tab -->
                <div id="groups" class="tab-content active">
                    <div class="card">
                        <h3>CICT Thesis Groups Under Your Supervision</h3>
                        
                        <div class="groups-grid">
                            <?php if (empty($groups_data)): ?>
                                <div class="no-groups-message">
                                    <i class="fas fa-users-slash"></i>
                                    <p>You don't have any assigned groups yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($groups_data as $group): ?>
                                <?php 
                                    $group_info = $group['group_info'];
                                    $members = $group['members'];
                                    $chapters = $group['chapters'];
                                    $pending_review_count = $group['pending_review_count'];
                                    
                                    // Determine course badge color
                                    $badge_class = ($group_info['course'] == 'BSCS') ? 'course-badge' : 'course-badge is-badge';
                                ?>
                                
                                <div class="group-card">
                                    <div class="group-header">
                                        <div class="group-title"><?php echo $group_info['title']; ?> - <?php echo $group_info['section']; ?></div>
                                        <div class="<?php echo $badge_class; ?>"><?php echo $group_info['course']; ?></div>
                                    </div>
                                    <div class="thesis-title"><?php echo htmlspecialchars($group_info['thesis_title']); ?></div>
                                    <div class="group-members">
                                        <h4>Group Members:</h4>
                                        <div class="members-list">
                                            <?php foreach ($members as $member): ?>
                                            <div>• <?php echo htmlspecialchars($member['first_name'] . ' ' . htmlspecialchars($member['last_name'])); ?>
                                                <?php if ($member['role_in_group'] == 'leader'): ?>
                                                <span class="leader-badge">(Leader)</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="chapter-progress">
                                        <h4>Chapter Progress:</h4>
                                        <div class="chapter-indicators">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php 
                                                    $chapter_status = 'pending';
                                                    $chapter_title = 'Chapter ' . $i . ': Not Started';
                                                    
                                                    foreach ($chapters as $chapter) {
                                                        if ($chapter['chapter_number'] == $i) {
                                                            $chapter_status = str_replace('_', '-', $chapter['status']);
                                                            $chapter_title = 'Chapter ' . $i . ': ' . ucfirst(str_replace('_', ' ', $chapter['status']));
                                                            if ($chapter['unresolved_comments'] > 0) {
                                                                $chapter_title .= ' (' . $chapter['unresolved_comments'] . ' unresolved comments)';
                                                            }
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                <div class="chapter-indicator <?php echo $chapter_status; ?>" 
                                                     title="<?php echo $chapter_title; ?>"><?php echo $i; ?></div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="group-actions">
                                        <button class="btn-expand" onclick="toggleGroupDetails('group<?php echo $group_info['id']; ?>')">View Details</button>
                                        <?php 
                                            /*
                                            // Pending review button logic commented out per request
                                            // Find the first chapter that needs review
                                            $chapter_to_review = null;
                                            foreach ($chapters as $chapter) {
                                                if ($chapter['status'] == 'under_review' || $chapter['status'] == 'needs_revision') {
                                                    $chapter_to_review = $chapter;
                                                    break;
                                                }
                                            }
                                            
                                            if ($chapter_to_review): 
                                        ?>
                                            <!-- Pending review button intentionally disabled -->
                                        <?php else: ?>
                                            <!-- Pending reviews link intentionally disabled -->
                                        <?php endif; ?>
                                        */
                                        ?>
                                    </div>

                                    <div class="group-details" id="group<?php echo $group_info['id']; ?>-details">
                                        <?php if (empty($chapters)): ?>
                                            <div class="no-chapters-message">
                                                <i class="fas fa-file-alt"></i>
                                                <p>No chapters uploaded yet.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($chapters as $chapter): ?>
                                            <div class="chapter-detail clickable-chapter" onclick="window.location.href='advisor_reviews.php'">
                                                <div class="chapter-detail-header">
                                                    <div class="chapter-title-section">
                                                        <span class="chapter-name">Chapter <?php echo $chapter['chapter_number']; ?>: <?php echo htmlspecialchars($chapter['chapter_name']); ?></span>
                                                        <div class="chapter-meta">
                                                            <span class="version-badge">
                                                                Version <?php echo $chapter['version']; ?>
                                                            </span>
                                                            <?php if ($chapter['has_feedback'] > 0): ?>
                                                                <span class="feedback-badge has-feedback">
                                                                    <i class="fas fa-comment"></i> Feedback Given
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="feedback-badge no-feedback">
                                                                    <i class="fas fa-comment-slash"></i> No Feedback
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <span class="chapter-score <?php
                                                            if ($chapter['status'] == 'approved') echo 'completed';
                                                            elseif ($chapter['status'] == 'needs_revision') echo 'needs-revision';
                                                            else echo 'in-progress';
                                                        ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $chapter['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="../JS/advisor_group.js"></script>
</body>
    <script src="../JS/session_timeout.js"></script>
</html>
