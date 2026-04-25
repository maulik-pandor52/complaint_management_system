<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/status_lookup.php");
require_once("../includes/sla_escalation.php");
require_once("../includes/workflow_helper.php");
require_once("../includes/csrf_helper.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$admin_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Status IDs (fallback to existing numeric IDs)
$ID_PENDING   = get_status_id_or($conn, "Pending", 1);
$ID_ASSIGNED  = get_status_id_or($conn, "Assigned", 2);
$ID_VERIFIED  = get_status_id_or($conn, "Verified", 7);
$ID_ESCALATED = get_status_id_or($conn, "Escalated", 8);
$ID_REOPEN_AP = get_status_id_or($conn, "Reopened - Pending Approval", 5);

// Handle Assignment Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign'])) {
    require_csrf_token();
    $staff_id = (int)$_POST['staff_id'];
    
    // Check if already assigned
    if (is_assigned_to_staff($conn, $complaint_id, $staff_id)) {
        set_flash_message('error', 'Already assigned to this staff member.');
    } else {
        // Ensure verified before assignment (Feature #4 + #5)
        $curr_status = get_complaint_status_id($conn, $complaint_id);

        if ($curr_status === $ID_PENDING) {
            set_flash_message('error', 'Please verify the complaint before assignment.');
        } elseif ($curr_status === $ID_REOPEN_AP) {
            set_flash_message('error', 'Reopened complaint requires approval before reassignment.');
        } elseif (!in_array($curr_status, [$ID_VERIFIED, $ID_ESCALATED], true)) {
            set_flash_message('error', 'Complaint is not eligible for assignment.');
        } else {
        $stmt = $conn->prepare("INSERT INTO assignments (complaint_id, staff_id, assigned_by) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iii", $complaint_id, $staff_id, $admin_id);
            $assigned = $stmt->execute();
            $stmt->close();
        } else {
            $assigned = false;
        }

        if ($assigned) {
            update_complaint_status_with_history($conn, $complaint_id, $ID_ASSIGNED, $admin_id, 'Assigned to staff');
            set_flash_message('success', 'Complaint successfully assigned!');
            echo "<script>window.location.href='view_complaint.php?id=$complaint_id';</script>";
            exit;
        } else {
            set_flash_message('error', 'Failed to assign.');
        }
        }
    }
}

// Fetch details
$query = "SELECT c.*, cat.category_name, a.level1, a.level2, a.level3, s.status_name, u.name as user_name 
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN area_master a ON c.area_id = a.area_id
          LEFT JOIN status_master s ON c.status_id = s.status_id
          LEFT JOIN users u ON c.user_id = u.user_id
          WHERE c.complaint_id = ?
          LIMIT 1";
$stmt = $conn->prepare($query);
$complaint = null;
if ($stmt) {
    $stmt->bind_param("i", $complaint_id);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$complaint) {
    echo "<div class='content-area'><p>Complaint not found.</p></div>";
    include("../includes/footer.php");
    exit;
}

$area_str = htmlspecialchars($complaint['level1'] . " - " . $complaint['level2']);
if (!empty($complaint['level3'])) $area_str .= " - " . htmlspecialchars($complaint['level3']);

// Fetch Staff List
$staff_arr = [];
$res_staff = $conn->prepare("SELECT user_id, name FROM users WHERE role_id = 2");
if ($res_staff) {
    $res_staff->execute();
    $staff_result = $res_staff->get_result();
    while ($s = mysqli_fetch_assoc($staff_result)) {
        $staff_arr[] = $s;
    }
    $res_staff->close();
}

// Fetch current assignments
$assigned_staff = [];
$as_res = $conn->prepare("SELECT u.name FROM assignments a JOIN users u ON a.staff_id = u.user_id WHERE a.complaint_id = ?");
if ($as_res) {
    $as_res->bind_param("i", $complaint_id);
    $as_res->execute();
    $assigned_result = $as_res->get_result();
    while ($ar = mysqli_fetch_assoc($assigned_result)) {
        $assigned_staff[] = $ar['name'];
    }
    $as_res->close();
}
?>

