<?php
include("../config/db.php");
$conn = getDBConnection();

header('Content-Type: application/json; charset=utf-8');

$campus = trim($_GET['campus'] ?? '');
if ($campus === '') {
    echo json_encode(['ok' => false, 'message' => 'campus is required', 'data' => []]);
    exit;
}

$sql = "SELECT DISTINCT level2 FROM area_master WHERE status = 1 AND level1 = ? ORDER BY level2 ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'db error', 'data' => []]);
    exit;
}

$stmt->bind_param("s", $campus);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row['level2'];
}

$stmt->close();
echo json_encode(['ok' => true, 'data' => $data]);

