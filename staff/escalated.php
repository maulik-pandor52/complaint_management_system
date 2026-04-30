<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/status_lookup.php");
require_once("../includes/sla_escalation.php");
require_once("../includes/assignment_helper.php");

if ($_SESSION['role_id'] != 2) {
    header("Location: ../auth/login.php");
    exit();
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");
include_once("../includes/status_helper.php");

run_sla_escalation($conn);
ensure_assignment_active_schema($conn);

$staff_id = (int)$_SESSION['user_id'];
$ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);

$sql = "
    SELECT c.complaint_id, c.title, c.created_at, c.resolution_sla_due, s.status_name
    FROM complaints c
    JOIN assignments a ON c.complaint_id = a.complaint_id
    LEFT JOIN status_master s ON c.status_id = s.status_id
    WHERE a.staff_id = ? AND a.is_active = 1 AND c.status_id = ?
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($sql);
$rows = [];
if ($stmt) {
    $stmt->bind_param("ii", $staff_id, $ID_ESCALATED);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Escalated Tasks</h2>
        <p class="text-muted mb-0">High priority tasks that breached SLA</p>
    </div>
    <a href="dashboard.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
        <i class="fas fa-arrow-left me-2"></i>Back
    </a>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold text-danger"><i class="fas fa-fire-flame-curved me-2"></i>Escalated Queue</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-container mb-0 border-0 shadow-none">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Title</th>
                        <th>Resolution SLA</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-circle-check fa-3x mb-3 d-block opacity-20"></i>
                                No escalated tasks assigned to you.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= (int)$r['complaint_id'] ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="small text-muted"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                                </td>
                                <td class="small text-danger fw-bold">
                                    <?= !empty($r['resolution_sla_due']) ? date('M d, H:i', strtotime($r['resolution_sla_due'])) : '---' ?>
                                </td>
                                <td><?= render_status_badge($r['status_name'] ?? 'Escalated', true) ?></td>
                                <td class="text-end pe-4">
                                    <a class="btn btn-primary btn-sm rounded-pill px-3 fw-bold" href="view_complaint.php?id=<?= (int)$r['complaint_id'] ?>">
                                        <i class="fas fa-hammer me-1"></i>Work
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