<!-- Header & Breadcrumbs -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <h2 class="mb-0">Complaint Details</h2>
            <?= render_status_badge($complaint['status_name']) ?>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="assign_complaint.php">Complaint Queue</a></li>
                <li class="breadcrumb-item active" aria-current="page">#<?= $complaint['complaint_id'] ?></li>
            </ol>
        </nav>
    </div>
    <a href="assign_complaint.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
        <i class="fas fa-arrow-left me-2"></i>Back to Queue
    </a>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Left Column: Primary Information -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-file-lines me-2 text-primary"></i>Information Overview</h5>
            </div>
            <div class="card-body">
                <h3 class="fw-bold mb-4"><?= htmlspecialchars($complaint['title']) ?></h3>
                
                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Problem Category</label>
                        <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($complaint['category_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Priority Level</label>
                        <div class="fw-bold fs-6 text-dark"><?= render_priority_badge($complaint['priority']) ?></div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Service Area / Location</label>
                        <div class="fw-bold fs-6 text-dark"><?= $area_str ?></div>
                    </div>
                    <?php if (!empty($complaint['exact_location'])): ?>
                    <div class="col-12">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Exact Location</label>
                        <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($complaint['exact_location']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-2">Original Narrative</label>
                    <div class="bg-light p-4 rounded-3 text-dark fs-6" style="line-height: 1.8; white-space: pre-line; border: 1px solid #e2e8f0;">
                        <?= htmlspecialchars($complaint['description']) ?>
                    </div>
                </div>

                <?php
                $attachments = [];
                $att_stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ?");
                if ($att_stmt) {
                    $att_stmt->bind_param("i", $complaint_id);
                    $att_stmt->execute();
                    $att_res = $att_stmt->get_result();
                    while ($att_row = mysqli_fetch_assoc($att_res)) {
                        $attachments[] = $att_row;
                    }
                    $att_stmt->close();
                }
                if(!empty($attachments)): ?>
                    <hr class="my-4">
                    <div class="mt-2">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-2">Evidence / Attachments</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach($attachments as $att): 
                                $filename = basename($att['file_path']); ?>
                                <a href="../<?= $att['file_path'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-paperclip me-2"></i><?= $filename ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Meta & Assignment -->
    <div class="col-lg-4">
        <!-- Reporter Info -->
        <div class="card shadow-sm border-0 mb-4 bg-primary-light">
            <div class="card-body">
                <label class="text-muted small fw-bold text-uppercase d-block mb-3">Reported By</label>
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($complaint['user_name']) ?></div>
                        <div class="small text-muted">Submitted <?= date('M d, H:i A', strtotime($complaint['created_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment Control -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Assignment Control</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-2">Assigned Personnel</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php 
                        if (!empty($assigned_staff)):
                            foreach($assigned_staff as $name): ?>
                                <span class="badge bg-white text-primary border border-primary-light rounded-pill px-3 py-2 fs-7 fw-bold shadow-sm">
                                    <i class="fas fa-hard-hat me-1"></i><?= htmlspecialchars($name) ?>
                                </span>
                            <?php endforeach;
                        else: ?>
                            <span class="text-danger fw-bold small"><i class="fas fa-triangle-exclamation me-1"></i>Currently Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="my-4 opacity-10">

                <form method="POST">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Assign New Staff</label>
                        <select name="staff_id" class="form-select border-primary-light" required>
                            <option value=''>Choose member...</option>
                            <?php foreach ($staff_arr as $st): ?>
                                <option value='<?= $st['user_id'] ?>'><?= htmlspecialchars($st['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assign" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-paper-plane me-2"></i>Confirm Assignment
                    </button>
                </form>
            </div>
        </div>

        <?php 
        // User Satisfaction
        if ($complaint['status_id'] >= 5) {
            $feedback_stmt = $conn->prepare("SELECT * FROM feedback WHERE complaint_id = ?");
            $fb = null;
            if ($feedback_stmt) {
                $feedback_stmt->bind_param("i", $complaint_id);
                $feedback_stmt->execute();
                $fb = $feedback_stmt->get_result()->fetch_assoc();
                $feedback_stmt->close();
            }
            if ($fb) {
                ?>
                <div class="card shadow-sm border-0 mb-4 bg-success-light border-0">
                    <div class="card-body">
                        <h6 class="fw-bold text-success mb-3"><i class="fas fa-star me-2"></i>User Satisfaction</h6>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="fs-4 fw-bold text-success"><?= $fb['rating'] ?>.0</span>
                            <div class="text-warning small">
                                <?php for($i=1;$i<=5;$i++) echo "<i class='fa".($i <= $fb['rating'] ? "s" : "r")." fa-star'></i>"; ?>
                            </div>
                        </div>
                        <p class="small text-muted italic mb-0">"<?= htmlspecialchars($fb['comments']) ?>"</p>
                    </div>
                </div>
                <?php
            }
        }
        ?>

        <!-- Lifecycle Tracker (Timeline) -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Lifecycle Tracker</h6>
            </div>
            <div class="card-body p-4">
                <div class="timeline-container px-2">
                    <div class="pb-3 position-relative" style="border-left: 2px dashed #e2e8f0; padding-left: 25px;">
                        <span class="position-absolute translate-middle p-1 bg-primary border border-light rounded-circle" style="left: -1px; top: 0;"></span>
                        <div class="small fw-bold text-dark">Initially Logged</div>
                        <div class="text-muted small" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($complaint['created_at'])) ?></div>
                    </div>

                    <?php
                    $history_rows = [];
                    $history_stmt = $conn->prepare("SELECT h.*, s.status_name, u.name as actor_name FROM complaint_history h LEFT JOIN status_master s ON h.status_id = s.status_id LEFT JOIN users u ON h.updated_by = u.user_id WHERE h.complaint_id = ? ORDER BY h.updated_at ASC");
                    if ($history_stmt) {
                        $history_stmt->bind_param("i", $complaint_id);
                        $history_stmt->execute();
                        $history_res = $history_stmt->get_result();
                        while ($history_row = mysqli_fetch_assoc($history_res)) {
                            $history_rows[] = $history_row;
                        }
                        $history_stmt->close();
                    }
                    $h_count = count($history_rows);
                    $idx = 0;
                    foreach($history_rows as $h): 
                        $idx++;
                        $is_last = ($idx == $h_count);
                        $dot_color = ($h['status_id'] >= 3) ? 'success' : 'primary';
                    ?>
                        <div class="pb-3 position-relative" style="<?= $is_last ? '' : 'border-left: 2px dashed #e2e8f0;' ?> padding-left: 25px;">
                            <span class="position-absolute translate-middle p-1 bg-<?= $dot_color ?> border border-light rounded-circle" style="left: -1px; top: 0;"></span>
                            <div class="small fw-bold text-dark"><?= htmlspecialchars($h['status_name']) ?></div>
                            <div class="text-muted small mb-1" style="font-size: 0.75rem;">By <?= htmlspecialchars($h['actor_name']) ?> &bull; <?= date('M d, H:i', strtotime($h['updated_at'])) ?></div>
                            <?php if(!empty($h['remark'])): ?>
                                <div class="bg-light p-2 rounded small text-muted fs-7"><?= htmlspecialchars($h['remark']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include("../includes/footer.php"); ?>
