<?php
include("../config/db.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

// Basic session-based protection (recommended).
// Admin can see all; Staff sees assigned; User sees own.
if (!isset($_SESSION['role_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

$role_id = (int)$_SESSION['role_id'];
$user_id = (int)$_SESSION['user_id'];

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($limit <= 0 || $limit > 500) $limit = 100;

$status_id = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;

// Base query
$sql = "SELECT c.* FROM complaints c";
$where = [];
$params = [];
$types = "";

if ($role_id === 2) {
    // Staff: only assigned complaints
    $sql .= " JOIN assignments a ON c.complaint_id = a.complaint_id";
    $where[] = "a.staff_id = ?";
    $types .= "i";
    $params[] = $user_id;
} elseif ($role_id === 3) {
    // User: only own complaints
    $where[] = "c.user_id = ?";
    $types .= "i";
    $params[] = $user_id;
}

if ($status_id > 0) {
    $where[] = "c.status_id = ?";
    $types .= "i";
    $params[] = $status_id;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY c.created_at DESC LIMIT ?";
$types .= "i";
$params[] = $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error', 'data' => []]);
    exit;
}

// bind_param using references
$bind = [$types];
foreach ($params as $p) $bind[] = $p;
$refs = [];
foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
call_user_func_array([$stmt, 'bind_param'], $refs);

$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) $data[] = $row;
$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Complaints loaded successfully.', 'data' => $data]);
?>
