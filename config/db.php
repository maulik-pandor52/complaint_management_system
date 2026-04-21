<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "complaint_database";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>