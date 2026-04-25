<?php
/**
 * Complaint Tracking JSON API (Feature #1 - second JSON API)
 * Returns complaint details + timeline (complaint_history) + attachments.
 */

include("../config/db.php");
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$role_id = (int)$_SESSION['role_id'];
$user_id = (int)$_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'id is required']);
    exit;
}

// Authorization check
if ($role_id === 2) {
    $chk = $conn->prepare("SELECT 1 FROM assignments WHERE complaint_id = ? AND staff_id = ? LIMIT 1");
    if (!$chk) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'DB error']);
        exit;
    }
    $chk->bind_param("ii", $complaint_id, $user_id);
    $chk->execute();
    $allowed = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Not assigned']);
        exit;
    }
} elseif ($role_id === 3) {
    $chk = $conn->prepare("SELECT 1 FROM complaints WHERE complaint_id = ? AND user_id = ? LIMIT 1");
    if (!$chk) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'DB error']);
        exit;
    }
    $chk->bind_param("ii", $complaint_id, $user_id);
    $chk->execute();
    $allowed = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Forbidden']);
        exit;
    }
}
// Admin: allowed

// Complaint details
$sql = "
    SELECT c.*, cat.category_name, a.level1, a.level2, a.level3, s.status_name
    FROM complaints c
    LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
    LEFT JOIN area_master a ON c.area_id = a.area_id
    LEFT JOIN status_master s ON c.status_id = s.status_id
    WHERE c.complaint_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error']);
    exit;
}
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$complaint) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Not found']);
    exit;
}

// Attachments
$att_stmt = $conn->prepare("SELECT attachment_id, file_path, attachment_type, uploaded_at FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC");
$attachments = [];
if ($att_stmt) {
    $att_stmt->bind_param("i", $complaint_id);
    $att_stmt->execute();
    $res = $att_stmt->get_result();
    while ($r = $res->fetch_assoc()) $attachments[] = $r;
    $att_stmt->close();
} else {
    // Old schema fallback
    $att_old = $conn->prepare("SELECT file_path FROM complaint_attachments WHERE complaint_id = ? ORDER BY attachment_id ASC");
    if ($att_old) {
        $att_old->bind_param("i", $complaint_id);
        $att_old->execute();
        $res = $att_old->get_result();
        while ($r = $res->fetch_assoc()) $attachments[] = ['file_path' => $r['file_path']];
        $att_old->close();
    }
}

// History (timeline)
$hist_stmt = $conn->prepare("
    SELECT h.history_id, h.status_id, s.status_name, h.remark, h.updated_at
    FROM complaint_history h
    LEFT JOIN status_master s ON h.status_id = s.status_id
    WHERE h.complaint_id = ?
    ORDER BY h.updated_at ASC
");
$history = [];
if ($hist_stmt) {
    $hist_stmt->bind_param("i", $complaint_id);
    $hist_stmt->execute();
    $res = $hist_stmt->get_result();
    while ($r = $res->fetch_assoc()) $history[] = $r;
    $hist_stmt->close();
}

echo json_encode([
    'ok' => true,
    'complaint' => $complaint,
    'attachments' => $attachments,
    'history' => $history
]);

