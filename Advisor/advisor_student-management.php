<?php
require_once __DIR__ . '/../auth.php';
requireRole(['advisor']);
require_once __DIR__ . '/../db/db.php';
// Add PHPMailer requirement
require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path as needed
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$advisor_id = $_SESSION['user_id'];
$advisor_name = $_SESSION['name'] ?? 'Advisor';

$profile_picture = '../images/default-user.png'; // Default image

// Function to send email using PHPMailer
function sendStudentCredentials($email, $firstName, $lastName, $studentId, $tempPassword, $section) {
    global $pdo, $advisor_id, $advisor_name;
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'klarerivera25@gmail.com'; // Your Gmail address
        $mail->Password = 'bztg uiur xzho wslv'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('klarerivera25@gmail.com', 'ThesisTrack System');
        $mail->addAddress($email, $firstName . ' ' . $lastName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your ThesisTrack Student Account Credentials';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4a86e8; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                    .credentials { background-color: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                    .important { color: #e74c3c; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>ThesisTrack Student Account</h2>
                    </div>
                    <div class='content'>
                        <p>Dear $firstName $lastName,</p>
                        <p>Your student account has been successfully created in the ThesisTrack system.</p>
                        
                        <div class='credentials'>
                            <h3>Your Login Credentials:</h3>
                            <p><strong>Email:</strong> $email</p>
                            <p><strong>Student ID:</strong> $studentId</p>
                            <p><strong>Section:</strong> $section</p>
                            <p><strong>Temporary Password:</strong> $tempPassword</p>
                        </div>
                        
                        <p class='important'>Important: You will be required to change your password upon first login for security purposes.</p>
                        
                        <p>You can access the system at: <a href='http://tcu-thesistrack.com/login.php'>http://tcu-thesistrack.com/login.php</a></p>
                        
                        <p>If you have any questions, please contact your advisor or the system administrator or the research coordinator</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Alternative plain text version for non-HTML mail clients
        $mail->AltBody = "
            ThesisTrack Student Account
            
            Dear $firstName $lastName,
            
            Your student account has been successfully created in the ThesisTrack system.
            
            Your Login Credentials:
            Email: $email
            Student ID: $studentId
            Section: $section
            Temporary Password: $tempPassword
            
            Important: You will be required to change your password upon first login for security purposes.
            
            You can access the system at: http://tcu-thesistrack.com/login.php
            
            If you have any questions, please contact your advisor or the system administrator or the research coordinator
            
            This is an automated message. Please do not reply to this email.
        ";
        
        $mail->send();
        
        // Now notify the coordinator about the new student
        notifyCoordinator($firstName, $lastName, $studentId, $email, $advisor_name, $section);
        
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// New function to notify coordinator
function notifyCoordinator($firstName, $lastName, $studentId, $email, $advisorName, $section) {
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
            
            $title = "New Student Account Created";
            $message = "Student $firstName $lastName (ID: $studentId, Section: $section) has been added by advisor $advisorName.";
            
            $notification_stmt->execute([
                $coordinator['id'],
                $title,
                $message
            ]);
            
            // Send email notification to coordinator
            sendCoordinatorNotification($coordinator['email'], $coordinator['first_name'], $firstName, $lastName, $studentId, $email, $advisorName, $section);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Coordinator notification error: " . $e->getMessage());
        return false;
    }
}

// Function to send email notification to coordinator
function sendCoordinatorNotification($coordinatorEmail, $coordinatorName, $studentFirstName, $studentLastName, $studentId, $studentEmail, $advisorName, $section) {
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
        $mail->Subject = 'New Student Registration - ThesisTrack System';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4a86e8; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
                    .student-info { background-color: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0; }
                    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>New Student Registration</h2>
                    </div>
                    <div class='content'>
                        <p>Dear $coordinatorName,</p>
                        <p>A new student has been registered in the ThesisTrack system.</p>
                        
                        <div class='student-info'>
                            <h3>Student Details:</h3>
                            <p><strong>Name:</strong> $studentFirstName $studentLastName</p>
                            <p><strong>Student ID:</strong> $studentId</p>
                            <p><strong>Section:</strong> $section</p>
                            <p><strong>Email:</strong> $studentEmail</p>
                            <p><strong>Added by Advisor:</strong> $advisorName</p>
                            <p><strong>Registration Date:</strong> " . date('F j, Y g:i A') . "</p>
                        </div>
                        
                        <p>You can access the system at: <a href='http://tcu-thesistrack.com/coordinator_dashboard.php'</a></p>
                        
                       
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
            New Student Registration - ThesisTrack System
            
            Dear $coordinatorName,
            
            A new student has been registered in the ThesisTrack system.
            
            Student Details:
            Name: $studentFirstName $studentLastName
            Student ID: $studentId
            Section: $section
            Email: $studentEmail
            Added by Advisor: $advisorName
            Registration Date: " . date('F j, Y g:i A') . "
            
            You can view all students in the system.
            
            This is an automated notification from ThesisTrack System.
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Coordinator email notification failed: " . $mail->ErrorInfo);
        return false;
    }
}

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
            $stmt->execute([$notification_id, $advisor_id]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'mark_all_read') {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND user_type = 'advisor' AND is_read = 0
            ");
            $stmt->execute([$advisor_id]);
            
            echo json_encode(['success' => true]);
            
        } elseif ($action === 'get_notifications') {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? AND user_type = 'advisor' 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$advisor_id]);
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

