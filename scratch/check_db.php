<?php
include("config/db.php");
$result = mysqli_query($conn, "SELECT * FROM status_master");
echo "STATUS MASTER:\n";
while($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}

$result = mysqli_query($conn, "SELECT complaint_id, status_id FROM complaints ORDER BY complaint_id DESC LIMIT 10");
echo "\nCOMPLAINTS:\n";
while($row = mysqli_fetch_assoc($result)) {
    print_r($row);
}
?>
