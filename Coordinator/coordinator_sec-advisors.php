<?php
require_once __DIR__ . '/../auth.php';
requireRole(['coordinator']);
require_once __DIR__ . '/../db/db.php';

// =================V7 UPDATE
// In your coordinator session verification code:
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, profile_picture FROM coordinators WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $coordinator = $stmt->fetch();
    
    if (!$coordinator) {
        header('Location: ../login.php');
        exit();
    }
    
    $user_name = $coordinator['name'];
    $profile_picture = $coordinator['profile_picture'] ? '../uploads/profile_pictures/' . $coordinator['profile_picture'] : '../images/default-user.png';
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}
// =================END OF V7 UPDATE


// Fetch section-advisor data from database
try {
    $stmt = $pdo->query("
        SELECT 
            asec.section,
            asec.course,
            asec.advisor_id,
            CONCAT(a.first_name, ' ', a.last_name) AS advisor_name,
            COUNT(DISTINCT sg.id) AS group_count,
                (
                    SELECT COUNT(*) 
                    FROM students s 
                    WHERE s.advisor_id = asec.advisor_id 
                    AND s.course = asec.course
                    AND s.section = asec.section
                ) AS student_count,
            a.status AS advisor_status
        FROM advisor_sections asec
        JOIN advisors a ON asec.advisor_id = a.id
        LEFT JOIN student_groups sg ON asec.section = sg.section AND asec.course = sg.course
        GROUP BY asec.section, asec.course, a.id
        ORDER BY asec.course, asec.section
    ");
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback to sample data if query fails
    $sections = [
        [
            'section' => 'BSCS-4A',
            'course' => 'BS Computer Science',
            'advisor_name' => 'Dr. Amanda Martinez',
            'group_count' => 2,
            'student_count' => 3, // This will now reflect actual student count from your database
            'advisor_status' => 'active'
        ],
        [
            'section' => 'BSCS-4B',
            'course' => 'BS Computer Science',
            'advisor_name' => 'Dr. Michael Thompson',
            'group_count' => 1,
            'student_count' => 0, // This will now reflect actual student count from your database
            'advisor_status' => 'active'
        ],
    ];
    error_log("Database error: " . $e->getMessage());
}

// ==================== PAGINATION LOGIC ==================== //
$entries_per_page = $_GET['entries'] ?? 5;
$page = $_GET['page'] ?? 1;
$total_sections = count($sections);
$total_pages = ceil($total_sections / $entries_per_page);
$offset = ($page - 1) * $entries_per_page;
$paginated_sections = array_slice($sections, $offset, $entries_per_page);
// ==================== END PAGINATION LOGIC ==================== //

function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active state: Solid caret-style arrow
        $arrow = $sort_order == 'asc' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow" title="Sorted"></i>';
    }
    // Neutral state: Standard sort icon
    return '<i class="fas fa-sort neutral-arrow" title="Click to sort"></i>';
}

