<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/workflow_helper.php");

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$complaint_id = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
$remark = trim($_POST['remark'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($complaint_id <= 0 || $status <= 0 || $remark === '') {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

// Must be assigned to this staff
$chk = $conn->prepare("SELECT 1 FROM assignments WHERE complaint_id = ? AND staff_id = ? LIMIT 1");
if ($chk) {
    $chk->bind_param("ii", $complaint_id, $user_id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$row) {
        http_response_code(403);
        echo "Not assigned";
        exit;
    }
}

// Validate transition
$curr = $conn->prepare("SELECT status_id FROM complaints WHERE complaint_id = ? LIMIT 1");
$current_status = null;
if ($curr) {
    $curr->bind_param("i", $complaint_id);
    $curr->execute();
    $row = $curr->get_result()->fetch_assoc();
    $current_status = $row ? (int)$row['status_id'] : null;
    $curr->close();
}
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

// Update status
$up = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
if ($up) {
    $up->bind_param("ii", $status, $complaint_id);
    $up->execute();
    $up->close();
}

// Insert history
$hist = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, ?, ?)");
if ($hist) {
    $hist->bind_param("iiis", $complaint_id, $status, $user_id, $remark);
    $hist->execute();
    $hist->close();
}

echo "Status Updated!";
?>
