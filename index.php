<?php
session_start();
require_once __DIR__ . '/includes/app_helper.php';

if (!isset($_SESSION['role_id'])) {
    app_redirect('auth/login.php');
}

if ($_SESSION['role_id'] == 1) {
    app_redirect('admin/dashboard.php');
} elseif ($_SESSION['role_id'] == 2) {
    app_redirect('staff/dashboard.php');
} else {
    app_redirect('user/dashboard.php');
}
?>