// Notification functions
function createCoordinatorNotification($pdo, $title, $message, $type = 'info', $group_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, type, is_read, group_id, created_at) 
            SELECT id, 'coordinator', ?, ?, ?, 0, ?, NOW() 
            FROM coordinators 
            WHERE status = 'active'
        ");
        $stmt->execute([$title, $message, $type, $group_id]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Specific notification functions for different events
function notifyCoordinatorChapterUpload($pdo, $group_id, $chapter_number, $chapter_name, $student_name) {
    $title = "New Chapter Submission";
    $message = "Group {$group_id} submitted Chapter {$chapter_number}: {$chapter_name} by {$student_name}";
    return createCoordinatorNotification($pdo, $title, $message, 'info', $group_id);
}

function notifyCoordinatorChapterReview($pdo, $group_id, $chapter_number, $advisor_name, $status) {
    $title = "Chapter Review Completed";
    $message = "Advisor {$advisor_name} {$status} Chapter {$chapter_number} for Group {$group_id}";
    return createCoordinatorNotification($pdo, $title, $message, 'success', $group_id);
}

function notifyCoordinatorAdvisorAssignment($pdo, $advisor_name, $section, $course) {
    $title = "New Advisor Assignment";
    $message = "{$advisor_name} assigned to {$section} ({$course})";
    return createCoordinatorNotification($pdo, $title, $message, 'info', null);
}

function notifyCoordinatorGroupCreation($pdo, $group_name, $section, $advisor_name) {
    $title = "New Thesis Group";
    $message = "Group '{$group_name}' created in {$section} under {$advisor_name}";
    return createCoordinatorNotification($pdo, $title, $message, 'info', null);
}

// Fetch coordinator-specific notifications
try {
    $notification_stmt = $pdo->prepare("
        SELECT n.*, sg.group_name 
        FROM notifications n 
        LEFT JOIN student_groups sg ON n.group_id = sg.id 
        WHERE n.user_type = 'coordinator' 
        ORDER BY n.created_at DESC 
        LIMIT 10
    ");
    $notification_stmt->execute();
    $notifications = $notification_stmt->fetchAll();
    
    // Count unread notifications
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_type = 'coordinator' AND is_read = 0
    ");
    $unread_stmt->execute();
    $unread_result = $unread_stmt->fetch();
    $unread_notifications_count = $unread_result['unread_count'] ?? 0;
    
} catch (PDOException $e) {
    error_log("Notification fetch error: " . $e->getMessage());
    $notifications = [];
    $unread_notifications_count = 0;
}

// Handle AJAX requests for notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'mark_as_read') {
        if (isset($_POST['notification_id'])) {
            // Mark single notification as read
            $notification_id = $_POST['notification_id'];
            try {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE id = ? AND user_type = 'coordinator'
                ");
                $stmt->execute([$notification_id]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log("Mark notification read error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            // Mark all as read
            try {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE user_type = 'coordinator' AND is_read = 0
                ");
                $stmt->execute();
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log("Mark all notifications read error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
        exit();
    }
    
    if ($_POST['action'] === 'get_notifications') {
        try {
            $notification_stmt = $pdo->prepare("
                SELECT n.*, sg.group_name 
                FROM notifications n 
                LEFT JOIN student_groups sg ON n.group_id = sg.id 
                WHERE n.user_type = 'coordinator' 
                ORDER BY n.created_at DESC 
                LIMIT 10
            ");
            $notification_stmt->execute();
            $notifications = $notification_stmt->fetchAll();
            
            $unread_stmt = $pdo->prepare("
                SELECT COUNT(*) as unread_count 
                FROM notifications 
                WHERE user_type = 'coordinator' AND is_read = 0
            ");
            $unread_stmt->execute();
            $unread_result = $unread_stmt->fetch();
            $unread_count = $unread_result['unread_count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unread_count
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/coordinator_sec-advisors.css">
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
               <div class="sidebar-user">
    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
         class="sidebar-avatar" 
         alt="Coordinator Avatar"
         id="currentProfilePicture"
         onerror="this.src='../images/default-user.png'" />
    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
</div>
                <span class="role-badge">Research Coordinator</span>
            </div>
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item" data-tab="overview">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item active" data-tab="sections">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item" data-tab="advisors">
                    <i class="fas fa-chalkboard-teacher"></i> Advisor Management
                </a>
                <a href="coordinator_thesis-titles-overview.php" class="nav-item">
                    <i class="fas fa-book"></i> Thesis Titles Overview
                </a>
                <a href="coordinator_document-control-panel.php" class="nav-item">
                    <i class="fas fa-book-open"></i> Document Control Panel
                </a>
                 <!-- CHANGE> Added audit logs navigation link -->
                <a href="coordinator_audit_log.php" class="nav-item" data-tab="audit-logs">
                    <i class="fas fa-history"></i> Audit Logs
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
        </aside>    <!-- End Sidebar -->

        <!-- Main Content -->
        <div class="content-wrapper">
              <!-- Header -->
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
                            <a href="#" class="view-all-notifications">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                    <div class="user-info dropdown">
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>?t=<?php echo time(); ?>" 
                            alt="User Avatar" 
                            class="user-avatar" 
                            id="userAvatar" 
                            tabindex="0"
                            onerror="this.src='../images/default-user.png'" />
                        <div class="dropdown-menu" id="userDropdown">
                            <a href="coordinator_settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="#" id="logoutLink" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>

                   
                </div>
            </header>   <!-- End Header -->

            <main class="main-content">
                <div id="sections" class="tab-content">
                
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-school"></i> CICT Sections and Advisor Assignments</h1>
                    <p>Manage section assignments and advisor workloads across BSCS and BSIS programs.</p>
                </div>
                 <!-- End of Page Header -->

                    <div class="card">
                        <h3>Section Advisory Overview</h3>

                        <!-- Show entries and search -->
                        <div class="table-controls-row">
                            <form method="GET" action="" class="entries-form">
                                <div class="entries-selector">
                                    <span>Show</span>
                                    <select name="entries" onchange="this.form.submit()" class="entries-select">
                                        <?php
                                        $entries_options = [5, 10, 25, 50];
                                        $selected_entries = $_GET['entries'] ?? 5;
                                        
                                        foreach ($entries_options as $option) {
                                            $selected = ($option == $selected_entries) ? 'selected' : '';
                                            echo "<option value='$option' $selected>$option</option>";
                                        }
                                        ?>
                                    </select>
                                    <span>entries</span>
                                </div>
                                
                                <!-- Preserve other GET parameters -->
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'entries' && $key !== 'page'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>

                            <form class="modern-search" method="GET" action="">
                                <div class="search-container">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" placeholder="Search here..." class="search-input" 
                                        value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES) ?>">
                                    <!-- Preserve other GET parameters including entries -->
                                    <?php foreach ($_GET as $key => $value): ?>
                                        <?php if ($key !== 'search' && $key !== 'page'): ?>
                                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>

                        <table id="sectionsTable" class="sections-table">
                            <thead>
                            <tr>
                                <th>Section <i class="fas fa-sort neutral-arrow"></i></th>
                                <th>Program <i class="fas fa-sort neutral-arrow"></i></th>
                                <th>Assigned Advisor <i class="fas fa-sort neutral-arrow"></i></th>
                                <th>Handled Groups <i class="fas fa-sort neutral-arrow"></i></th>
                                <th>Students <i class="fas fa-sort neutral-arrow"></i></th>
                                <th>Status <i class="fas fa-sort neutral-arrow"></i></th>
                                <!-- <th>Actions</th> -->
                            </tr>
                        </thead>

                            <tbody>
                                <?php foreach ($paginated_sections as $section): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($section['section']); ?></strong></td>
                                        <td>
                                            <span class="course-badge <?php echo strtolower(str_replace(' ', '-', $section['course'])); ?>">
                                                <?php echo htmlspecialchars($section['course']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($section['advisor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['group_count']); ?></td>
                                        <td><?php echo htmlspecialchars($section['student_count']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $section['advisor_status'] === 'active' ? 'active' : 'inactive'; ?>">
                                                <?php echo ucfirst($section['advisor_status']); ?>
                                            </span>
                                        </td>
                                        <!-- <td>
                                            <button class="btn-secondary btn-small" onclick="viewSectionDetails('<?php echo htmlspecialchars($section['section']); ?>')">
                                                View Details
                                            </button>
                                        </td> -->
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&entries=<?= $entries_per_page ?>">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?= $i ?>&entries=<?= $entries_per_page ?>" <?= $i == $page ? 'class="active"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page+1 ?>&entries=<?= $entries_per_page ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
   
    <script src="../JS/coordinator_sec-advisors.js"></script>

</body>
    <script src="../JS/session_timeout.js"></script>
</html>
