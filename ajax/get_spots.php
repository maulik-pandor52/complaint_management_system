<?php
include("../config/db.php");
$conn = getDBConnection();

header('Content-Type: application/json; charset=utf-8');

$campus = trim($_GET['campus'] ?? '');
$building = trim($_GET['building'] ?? '');

if ($campus === '' || $building === '') {
    echo json_encode(['ok' => false, 'message' => 'campus and building are required', 'data' => []]);
    exit;
}

$sql = "SELECT area_id, level3 FROM area_master WHERE status = 1 AND level1 = ? AND level2 = ? ORDER BY level3 ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'db error', 'data' => []]);
    exit;
}

$stmt->bind_param("ss", $campus, $building);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'area_id' => (int)$row['area_id'],
        'spot' => $row['level3'] ?? ''
    ];
}

$stmt->close();
echo json_encode(['ok' => true, 'data' => $data]);

