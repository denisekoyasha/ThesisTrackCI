<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);
require_once __DIR__ . '/../db/db.php';
// Add PHPMailer requirement
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function to send JSON response
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit(); // CRITICAL: Stop script execution after sending JSON
}

$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['name'] ?? 'Advisor';

$profile_picture = '../images/default-user.png';

// Function to notify coordinator about new group
function notifyCoordinatorNewGroup($groupName, $thesisTitle, $section, $advisorName, $memberCount) {
    global $pdo;
    
    try {
        // Get all coordinators
        $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM coordinators WHERE status = 'active'");
        $stmt->execute();
        $coordinators = $stmt->fetchAll();
        
        foreach ($coordinators as $coordinator) {
            // Add notification to database
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, user_type, title, message, type, is_read, created_at)
                VALUES (?, 'coordinator', ?, ?, 'info', 0, NOW())
            ");
            
            $title = "New Group Created";
            $message = "Group '$groupName' (Thesis: $thesisTitle, Section: $section) has been created by advisor $advisorName with $memberCount members.";
            
            $notification_stmt->execute([
                $coordinator['id'],
                $title,
                $message
            ]);
            
            // Send email notification to coordinator
            sendCoordinatorGroupNotification($coordinator['email'], $coordinator['first_name'], $groupName, $thesisTitle, $section, $advisorName, $memberCount);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Coordinator notification error: " . $e->getMessage());
        return false;
    }
}

