<?php
include("../config/db.php");
include("../includes/auth.php");

if ($_SESSION['role_id'] != 2) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$staff_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);
    
    // Update main status
    $up1 = mysqli_query($conn, "UPDATE complaints SET status_id='$new_status' WHERE complaint_id='$complaint_id'");
    
    // Insert into history
    $up2 = mysqli_query($conn, "INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES ('$complaint_id', '$new_status', '$staff_id', '$remark')");

    if ($up1 && $up2) {
        set_flash_message('success', 'Complaint status updated successfully!');
        echo "<script>window.location.href='view_complaint.php?id=$complaint_id';</script>";
        exit;
    } else {
        set_flash_message('error', 'Error updating status.');
    }
}

// Fetch details
$query = "SELECT c.*, cat.category_name, a.level1, a.level2, s.status_name, u.name as user_name 
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
    <a href="my_complaints.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
        <i class="fas fa-list me-2"></i>Back to List
    </a>
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
                        <div class="fw-bold fs-6"><?= htmlspecialchars($complaint['level1'] . " &raquo; " . $complaint['level2']) ?></div>
                    </div>
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
                            $status_res = mysqli_query($conn, "SELECT * FROM status_master");
                            while ($s = mysqli_fetch_assoc($status_res)) {
                                $sel = ($s['status_id'] == $complaint['status_id']) ? "selected" : "";
                                echo "<option value='{$s['status_id']}' $sel>{$s['status_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold text-uppercase text-muted">Internal Remarks / Action Taken</label>
                        <textarea name="remark" rows="6" class="form-control" placeholder="Describe the steps taken to resolve this issue..." required></textarea>
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