// Get advisor's section and course
try {
    $stmt = $pdo->prepare("SELECT sections_handled, department FROM advisors WHERE id = ?");
    $stmt->execute([$advisor_id]);
    $advisor_info = $stmt->fetch();
    $advisor_section = $advisor_info['sections_handled'] ?? null;
    $advisor_course = $advisor_info['department'] ?? null;
    $available_sections = [];

    if (!empty($advisor_section)) {
        $available_sections = array_map('trim', explode(',', $advisor_section));
    }
} catch (PDOException $e) {
    $advisor_section = null;
    $advisor_course = null;
    $available_sections = [];
}

// ================== CSV IMPORT/EXPORT FUNCTIONALITY ================== //

// Handle CSV export (template or data)
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $type = $_GET['type'] ?? 'template';
    
    if ($type === 'template') {
        // Generate CSV template for import
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=student_import_template.csv');
        
        $output = fopen('php://output', 'w');
        
        // Get available sections from the advisor's profile, not from existing students
        $available_sections_for_template = [];
        
        if (!empty($advisor_section)) {
            $available_sections_for_template = array_map('trim', explode(',', $advisor_section));
        }
        
        // Add comment row with available sections
        if (!empty($available_sections_for_template)) {
            fputcsv($output, ['# Available sections: ' . implode(', ', $available_sections_for_template)]);
            fputcsv($output, ['# IMPORTANT: Section must be one of the values above']);
        } else {
            fputcsv($output, ['# No sections available. Please contact administrator.']);
        }
        
        // Add empty row for separation
        fputcsv($output, []);
        
        // Add headers
        fputcsv($output, ['first_name', 'last_name', 'middle_name', 'email', 'section']);
        fclose($output);
        exit();
        
    } elseif ($type === 'data') {
        // Export current student data
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=student_data_export.csv');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['student_id', 'first_name', 'last_name', 'middle_name', 'email', 'section', 'status', 'group_assignment']);
        
        try {
            $stmt = $pdo->prepare("
                SELECT s.student_id, s.first_name, s.last_name, s.middle_name, s.email, s.section, s.status,
                       GROUP_CONCAT(g.title SEPARATOR ', ') as group_title
                FROM students s
                LEFT JOIN group_members gm ON s.id = gm.student_id
                LEFT JOIN groups g ON gm.group_id = g.id
                WHERE s.advisor_id = ?
                GROUP BY s.id
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$advisor_id]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['student_id'],
                    $row['first_name'],
                    $row['last_name'],
                    $row['middle_name'] ?? '',
                    $row['email'],
                    $row['section'],
                    $row['status'],
                    $row['group_title'] ?? 'Not Assigned'
                ]);
            }
        } catch (PDOException $e) {
            // Log error but continue with empty export
            error_log("Export error: " . $e->getMessage());
        }
        
        fclose($output);
        exit();
    }
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_csv') {
    header('Content-Type: application/json');
    
    // Check if advisor has required assignments
    if (empty($advisor_section) || empty($advisor_course)) {
        echo json_encode(['success' => false, 'message' => 'You must be assigned to a section and course.']);
        exit();
    }
    
    // Check if file was uploaded successfully
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid CSV file.']);
        exit();
    }
    
    // Get available sections for validation - use advisor's assigned sections
    try {
        $available_sections = [];
        if (!empty($advisor_section)) {
            $available_sections = array_map('trim', explode(',', $advisor_section));
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching available sections.']);
        exit();
    }
    
    $file = $_FILES['csv_file']['tmp_name'];
    
    // Check if file is readable
    if (!is_readable($file)) {
        echo json_encode(['success' => false, 'message' => 'Cannot read the uploaded file.']);
        exit();
    }
    
    $handle = fopen($file, 'r');
    
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Failed to open the uploaded file.']);
        exit();
    }
    
    // Skip possible comment rows and get to actual headers
    $header = [];
    while (($row = fgetcsv($handle)) !== FALSE) {
        if (empty($row[0]) || strpos($row[0], '#') === 0) {
            continue; // Skip comment rows and empty rows
        }
        $header = $row;
        break;
    }
    
    // Validate header structure
    $expected_headers = ['first_name', 'last_name', 'middle_name', 'email', 'section'];
    if (count($header) < 5 || $header !== $expected_headers) {
        fclose($handle);
        echo json_encode(['success' => false, 'message' => 'Invalid CSV format. Expected headers: first_name, last_name, middle_name, email, section']);
        exit();
    }
    
    $imported = 0;
    $errors = [];
    $line = 1; // Start counting from header row
    
    while (($data = fgetcsv($handle)) !== FALSE) {
        $line++;
        
        // Skip empty rows
        if (empty(array_filter($data))) {
            continue;
        }
        
        // Skip comment rows
        if (!empty($data[0]) && strpos($data[0], '#') === 0) {
            continue;
        }
        
        // Validate required number of columns
        if (count($data) < 5) {
            $errors[] = "Line $line: Insufficient data columns (need: first_name, last_name, middle_name, email, section)";
            continue;
        }
        
        $first_name = sanitize(trim($data[0]));
        $last_name = sanitize(trim($data[1]));
        $middle_name = sanitize(trim($data[2] ?? ''));
        $email = sanitize(trim($data[3]));
        $section = sanitize(trim($data[4]));
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
            $errors[] = "Line $line: Missing required fields (first name, last name, email, or section)";
            continue;
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Line $line: Invalid email format '$email'";
            continue;
        }
        
        // Validate section is in available sections
        if (!in_array($section, $available_sections)) {
            $errors[] = "Line $line: Invalid section '$section'. Must be one of: " . implode(', ', $available_sections);
            continue;
        }
        
        try {
            // Check if student already exists by email
            $check_stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $check_stmt->execute([$email]);
            
            if ($check_stmt->fetch()) {
                $errors[] = "Line $line: Student with email '$email' already exists";
                continue;
            }
            
            // Generate student ID
            $year = date('Y');
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE course = ? AND YEAR(created_at) = ?");
            $count_stmt->execute([$advisor_course, $year]);
            $count = $count_stmt->fetch()['count'];
            $student_id = $year . '-' . $advisor_course . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            
            // Fixed default password - Generate "student" + random number
            $tempPassword = 'student' . rand(1000, 9999);
            $hashed_password = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Insert student
            $insert_stmt = $pdo->prepare("
                INSERT INTO students 
                (first_name, middle_name, last_name, email, password, student_id, year_level, section, course, status, profile_picture, advisor_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 3, ?, ?, 'active', '', ?, NOW())
            ");
            
            $insert_stmt->execute([
                $first_name, $middle_name, $last_name, $email, $hashed_password,
                $student_id, $section, $advisor_course, $advisor_id
            ]);
            
            // Get the inserted student ID
            $student_id_db = $pdo->lastInsertId();
            
            // Send email with credentials
            $emailSent = sendStudentCredentials($email, $first_name, $last_name, $student_id, $tempPassword, $section);
            
            $imported++;
            
        } catch (PDOException $e) {
            $errors[] = "Line $line: Database error - " . $e->getMessage();
            error_log("Import error on line $line: " . $e->getMessage());
        }
    }
    
    fclose($handle);
    
    // Prepare response
    $response = [
        'success' => $imported > 0,
        'imported' => $imported,
        'total_errors' => count($errors),
        'errors' => $errors
    ];
    
    if ($imported > 0) {
        $response['message'] = "Successfully imported $imported students. Students account credentials successfully sent.";
        if (!empty($errors)) {
            $response['message'] .= " " . count($errors) . " errors occurred during import.";
        }
    } else {
        $response['message'] = 'No students were imported.';
        if (!empty($errors)) {
            $response['message'] .= " " . count($errors) . " errors occurred.";
        }
    }
    
    echo json_encode($response);
    exit();
}

