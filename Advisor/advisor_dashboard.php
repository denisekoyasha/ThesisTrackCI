<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);

// Get advisor information from database
$advisor_id = $_SESSION['user_id'];
$user_name = 'Advisor';
$total_groups = 0;
$completed_chapters = 0;
$pending_reviews = 0;
$average_score = 0;
$group_progress = [];
$unread_notifications_count = 0;

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

    // Get notifications for advisor
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$advisor_id]);
    $notifications = $stmt->fetchAll();

    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count FROM notifications 
        WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
    ");
    $stmt->execute([$advisor_id]);
    $unread_result = $stmt->fetch();
    $unread_notifications_count = $unread_result['unread_count'];

    // Get all statistics in single queries
    // Get total groups
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM student_groups WHERE advisor_id = ?");
    $stmt->execute([$advisor_id]);
    $total_groups = $stmt->fetch()['total'];

    // Get completed chapters
    // Note: chapters.group_id references the `groups` table. The `student_groups` table stores a mapping
    // and contains a `group_id` column that points to `groups.id`. Join using `sg.group_id = c.group_id`.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completed 
        FROM chapters c
        INNER JOIN student_groups sg ON sg.group_id = c.group_id
        WHERE sg.advisor_id = ? AND c.status = 'approved'
    ");
    $stmt->execute([$advisor_id]);
    $completed_chapters = (int)$stmt->fetch()['completed'];

        // Get pending reviews. Chapters reference `groups` via c.group_id, and `student_groups` maps advisor
        // to the canonical `groups` via sg.group_id. Use the same set of statuses as in advisor_reviews.php
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending 
            FROM chapters c
            JOIN student_groups sg ON sg.group_id = c.group_id
            WHERE sg.advisor_id = ? 
            AND c.status IN ('pending','under_review','uploaded')
        ");
        $stmt->execute([$advisor_id]);
        $pending_reviews = (int)$stmt->fetchColumn(0);

    // Get average score
    $stmt = $pdo->prepare("
        SELECT AVG(review_score) AS average 
        FROM chapters 
        WHERE reviewer_id = ? AND reviewer_type = 'advisor' AND review_score IS NOT NULL
    ");
    $stmt->execute([$advisor_id]);
    $result = $stmt->fetch();
    $average_score = $result['average'] ? round($result['average'], 2) : 0;

    // Get group progress. For the current thesis flow we expect 5 chapters (1..5).
    // Use COUNT(DISTINCT ...) on chapter_number to avoid counting multiple versions, and fix total_chapters=5.
    $stmt = $pdo->prepare("
        SELECT 
            sg.id,
            sg.group_name,
            sg.section,
            sg.thesis_title,
            5 AS total_chapters,
            COUNT(DISTINCT CASE WHEN c.status = 'approved' AND c.chapter_number BETWEEN 1 AND 5 THEN c.chapter_number END) AS completed_chapters,
            CASE
                WHEN 5 = 0 THEN 0
                ELSE ROUND(COUNT(DISTINCT CASE WHEN c.status = 'approved' AND c.chapter_number BETWEEN 1 AND 5 THEN c.chapter_number END) / 5 * 100)
            END AS progress_percentage
        FROM student_groups sg
        LEFT JOIN chapters c ON sg.group_id = c.group_id
            AND c.chapter_number BETWEEN 1 AND 5
        WHERE sg.advisor_id = ?
        GROUP BY sg.id, sg.group_name, sg.section, sg.thesis_title
        ORDER BY sg.group_name
    ");
    $stmt->execute([$advisor_id]);
    $group_progress = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error in advisor dashboard: " . $e->getMessage());
}

// Handle profile picture upload via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    header('Content-Type: application/json');
    
    try {
        $file = $_FILES['profile_picture'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed']);
            exit();
        }
        
        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'Image must be less than 2MB']);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'advisor_' . $advisor_id . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Update database
            $stmt = $pdo->prepare("UPDATE advisors SET profile_picture = ? WHERE id = ?");
            $stmt->execute([$filePath, $advisor_id]);
            
            echo json_encode(['success' => true, 'filePath' => $filePath]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        }
    } catch (PDOException $e) {
        error_log("Profile upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
    exit();
}

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['action'] === 'mark_as_read') {
            $notification_id = $_POST['notification_id'] ?? null;
            
            if ($notification_id) {
                // Mark single notification as read
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND user_type = 'advisor'");
                $stmt->execute([$notification_id, $advisor_id]);
            } else {
                // Mark all notifications as read
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'advisor'");
                $stmt->execute([$advisor_id]);
            }
            
            echo json_encode(['success' => true]);
            
        } elseif ($_POST['action'] === 'get_notifications') {
            // Return notifications for AJAX request
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? AND user_type = 'advisor' 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$advisor_id]);
            $notifications = $stmt->fetchAll();
            
            // Count unread notifications
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count FROM notifications 
                WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
            ");
            $stmt->execute([$advisor_id]);
            $unread_result = $stmt->fetch();
            $unread_count = $unread_result['unread_count'];
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
        }
    } catch (PDOException $e) {
        error_log("Notification action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    }
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_dashboard.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
</head>
<body>
    <div class="app-container">
       <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item active" data-tab="analytics">
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
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                            alt="User Avatar" 
                            class="user-avatar" 
                            id="userAvatar" 
                            tabindex="0" />
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

            <!-- Main Content -->
            <main class="main-content">
                <header class="main-header">
                    <h1>Welcome, <?php echo htmlspecialchars($user_name); ?></h1>
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                    </div>
                </header>

                <!-- Analytics Tab -->
                <div id="analytics" class="tab-content">
                    <div class="card">
                        <h3>Progress Analytics</h3>
                        <p class="analytics-subtitle">Overview of thesis progress across all your supervised groups.</p>

                        <div class="analytics-grid">
                            <a href="advisor_thesis-group.php" class="stat-card gradient-blue">
                                <div class="stat-number"><?php echo $total_groups; ?></div>
                                <div class="stat-label">Total Groups</div>
                            </a>

                            <a href="advisor_group.php" class="stat-card gradient-green">
                                <div class="stat-number"><?php echo $completed_chapters; ?></div>
                                <div class="stat-label">Completed Chapters</div>
                            </a>

                            <a href="advisor_reviews.php" class="stat-card gradient-orange">
                                <div class="stat-number"><?php echo $pending_reviews; ?></div>
                                <div class="stat-label">Pending Reviews</div>
                            </a>

                            <a href="#" class="stat-card gradient-teal">
                                <div class="stat-number"><?php echo $average_score; ?>%</div>
                                <div class="stat-label">Average Score</div>
                            </a>
                        </div>
                    </div>

                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
                        <h4 style="color: #2d3748; margin-bottom: 1rem;">Group Progress Overview</h4>
                        <?php if ($total_groups > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                <?php foreach ($group_progress as $group): 
                                    $progress = $group['progress_percentage'] ?? 0;
                                    $color = match(true) {
                                        $progress >= 70 => '#48bb78',
                                        $progress >= 40 => '#ed8936',
                                        default => '#38b2ac'
                                    };
                                    $thesis_title = !empty($group['thesis_title']) ? 
                                        htmlspecialchars($group['thesis_title']) : 
                                        '[No Thesis Title]';
                                ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span style="font-weight: 600;">
                                            <?php echo $thesis_title; ?>, 
                                            <?php echo htmlspecialchars($group['group_name']); ?> 
                                            (<?php echo htmlspecialchars($group['section']); ?>)
                                        </span>
                                        <span style="font-weight: 600; color: <?php echo $color; ?>">
                                            <?php echo $progress; ?>%
                                        </span>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%; background-color: <?php echo $color; ?>;">
                                            <?php if ($progress > 5): ?>
                                                <?php echo $progress; ?>%
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #718096;">
                                        <span><?php echo $group['completed_chapters']; ?> of <?php echo $group['total_chapters']; ?> chapters completed</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-groups-message">
                                <i class="fas fa-users-slash" style="font-size: 2rem; color: #a0aec0; margin-bottom: 1rem;"></i>
                                <h4 style="color: #4a5568;">No Groups Assigned</h4>
                                <p style="color: #718096;">You currently don't have any thesis groups assigned to you.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="profile-upload-modal" id="uploadModal">
        <div class="profile-upload-modal-content">
            <span class="profile-upload-close" onclick="closeUploadModal()">&times;</span>
            <h3>Update Profile Picture</h3>
            
            <form id="avatarUploadForm" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to select an image</p>
                    <img id="imagePreview" class="preview-image">
                    <input type="file" id="fileInput" name="profile_picture" accept="image/*" style="display:none;" onchange="previewImage(this)">
                </div>
                <button type="button" class="upload-button" id="uploadBtn" onclick="uploadProfilePicture()">Upload</button>
            </form>
        </div>
    </div>

    <script src="../JS/advisor_dashboard.js"></script>
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="../JS/session_timeout.js"></script>
   
</body>
</html>
