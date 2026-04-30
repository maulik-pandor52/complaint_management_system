<?php
include("../config/db.php");
$conn = getDBConnection();
session_start();
require_once("../includes/status_lookup.php");

header('Content-Type: application/json; charset=utf-8');

// Only Admin for analytics API (simple security rule)
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden', 'data' => []]);
    exit;
}

$ID_RESOLVED  = get_status_id_or($conn, "Resolved", 3);
$ID_CLOSED    = get_status_id_or($conn, "Closed", 4);
$ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);

// Category summary with SLA signals
$sql = "
    SELECT
        cat.category_id,
        cat.category_name,
        COUNT(c.complaint_id) AS total,
        SUM(CASE WHEN c.status_id = ? THEN 1 ELSE 0 END) AS escalated,
        SUM(CASE WHEN c.status_id IN (?, ?) THEN 1 ELSE 0 END) AS resolved_closed,
        SUM(CASE WHEN c.complaint_id IS NOT NULL AND c.status_id NOT IN (?, ?) THEN 1 ELSE 0 END) AS open
    FROM complaint_categories cat
    LEFT JOIN complaints c ON c.category_id = cat.category_id
    GROUP BY cat.category_id, cat.category_name
    ORDER BY cat.category_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error', 'data' => []]);
    exit;
}

$stmt->bind_param("iiiii", $ID_ESCALATED, $ID_RESOLVED, $ID_CLOSED, $ID_RESOLVED, $ID_CLOSED);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'category_id' => (int)$row['category_id'],
        'category_name' => $row['category_name'],
        'total' => (int)$row['total'],
        'open' => (int)$row['open'],
        'resolved_closed' => (int)$row['resolved_closed'],
        'escalated' => (int)$row['escalated'],
    ];
}
$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Category summary loaded successfully.', 'data' => $data]);
