<?php
// start of change for version 14
// created audit log page for coordinator role
// displays all system activities with search and filter functionality
// end of change for version 14


require_once __DIR__ . '/../auth.php';
requireRole(['coordinator']);
require_once __DIR__ . '/../db/db.php';

// Coordinator session verification
try {
    $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name, profile_picture FROM coordinators WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $coordinator = $stmt->fetch();
    
    if (!$coordinator) {
        header('Location: ../login.php');
        exit();
    }
    
    $user_name = $coordinator['name'];
    
    // Check if profile picture exists
    $profile_picture = '../images/default-user.png';
    if (!empty($coordinator['profile_picture'])) {
        $imagePath = '../uploads/profile_pictures/' . $coordinator['profile_picture'];
        if (file_exists($imagePath)) {
            $profile_picture = $imagePath;
        } else {
            $updateStmt = $pdo->prepare("UPDATE coordinators SET profile_picture = NULL WHERE id = ?");
            $updateStmt->execute([$_SESSION['user_id']]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}

// start of change for version 14
// added search and filter functionality for audit logs
// supports filtering by role, action, category, and date range
// end of change for version 14

// Pagination and search parameters
$entries_per_page = $_GET['entries'] ?? 10;
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$action_filter = $_GET['action'] ?? '';
$category_filter = $_GET['category'] ?? ''; // version 14 start - Added category filter parameter
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($action_filter)) {
    $where_conditions[] = "action LIKE ?";
    $params[] = "%$action_filter%";
}

// version 14 start - Added category filter to WHERE clause
// Filter audit logs by action_category if category filter is provided
if (!empty($category_filter)) {
    $where_conditions[] = "action_category = ?";
    $params[] = $category_filter;
}
// version 14 end - Added category filter to WHERE clause

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
try {
    $count_query = "SELECT COUNT(*) as total FROM audit_logs $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_logs = $count_stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Count query error: " . $e->getMessage());
    $total_logs = 0;
}

$total_pages = ceil($total_logs / $entries_per_page);
$offset = ($page - 1) * $entries_per_page;

// Fetch audit logs
try {
    $query = "SELECT * FROM audit_logs $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$entries_per_page, $offset]));
    $audit_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Audit logs query error: " . $e->getMessage());
    $audit_logs = [];
}

// Fetch coordinator notifications
try {
    $notification_stmt = $pdo->prepare("SELECT n.*, sg.group_name FROM notifications n LEFT JOIN student_groups sg ON n.group_id = sg.id WHERE n.user_type = 'coordinator' ORDER BY n.created_at DESC LIMIT 10");
    $notification_stmt->execute();
    $notifications = $notification_stmt->fetchAll();
    
    $unread_stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_type = 'coordinator' AND is_read = 0");
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
            $notification_id = $_POST['notification_id'];
            try {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_type = 'coordinator'");
                $stmt->execute([$notification_id]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                error_log("Mark notification read error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_type = 'coordinator' AND is_read = 0");
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
            $notification_stmt = $pdo->prepare("SELECT n.*, sg.group_name FROM notifications n LEFT JOIN student_groups sg ON n.group_id = sg.id WHERE n.user_type = 'coordinator' ORDER BY n.created_at DESC LIMIT 10");
            $notification_stmt->execute();
            $notifications = $notification_stmt->fetchAll();
            
            $unread_stmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_type = 'coordinator' AND is_read = 0");
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
    <link rel="stylesheet" href="../CSS/coordinator_dashboard.css">
    <link rel="stylesheet" href="../CSS/coordinator_audit_log.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack - Audit Log</title>
</head>
<body>
    <div class="app-container">
        <!-- Start Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user" onclick="openUploadModal()">
                    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
                         class="sidebar-avatar" 
                         alt="User Avatar" 
                         id="currentProfilePicture" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
                <span class="role-badge">Research Coordinator</span>
            </div>
            <!-- Sidebar Navigation -->
            <nav class="sidebar-nav">
                <a href="coordinator_dashboard.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="coordinator_sec-advisors.php" class="nav-item">
                    <i class="fas fa-school"></i> Sections & Advisors
                </a>
                <a href="coordinator_thesis-groups.php" class="nav-item">
                    <i class="fas fa-users"></i> Thesis Groups
                </a>
                <a href="coordinator_advisor-mngt.php" class="nav-item">
                    <i class="fas fa-chalkboard-teacher"></i> Advisor Management
                </a>
                <a href="coordinator_thesis-titles-overview.php" class="nav-item">
                    <i class="fas fa-book"></i> Thesis Titles Overview
                </a>
                <a href="coordinator_document-control-panel.php" class="nav-item">
                    <i class="fas fa-book-open"></i> Document Control Panel
                </a>
                <!-- start of change for version 14 -->
                <!-- added audit log link to sidebar navigation -->
                <!-- end of change for version 14 -->
                <a href="coordinator_audit_log.php" class="nav-item active">
                    <i class="fas fa-history"></i> Audit Log
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
                                <h4>System Monitoring</h4>
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
                                            <?php if ($notification['group_name']): ?>
                                                <div class="notification-group">
                                                    Group: <?php echo htmlspecialchars($notification['group_name']); ?>
                                                </div>
                                            <?php endif; ?>
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
                                        <p>No system notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <a href="coordinator_notifications.php" class="view-all-notifications">
                                View All System Activities
                            </a>
                        </div>
                    </div>
                    <div class="user-info dropdown">
                        <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
                             alt="User Avatar" 
                             class="user-avatar" 
                             id="userAvatar" 
                             tabindex="0" />
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
            </header>
            <!-- End HEADER -->

            <!-- Main Content -->
            <main class="main-content">
                

              <div class="page-header">
                    <h1><i class="fas fa-history"></i> System Audit Logs</h1>
                    <p>Track all system activities including logins, uploads, deletions, and modifications. Use the filters below to narrow down specific events</p>
                </div>

                                    <div id="messageContainer"></div>

                <!-- Enhanced card with dark header section -->
                <div class="card">
                    <div class="card-header" style="background-color: #343a40; color: white;">
                        <h3><i class="fas fa-chart-line"></i>Activity Monitoring</h3>
                        
                    </div>
                    
                    <div class="card-body">
                        <!-- Redesigned filters section with better structure and spacing -->
                        <div class="filters-section">
                            <form method="GET" action="">
                                <div class="filters-inline">
                                    <div class="filter-group">
                                        <label>Role</label>
                                        <select name="role" class="filter-select">
                                            <option value="">All Roles</option>
                                            <option value="coordinator" <?php echo $role_filter === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
                                            <option value="advisor" <?php echo $role_filter === 'advisor' ? 'selected' : ''; ?>>Advisor</option>
                                            <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                                        </select>
                                    </div>

                                    <!-- version 14 start - Added category filter dropdown -->
                                    <!-- Added category filter to allow filtering by action categories -->
                                    <div class="filter-group">
                                        <label>Category</label>
                                        <select name="category" class="filter-select">
                                            <option value="">All Categories</option>
                                            <option value="System Access" <?php echo $category_filter === 'System Access' ? 'selected' : ''; ?>>System Access</option>
                                            <option value="Account Management" <?php echo $category_filter === 'Account Management' ? 'selected' : ''; ?>>Account Management</option>
                                            <option value="Chapter Management" <?php echo $category_filter === 'Chapter Management' ? 'selected' : ''; ?>>Chapter Management</option>
                                            <option value="File Management" <?php echo $category_filter === 'File Management' ? 'selected' : ''; ?>>File Management</option>
                                        </select>
                                    </div>
                                    <!-- version 14 end - Added category filter dropdown -->

                                    <div class="filter-group">
                                        <label>Action</label>
                                        <select name="action" class="filter-select">
                                            <option value="">All Actions</option>
                                            <option value="login" <?php echo $action_filter === 'login' ? 'selected' : ''; ?>>Login</option>
                                            <option value="logout" <?php echo $action_filter === 'logout' ? 'selected' : ''; ?>>Logout</option>
                                            <option value="upload" <?php echo $action_filter === 'upload' ? 'selected' : ''; ?>>Upload</option>
                                            <option value="delete" <?php echo $action_filter === 'delete' ? 'selected' : ''; ?>>Delete</option>
                                            <option value="update" <?php echo $action_filter === 'update' ? 'selected' : ''; ?>>Update</option>
                                            <option value="create" <?php echo $action_filter === 'create' ? 'selected' : ''; ?>>Create</option>
                                        </select>
                                    </div>

                                    <div class="filter-group">
                                        <label>Date From</label>
                                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="filter-input">
                                    </div>

                                    <div class="filter-group">
                                        <label>Date To</label>
                                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="filter-input">
                                    </div>

                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-filter"></i> Filter
                                    </button>
                                    
                                    <a href="coordinator_audit_log.php" class="btn-secondary">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </div>

                                <!-- Preserve entries parameter -->
                                <input type="hidden" name="entries" value="<?php echo htmlspecialchars($entries_per_page); ?>">
                            </form>
                        </div>

                        <!-- Enhanced table controls with better spacing and visual hierarchy -->
                        <div class="table-controls-row">
                            <form method="GET" action="" class="entries-form">
                                <label>Show</label>
                                <select name="entries" onchange="this.form.submit()" class="entries-select">
                                    <?php
                                    $entries_options = [10, 25, 50, 100];
                                    foreach ($entries_options as $option) {
                                        $selected = ($option == $entries_per_page) ? 'selected' : '';
                                        echo "<option value='$option' $selected>$option</option>";
                                    }
                                    ?>
                                </select>
                                <span>entries</span>
                                
                                <!-- Preserve other GET parameters -->
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'entries' && $key !== 'page'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>

                            <form class="modern-search" method="GET" action="">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search..." class="search-input" 
                                    value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
                                <!-- Preserve other GET parameters -->
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'search' && $key !== 'page'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        </div>

                        <table class="sections-table audit-log-table" style="margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <!-- version 14 start - Added Category column to audit log table -->
                                    <th>Category</th>
                                    <!-- version 14 end - Added Category column -->
                                    <th>Action</th>
                                    <th>Severity</th>
                                    <th>Details</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($audit_logs)): ?>
                                    <?php foreach ($audit_logs as $log): ?>
                                        <tr class="audit-row">
                                            <td><strong><?php echo htmlspecialchars($log['user_name']); ?></strong></td>
                                            <td>
                                                <span class="role-badge-small role-<?php echo strtolower($log['role']); ?>">
                                                    <?php echo ucfirst($log['role']); ?>
                                                </span>
                                            </td>
                                            <!-- version 14 start - Display action_category in table -->
                                            <td>
                                                <span class="category-badge category-<?php echo strtolower(str_replace(' ', '-', $log['action_category'] ?? 'General')); ?>">
                                                    <?php echo htmlspecialchars($log['action_category'] ?? 'General'); ?>
                                                </span>
                                            </td>
                                            <!-- version 14 end - Display action_category -->
                                            <td>
                                                <span class="action-badge action-<?php echo strtolower(explode(' ', $log['action'])[0]); ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="severity-badge severity-<?php echo strtolower($log['severity'] ?? 'medium'); ?>">
                                                    <i class="fas fa-<?php 
                                                        $severity = strtolower($log['severity'] ?? 'medium');
                                                        echo $severity === 'critical' ? 'exclamation-circle' : 
                                                             ($severity === 'high' ? 'exclamation-triangle' : 
                                                             ($severity === 'medium' ? 'info-circle' : 'check-circle'));
                                                    ?>"></i>
                                                    <?php echo ucfirst($log['severity'] ?? 'Medium'); ?>
                                                </span>
                                            </td>
                                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['details']); ?>">
                                                <?php echo htmlspecialchars($log['details'] ?? 'N/A'); ?>
                                            </td>
                                            <td>
                                                <div class="timestamp-cell">
                                                    <div class="date-part"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                                    <div class="time-part"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <!-- START of Version 14 changes -->
                                        <!-- Updated colspan from 8 to 7 after removing IP Address column -->
                                        <td colspan="7" style="text-align: center; padding: 3rem; color: #6c757d;">
                                        <!-- END of Version 14 changes -->
                                            <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem;"></i>
                                            No audit logs found matching your criteria.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&entries=<?= $entries_per_page ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($role_filter) ? '&role='.$role_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($category_filter) ? '&category='.$category_filter : '' ?><?= !empty($date_from) ? '&date_from='.$date_from : '' ?><?= !empty($date_to) ? '&date_to='.$date_to : '' ?>" class="page-link">&laquo; Previous</a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?= $i ?>&entries=<?= $entries_per_page ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($role_filter) ? '&role='.$role_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($category_filter) ? '&category='.$category_filter : '' ?><?= !empty($date_from) ? '&date_from='.$date_from : '' ?><?= !empty($date_to) ? '&date_to='.$date_to : '' ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page+1 ?>&entries=<?= $entries_per_page ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($role_filter) ? '&role='.$role_filter : '' ?><?= !empty($action_filter) ? '&action='.$action_filter : '' ?><?= !empty($category_filter) ? '&category='.$category_filter : '' ?><?= !empty($date_from) ? '&date_from='.$date_from : '' ?><?= !empty($date_to) ? '&date_to='.$date_to : '' ?>" class="page-link">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Enhanced info box with sidebar violet theme -->
                        <div class="info-box" style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px; font-size: 0.9rem; color: #64748b;">
                            <strong>Total Records:</strong> <?php echo number_format($total_logs); ?> | 
                            <strong>Showing:</strong> <?php echo number_format($offset + 1); ?> - <?php echo number_format(min($offset + $entries_per_page, $total_logs)); ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

   
    <script src="../JS/coordinator_dashboard.js"></script>
    <!-- start of change for version 14 -->
    <!-- added dedicated JavaScript file for audit log functionality -->
    <!-- end of change for version 14 -->
    <script src="../JS/coordinator_audit_log.js"></script>
    
</body>
</html>
