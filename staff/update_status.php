<?php
include("../config/db.php");
include("../includes/auth.php");

$complaint_id = $_POST['complaint_id'];
$status = $_POST['status'];
$remark = $_POST['remark'];
$user_id = $_SESSION['user_id'];

// Update status
mysqli_query($conn, "UPDATE complaints SET status_id='$status' 
WHERE complaint_id='$complaint_id'");

// Insert history
mysqli_query($conn, "INSERT INTO complaint_history 
(complaint_id, status_id, updated_by, remark)
VALUES ('$complaint_id','$status','$user_id','$remark')");

echo "Status Updated!";
?>