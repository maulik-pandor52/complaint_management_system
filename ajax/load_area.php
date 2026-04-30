<?php
include("../config/db.php");
$conn = getDBConnection();

header('Content-Type: application/json; charset=utf-8');

$level1 = trim($_GET['level1'] ?? '');

if ($level1 === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'level1 is required', 'data' => []]);
    exit;
}

$stmt = $conn->prepare("SELECT area_id, level2, level3 FROM area_master WHERE status = 1 AND level1 = ? ORDER BY level2 ASC, level3 ASC");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error', 'data' => []]);
    exit;
}

$stmt->bind_param("s", $level1);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'area_id' => (int)$row['area_id'],
        'building' => $row['level2'],
        'spot' => $row['level3'],
    ];
}

$stmt->close();

echo json_encode(['ok' => true, 'message' => 'Area records loaded successfully.', 'data' => $data]);
?>