// ================== version 7 update here  ================== //

function getSortArrows($current_col, $sort_col, $sort_order) {
    if ($current_col == $sort_col) {
        // Active sorting - show caret up/down
        $arrow = $sort_order == 'ASC' ? 'caret-up' : 'caret-down';
        return '<i class="fas fa-'.$arrow.' active-arrow"></i>';
    }
    // Neutral state - show sort icon
    return '<i class="fas fa-sort neutral-arrow"></i>';
}

// Sorting functionality
$sort_column = $_GET['sort'] ?? 'last_name';
$sort_order = $_GET['order'] ?? 'asc';
$search_term = $_GET['search'] ?? '';
$entries_per_page = $_GET['entries'] ?? 5; 

// Validate sort column and order
$valid_columns = ['student_id', 'first_name', 'last_name', 'email', 'section', 'group_count', 'status'];
if (!in_array($sort_column, $valid_columns)) {
    $sort_column = 'last_name';
}
$sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

// ================== end of version 7 update here ================== //

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'add_student':
            if (!$advisor_section || !$advisor_course) {
                echo json_encode(['success' => false, 'message' => 'You must be assigned to a section and course.']);
                exit();
            }

            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email'] ?? '');
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, email, and section are required.']);
                exit();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                exit();
            }

            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists.']);
                    exit();
                }
                
                $year = date('Y');
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE course = ?");
                $stmt->execute([$advisor_course]);
                $count = $stmt->fetch()['count'];
                $student_id = $year . '-' . $advisor_course . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

                // Fixed default password - Generate "student" + random number
                $tempPassword = 'student' . rand(1000, 9999);
                $hashed_password = password_hash($tempPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO students 
                    (first_name, middle_name, last_name, email, password, student_id, year_level, section, course, status, profile_picture, advisor_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 3, ?, ?, 'active', '', ?, NOW())
                ");
                $stmt->execute([
                    $first_name, $middle_name, $last_name, $email, $hashed_password,
                    $student_id, $section, $advisor_course, $advisor_id
                ]);

                // Send email with credentials
                $emailSent = sendStudentCredentials($email, $first_name, $last_name, $student_id, $tempPassword, $section);
                
                $emailMessage = $emailSent 
                    ? " Email with credentials has been sent to the student." 
                    : " Note: Failed to send email with credentials.";

                echo json_encode([
                    'success' => true,
                    'message' => 'Student added successfully!' . $emailMessage . ' Coordinator has been notified.',
                    'student_data' => [
                        'name' => $first_name . ' ' . $middle_name . ' ' . $last_name,
                        'email' => $email,
                        'student_id' => $student_id,
                        'section' => $section,
                        'temp_password' => $tempPassword
                    ],
                    'email_sent' => $emailSent
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add student.']);
            }
            exit();

        case 'edit_student':
            $student_id = (int)$_POST['student_id'];
            $first_name = sanitize($_POST['first_name']);
            $middle_name = sanitize($_POST['middle_name'] ?? '');
            $last_name = sanitize($_POST['last_name']);
            $email = sanitize($_POST['email'] ?? '');
            $section = sanitize($_POST['section'] ?? '');

            if (empty($first_name) || empty($last_name) || empty($email) || empty($section)) {
                echo json_encode(['success' => false, 'message' => 'First name, last name, email, and section are required.']);
                exit();
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
                exit();
            }

            try {
                // Check if email already exists for another student
                $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
                $stmt->execute([$email, $student_id]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists for another student.']);
                    exit();
                }

                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET first_name = ?, middle_name = ?, last_name = ?, email = ?, section = ?
                    WHERE id = ? AND advisor_id = ?
                ");
                $stmt->execute([$first_name, $middle_name, $last_name, $email, $section, $student_id, $advisor_id]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Student updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No changes made or permission denied.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Update failed.']);
            }
            exit();

        case 'delete_student':
            $student_id = (int)$_POST['student_id'];

            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM group_members WHERE student_id = ?");
                $stmt->execute([$student_id]);
                $group_count = $stmt->fetch()['count'];

                if ($group_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Student is part of a group.']);
                    exit();
                }

                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND advisor_id = ?");
                $stmt->execute([$student_id, $advisor_id]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Student deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Student not found or permission denied.']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Deletion failed.']);
            }
            exit();
    }
}

