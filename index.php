<?php
session_start();

if (!isset($_SESSION['role_id'])) {
    header("Location: auth/login.php");
}

if ($_SESSION['role_id'] == 1) {
    header("Location: admin/dashboard.php");
} elseif ($_SESSION['role_id'] == 2) {
    header("Location: staff/dashboard.php");
} else {
    header("Location: user/dashboard.php");
}
?>