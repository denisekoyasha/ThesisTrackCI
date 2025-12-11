<?php
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
    $profile_picture = '../images/default-user.png';
    if (!empty($coordinator['profile_picture'])) {
        $imagePath = '../uploads/profile_pictures/' . $coordinator['profile_picture'];
        if (file_exists($imagePath)) {
            $profile_picture = $imagePath;
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Location: ../login.php');
    exit();
}

// Complete chapters data with all sections
$chapters = [
    [
        'id' => 1,
        'number' => 1,
        'title' => 'Introduction',
        'sections' => [
            ['id' => 1, 'name' => 'Introduction', 'enabled' => true],
            ['id' => 2, 'name' => 'Project Context', 'enabled' => true],
            ['id' => 3, 'name' => 'Purpose and Description', 'enabled' => true],
            ['id' => 4, 'name' => 'Objectives of the Study', 'enabled' => true],
            ['id' => 5, 'name' => 'General Objectives', 'enabled' => true],
            ['id' => 6, 'name' => 'Specific Objectives', 'enabled' => true],
            ['id' => 7, 'name' => 'Conceptual Paradigm', 'enabled' => true],
            ['id' => 8, 'name' => 'Scope and Limitation of the Study', 'enabled' => true],
            ['id' => 9, 'name' => 'Significance of the Study', 'enabled' => true],
            ['id' => 10, 'name' => 'Definition of Terms', 'enabled' => true],
        ]
    ],
    [
        'id' => 2,
        'number' => 2,
        'title' => 'Review of Related Literature and Studies',
        'sections' => [
            ['id' => 11, 'name' => 'Review of Related Literature and Studies', 'enabled' => true],
            ['id' => 12, 'name' => 'Synthesis of the Study', 'enabled' => true],
        ]
    ],
    [
        'id' => 3,
        'number' => 3,
        'title' => 'Research Methodology',
        'sections' => [
            ['id' => 13, 'name' => 'Research Methodology', 'enabled' => true],
            ['id' => 14, 'name' => 'Research Method Used', 'enabled' => true],
            ['id' => 15, 'name' => 'Population, Sample Size, and Sampling Technique', 'enabled' => true],
            ['id' => 16, 'name' => 'Description of Respondents', 'enabled' => true],
            ['id' => 17, 'name' => 'Research Instrument', 'enabled' => true],
            ['id' => 18, 'name' => 'Data Gathering Procedure', 'enabled' => true],
            ['id' => 19, 'name' => 'Survey Questionnaire', 'enabled' => true],
            ['id' => 20, 'name' => 'Software Evaluation Instrument of ISO 25010', 'enabled' => true],
            ['id' => 21, 'name' => 'Interview and Observation', 'enabled' => true],
            ['id' => 22, 'name' => 'Data Analysis and Procedure', 'enabled' => true],
            ['id' => 23, 'name' => 'Validation and Distribution of the Instrument', 'enabled' => true],
            ['id' => 24, 'name' => 'Data Encoding and Formulation of the Solution', 'enabled' => true],
            ['id' => 25, 'name' => 'Evaluation of Data and Result', 'enabled' => true],
            ['id' => 26, 'name' => 'Statistical Treatment of Data', 'enabled' => true],
            ['id' => 27, 'name' => 'Statistical Tools', 'enabled' => true],
            ['id' => 28, 'name' => 'Frequency', 'enabled' => true],
            ['id' => 29, 'name' => 'Percentage', 'enabled' => true],
            ['id' => 30, 'name' => 'Weighted Mean', 'enabled' => true],
            ['id' => 31, 'name' => 'Technical Requirements', 'enabled' => true],
            ['id' => 32, 'name' => 'Hardware Requirements', 'enabled' => true],
            ['id' => 33, 'name' => 'Software Requirements', 'enabled' => true],
            ['id' => 34, 'name' => 'Network Requirements', 'enabled' => true],
            ['id' => 35, 'name' => 'API Specifications', 'enabled' => true],
            ['id' => 36, 'name' => 'Project Design', 'enabled' => true],
            ['id' => 37, 'name' => 'Diagrams', 'enabled' => true],
            ['id' => 38, 'name' => 'System Architecture', 'enabled' => true],
            ['id' => 39, 'name' => 'Data Flow Diagram', 'enabled' => true],
            ['id' => 40, 'name' => 'Proposed Flowchart', 'enabled' => true],
            ['id' => 41, 'name' => 'Unified Modeling Language', 'enabled' => true],
            ['id' => 42, 'name' => 'System Development', 'enabled' => true],
            ['id' => 43, 'name' => 'Algorithm Discussion', 'enabled' => true],
            ['id' => 44, 'name' => 'Features', 'enabled' => true],
            ['id' => 45, 'name' => 'Function', 'enabled' => true],
            ['id' => 46, 'name' => 'Uses', 'enabled' => true],
        ]
    ],
    [
        'id' => 4,
        'number' => 4,
        'title' => 'Results and Discussion',
        'sections' => [
            ['id' => 47, 'name' => 'Results and Discussion', 'enabled' => true],
            ['id' => 48, 'name' => 'Evaluation and Scoring', 'enabled' => true],
        ]
    ],
    [
        'id' => 5,
        'number' => 5,
        'title' => 'Summary, Conclusions, and Recommendations',
        'sections' => [
            ['id' => 49, 'name' => 'Summary, Conclusions, and Recommendations', 'enabled' => true],
            ['id' => 50, 'name' => 'Summary of Findings', 'enabled' => true],
            ['id' => 51, 'name' => 'Conclusions', 'enabled' => true],
            ['id' => 52, 'name' => 'Recommendations', 'enabled' => true],
            ['id' => 53, 'name' => 'Bibliography', 'enabled' => true],
        ]
    ]
];

$typographySettings = [
    'fontStyles' => [
        ['id' => 'arial', 'name' => 'Arial', 'value' => 'Arial, sans-serif'],
        ['id' => 'times', 'name' => 'Times New Roman', 'value' => 'Times New Roman, serif'],
        ['id' => 'calibri', 'name' => 'Calibri', 'value' => 'Calibri, sans-serif'],
        ['id' => 'georgia', 'name' => 'Georgia', 'value' => 'Georgia, serif'],
    ],
    'fontSizes' => [
        ['id' => '10', 'name' => '10pt', 'value' => '10'],
        ['id' => '11', 'name' => '11pt', 'value' => '11'],
        ['id' => '12', 'name' => '12pt', 'value' => '12'],
        ['id' => '14', 'name' => '14pt', 'value' => '14'],
    ],
    'alignments' => [
        ['id' => 'left', 'name' => 'Left', 'icon' => 'fa-align-left'],
        ['id' => 'center', 'name' => 'Center', 'icon' => 'fa-align-center'],
        ['id' => 'right', 'name' => 'Right', 'icon' => 'fa-align-right'],
        ['id' => 'justify', 'name' => 'Justify', 'icon' => 'fa-align-justify'],
    ],
];

function getProfilePicture($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT profile_picture FROM coordinators WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        if ($result && !empty($result['profile_picture'])) {
            $imagePath = '../uploads/profile_pictures/' . $result['profile_picture'];
            if (file_exists($imagePath)) {
                return $imagePath;
            }
        }
    } catch (PDOException $e) {
        error_log("Database error fetching profile picture: " . $e->getMessage());
    }
    return '../images/default-user.png';
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
    <link rel="stylesheet" href="../CSS/coordinator_dashboard.css">
    <link rel="stylesheet" href="../CSS/coordinator_document-control-panel.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>Document Control Panel - ThesisTrack</title>
    <style>
        /* Additional styles for the control panel */
        .formatting-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .control-group {
            margin-bottom: 15px;
        }
        
        .control-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .control-select, .control-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .alignment-buttons {
            display: flex;
            gap: 5px;
        }
        
        .align-btn {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .align-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .margin-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .margin-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-apply-formatting {
            width: 100%;
            padding: 10px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-apply-formatting:hover {
            background: #219a52;
        }
        
        .chapter-previews {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .action-btn-preview {
            width: 100%;
            margin-bottom: 8px;
            padding: 8px 12px;
            background: #8e44ad;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .action-btn-preview:hover {
            background: #7d3c98;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>ThesisTrack</h2>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user" onclick="openUploadModal()">
                    <img src="<?php echo $profile_picture; ?>?t=<?php echo time(); ?>" 
                         class="sidebar-avatar" 
                         alt="User Avatar" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($user_name); ?></div>
                </div>
                <span class="role-badge">Research Coordinator</span>
            </div>
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
                <a href="coordinator_document-control-panel.php" class="nav-item active">
                    <i class="fas fa-file-pdf"></i> Document Control Panel
                </a>
                <a href="coordinator_audit_log.php" class="nav-item">
                    <i class="fas fa-history"></i> Audit Logs
                </a>
                <a href="#" id="logoutBtn" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
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
                            <button class="btn-modal btn-cancel" onclick="closeLogoutModal()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button class="btn-modal btn-danger" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i> Yes, Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Content Wrapper -->
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
                        <img src="<?php echo getProfilePicture($_SESSION['user_id']); ?>?t=<?php echo time(); ?>" 
                             alt="User Avatar" 
                             class="user-avatar" 
                             id="userAvatar" />
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

            <!-- Main Content -->
            <main class="main-content">
                <div class="page-header">
                    <h1><i class="fas fa-book-open"></i> Document Control Panel</h1>
                    <p>Manage thesis document sections and formatting.</p>
                </div>
                <div id="messageContainer"></div>

                <header class="main-header">
                    <div>
                        <h1>Thesis Document Sections</h1>
                        <p class="header-subtitle">Enable or disable sections for your thesis document structure</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn-secondary" onclick="toggleSidebar()">☰</button>
                    </div>
                </header>

                <!-- Control Panel Container -->
                <div class="control-panel-container">
                    <!-- Main Content Area -->
                    <div class="main-panel">
                        <!-- Chapters Section -->
                        <div class="chapters-section">
                            <div class="chapters-list">
                                <?php foreach ($chapters as $chapter): ?>
                                    <div class="chapter-card" data-chapter="<?php echo $chapter['id']; ?>">
                                        <div class="chapter-header">
                                            <div class="chapter-info">
                                                <div class="chapter-number">
                                                    <span class="badge">Chapter <?php echo $chapter['number']; ?></span>
                                                </div>
                                                <h3 class="chapter-title"><?php echo htmlspecialchars($chapter['title']); ?></h3>
                                                <span class="section-count"><?php echo count($chapter['sections']); ?> sections</span>
                                            </div>
                                            <div class="chapter-toggle">
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                        </div>

                                        <!-- Sections List -->
                                        <div class="chapter-content">
                                            <div class="sections-grid">
                                                <?php foreach ($chapter['sections'] as $section): ?>
                                                    <div class="section-item" data-section="<?php echo $section['id']; ?>">
                                                        <div class="section-toggle-wrapper">
                                                            <label class="toggle-switch">
                                                                <input type="checkbox" 
                                                                       class="section-checkbox" 
                                                                       data-section="<?php echo $section['id']; ?>"
                                                                       <?php echo $section['enabled'] ? 'checked' : ''; ?>>
                                                                <span class="toggle-slider"></span>
                                                            </label>
                                                        </div>
                                                        <div class="section-details">
                                                            <p class="section-name"><?php echo htmlspecialchars($section['name']); ?></p>
                                                            <span class="section-status <?php echo $section['enabled'] ? 'enabled' : 'disabled'; ?>">
                                                                <?php echo $section['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Panel -->
                    <div class="sidebar-panel">
                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <h3><i class="fas fa-lightning-bolt"></i> Quick Actions</h3>
                            <button class="action-btn action-btn-primary" onclick="enableAllSections()">
                                <i class="fas fa-check-double"></i> Enable All
                            </button>
                            <button class="action-btn action-btn-secondary" onclick="disableAllSections()">
                                <i class="fas fa-times-circle"></i> Disable All
                            </button>
                            <button class="action-btn action-btn-info" onclick="resetToDefaults()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button class="action-btn action-btn-primary" onclick="openPreview()">
                                <i class="fas fa-eye"></i> Preview Template
                            </button>

                            <!-- Individual Chapter Preview Buttons -->
                            <div class="chapter-previews">
                                <h4><i class="fas fa-file-pdf"></i> Chapter Templates</h4>
                                <button class="action-btn action-btn-preview" onclick="openChapterPreview(1)">
                                    <i class="fas fa-eye"></i> Preview Chapter 1
                                </button>
                                <button class="action-btn action-btn-preview" onclick="openChapterPreview(2)">
                                    <i class="fas fa-eye"></i> Preview Chapter 2
                                </button>
                                <button class="action-btn action-btn-preview" onclick="openChapterPreview(3)">
                                    <i class="fas fa-eye"></i> Preview Chapter 3
                                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
  <i class="fas fa-bars"></i>
</button>
                                </button>
                                <button class="action-btn action-btn-preview" onclick="openChapterPreview(4)">
                                    <i class="fas fa-eye"></i> Preview Chapter 4
                                </button>
                                <button class="action-btn action-btn-preview" onclick="openChapterPreview(5)">
                                    <i class="fas fa-eye"></i> Preview Chapter 5
                                </button>
                            </div>
                        </div>

                        <!-- Formatting Controls Section -->
                        <div class="formatting-controls">
                            <h3><i class="fas fa-palette"></i> Formatting</h3>
                            
                            <!-- Font Style -->
                            <div class="control-group">
                                <label class="control-label">Font Style</label>
                                <select id="fontStyle" class="control-select" onchange="updateFormatting('fontStyle')">
                                    <?php foreach ($typographySettings['fontStyles'] as $font): ?>
                                        <option value="<?php echo $font['id']; ?>"><?php echo $font['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Font Size -->
                            <div class="control-group">
                                <label class="control-label">Font Size</label>
                                <select id="fontSize" class="control-select" onchange="updateFormatting('fontSize')">
                                    <?php foreach ($typographySettings['fontSizes'] as $size): ?>
                                        <option value="<?php echo $size['id']; ?>"><?php echo $size['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Text Alignment -->
                            <div class="control-group">
                                <label class="control-label">Alignment</label>
                                <div class="alignment-buttons">
                                    <?php foreach ($typographySettings['alignments'] as $align): ?>
                                        <button class="align-btn" 
                                                data-align="<?php echo $align['id']; ?>"
                                                title="<?php echo $align['name']; ?>"
                                                onclick="updateFormatting('alignment', '<?php echo $align['id']; ?>')">
                                            <i class="fas <?php echo $align['icon']; ?>"></i>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Line Spacing -->
                            <div class="control-group">
                                <label class="control-label">Line Spacing</label>
                                <select id="lineSpacing" class="control-select" onchange="updateFormatting('lineSpacing')">
                                    <option value="1.0">Single</option>
                                    <option value="1.5">1.5 Lines</option>
                                    <option value="2.0">Double</option>
                                </select>
                            </div>

                            <!-- Margins -->
                            <div class="control-group">
                                <label class="control-label">Margins (inches)</label>
                                <div class="margin-inputs">
                                    <input type="number" id="marginTop" class="margin-input" placeholder="Top" min="0.5" max="2" step="0.1" value="1" onchange="updateFormatting('margins')">
                                    <input type="number" id="marginBottom" class="margin-input" placeholder="Bottom" min="0.5" max="2" step="0.1" value="1" onchange="updateFormatting('margins')">
                                    <input type="number" id="marginLeft" class="margin-input" placeholder="Left" min="0.5" max="2" step="0.1" value="1.5" onchange="updateFormatting('margins')">
                                    <input type="number" id="marginRight" class="margin-input" placeholder="Right" min="0.5" max="2" step="0.1" value="1" onchange="updateFormatting('margins')">
                                </div>
                            </div>

                            <!-- Indentation -->
                            <div class="control-group">
                                <label class="control-label">First Line Indent (inches)</label>
                                <input type="number" id="indent" class="control-input" placeholder="0.5" min="0" max="1" step="0.1" value="0.5" onchange="updateFormatting('indent')">
                            </div>

                            <!-- Borders -->
                            <div class="control-group">
                                <label class="control-label">
                                    <input type="checkbox" id="borderToggle" onchange="updateFormatting('border')">
                                    <span>Enable Borders</span>
                                </label>
                            </div>

                            <!-- Logo Positioning -->
                            <div class="control-group">
                                <label class="control-label">Logo Position</label>
                                <select id="logoPosition" class="control-select" onchange="updateFormatting('logoPosition')">
                                    <option value="top-left">Top Left</option>
                                    <option value="top-center">Top Center</option>
                                    <option value="top-right">Top Right</option>
                                    <option value="none">None</option>
                                </select>
                            </div>

                            <!-- Apply Formatting Button -->
                            <button class="btn-apply-formatting" onclick="applyAllFormatting()">
                                <i class="fas fa-check"></i> Apply Formatting
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal-overlay">
        <div class="confirmation-modal">
            <div class="modal-header">
                <h3 id="confirmTitle">Confirm Action</h3>
                <button class="close-modal" onclick="closeConfirmationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="confirmation-content">
                    <div class="confirmation-icon" id="confirmIcon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <p id="confirmMessage">Are you sure?</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-modal btn-cancel" onclick="closeConfirmationModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-modal btn-confirm" id="confirmBtn" onclick="executeConfirmation()">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <script>
    // Document Control Panel JavaScript

    // Global state management
    let currentFormatting = {
        fontStyle: 'times',
        fontSize: '12',
        alignment: 'justify',
        margins: { 
            top: 1,
            bottom: 1,
            left: 1.5,
            right: 1
        },
        indent: 0.5,
        border: false,
        logoPosition: 'none',
        lineSpacing: 1.5
    };

    // Initialize on page load
    document.addEventListener("DOMContentLoaded", () => {
        initializeEventListeners();
        loadInitialData();
        initializeNotifications();
    });

    async function loadInitialData() {
        try {
            console.log('🔄 Loading initial data...');
            await loadFormatConfig();
            setupSectionToggles();
            console.log('✅ All data loaded successfully');
        } catch (error) {
            console.error('❌ Error loading initial data:', error);
            showMessage('Error loading configuration data', 'error');
        }
    }

    async function loadFormatConfig() {
        try {
            console.log('📥 Loading format configuration...');
            const response = await fetch('load_format_config.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            console.log('📄 Raw response:', text);
            
            const result = JSON.parse(text);

            if (!result.success) {
                console.warn('No saved configuration found, using defaults');
                return;
            }

            console.log('📊 Loaded configuration:', result.data);

            // Update UI based on loaded configuration
            if (result.data && typeof result.data === 'object') {
                Object.entries(result.data).forEach(([chapter, settings]) => {
                    updateChapterUI(chapter, settings);
                });

                // Update formatting controls with first chapter's settings
                updateFormattingControls(result.data);
            }

        } catch (err) {
            console.error('Error loading format config:', err);
            showMessage('Error loading saved configuration: ' + err.message, 'error');
        }
    }

    function updateChapterUI(chapter, settings) {
        const chapterNumber = chapter.replace('Chapter ', '');
        const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapterNumber}"]`);
        
        if (!chapterCard) {
            console.warn(`Chapter card not found for: ${chapter}`);
            return;
        }

        // Update section toggles based on active_sections
        if (settings.active_sections && Array.isArray(settings.active_sections)) {
            chapterCard.querySelectorAll('.section-item').forEach(sectionItem => {
                const sectionName = sectionItem.querySelector('.section-name').textContent.trim();
                const checkbox = sectionItem.querySelector('.section-checkbox');
                const isActive = settings.active_sections.includes(sectionName);
                
                checkbox.checked = isActive;
                updateSectionStatus(sectionItem, isActive);
            });
        }
    }

    function updateFormattingControls(configData) {
        if (!configData || Object.keys(configData).length === 0) {
            console.log('No configuration data found, using defaults');
            return;
        }

        // Use first available chapter's settings
        const firstChapter = Object.values(configData)[0];
        if (!firstChapter) return;

        console.log('🎛️ Updating formatting controls with:', firstChapter);

        // Update formatting controls
        if (firstChapter.font_family) {
            const fontSelect = document.getElementById("fontStyle");
            if (fontSelect) {
                fontSelect.value = firstChapter.font_family;
                currentFormatting.fontStyle = firstChapter.font_family;
            }
        }

        if (firstChapter.font_size) {
            const sizeSelect = document.getElementById("fontSize");
            if (sizeSelect) {
                sizeSelect.value = firstChapter.font_size;
                currentFormatting.fontSize = firstChapter.font_size;
            }
        }

        // Update alignment
        if (firstChapter.alignment) {
            const alignment = firstChapter.alignment;
            document.querySelectorAll(".align-btn").forEach(btn => {
                if (btn.dataset.align === alignment) {
                    btn.classList.add("active");
                } else {
                    btn.classList.remove("active");
                }
            });
            currentFormatting.alignment = alignment;
        }

        // Update margins
        if (firstChapter.margins) {
            const marginTop = document.getElementById("marginTop");
            const marginBottom = document.getElementById("marginBottom");
            const marginLeft = document.getElementById("marginLeft");
            const marginRight = document.getElementById("marginRight");
            
            if (marginTop) marginTop.value = firstChapter.margins.top || 1;
            if (marginBottom) marginBottom.value = firstChapter.margins.bottom || 1;
            if (marginLeft) marginLeft.value = firstChapter.margins.left || 1.5;
            if (marginRight) marginRight.value = firstChapter.margins.right || 1;
            
            currentFormatting.margins = {
                top: firstChapter.margins.top || 1,
                bottom: firstChapter.margins.bottom || 1,
                left: firstChapter.margins.left || 1.5,
                right: firstChapter.margins.right || 1
            };
        }

        // Update other controls
        if (firstChapter.indentation !== undefined) {
            const indentInput = document.getElementById("indent");
            if (indentInput) {
                indentInput.value = firstChapter.indentation;
                currentFormatting.indent = firstChapter.indentation;
            }
        }

        if (firstChapter.border_style) {
            const borderToggle = document.getElementById("borderToggle");
            if (borderToggle) {
                borderToggle.checked = firstChapter.border_style === 'solid';
                currentFormatting.border = firstChapter.border_style === 'solid';
            }
        }

        if (firstChapter.logo_position) {
            const logoSelect = document.getElementById("logoPosition");
            if (logoSelect) {
                logoSelect.value = firstChapter.logo_position;
                currentFormatting.logoPosition = firstChapter.logo_position;
            }
        }

        if (firstChapter.line_spacing) {
            const lineSpacingSelect = document.getElementById("lineSpacing");
            if (lineSpacingSelect) {
                lineSpacingSelect.value = firstChapter.line_spacing;
                currentFormatting.lineSpacing = firstChapter.line_spacing;
            }
        }

        console.log('✅ Formatting controls updated:', currentFormatting);
    }

    function setupSectionToggles() {
        document.querySelectorAll('.section-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', async (e) => {
                const sectionItem = e.target.closest('.section-item');
                const chapterCard = sectionItem.closest('.chapter-card');
                const chapterNumber = chapterCard.dataset.chapter;
                const isEnabled = e.target.checked;

                updateSectionStatus(sectionItem, isEnabled);
                
                // Save changes immediately
                await saveChapterSections(`Chapter ${chapterNumber}`);
            });
        });

        // Chapter expand/collapse
        document.querySelectorAll('.chapter-header').forEach(header => {
            header.addEventListener('click', function() {
                const chapterCard = this.closest('.chapter-card');
                chapterCard.classList.toggle('expanded');
            });
        });
    }

    function updateSectionStatus(sectionItem, isEnabled) {
        const statusBadge = sectionItem.querySelector('.section-status');
        
        if (isEnabled) {
            statusBadge.textContent = "Enabled";
            statusBadge.classList.remove("disabled");
            statusBadge.classList.add("enabled");
            sectionItem.classList.remove("disabled");
        } else {
            statusBadge.textContent = "Disabled";
            statusBadge.classList.remove("enabled");
            statusBadge.classList.add("disabled");
            sectionItem.classList.add("disabled");
        }
    }

    async function saveChapterSections(chapter) {
        const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapter.replace('Chapter ', '')}"]`);
        if (!chapterCard) {
            console.error('Chapter card not found:', chapter);
            return false;
        }

        const activeSections = [];
        
        chapterCard.querySelectorAll('.section-item').forEach(item => {
            const name = item.querySelector('.section-name').textContent.trim();
            const checkbox = item.querySelector('.section-checkbox');
            
            if (checkbox.checked) {
                activeSections.push(name);
            }
        });

        const payload = {
            chapter: chapter,
            active_sections: activeSections,
            font_family: currentFormatting.fontStyle,
            font_size: parseInt(currentFormatting.fontSize),
            alignment: currentFormatting.alignment,
            line_spacing: parseFloat(currentFormatting.lineSpacing),
            indentation: parseFloat(currentFormatting.indent),
            border_style: currentFormatting.border ? 'solid' : 'none',
            logo_position: currentFormatting.logoPosition,
            margin_top: parseFloat(currentFormatting.margins.top),
            margin_bottom: parseFloat(currentFormatting.margins.bottom),
            margin_left: parseFloat(currentFormatting.margins.left),
            margin_right: parseFloat(currentFormatting.margins.right)
        };

        console.log('💾 Saving payload for', chapter, ':', payload);

        try {
            const response = await fetch('save_format_config.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Unknown server error');
            }
            
            console.log(`✅ Saved ${chapter} configuration`);
            return true;
        } catch (err) {
            console.error('❌ Save failed for', chapter, ':', err);
            showMessage(`Error saving ${chapter}: ${err.message}`, 'error');
            return false;
        }
    }

    function applyFontSizeToPreview(fontSize) {
    // This function will be called when the preview is opened
    // The actual application happens in the preview window
    console.log('Applying font size to preview:', fontSize);
}

   function updateFormatting(type, value) {
    switch (type) {
        case 'fontStyle':
            currentFormatting.fontStyle = value;
            break;
        case 'fontSize':
            currentFormatting.fontSize = value;
            // Apply font size to both section titles and content
            applyFontSizeToPreview(value);
            break;
        case 'alignment':
            currentFormatting.alignment = value;
            document.querySelectorAll(".align-btn").forEach(btn => {
                btn.classList.toggle("active", btn.dataset.align === value);
            });
            break;
        case 'lineSpacing':
            currentFormatting.lineSpacing = value;
            break;
        case 'margins':
            currentFormatting.margins = {
                top: document.getElementById("marginTop").value,
                bottom: document.getElementById("marginBottom").value,
                left: document.getElementById("marginLeft").value,
                right: document.getElementById("marginRight").value
            };
            break;
        case 'indent':
            currentFormatting.indent = document.getElementById("indent").value;
            break;
        case 'border':
            currentFormatting.border = document.getElementById("borderToggle").checked;
            break;
        case 'logoPosition':
            currentFormatting.logoPosition = document.getElementById("logoPosition").value;
            break;
    }
    
    console.log('📝 Formatting updated:', type, value, currentFormatting);
}

    async function applyAllFormatting() {
        showConfirmation(
            "Apply Formatting?",
            "This will apply the selected formatting settings to all chapters. Continue?",
            "info",
            async () => {
                const chapters = document.querySelectorAll('.chapter-card');
                let successCount = 0;
                let errorCount = 0;
                
                for (const chapterCard of chapters) {
                    const chapterNumber = chapterCard.dataset.chapter;
                    const success = await saveChapterSections(`Chapter ${chapterNumber}`);
                    if (success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                }
                
                if (errorCount === 0) {
                    showMessage(`All ${successCount} chapters saved successfully!`, "success");
                } else {
                    showMessage(`Saved ${successCount} chapters, ${errorCount} failed. Check console for details.`, "warning");
                }
            }
        );
    }

    // Quick Actions
    async function enableAllSections() {
        showConfirmation(
            "Enable All Sections?",
            "This will enable all thesis document sections. Continue?",
            "success",
            async () => {
                const chapters = new Set();
                
                document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                    if (!checkbox.checked) {
                        checkbox.checked = true;
                        const sectionItem = checkbox.closest('.section-item');
                        updateSectionStatus(sectionItem, true);
                        
                        const chapterCard = sectionItem.closest('.chapter-card');
                        chapters.add(`Chapter ${chapterCard.dataset.chapter}`);
                    }
                });
                
                // Save all modified chapters
                let successCount = 0;
                for (const chapter of chapters) {
                    const success = await saveChapterSections(chapter);
                    if (success) successCount++;
                }
                
                showMessage(`Enabled all sections across ${successCount} chapters`, "success");
            }
        );
    }

    async function disableAllSections() {
        showConfirmation(
            "Disable All Sections?",
            "This will disable all thesis document sections. Continue?",
            "warning",
            async () => {
                const chapters = new Set();
                
                document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                    if (checkbox.checked) {
                        checkbox.checked = false;
                        const sectionItem = checkbox.closest('.section-item');
                        updateSectionStatus(sectionItem, false);
                        
                        const chapterCard = sectionItem.closest('.chapter-card');
                        chapters.add(`Chapter ${chapterCard.dataset.chapter}`);
                    }
                });
                
                // Save all modified chapters
                let successCount = 0;
                for (const chapter of chapters) {
                    const success = await saveChapterSections(chapter);
                    if (success) successCount++;
                }
                
                showMessage(`Disabled all sections across ${successCount} chapters`, "success");
            }
        );
    }

    function resetToDefaults() {
        showConfirmation(
            "Reset to Defaults?",
            "This will reset all sections and formatting to TCU thesis standards. Continue?",
            "info",
            async () => {
                // Reset formatting to TCU thesis standards
                currentFormatting = {
                    fontStyle: 'times',
                    fontSize: '12',
                    alignment: 'justify',
                    margins: { 
                        top: 1,
                        bottom: 1,
                        left: 1.5,
                        right: 1
                    },
                    indent: 0.5,
                    border: false,
                    logoPosition: 'none',
                    lineSpacing: 1.5
                };
                
                // Update UI controls
                document.getElementById("fontStyle").value = currentFormatting.fontStyle;
                document.getElementById("fontSize").value = currentFormatting.fontSize;
                document.querySelectorAll(".align-btn").forEach(btn => {
                    btn.classList.toggle("active", btn.dataset.align === currentFormatting.alignment);
                });
                document.getElementById("lineSpacing").value = currentFormatting.lineSpacing;
                document.getElementById("marginTop").value = currentFormatting.margins.top;
                document.getElementById("marginBottom").value = currentFormatting.margins.bottom;
                document.getElementById("marginLeft").value = currentFormatting.margins.left;
                document.getElementById("marginRight").value = currentFormatting.margins.right;
                document.getElementById("indent").value = currentFormatting.indent;
                document.getElementById("borderToggle").checked = currentFormatting.border;
                document.getElementById("logoPosition").value = currentFormatting.logoPosition;
                
                // Enable all sections
                document.querySelectorAll(".section-checkbox").forEach((checkbox) => {
                    checkbox.checked = true;
                    const sectionItem = checkbox.closest('.section-item');
                    updateSectionStatus(sectionItem, true);
                });
                
                showMessage("Reset to TCU thesis standards completed", "success");
            }
        );
    }

   // Update the preview functions to include font size for sections
function openChapterPreview(chapterNumber) {
    // Get enabled sections for this chapter
    const enabledSections = getEnabledSectionsForChapter(chapterNumber);
    
    // Collect formatting values
    const params = new URLSearchParams({
        chapter: chapterNumber,
        fontFamily: getFontFamilyValue(currentFormatting.fontStyle),
        fontSize: currentFormatting.fontSize,
        sectionFontSize: currentFormatting.fontSize, // Pass same font size for sections
        textAlign: currentFormatting.alignment,
        lineSpacing: currentFormatting.lineSpacing,
        marginTop: currentFormatting.margins.top,
        marginBottom: currentFormatting.margins.bottom,
        marginLeft: currentFormatting.margins.left,
        marginRight: currentFormatting.margins.right,
        indent: currentFormatting.indent,
        borderEnabled: currentFormatting.border,
        logoPosition: currentFormatting.logoPosition,
        enabledSections: JSON.stringify(enabledSections)
    });
    
    // Open chapter-specific preview
    window.open(`chapter-preview.php?${params.toString()}`, '_blank');
}


    function getEnabledSectionsForChapter(chapterNumber) {
        const chapterCard = document.querySelector(`.chapter-card[data-chapter="${chapterNumber}"]`);
        if (!chapterCard) return [];
        
        const enabledSections = [];
        
        chapterCard.querySelectorAll('.section-item').forEach(item => {
            const checkbox = item.querySelector('.section-checkbox');
            const sectionName = item.querySelector('.section-name').textContent.trim();
            
            if (checkbox.checked) {
                enabledSections.push(sectionName);
            }
        });
        
        return enabledSections;
    }

    function openPreview() {
    // Collect all formatting values from the control panel
    const fontFamily = document.getElementById('fontStyle').value;
    const fontSize = document.getElementById('fontSize').value;
    
    // Get active alignment
    const activeAlignBtn = document.querySelector('.align-btn.active');
    const textAlign = activeAlignBtn ? activeAlignBtn.dataset.align : 'left';
    
    // Get margin values
    const marginTop = document.getElementById('marginTop').value || '1';
    const marginBottom = document.getElementById('marginBottom').value || '1';
    const marginLeft = document.getElementById('marginLeft').value || '1.5';
    const marginRight = document.getElementById('marginRight').value || '1';
    
    // Get other formatting values
    const indent = document.getElementById('indent').value || '0.5';
    const borderEnabled = document.getElementById('borderToggle').checked;
    const logoPosition = document.getElementById('logoPosition').value || 'none';
    const lineSpacing = document.getElementById('lineSpacing').value || '1.5';
    
    // Build URL with all formatting parameters
    const params = new URLSearchParams({
        fontFamily: getFontFamilyValue(fontFamily),
        fontSize: fontSize,
        sectionFontSize: fontSize, // Apply same font size to sections
        textAlign: textAlign,
        lineSpacing: lineSpacing,
        marginTop: marginTop,
        marginBottom: marginBottom,
        marginLeft: marginLeft,
        marginRight: marginRight,
        indent: indent,
        borderEnabled: borderEnabled,
        logoPosition: logoPosition
    });
    
    // Open preview in new tab
    window.open('document-preview.html?' + params.toString(), '_blank');
}

    // Helper function to convert font IDs to CSS values
    function getFontFamilyValue(fontId) {
        const fontMap = {
            'arial': 'Arial, sans-serif',
            'times': 'Times New Roman, serif',
            'calibri': 'Calibri, sans-serif',
            'georgia': 'Georgia, serif'
        };
        return fontMap[fontId] || 'Times New Roman, serif';
    }

    // UI Helper Functions
    function initializeEventListeners() {
        // Formatting controls
        const fontStyle = document.getElementById("fontStyle");
        const fontSize = document.getElementById("fontSize");
        const lineSpacing = document.getElementById("lineSpacing");
        const marginTop = document.getElementById("marginTop");
        const marginBottom = document.getElementById("marginBottom");
        const marginLeft = document.getElementById("marginLeft");
        const marginRight = document.getElementById("marginRight");
        const indent = document.getElementById("indent");
        const borderToggle = document.getElementById("borderToggle");
        const logoPosition = document.getElementById("logoPosition");

        if (fontStyle) fontStyle.addEventListener("change", (e) => updateFormatting('fontStyle', e.target.value));
        if (fontSize) fontSize.addEventListener("change", (e) => updateFormatting('fontSize', e.target.value));
        if (lineSpacing) lineSpacing.addEventListener("change", (e) => updateFormatting('lineSpacing', e.target.value));
        if (marginTop) marginTop.addEventListener("change", () => updateFormatting('margins'));
        if (marginBottom) marginBottom.addEventListener("change", () => updateFormatting('margins'));
        if (marginLeft) marginLeft.addEventListener("change", () => updateFormatting('margins'));
        if (marginRight) marginRight.addEventListener("change", () => updateFormatting('margins'));
        if (indent) indent.addEventListener("change", (e) => updateFormatting('indent', e.target.value));
        if (borderToggle) borderToggle.addEventListener("change", () => updateFormatting('border'));
        if (logoPosition) logoPosition.addEventListener("change", (e) => updateFormatting('logoPosition', e.target.value));

        // Alignment buttons
        document.querySelectorAll(".align-btn").forEach(btn => {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                const alignment = this.dataset.align;
                updateFormatting('alignment', alignment);
            });
        });

        // Logout functionality
        const logoutBtn = document.getElementById("logoutBtn");
        const logoutLink = document.getElementById("logoutLink");

        if (logoutBtn) logoutBtn.addEventListener("click", (e) => {
            e.preventDefault();
            openLogoutModal();
        });

        if (logoutLink) logoutLink.addEventListener("click", (e) => {
            e.preventDefault();
            openLogoutModal();
        });

        // User dropdown
        const userAvatar = document.getElementById("userAvatar");
        if (userAvatar) userAvatar.addEventListener("click", toggleUserDropdown);

        // Close dropdowns when clicking outside
        document.addEventListener("click", (e) => {
            if (!e.target.closest(".user-info")) {
                const dropdown = document.getElementById("userDropdown");
                if (dropdown) dropdown.style.display = "none";
            }
        });
    }

    function showMessage(message, type = "info") {
        const container = document.getElementById("messageContainer");
        if (!container) {
            console.error('Message container not found');
            return;
        }

        const messageEl = document.createElement("div");
        messageEl.className = `message ${type} animated fadeIn`;
        
        const iconMap = {
            success: "check-circle",
            error: "exclamation-circle",
            warning: "exclamation-triangle",
            info: "info-circle"
        };

        messageEl.innerHTML = `
            <i class="fas fa-${iconMap[type]}"></i>
            <span>${message}</span>
            <button class="message-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(messageEl);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageEl.parentElement) {
                messageEl.style.animation = "fadeOut 0.3s ease";
                setTimeout(() => messageEl.remove(), 300);
            }
        }, 5000);
    }

    // Confirmation modal functions
    function showConfirmation(title, message, type = "info", onConfirm, onCancel) {
        const modal = document.getElementById("confirmationModal");
        const titleEl = document.getElementById("confirmTitle");
        const messageEl = document.getElementById("confirmMessage");
        const iconEl = document.getElementById("confirmIcon");
        const confirmBtn = document.getElementById("confirmBtn");

        if (!modal || !titleEl || !messageEl || !iconEl || !confirmBtn) {
            console.error('Confirmation modal elements not found');
            if (onConfirm) onConfirm();
            return;
        }

        titleEl.textContent = title;
        messageEl.textContent = message;

        // Update icon based on type
        iconEl.className = "confirmation-icon " + type;
        const iconMap = {
            success: "fa-check-circle",
            warning: "fa-exclamation-circle",
            danger: "fa-trash-alt",
            info: "fa-info-circle",
        };
        iconEl.innerHTML = `<i class="fas ${iconMap[type]}"></i>`;

        // Store callbacks
        window.confirmCallback = onConfirm;
        window.cancelCallback = onCancel;

        modal.classList.add("show");
    }

    function executeConfirmation() {
        if (window.confirmCallback) {
            window.confirmCallback();
        }
        closeConfirmationModal();
    }

    function closeConfirmationModal() {
        const modal = document.getElementById("confirmationModal");
        if (modal) {
            modal.classList.remove("show");
        }
        if (window.cancelCallback) {
            window.cancelCallback();
            window.cancelCallback = null;
        }
    }

    function toggleUserDropdown() {
        const dropdown = document.getElementById("userDropdown");
        if (dropdown) {
            dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
        }
    }

    function openLogoutModal() {
        const modal = document.getElementById("logoutModal");
        if (modal) {
            modal.style.display = "flex";
        }
    }

    function closeLogoutModal() {
        const modal = document.getElementById("logoutModal");
        if (modal) {
            modal.style.display = "none";
        }
    }

    function confirmLogout() {
        window.location.href = "../logout.php";
    }

    function toggleSidebar() {
        const sidebar = document.querySelector(".sidebar");
        if (sidebar) {
            sidebar.classList.toggle("open");
        }
    }

    // Close modals when clicking outside
    document.addEventListener("click", (e) => {
        const modal = document.getElementById("confirmationModal");
        if (e.target === modal) {
            closeConfirmationModal();
        }
    });

    // Notification functionality
    function initializeNotifications() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationMenu = document.getElementById('notificationMenu');
        const markAllReadBtn = document.getElementById('markAllRead');
        const notificationList = document.getElementById('notificationList');

        // Toggle notification dropdown
        if (notificationBtn && notificationMenu) {
            notificationBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationMenu.classList.toggle('show');
                if (notificationMenu.classList.contains('show')) {
                    refreshNotifications();
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                notificationMenu.classList.remove('show');
            });

            notificationMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function() {
                markAllNotificationsAsRead();
            });
        }

        // Mark individual notification as read
        if (notificationList) {
            notificationList.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                if (notificationItem && notificationItem.classList.contains('unread')) {
                    const notificationId = notificationItem.getAttribute('data-id');
                    markNotificationAsRead(notificationId, notificationItem);
                }
            });
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(refreshNotifications, 30000);
    }

    function markNotificationAsRead(notificationId, element) {
        fetch('coordinator_document-control-panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_as_read&notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                element.classList.remove('unread');
                updateNotificationBadge();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }

    function markAllNotificationsAsRead() {
        fetch('coordinator_document-control-panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_as_read'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                updateNotificationBadge();
                showMessage('All notifications marked as read', 'success');
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
            showMessage('Error updating notifications', 'error');
        });
    }

    function refreshNotifications() {
        fetch('coordinator_document-control-panel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_notifications'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationDisplay(data.notifications, data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error refreshing notifications:', error);
        });
    }

    function updateNotificationDisplay(notifications, unreadCount) {
        updateNotificationBadge(unreadCount);
    }

    function updateNotificationBadge(unreadCount = null) {
        if (unreadCount === null) {
            unreadCount = document.querySelectorAll('.notification-item.unread').length;
        }
        
        const badge = document.getElementById('notificationBadge');
        const notificationBtn = document.getElementById('notificationBtn');
        
        if (unreadCount > 0) {
            if (!badge && notificationBtn) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge';
                newBadge.id = 'notificationBadge';
                newBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                notificationBtn.appendChild(newBadge);
            } else if (badge) {
                badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            }
        } else if (badge) {
            badge.remove();
            
            const markAllReadBtn = document.getElementById('markAllRead');
            if (markAllReadBtn && unreadCount === 0) {
                markAllReadBtn.remove();
            }
        }
    }
    </script>

    <link rel="stylesheet" href="/v10/CSS/session_timeout.css">
    <script src="/v10/JS/session_timeout.js"></script>
</body>
</html>
