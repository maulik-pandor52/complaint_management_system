<?php
include("../config/db.php");

$level1 = $_GET['level1'];

$result = mysqli_query($conn, "SELECT * FROM area_master WHERE level1='$level1'");

while ($row = mysqli_fetch_assoc($result)) {
    echo "<option value='{$row['area_id']}'>{$row['level2']}</option>";
}
?>