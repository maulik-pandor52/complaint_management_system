<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/sla_escalation.php");

// Only Staff can access
if ($_SESSION['role_id'] != 2) {
    header("Location: ../auth/login.php");
    exit();
}

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$staff_id = $_SESSION['user_id'];

// Get counts
$total_assigned = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM assignments a JOIN complaints c ON a.complaint_id = c.complaint_id WHERE a.staff_id='$staff_id' AND c.status_id <> 9"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints c JOIN assignments a ON c.complaint_id = a.complaint_id WHERE a.staff_id='$staff_id' AND c.status_id NOT IN (3, 4, 9)"))['c'];

?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Staff Dashboard</h2>
        <p class="text-muted mb-0">Manage your assigned complaints and track SLAs</p>
    </div>
</div>

<?php display_flash_message(); ?>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
            <span class="stat-value text-primary"><?= htmlspecialchars($total_assigned) ?></span>
            <span class="stat-label">Total Assignments</span>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-fire-flame-curved"></i></div>
            <span class="stat-value text-danger"><?= htmlspecialchars($pending) ?></span>
            <span class="stat-label">Action Required</span>
        </div>
    </div>
</div>

<!-- Assignments Section -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-bold"><i class="fas fa-list-check me-2 text-primary"></i>Assigned Complaints</h5>
        <span class="badge bg-primary-light text-primary rounded-pill"><?= $total_assigned ?> Total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-container mb-0 border-0 shadow-none">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Title / Description</th>
                        <th>Status</th>
                        <th>SLA Deadline</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = mysqli_query($conn, "SELECT c.*, a.assigned_at, s.status_name FROM complaints c JOIN assignments a ON c.complaint_id = a.complaint_id LEFT JOIN status_master s ON c.status_id = s.status_id WHERE a.staff_id='$staff_id' AND c.status_id <> 9 ORDER BY a.assigned_at DESC");
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $due_date = !empty($row['resolution_sla_due']) ? date('M d, H:i', strtotime($row['resolution_sla_due'])) : "---";
                            $is_late = (!empty($row['resolution_sla_due']) && strtotime($row['resolution_sla_due']) < time() && $row['status_id'] < 3);
                            $badge_class = "badge-" . strtolower(str_replace(' ', '-', $row['status_name']));

                            echo "<tr>
                                    <td class='ps-4 fw-bold text-muted'>#{$row['complaint_id']}</td>
                                    <td>
                                        <div class='fw-semibold'>" . htmlspecialchars($row['title']) . "</div>
                                        <small class='text-muted'>Assigned: " . date('M d', strtotime($row['assigned_at'])) . "</small>
                                    </td>
                                    <td>" . render_status_badge($row['status_name'], $is_late) . "</td>
                                    <td>
                                        <div class='" . ($is_late ? "text-danger" : "text-dark") . " fw-medium'>
                                            <i class='far fa-clock me-1'></i>{$due_date}
                                        </div>
                                        
                                    </td>
                                    <td class='text-end pe-4'>
                                        <a href='view_complaint.php?id={$row['complaint_id']}' class='btn btn-light btn-sm rounded-pill px-3 fw-bold'>
                                            <i class='fas fa-eye me-1'></i>View
                                        </a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>
                                <i class='fas fa-folder-open fa-3x mb-3 d-block opacity-20'></i>
                                No assignments found yet.
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>