<?php
include("../config/db.php");
include("../includes/auth.php");
include("../includes/upload_helper.php");
require_once("../includes/status_lookup.php");
require_once("../includes/workflow_helper.php");
require_once("../includes/sla_escalation.php");

if ($_SESSION['role_id'] != 2) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$staff_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

// Verify assignment
$check_assign = mysqli_query($conn, "SELECT * FROM assignments WHERE complaint_id='$complaint_id' AND staff_id='$staff_id'");
if (mysqli_num_rows($check_assign) == 0) {
    echo "<div class='content-area'><p>Complaint not found or not assigned to you.</p></div>";
    include("../includes/footer.php");
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['status_id'])) {
    $new_status = (int)$_POST['status_id'];
    $remark = trim($_POST['remark'] ?? '');

    // Fetch current status to validate transitions (Feature #3)
    $curr_stmt = $conn->prepare("SELECT status_id FROM complaints WHERE complaint_id = ? LIMIT 1");
    $current_status = null;
    if ($curr_stmt) {
        $curr_stmt->bind_param("i", $complaint_id);
        $curr_stmt->execute();
        $row = $curr_stmt->get_result()->fetch_assoc();
        $current_status = $row ? (int)$row['status_id'] : null;
        $curr_stmt->close();
    }

    // Staff allowed targets (prevents invalid transitions)
    $allowed_targets = $current_status !== null ? allowed_staff_status_targets($conn, $current_status) : [];
    if ($current_status === null) {
        set_flash_message('error', 'Complaint not found.');
    } elseif (!in_array($new_status, $allowed_targets, true)) {
        set_flash_message('error', 'Invalid status transition. Please follow the workflow.');
    } elseif ($remark === '') {
        set_flash_message('error', 'Remark is required.');
    } else {
        // Update complaint status (prepared statement)
        $up1 = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ?");
        $ok1 = false;
        if ($up1) {
            $up1->bind_param("ii", $new_status, $complaint_id);
            $ok1 = $up1->execute();
            $up1->close();
        }

        // Insert history (prepared statement)
        $up2 = $conn->prepare("INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES (?, ?, ?, ?)");
        $ok2 = false;
        if ($up2) {
            $up2->bind_param("iiis", $complaint_id, $new_status, $staff_id, $remark);
            $ok2 = $up2->execute();
            $up2->close();
        }

        // Action proof upload during resolution (Feature #6)
        $ID_RESOLVED = get_status_id_or($conn, "Resolved", 3);
        if ($ok1 && $ok2 && $new_status === $ID_RESOLVED && !empty($_FILES['action_proof']['name'])) {
            $upload = uploadFile($_FILES['action_proof']);
            if ($upload['status']) {
                $path = $upload['path'];

                // Try to insert with attachment_type (new schema). If it fails, fallback to old insert.
                $stmt_att = $conn->prepare("INSERT INTO complaint_attachments (complaint_id, file_path, attachment_type, uploaded_by) VALUES (?, ?, 'action_proof', ?)");
                if ($stmt_att) {
                    $stmt_att->bind_param("isi", $complaint_id, $path, $staff_id);
                    $stmt_att->execute();
                    $stmt_att->close();
                } else {
                    // Old schema fallback
                    $stmt_old = $conn->prepare("INSERT INTO complaint_attachments (complaint_id, file_path) VALUES (?, ?)");
                    if ($stmt_old) {
                        $stmt_old->bind_param("is", $complaint_id, $path);
                        $stmt_old->execute();
                        $stmt_old->close();
                    }
                }
            } else {
                // Status update was successful, but upload failed; show a warning message.
                set_flash_message('error', 'Status updated, but action proof upload failed: ' . htmlspecialchars($upload['msg'] ?? 'Upload error'));
                echo "<script>window.location.href='view_complaint.php?id=$complaint_id';</script>";
                exit;
            }
        }

        if ($ok1 && $ok2) {
            set_flash_message('success', 'Complaint status updated successfully!');
            echo "<script>window.location.href='view_complaint.php?id=$complaint_id';</script>";
            exit;
        } else {
            set_flash_message('error', 'Error updating status.');
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
          WHERE c.complaint_id = '$complaint_id'";

$result = mysqli_query($conn, $query);
$complaint = mysqli_fetch_assoc($result);

?>

<!-- Header & Breadcrumbs -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <h2 class="mb-0">Process Complaint #<?= $complaint['complaint_id'] ?></h2>
            <?= render_status_badge($complaint['status_name']) ?>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="my_complaints.php">Assigned Queue</a></li>
                <li class="breadcrumb-item active" aria-current="page">Action Log</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <?php if ($complaint['status_id'] <= 2 || $complaint['status_id'] == 6): ?>
        <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm border-0 fw-bold" onclick="document.getElementById('remark_field').focus()">
            <i class="fas fa-person-running me-2"></i>Give Progress
        </button>
        <?php endif; ?>
        <a href="my_complaints.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
            <i class="fas fa-list me-2"></i>Back to List
        </a>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Left Column: Complaint Details -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 mb-4 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-circle-info me-2 text-primary"></i>Reporter's Information</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-primary-light rounded-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-primary text-uppercase">Citizen / Employee</div>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($complaint['user_name']) ?></div>
                    </div>
                </div>

                <h4 class="fw-bold mb-3"><?= htmlspecialchars($complaint['title']) ?></h4>
                
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase d-block">Category</label>
                        <div class="fw-bold fs-6"><?= htmlspecialchars($complaint['category_name']) ?></div>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small fw-bold text-uppercase d-block">Priority</label>
                        <div class="fw-bold fs-6">
                            <?= render_priority_badge($complaint['priority']) ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small fw-bold text-uppercase d-block">Service Area</label>
                        <div class="fw-bold fs-6">
                            <?= htmlspecialchars($complaint['level1'] . " » " . $complaint['level2'] . (!empty($complaint['level3']) ? " » " . $complaint['level3'] : "")) ?>
                        </div>
                    </div>
                    <?php if (!empty($complaint['exact_location'])): ?>
                    <div class="col-12">
                        <label class="text-muted small fw-bold text-uppercase d-block">Exact Location</label>
                        <div class="fw-bold fs-6"><?= htmlspecialchars($complaint['exact_location']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <label class="text-muted small fw-bold text-uppercase d-block mb-2">Original Narrative</label>
                <div class="bg-light p-3 rounded text-dark mb-4 fs-7" style="line-height: 1.6; border: 1px solid #e2e8f0; white-space: pre-line;">
                    <?= htmlspecialchars($complaint['description']) ?>
                </div>

                <?php
                $att_res = mysqli_query($conn, "SELECT * FROM complaint_attachments WHERE complaint_id='$complaint_id'");
                if(mysqli_num_rows($att_res) > 0): ?>
                    <label class="text-muted small fw-bold text-uppercase d-block mb-2">Evidence / Attachments</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php while($att = mysqli_fetch_assoc($att_res)): 
                            $filename = basename($att['file_path']); ?>
                            <a href="../<?= $att['file_path'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="fas fa-paperclip me-2"></i><?= $filename ?>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Update Action -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-4 bg-light">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-pen-to-square me-2 text-primary"></i>Deployment Log</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Update Progress Status</label>
                        <select name="status_id" class="form-control" required>
                            <?php
                            // Only allow workflow-legal staff options (Feature #3)
                            $allowed_ids = allowed_staff_status_targets($conn, (int)$complaint['status_id']);
                            if (empty($allowed_ids)) {
                                echo "<option value='".(int)$complaint['status_id']."' selected>".htmlspecialchars($complaint['status_name'])."</option>";
                            } else {
                                $placeholders = implode(',', array_fill(0, count($allowed_ids), '?'));
                                $types = str_repeat('i', count($allowed_ids));

                                // Build dynamic prepared statement safely
                                $sql = "SELECT status_id, status_name FROM status_master WHERE status_id IN ($placeholders) ORDER BY status_id ASC";
                                $stmt = $conn->prepare($sql);
                                if ($stmt) {
                                    // bind_param needs references
                                    $params = [$types];
                                    foreach ($allowed_ids as $id) $params[] = $id;
                                    $refs = [];
                                    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
                                    call_user_func_array([$stmt, 'bind_param'], $refs);

                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    while ($s = $res->fetch_assoc()) {
                                        $sid = (int)$s['status_id'];
                                        // Default to In Progress (10) if current is Assigned (2)
                                        $is_default_target = ($sid == 10 && $complaint['status_id'] == 2);
                                        $sel = ($sid == (int)$complaint['status_id'] || $is_default_target) ? "selected" : "";
                                        echo "<option value='{$sid}' {$sel}>".htmlspecialchars($s['status_name'])."</option>";
                                    }
                                    $stmt->close();
                                } else {
                                    // Fallback (should rarely happen)
                                    foreach ($allowed_ids as $sid) {
                                        $sel = ($sid == (int)$complaint['status_id']) ? "selected" : "";
                                        echo "<option value='{$sid}' {$sel}>Status #{$sid}</option>";
                                    }
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Action Proof Upload (Feature #6) -->
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Action Proof (Optional)</label>
                        <input type="file" name="action_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="form-text small opacity-75 mt-2">Upload proof of the resolution (image/PDF). Max 2MB.</div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Internal Remarks / Action Taken</label>
                        <textarea name="remark" id="remark_field" rows="6" class="form-control" placeholder="Describe the steps taken to resolve this issue..." required></textarea>
                        <div class="form-text small opacity-75 mt-2">This log will be visible to the Admin and the User.</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-save me-2"></i>Commit Changes
                    </button>
                </form>
            </div>
        </div>

        <?php 
        if ($complaint['status_id'] >= 5) {
            $fb_res = mysqli_query($conn, "SELECT * FROM feedback WHERE complaint_id='$complaint_id'");
            if (mysqli_num_rows($fb_res) > 0) {
                $fb = mysqli_fetch_assoc($fb_res);
                ?>
                <div class="card shadow-sm border-0 bg-success-light mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold text-success mb-3"><i class="fas fa-star me-2"></i>User Satisfaction Feedback</h6>
                        <div class="d-flex align-items-baseline gap-2 mb-2">
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
                    $history_res = mysqli_query($conn, "SELECT h.*, s.status_name, u.name as actor_name FROM complaint_history h LEFT JOIN status_master s ON h.status_id = s.status_id LEFT JOIN users u ON h.updated_by = u.user_id WHERE h.complaint_id = '$complaint_id' ORDER BY h.updated_at ASC");
                    $h_count = mysqli_num_rows($history_res);
                    $idx = 0;
                    while($h = mysqli_fetch_assoc($history_res)): 
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
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>
