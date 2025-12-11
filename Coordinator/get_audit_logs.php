<?php
// start of change for version 14
// created API endpoint to fetch audit logs with search and filter capabilities
// supports pagination, role filter, action filter, and date range filter
// end of change for version 14

session_start();
require_once '../db/db.php';

// Check coordinator session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coordinator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$action_filter = isset($_GET['action']) ? sanitize($_GET['action']) : '';
//version 14 start - Added category filter parameter to API
//Added category_filter to support filtering by action_category
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';
//version 14 end - Added category filter parameter
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

// START of Version 14 changes
// Build query
$query = "SELECT id, user_id, user_name, role, action, action_category, details, severity, created_at FROM audit_logs WHERE 1=1";
// END of Version 14 changes
$params = [];

// Search filter
if (!empty($search)) {
    $query .= " AND (user_name LIKE ? OR action LIKE ? OR details LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Role filter
if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

// Action filter
if (!empty($action_filter)) {
    $query .= " AND action = ?";
    $params[] = $action_filter;
}

//version 14 start - Added category filter to SQL query
//Filter by action_category if category filter is provided
if (!empty($category_filter)) {
    $query .= " AND action_category = ?";
    $params[] = $category_filter;
}
//version 14 end - Added category filter to SQL query

// Date range filter
if (!empty($date_from)) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $date_to;
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
$countParams = $params;

if (!empty($search)) {
    $countQuery .= " AND (user_name LIKE ? OR action LIKE ? OR details LIKE ?)";
}
if (!empty($role_filter)) {
    $countQuery .= " AND role = ?";
}
if (!empty($action_filter)) {
    $countQuery .= " AND action = ?";
}
//version 14 start - Added category filter to count query
if (!empty($category_filter)) {
    $countQuery .= " AND action_category = ?";
}
//version 14 end - Added category filter to count query
if (!empty($date_from)) {
    $countQuery .= " AND DATE(created_at) >= ?";
}
if (!empty($date_to)) {
    $countQuery .= " AND DATE(created_at) <= ?";
}

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$totalRecords = $countStmt->fetch()['total'];

// Add ordering and pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'logs' => $logs,
    'total' => $totalRecords,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalRecords / $limit)
]);
?>