// Function to notify students about group assignment
function notifyStudentsGroupAssignment($studentIds, $groupName, $thesisTitle, $advisorName) {
    global $pdo;
    
    try {
        // Get student details
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $pdo->prepare("
            SELECT id, email, first_name, last_name 
            FROM students 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($studentIds);
        $students = $stmt->fetchAll();
        
        foreach ($students as $student) {
            // Add notification to database for student
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications 
                (user_id, user_type, title, message, type, is_read, created_at)
                VALUES (?, 'student', ?, ?, 'info', 0, NOW())
            ");
            
            $title = "Group Assignment";
            $message = "You have been assigned to group '$groupName' (Thesis: $thesisTitle) by advisor $advisorName.";
            
            $notification_stmt->execute([
                $student['id'],
                $title,
                $message
            ]);
            
            // Send email notification to student
            sendStudentGroupNotification($student['email'], $student['first_name'], $student['last_name'], $groupName, $thesisTitle, $advisorName);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Student notification error: " . $e->getMessage());
        return false;
    }
}

// Function to send email notification to coordinator about new group
function sendCoordinatorGroupNotification($coordinatorEmail, $coordinatorName, $groupName, $thesisTitle, $section, $advisorName, $memberCount) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'klarerivera25@gmail.com';
        $mail->Password = 'bztg uiur xzho wslv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('klarerivera25@gmail.com', 'ThesisTrack System');
        $mail->addAddress($coordinatorEmail, $coordinatorName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Group Created - ThesisTrack System';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4a86e8; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                    .group-info { background-color: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Group Created</h2>
                    </div>
                    <div class='content'>
                        <p>Dear $coordinatorName,</p>
                        <p>A new thesis group has been created in the ThesisTrack system.</p>
                        
                        <div class='group-info'>
                            <h3>Group Details:</h3>
                            <p><strong>Group Name:</strong> $groupName</p>
                            <p><strong>Thesis Title:</strong> $thesisTitle</p>
                            <p><strong>Section:</strong> $section</p>
                            <p><strong>Number of Members:</strong> $memberCount</p>
                            <p><strong>Created by Advisor:</strong> $advisorName</p>
                            <p><strong>Creation Date:</strong> " . date('F j, Y g:i A') . "</p>
                        </div>
                        
                        <p>You can view all groups in the system through the coordinator dashboard.</p>
                        
                        <p><a href='http://tcu-thesistrack.com/coordinator_dashboard.php' style='background-color: #4a86e8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View Dashboard</a></p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated notification from ThesisTrack System.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Alternative plain text version
        $mail->AltBody = "
            New Group Created - ThesisTrack System
            
            Dear $coordinatorName,
            
            A new thesis group has been created in the ThesisTrack system.
            
            Group Details:
            Group Name: $groupName
            Thesis Title: $thesisTitle
            Section: $section
            Number of Members: $memberCount
            Created by Advisor: $advisorName
            Creation Date: " . date('F j, Y g:i A') . "
            
            You can view all groups in the system through the coordinator dashboard.
            
            This is an automated notification from ThesisTrack System.
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Coordinator group email notification failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send email notification to student about group assignment
function sendStudentGroupNotification($studentEmail, $studentFirstName, $studentLastName, $groupName, $thesisTitle, $advisorName) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'klarerivera25@gmail.com';
        $mail->Password = 'bztg uiur xzho wslv';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('klarerivera25@gmail.com', 'ThesisTrack System');
        $mail->addAddress($studentEmail, $studentFirstName . ' ' . $studentLastName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Group Assignment - ThesisTrack System';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4a86e8; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                    .group-info { background-color: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Group Assignment</h2>
                    </div>
                    <div class='content'>
                        <p>Dear $studentFirstName $studentLastName,</p>
                        <p>You have been assigned to a thesis group in the ThesisTrack system.</p>
                        
                        <div class='group-info'>
                            <h3>Your Group Details:</h3>
                            <p><strong>Group Name:</strong> $groupName</p>
                            <p><strong>Thesis Title:</strong> $thesisTitle</p>
                            <p><strong>Assigned by Advisor:</strong> $advisorName</p>
                            <p><strong>Assignment Date:</strong> " . date('F j, Y g:i A') . "</p>
                        </div>
                        
                        <p>You can access your group dashboard to start working on your thesis:</p>
                        
                        <p><a href='http://tcu-thesistrack.com/student_dashboard.php' style='background-color: #4a86e8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Access Dashboard</a></p>
                        
                        <p>If you have any questions about your group assignment, please contact your advisor $advisorName.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated notification from ThesisTrack System.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Alternative plain text version
        $mail->AltBody = "
            Group Assignment - ThesisTrack System
            
            Dear $studentFirstName $studentLastName,
            
            You have been assigned to a thesis group in the ThesisTrack system.
            
            Your Group Details:
            Group Name: $groupName
            Thesis Title: $thesisTitle
            Assigned by Advisor: $advisorName
            Assignment Date: " . date('F j, Y g:i A') . "
            
            You can access your group dashboard to start working on your thesis.
            
            If you have any questions about your group assignment, please contact your advisor $advisorName.
            
            This is an automated notification from ThesisTrack System.
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Student group email notification failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle ALL AJAX requests FIRST - before any other processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleAjaxRequest();
    exit(); // Stop execution after handling AJAX
}

// Handle notification actions separately
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action'])) {
    handleNotificationRequest();
    exit(); // Stop execution after handling notifications
}

/**
 * Handle all group-related AJAX requests
 */
/**
 * Handle all group-related AJAX requests
 */
function handleAjaxRequest() {
    global $pdo, $advisor_id, $advisor_name;
    
    try {
        // Get advisor's sections for validation
        $stmt = $pdo->prepare("SELECT sections_handled, department FROM advisors WHERE id = ?");
        $stmt->execute([$advisor_id]);
        $advisor_info = $stmt->fetch();
        
        $advisor_sections = array_filter(
            array_map('trim', 
                explode(',', $advisor_info['sections_handled'] ?? '')
            ),
            function($section) { return !empty($section); }
        );
        
        $advisor_course = $advisor_info['department'] ?? null;
        
        switch ($_POST['action']) {
            case 'create_group':
                handleCreateGroup($advisor_sections, $advisor_course);
                break;
                
            case 'update_group':
                handleUpdateGroup();
                break;
                
            case 'get_group_data':
                handleGetGroupData();
                break;
                
            case 'delete_group':
                handleDeleteGroup();
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("AJAX Error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Handle create group request
 */
function handleCreateGroup($advisor_sections, $advisor_course) {
    global $pdo, $advisor_id, $advisor_name;
    
    $group_name = trim($_POST['group_name'] ?? '');
    $thesis_title = trim($_POST['thesis_title'] ?? '');
    $section = $_POST['section'] ?? '';
    // Max members allowed for this group (advisor can set)
                    $max_members = isset($_POST['max_members']) ? (int)$_POST['max_members'] : 4;
    if ($max_members < 1) $max_members = 1;
    
    // FIX: Properly handle student_ids as array
    $student_ids = $_POST['student_ids'] ?? [];
    if (is_string($student_ids)) {
        $student_ids = [$student_ids]; // Convert to array if it's a string
    }
    
    // FIX: Properly handle student_roles as array
    $student_roles = $_POST['student_roles'] ?? [];
    if (is_string($student_roles)) {
        $student_roles = [$student_roles]; // Convert to array if it's a string
    }

    // Validate inputs
    if (empty($group_name)) {
        throw new Exception('Group name is required');
    }

    if (empty($thesis_title)) {
        throw new Exception('Thesis title is required');
    }

    // Validate section belongs to advisor
    if (!in_array($section, $advisor_sections)) {
        throw new Exception('Invalid section selected');
    }

    // Validate member count - FIX: Use count() safely
    $student_count = is_countable($student_ids) ? count($student_ids) : 0;
    if ($student_count === 0) {
        throw new Exception('Please select at least one student');
    }

    if ($student_count > $max_members) {
        throw new Exception('A group can have maximum ' . $max_members . ' members');
    }

    // Validate exactly 1 leader - FIX: Use count() safely
    $leaderCount = 0;
    if (is_countable($student_roles)) {
        $role_counts = array_count_values($student_roles);
        $leaderCount = $role_counts['leader'] ?? 0;
    }
    
    if ($leaderCount !== 1) {
        throw new Exception('Each group must have exactly 1 Leader');
    }

    $pdo->beginTransaction();
    
    try {
        // 1. Create the main group record
        $stmt = $pdo->prepare("INSERT INTO groups (title, advisor_id, section, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$group_name, $advisor_id, $section]);
        $group_id = $pdo->lastInsertId();
        
        // 2. Create the student_group record with group_id reference
    // Persist max_members in student_groups (requires DB migration)
    $stmt = $pdo->prepare("INSERT INTO student_groups (group_id, group_name, thesis_title, advisor_id, section, course, max_members, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
    try {
        $stmt->execute([$group_id, $group_name, $thesis_title, $advisor_id, $section, $advisor_course, $max_members]);
    } catch (PDOException $e) {
        error_log('student_groups INSERT failed: ' . $e->getMessage());
        // Also log SQL and params for debugging if available
        if (isset($stmt)) {
            error_log('Last prepared SQL: ' . $stmt->queryString);
        }
        throw $e;
    }
        $student_group_id = $pdo->lastInsertId();
        
        // 3. Add members
        foreach ($student_ids as $index => $student_id) {
            $role = $student_roles[$index] ?? 'member';
            
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id, role_in_group) VALUES (?, ?, ?)");
            $stmt->execute([$group_id, $student_id, $role]);
            
            // Update student's advisor
            $stmt = $pdo->prepare("UPDATE students SET advisor_id = ? WHERE id = ?");
            $stmt->execute([$advisor_id, $student_id]);
        }
        
        $pdo->commit();

        // Send notifications after successful group creation
        $memberCount = $student_count;
        $coordinatorNotified = notifyCoordinatorNewGroup($group_name, $thesis_title, $section, $advisor_name, $memberCount);
        $studentsNotified = notifyStudentsGroupAssignment($student_ids, $group_name, $thesis_title, $advisor_name);

        $notificationMessage = '';
        if ($coordinatorNotified) {
            $notificationMessage .= ' Coordinator has been notified.';
        }
        if ($studentsNotified) {
            $notificationMessage .= ' Students have been notified.';
        }

        sendJsonResponse([
            'success' => true,
            'message' => 'Group created successfully.' . $notificationMessage,
            'group_id' => $group_id,
            'student_group_id' => $student_group_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle update group request
 */
function handleUpdateGroup() {
    global $pdo, $advisor_id, $advisor_name;
    
    $group_id = $_POST['group_id'] ?? 0;
    $student_group_id = $_POST['student_group_id'] ?? 0;
    $group_name = trim($_POST['group_name'] ?? '');
    $thesis_title = trim($_POST['thesis_title'] ?? '');
                    $max_members = isset($_POST['max_members']) ? (int)$_POST['max_members'] : 4;
    if ($max_members < 1) $max_members = 1;
    
    // FIX: Properly handle student_ids as array
    $student_ids = $_POST['student_ids'] ?? [];
    if (is_string($student_ids)) {
        $student_ids = [$student_ids]; // Convert to array if it's a string
    }
    
    // FIX: Properly handle student_roles as array
    $student_roles = $_POST['student_roles'] ?? [];
    if (is_string($student_roles)) {
        $student_roles = [$student_roles]; // Convert to array if it's a string
    }

    // Validate inputs
    if (empty($group_name)) {
        throw new Exception('Group name is required');
    }

    if (empty($thesis_title)) {
        throw new Exception('Thesis title is required');
    }

    // Validate member count - FIX: Use count() safely
    $student_count = is_countable($student_ids) ? count($student_ids) : 0;
    if ($student_count === 0) {
        throw new Exception('Please select at least one student');
    }

    if ($student_count > $max_members) {
        throw new Exception('A group can have maximum ' . $max_members . ' members');
    }

    // Validate exactly 1 leader - FIX: Use count() safely
    $leaderCount = 0;
    if (is_countable($student_roles)) {
        $role_counts = array_count_values($student_roles);
        $leaderCount = $role_counts['leader'] ?? 0;
    }
    
    if ($leaderCount !== 1) {
        throw new Exception('Each group must have exactly 1 Leader');
    }

    $pdo->beginTransaction();
    
    try {
        // Verify group belongs to this advisor and get current section
        $stmt = $pdo->prepare("SELECT id, section FROM groups WHERE id = ? AND advisor_id = ?");
        $stmt->execute([$group_id, $advisor_id]);
        $group_info = $stmt->fetch();
        
        if (!$group_info) {
            throw new Exception('Group not found or access denied');
        }
        
        $current_section = $group_info['section'];

        // Validate all new students are from the group's current section
        if ($student_count > 0) {
            $placeholders = implode(',', array_fill(0, $student_count, '?'));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as match_count
                FROM students
                WHERE id IN ($placeholders) AND section = ?
            ");
            $params = array_merge($student_ids, [$current_section]);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            if ($result['match_count'] != $student_count) {
                throw new Exception('All students must be from the group\'s current section: ' . $current_section);
            }
        }

    // Update group name in groups table
    $stmt = $pdo->prepare("UPDATE groups SET title = ? WHERE id = ?");
    $stmt->execute([$group_name, $group_id]);
        
    // Update group info in student_groups table (including max_members)
    $stmt = $pdo->prepare("UPDATE student_groups SET group_name = ?, thesis_title = ?, max_members = ? WHERE id = ?");
    $stmt->execute([$group_name, $thesis_title, $max_members, $student_group_id]);
        
        // Remove all current members
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // Add all members (existing and new) with their roles
        foreach ($student_ids as $index => $student_id) {
            $role = $student_roles[$index] ?? 'member';
            $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id, role_in_group) VALUES (?, ?, ?)");
            $stmt->execute([$group_id, $student_id, $role]);
            
            // Update student's advisor
            $stmt = $pdo->prepare("UPDATE students SET advisor_id = ? WHERE id = ?");
            $stmt->execute([$advisor_id, $student_id]);
        }
        
        $pdo->commit();

        // Send notifications to students about group update
        $studentsNotified = notifyStudentsGroupAssignment($student_ids, $group_name, $thesis_title, $advisor_name);

        $notificationMessage = '';
        if ($studentsNotified) {
            $notificationMessage = ' Students have been notified about the group update.';
        }

        sendJsonResponse([
            'success' => true, 
            'message' => 'Group updated successfully.' . $notificationMessage
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle get group data request
 */
function handleGetGroupData() {
    global $pdo, $advisor_id;
    
    $group_id = $_POST['group_id'] ?? 0;
    
    // Get basic group info
    $stmt = $pdo->prepare("
    SELECT g.id, g.title as group_name, sg.id as student_group_id, sg.thesis_title, g.section, COALESCE(sg.max_members, 4) as max_members
        FROM groups g
        JOIN student_groups sg ON g.id = sg.group_id
        WHERE g.id = ? AND g.advisor_id = ?
    ");
    $stmt->execute([$group_id, $advisor_id]);
    $group_info = $stmt->fetch();
    
    if (!$group_info) {
        throw new Exception('Group not found or access denied');
    }
    
    // Get current members
    $stmt = $pdo->prepare("
        SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, gm.role_in_group as role
        FROM group_members gm
        JOIN students s ON gm.student_id = s.id
        WHERE gm.group_id = ?
        ORDER BY 
        CASE gm.role_in_group WHEN 'leader' THEN 0 ELSE 1 END,
        s.last_name, s.first_name
    ");
    $stmt->execute([$group_id]);
    $members = $stmt->fetchAll();
    
    // Get available students (ungrouped in same section)
    $stmt = $pdo->prepare("
        SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.section
        FROM students s
        LEFT JOIN group_members gm ON s.id = gm.student_id
        WHERE (s.advisor_id IS NULL OR s.advisor_id = ?)
        AND s.section = ?
        AND gm.student_id IS NULL
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$advisor_id, $group_info['section']]);
    $available_students = $stmt->fetchAll();
    
    sendJsonResponse([
        'success' => true,
        'data' => $group_info,
        'members' => $members,
        'available_students' => $available_students
    ]);
}

/**
 * Handle delete group request
 */
function handleDeleteGroup() {
    global $pdo, $advisor_id;
    
    $group_id = $_POST['group_id'] ?? 0;
    $student_group_id = $_POST['student_group_id'] ?? 0;
    
    $pdo->beginTransaction();
    
    try {
        // Verify group belongs to this advisor
        $stmt = $pdo->prepare("SELECT id, section FROM groups WHERE id = ? AND advisor_id = ?");
        $stmt->execute([$group_id, $advisor_id]);
        $group_info = $stmt->fetch();
        
        if (!$group_info) {
            throw new Exception('Group not found or access denied');
        }
        
        // Get member IDs to update their advisor_id to NULL
        $stmt = $pdo->prepare("SELECT student_id FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete group members
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$group_id]);
        
        // Delete the group from groups table
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
        $stmt->execute([$group_id]);
        
        // Delete the corresponding student_group
        $stmt = $pdo->prepare("DELETE FROM student_groups WHERE id = ?");
        $stmt->execute([$student_group_id]);
        
        // Update students' advisor_id to NULL
        // if (!empty($member_ids)) {
        //     $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
        //     $stmt = $pdo->prepare("UPDATE students SET advisor_id = NULL WHERE id IN ($placeholders)");
        //     $stmt->execute($member_ids);
        // }
        
        $pdo->commit();
        sendJsonResponse(['success' => true, 'message' => 'Group deleted successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle notification requests
 */
function handleNotificationRequest() {
    global $pdo, $advisor_id;
    
    $action = $_POST['notification_action'];
    
    if ($action === 'mark_as_read' && isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = ? AND user_id = ? AND user_type = 'advisor'
        ");
        $stmt->execute([$notification_id, $advisor_id]);
        
        sendJsonResponse(['success' => true]);
        
    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
        ");
        $stmt->execute([$advisor_id]);
        
        sendJsonResponse(['success' => true]);
        
    } elseif ($action === 'get_notifications') {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND user_type = 'advisor' 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$advisor_id]);
        $notifications = $stmt->fetchAll();
        
        sendJsonResponse([
            'success' => true,
            'notifications' => $notifications
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Invalid action']);
    }
}

// Only continue with HTML rendering if it's not an AJAX request
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
    // Log the error and use default values
    error_log("Database error fetching advisor details: " . $e->getMessage());
    $user_name = 'Advisor';
    $profile_picture = '../images/default-user.png';
}

// Fetch unread notifications count and recent notifications
try {
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
    
} catch (PDOException $e) {
    $notifications = [];
    $unread_notifications_count = 0;
    error_log("Error fetching notifications: " . $e->getMessage());
}

// Initialize sorting variables from URL parameters
$sort_col = $_GET['sort'] ?? 'title'; // Default sort column
$sort_order = $_GET['order'] ?? 'asc'; // Default sort order

// Validate sort column to prevent SQL injection
$valid_columns = ['id', 'title', 'section', 'status', 'thesis_title'];
if (!in_array($sort_col, $valid_columns)) {
    $sort_col = 'title';
}

// Validate sort order
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

// Function to generate sort arrows
function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active state: Solid caret-style arrow
        $arrow = $sort_order == 'ASC' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow" title="Sorted"></i>';
    }
    // Neutral state: Standard sort icon
    return '<i class="fas fa-sort neutral-arrow" title="Click to sort"></i>';
}       

// Get advisor's sections and course with proper handling of multiple sections
try {
    $stmt = $pdo->prepare("SELECT sections_handled, department FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor_info = $stmt->fetch();
    
    // Clean and split sections
    $advisor_sections = array_filter(
        array_map('trim', 
            explode(',', $advisor_info['sections_handled'] ?? '')
        ),
        function($section) { return !empty($section); }
    );
    
    $advisor_course = $advisor_info['department'] ?? null;
    
    // If no sections found, set to empty array
    if (empty($advisor_sections)) {
        $advisor_sections = [];
    }
} catch (PDOException $e) {
    $advisor_sections = [];
    $advisor_course = null;
}

// Fetch groups for this advisor with combined data from both tables
try {
    $stmt = $pdo->prepare("\n        SELECT g.id, g.title as group_name, g.section, g.status,\n               sg.thesis_title, sg.id as student_group_id, COALESCE(sg.max_members, 4) as max_members\n        FROM groups g\n        JOIN student_groups sg ON g.id = sg.group_id\n        WHERE g.advisor_id = ?\n        ORDER BY $sort_col $sort_order, g.section, g.title\n    ");
    $stmt->execute([$advisor_id]);
    $groups = $stmt->fetchAll();


    // Get members for each group
    foreach ($groups as &$group) {
        $stmt = $pdo->prepare("
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, gm.role_in_group as role
            FROM group_members gm
            JOIN students s ON gm.student_id = s.id
            WHERE gm.group_id = ?
            ORDER BY 
            CASE gm.role_in_group WHEN 'leader' THEN 0 ELSE 1 END,
            s.last_name, s.first_name
        ");
        $stmt->execute([$group['id']]);
        $group['members'] = $stmt->fetchAll();
    }
    unset($group); // Break the reference

} catch (PDOException $e) {
    $groups = [];
}

// Fetch ungrouped students for this advisor's sections
try {
    if (empty($advisor_sections)) {
        $ungrouped_students = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($advisor_sections), '?'));
        $query = "
            SELECT s.id, s.first_name, s.last_name, s.section
            FROM students s
            LEFT JOIN group_members gm ON s.id = gm.student_id
            WHERE (s.advisor_id IS NULL OR s.advisor_id = ?)
            AND s.section IN ($placeholders)
            AND gm.student_id IS NULL
            ORDER BY s.section, s.last_name, s.first_name
        ";
        
        $stmt = $pdo->prepare($query);
        $params = array_merge([$advisor_id], $advisor_sections);
        $stmt->execute($params);
        $ungrouped_students = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $ungrouped_students = [];
}

// Pagination settings - use the selected entries value
$per_page = isset($_GET['entries']) ? (int)$_GET['entries'] : 5; // Number of items per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1; // Current page
$offset = ($page - 1) * $per_page;

// Apply pagination to your groups data
$paginated_groups = array_slice($groups, $offset, $per_page);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_thesis-group.css">
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
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user">
                   <img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                    <div class="sidebar-username"><?php echo htmlspecialchars($advisor_name); ?></div>
                </div>
                <span class="role-badge">Subject Advisor</span>
            </div>
            <nav class="sidebar-nav">
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item" data-tab="students">
                    <i class="fas fa-user-graduate"></i> Student Management
                </a>
                <a href="advisor_thesis-group.php" class="nav-item active" data-tab="students">
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
                    <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                        alt="User Avatar" 
                        class="user-avatar" 
                        id="userAvatar" 
                        tabindex="0" />
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="advisor_settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="#" class="dropdown-item" id="headerLogoutLink">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            </header>
        <!-- End Header -->

            <main class="main-content">
                <!-- Message container -->
                <div id="messageContainer"></div>

                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-users-rectangle"></i> Thesis Group Management</h1>
                    <p>Manage thesis groups in your assigned sections: <?php echo htmlspecialchars(implode(', ', $advisor_sections)); ?></p>
                </div>

                <!-- Group Management Card -->
                <div class="card">
                    <h3><i class="fas fa-users-cog"></i> Thesis Groups</h3>
                    
                    <div class="action-section">
                        <button class="btn-primary" onclick="showCreateGroupModal()">
                            <i class="fas fa-plus"></i> Create New Group
                        </button>
                        <div class="section-info">
                            <span class="info-badge">Total Groups: <?php echo count($groups); ?></span>
                            <span class="info-badge">Ungrouped Students: <?php echo count($ungrouped_students); ?></span>
                        </div>
                    </div>

                   <!-- Show entries and Search -->
                    <div class="table-controls-row">
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

                        <form class="modern-search" method="GET" action="">
                            <div class="search-container">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search here..." class="search-input" 
                                    value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES) ?>">
                                
                                <!-- Preserve all GET parameters except search and page -->
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort'] ?? '') ?>">
                                <input type="hidden" name="order" value="<?= htmlspecialchars($_GET['order'] ?? '') ?>">
                                <input type="hidden" name="entries" value="<?= htmlspecialchars($_GET['entries'] ?? '') ?>">
                            </div>
                        </form>
                    </div>
    
                    <!-- Groups Table -->
                    <div class="table-container">
                        <table id="groupsTable" class="students-table">
                         <thead>
                            <tr>
                                <th><a href="?sort=id&order=<?= $sort_col == 'id' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Group ID <?= getSortArrows('id', $sort_col, $sort_order) ?></a></th>
                                <th><a href="?sort=title&order=<?= $sort_col == 'title' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Group Name <?= getSortArrows('title', $sort_col, $sort_order) ?></a></th>
                                <th><a href="?sort=thesis_title&order=<?= $sort_col == 'thesis_title' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Thesis Title <?= getSortArrows('thesis_title', $sort_col, $sort_order) ?></a></th>
                                <th>Max</th>
                                <th>Members</th>
                                <th><a href="?sort=section&order=<?= $sort_col == 'section' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Section <?= getSortArrows('section', $sort_col, $sort_order) ?></a></th>
                                <th>Advisor</th>
                                <th><a href="?sort=status&order=<?= $sort_col == 'status' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>">Status <?= getSortArrows('status', $sort_col, $sort_order) ?></a></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                            <tbody>
                                <?php if (empty($groups)): ?>
                                    <tr>
                                        <td colspan="9" class="no-data">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No groups found.</p>
                                            <p>Click "Create New Group" to get started.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($groups as $group): ?>
                                        <tr data-group-id="<?php echo $group['id']; ?>">
                                            <td><strong>GRP-<?php echo str_pad($group['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                            <td data-student-group-id="<?php echo $group['student_group_id']; ?>"><?php echo htmlspecialchars($group['thesis_title'] ?? 'Not set'); ?></td>
                                            <td class="max-members-cell"><?php echo htmlspecialchars($group['max_members'] ?? '4'); ?></td>
                                            <td>
                                                <ul class="member-list">
                                                    <?php foreach ($group['members'] as $member): ?>
                                                    <li class="member-item">
                                                        <span class="member-name"><?php echo htmlspecialchars($member['name']); ?></span>
                                                        <span class="<?php echo $member['role'] === 'leader' ? 'leader-role' : 'member-role'; ?>">
                                                            (<?php echo $member['role'] === 'leader' ? 'Leader' : 'Member'; ?>)
                                                        </span>
                                                    </li>

                                                    <?php endforeach; ?>
                                                </ul>
                                            </td>
                                            <td><?php echo htmlspecialchars($group['section']); ?></td>
                                            <td><?php echo htmlspecialchars($advisor_name); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $group['status']; ?>">
                                                    <?php echo ucfirst($group['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-dropdown">
                                                    <button class="action-btn" onclick="toggleActionDropdown(<?php echo $group['id']; ?>)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="action-menu" id="actionMenu<?php echo $group['id']; ?>">
                                                        <a href="#" onclick="editGroup(<?php echo $group['id']; ?>, <?php echo $group['student_group_id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="#" onclick="deleteGroup(<?php echo $group['id']; ?>, <?php echo $group['student_group_id']; ?>)">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

     <!-- Create Group Modal -->
    <div id="createGroupModal" class="groupmodal">
        
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-users-cog"></i> Create New Group</h3>
                <span class="close" onclick="closeCreateGroupModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createGroupForm">
                    <div class="form-group">
                        <label for="groupName">Group Name *</label>
                        <input type="text" id="groupName" name="group_name" required>
                    </div>
                    <div class="form-group">
                        <label for="thesisTitle">Thesis Title *</label>
                        <input type="text" id="thesisTitle" name="thesis_title" required>
                    </div>
                    <div class="form-group">
                        <label for="groupSection">Section *</label>
                        <select id="groupSection" name="section" required>
                            <?php foreach ($advisor_sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="maxMembers">Max Members *</label>
                        <input type="number" id="maxMembers" name="max_members" value="4" min="1" max="10">
                        <small>Set the maximum number of members allowed for this group (default 4).</small>
                    </div>
                    <div class="form-group">
                        <label>Select Members (check to include)</label>
                        <div class="student-selector" id="studentSelector">
                            <?php if (empty($ungrouped_students)): ?>
                                <p>No ungrouped students available.</p>
                            <?php else: ?>
                                <?php foreach ($ungrouped_students as $student): ?>
                                    <div class="student-select-item">
                                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" 
                                               id="student_<?php echo $student['id']; ?>" class="student-checkbox">
                                        <label for="student_<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                            (<?php echo htmlspecialchars($student['section']); ?>)
                                        </label>
                                        <select name="student_roles[]" class="role-selector" disabled>
                                            <option value="member">Member</option>
                                            <option value="leader">Leader</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </form>
                
                <!-- Loading Indicator -->
                <div id="createGroupLoading" class="loading-indicator" style="display: none;">
                    <div class="spinner"></div>
                    <span>Creating group and sending notifications, please wait...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" id="createGroupBtn" onclick="createGroup()" <?php echo empty($ungrouped_students) ? 'disabled' : ''; ?>>
                    <i class="fas fa-save"></i> Create Group
                </button>
                <button class="btn-secondary" onclick="closeCreateGroupModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div id="editGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Group</h3>
                <span class="close" onclick="closeEditGroupModal()">&times;</span>
            </div>
            <div class="modal-body" id="editGroupModalBody">
                <!-- Content will be loaded dynamically -->
                
                <!-- Loading Indicator for Edit -->
                <div id="editGroupLoading" class="loading-indicator" style="display: none;">
                    <div class="spinner"></div>
                    <span>Updating group and sending notifications, please wait...</span>
                </div>
            </div>
        </div>
    </div>

    <!--  Delete Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="confirmModalTitle">Confirm Action</h3>
                <span class="close" onclick="closeConfirmModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmModalMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="modal-footer">
                <button class="btn-danger" id="confirmActionBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
                <button class="btn-secondary" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="../JS/advisor_thesis-group.js"></script>
   
</body>
    <script src="../JS/session_timeout.js"></script>
</html>
