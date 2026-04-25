<?php
include("../config/db.php");

$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$area = isset($_GET['area']) ? (int)$_GET['area'] : 0;

if ($category <= 0 || $area <= 0) {
    echo "No duplicate.";
    exit;
}

$query = "SELECT complaint_id FROM complaints
          WHERE category_id = ? AND area_id = ?
            AND created_at >= NOW() - INTERVAL 7 DAY
          LIMIT 1";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo "No duplicate.";
    exit;
}
$stmt->bind_param("ii", $category, $area);
$stmt->execute();
$result = $stmt->get_result();

if (mysqli_num_rows($result) > 0) {
    echo "Duplicate complaint found!";
} else {
    echo "No duplicate.";
}
$stmt->close();
?>
