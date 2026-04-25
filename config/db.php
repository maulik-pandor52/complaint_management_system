<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "complaint_database";

$conn = @mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Database connection is temporarily unavailable. Please try again later.');
}

mysqli_set_charset($conn, 'utf8mb4');
?>
