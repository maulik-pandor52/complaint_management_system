<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/workflow_helper.php");
require_once("../includes/csrf_helper.php");

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$complaint_id = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
$remark = trim($_POST['remark'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo "Invalid request token";
    exit;
}

if ($complaint_id <= 0 || $status <= 0 || $remark === '') {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

// Must be assigned to this staff
if (!is_assigned_to_staff($conn, $complaint_id, $user_id)) {
    http_response_code(403);
    echo "Not assigned";
    exit;
}

// Validate transition
$current_status = get_complaint_status_id($conn, $complaint_id);
if ($current_status === null) {
    http_response_code(404);
    echo "Not found";
    exit;
}

$allowed = allowed_staff_status_targets($conn, $current_status);
if (!in_array($status, $allowed, true)) {
    http_response_code(422);
    echo "Invalid transition";
    exit;
}

if (!update_complaint_status_with_history($conn, $complaint_id, $status, $user_id, $remark)) {
    http_response_code(500);
    echo "Unable to update status";
    exit;
}

echo "Status Updated!";
?>