// Fetch students assigned to this advisor with sorting and searching
try {
    $search_condition = '';
    $params = [$advisor_id];
    
    if (!empty($search_term)) {
        $search_condition = " AND (s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.section LIKE ?)";
        $search_param = "%$search_term%";
        array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param);
    }

    $query = "
        SELECT s.*, 
               CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) AS full_name,
               COALESCE(g.group_count, 0) as group_count,
               g.group_title
        FROM students s
        LEFT JOIN (
            SELECT gm.student_id, 
                   COUNT(gm.group_id) as group_count,
                   GROUP_CONCAT(gr.title SEPARATOR ', ') as group_title
            FROM group_members gm
            JOIN groups gr ON gm.group_id = gr.id
            GROUP BY gm.student_id
        ) g ON s.id = g.student_id
        WHERE s.advisor_id = ? $search_condition
        ORDER BY $sort_column $sort_order
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(); // Store all results in $all_students
    
    // Pagination
    $total_students = count($students);
    $total_pages = ceil($total_students / $entries_per_page);
    $current_page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $total_pages)) : 1;
    $offset = ($current_page - 1) * $entries_per_page;
    $paginated_students = array_slice($students, $offset, $entries_per_page);
} catch (PDOException $e) {
    $students = [];
    $paginated_students = [];
    error_log("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="../images/book-icon.ico">
    <link rel="stylesheet" href="../CSS/advisor_student-management.css">
    <link rel="stylesheet" href="../CSS/session_timeout.css">
    <script src="https://kit.fontawesome.com/4ef2a0fa98.js" crossorigin="anonymous"></script>
    <title>ThesisTrack</title>
    
</head>
<body>
    <div class="app-container">

    
        <!-- Sidebar -->
       <aside class="sidebar">
            <div class="sidebar-header">
                <h3>ThesisTrack</h3>
                <div class="college-info">College of Information and Communication Technology</div>
                <div class="sidebar-user"><img src="<?php echo htmlspecialchars($profile_picture); ?>" class="image-sidebar-avatar" id="sidebarAvatar" />
                <div class="sidebar-username"><?php echo htmlspecialchars($advisor_name); ?></div></div>
                <span class="role-badge">Subject Advisor</span>
            </div>
             <nav class="sidebar-nav">
                
                <a href="advisor_dashboard.php" class="nav-item" data-tab="analytics">
                    <i class="fas fa-chart-line"></i> Dashboard
                </a>
                <a href="advisor_group.php" class="nav-item" data-tab="groups">
                    <i class="fas fa-users"></i> Groups
                </a>
                <a href="advisor_student-management.php" class="nav-item active" data-tab="students">
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
            <!-- Main Content -->
            <main class="main-content">
                <!-- Message container -->
                <div id="messageContainer"></div>

                <!-- Page Title -->
                <div class="page-title-section">
                    <h1><i class="fas fa-user-graduate"></i> Student Management</h1>
                    <p>Manage students in your assigned section: <?php echo htmlspecialchars($advisor_section ?? 'Not Assigned'); ?></p>
                </div>
                <!-- End of Page Title -->

                <!-- Student Management Card -->
                <div class="card">
                    <h3><i class="fas fa-users"></i> List of Students</h3>
                    
                    <?php if (!$advisor_section): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Section Not Assigned:</strong> Please contact the coordinator to assign you to a section before adding students.
                        </div>
                    <?php else: ?>
                        <div class="action-section">
                           
                            
                            <!-- CSV Import/Export Buttons -->
                            <div class="csv-buttons">
                                <button class="btn-secondary" onclick="exportCSV('template')">
                                    <i class="fas fa-download"></i> Download Template
                                </button>
                                <button class="btn-secondary" onclick="showImportModal()">
                                    <i class="fas fa-upload"></i> Import CSV
                                </button>
                                <button class="btn-secondary" onclick="exportCSV('data')">
                                    <i class="fas fa-file-export"></i> Export Data
                                </button>
                            </div>
                            
                            <div class="section-info">
                                <span class="info-badge">Section: <?php echo htmlspecialchars($advisor_section); ?></span>
                                <span class="info-badge">Course: <?php echo htmlspecialchars($advisor_course); ?></span>
                                <span class="info-badge">Total Students: <?php echo count($students); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                     <!-- Show entries and Search-->
                    <div class="table-controls-row">
                        <form class="modern-search" method="GET" action="">
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

                            <div class="search-container">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search here..." class="search-input" 
                                    value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES) ?>">
                            
                                <!-- Preserve all GET parameters except page and entries -->
                                <?php foreach ($_GET as $key => $value): ?>
                                    <?php if ($key !== 'search' && $key !== 'page' && $key !== 'entries'): ?>
                                        <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Students Table -->
                    <?php
                    // Use the selected entries value for pagination
                    $students_per_page = $_GET['entries'] ?? 5;
                    $total_students = count($students);
                    $total_pages = ceil($total_students / $students_per_page);
                    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                    $current_page = max(1, min($current_page, $total_pages)); 
                    $start_index = ($current_page - 1) * $students_per_page;
                    $paginated_students = array_slice($students, $start_index, $students_per_page);
                    ?>


                    <div class="table-container">
                    <table class="students-table">
                      <thead>
                        <tr>
                            <th>
                                <a href="?sort=student_id&order=<?= $sort_column == 'student_id' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Student ID <?= getSortArrows('student_id', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=last_name&order=<?= $sort_column == 'last_name' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Student Name <?= getSortArrows('last_name', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=email&order=<?= $sort_column == 'email' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Email <?= getSortArrows('email', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=section&order=<?= $sort_column == 'section' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Section <?= getSortArrows('section', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=group_count&order=<?= $sort_column == 'group_count' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Group Assignment <?= getSortArrows('group_count', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>
                                <a href="?sort=status&order=<?= $sort_column == 'status' && $sort_order == 'ASC' ? 'desc' : 'asc' ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                                    Status <?= getSortArrows('status', $sort_column, $sort_order) ?>
                                </a>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No students found.</p>
                                            <?php if ($advisor_section): ?>
                                                <p>Click "Add New Student" to get started.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($paginated_students as $student): ?>

                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['section']); ?></td>
                                            <td>
                                                <?php if ($student['group_count'] > 0): ?>
                                                    <span class="group-badge assigned">
                                                        <i class="fas fa-users"></i> 
                                                        <?php echo htmlspecialchars($student['group_title']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="group-badge unassigned">
                                                         Not Assigned
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $student['status']; ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-dropdown">
                                                    <button class="action-btn" onclick="toggleActionDropdown(<?php echo $student['id']; ?>)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div class="action-menu" id="actionMenu<?php echo $student['id']; ?>">
                                                        <a href="#" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="#" onclick="deleteStudent(<?php echo $student['id']; ?>)" 
                                                           <?php echo $student['group_count'] > 0 ? 'class="disabled"' : ''; ?>>
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

                     <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                            <a class="page-link <?= ($page == $current_page) ? 'active' : '' ?>" 
                            href="?page=<?= $page ?>&sort=<?= $sort_column ?>&order=<?= $sort_order ?>&search=<?= urlencode($search_term) ?>&entries=<?= $entries_per_page ?>">
                            <?= $page ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                     <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    
<!-- Student Edit Modal -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="studentModalTitle">Edit Student</h3>
            <span class="close" onclick="closeStudentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="studentForm">
                <input type="hidden" id="studentId" name="student_id">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name *</label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middle_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastName">Last Name *</label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="section">Section *</label>
                    <select id="section" name="section" required>
                        <option value="">Select Section</option>
                        <?php foreach ($available_sections as $section): ?>
                            <option value="<?php echo htmlspecialchars($section); ?>"><?php echo htmlspecialchars($section); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-primary" onclick="saveStudent()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button class="btn-secondary" onclick="closeStudentModal()">
                Cancel
            </button>
        </div>
    </div>
</div>

   
   <!-- Confirmation Modal -->
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

    <!-- CSV Import Modal -->
<div id="importModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Import Students from CSV</h3>
            <span class="close" onclick="closeImportModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="import-instructions">
                <p><strong>CSV Format Requirements:</strong></p>
                <ul>
                    <li>File is in CSV format</li>
                    <li>Sections must be one of: 
                        <?php 
                        if (!empty($available_sections)) {
                            echo implode(', ', $available_sections);
                        } else {
                            echo 'No sections assigned';
                        }
                        ?>
                    </li>
                    
                <div class="download-template">
                    <a href="javascript:void(0)" onclick="exportCSV('template')">
                        <i class="fas fa-download"></i> Download template
                    </a>
                </div>

                </ul>

            
            <form id="importForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csvFile">Select CSV File</label>
                    <input type="file" id="csvFile" name="csv_file" accept=".csv" required>
                </div>
            </form>
            
            <div id="importResults" style="display: none;">
                <h4>Import Results</h4>
                <div id="importSuccess" class="alert alert-success"></div>
                <div id="importErrors" class="alert alert-error"></div>
            </div>
        </div>
                        <!-- Loading indicator -->
                    <div id="importLoading" class="loading-indicator" style="display: none;">
                        <div class="spinner"></div>
                        <span>Importing students, please wait...</span>
                    </div>
                    <!-- End Loading indicator -->
            <div class="modal-footer">
                <button id="importButton" type="button" class="btn-primary" onclick="submitImport()">
                    <i class="fas fa-upload"></i> Import
                </button>
                <button type="button" class="btn-secondary" onclick="closeImportModal()">
                    Cancel
                </button>
            </div>

    </div>
</div>

    <script src="../JS/advisor_student-management.js"></script>
  
</body>
    <script src="../JS/session_timeout.js"></script>
</html>
