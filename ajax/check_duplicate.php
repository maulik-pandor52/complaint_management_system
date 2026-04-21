<?php
include("../config/db.php");

$category = $_GET['category'];
$area = $_GET['area'];

$query = "SELECT * FROM complaints 
WHERE category_id='$category' AND area_id='$area' 
AND created_at >= NOW() - INTERVAL 7 DAY";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    echo "Duplicate complaint found!";
} else {
    echo "No duplicate.";
}
?>