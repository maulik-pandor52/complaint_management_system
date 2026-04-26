<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/app_helper.php';

if (!isset($_SESSION['user_id'])) {
    app_redirect('auth/login.php');
}
?>
