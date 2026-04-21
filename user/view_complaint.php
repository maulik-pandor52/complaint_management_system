<?php
include("../config/db.php");
include("../includes/auth.php");

if ($_SESSION['role_id'] != 3 && !isset($_SESSION['role_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$user_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch complaint with joins
$query = "SELECT c.*, cat.category_name, a.level1, a.level2, s.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
          LEFT JOIN area_master a ON c.area_id = a.area_id
          LEFT JOIN status_master s ON c.status_id = s.status_id
          WHERE c.complaint_id = '$complaint_id' AND c.user_id = '$user_id'";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    echo "<div class='content-area'><p>Complaint not found or you don't have permission to view it.</p></div>";
    include("../includes/footer.php");
    exit;
}

$complaint = mysqli_fetch_assoc($result);

// Feedback submission logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['rating'])) {
    $rating = (int)$_POST['rating'];
    $comments = mysqli_real_escape_string($conn, $_POST['comments']);
    
    // Check if feedback already provided
    $check_fb = mysqli_query($conn, "SELECT * FROM feedback WHERE complaint_id = '$complaint_id'");
    if (mysqli_num_rows($check_fb) == 0) {
        $fb_query = "INSERT INTO feedback (complaint_id, rating, comments) VALUES ('$complaint_id', '$rating', '$comments')";
        if(mysqli_query($conn, $fb_query)) {
            set_flash_message('success', 'Thank you for your feedback!');
            // Reload page to avoid resubmission
            echo "<script>window.location.href = 'view_complaint.php?id=$complaint_id';</script>";
            exit;
        } else {
            set_flash_message('error', 'Failed to submit feedback.');
        }
    }
}

// Reopen Submission Logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reopen'])) {
    $reason = mysqli_real_escape_string($conn, $_POST['reopen_reason']);
    $up1 = mysqli_query($conn, "UPDATE complaints SET status_id=5 WHERE complaint_id='$complaint_id'");
    // Here we can also reset the SLA or modify it if needed, but the rule didn't explicitly override math SLA so we leave SLAs intact.
    $up2 = mysqli_query($conn, "INSERT INTO complaint_history (complaint_id, status_id, updated_by, remark) VALUES ('$complaint_id', 5, '$user_id', '$reason')");
    if($up1 && $up2) {
        set_flash_message('success', 'Complaint Reopened. It now requires Supervisor Approval (Rule U-38).');
        echo "<script>window.location.href = 'view_complaint.php?id=$complaint_id';</script>";
        exit;
    } else {
        set_flash_message('error', 'Failed to reopen complaint.');
    }
}
?>

<?php display_flash_message(); ?>

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
                <li class="breadcrumb-item"><a href="my_complaints.php">My Complaints</a></li>
                <li class="breadcrumb-item active" aria-current="page">#<?= $complaint['complaint_id'] ?></li>
            </ol>
        </nav>
    </div>
    <a href="my_complaints.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
        <i class="fas fa-arrow-left me-2"></i>Back to List
    </a>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Left Column: Details & Description -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-file-lines me-2 text-primary"></i>Information Overview</h5>
            </div>
            <div class="card-body">
                <h3 class="fw-bold mb-4"><?= htmlspecialchars($complaint['title']) ?></h3>
                
                <div class="row g-4 mb-5">
                    <div class="col-md-4">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Category</label>
                        <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($complaint['category_name']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Priority</label>
                        <div class="fw-bold fs-6 text-dark">
                            <?= render_priority_badge($complaint['priority']) ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Location</label>
                        <div class="fw-bold fs-6 text-dark"><?= htmlspecialchars($complaint['level1'] . " - " . $complaint['level2']) ?></div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="text-muted small fw-bold text-uppercase d-block mb-2">Detailed Description</label>
                    <div class="bg-light p-4 rounded-3 text-dark fs-6" style="line-height: 1.8; white-space: pre-line; border: 1px solid #e2e8f0;">
                        <?= htmlspecialchars($complaint['description']) ?>
                    </div>
                </div>

                <?php
                $att_res = mysqli_query($conn, "SELECT * FROM complaint_attachments WHERE complaint_id='$complaint_id'");
                if(mysqli_num_rows($att_res) > 0) {
                    ?>
                    <hr class="my-4">
                    <div class="mt-2">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-2">Attached Evidence</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php while($att = mysqli_fetch_assoc($att_res)): 
                                $filename = basename($att['file_path']); ?>
                                <a href="../<?= $att['file_path'] ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                    <i class="fas fa-paperclip me-2"></i><?= $filename ?>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <?php if ($complaint['status_id'] == 3 || $complaint['status_id'] == 4): ?>
            <!-- Interaction Sections (Feedback/Reopen) -->
            <div class="row g-4">
                <div class="col-md-12">
                    <div class="card shadow-sm border-0 bg-primary-light">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-primary mb-3"><i class="fas fa-star me-2"></i>Service Feedback</h5>
                            <?php
                            $check_fb = mysqli_query($conn, "SELECT * FROM feedback WHERE complaint_id = '$complaint_id'");
                            if (mysqli_num_rows($check_fb) == 0): ?>
                                <form method="POST">
                                    <p class="small text-dark opacity-75 mb-3">This complaint is resolved. How was your experience?</p>
                                    <div class="mb-3">
                                        <select name="rating" class="form-control" required>
                                            <option value="5">5 - Exceptional</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="3">3 - Satisfactory</option>
                                            <option value="2">2 - Needs Improvement</option>
                                            <option value="1">1 - Poor</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <textarea name="comments" rows="3" class="form-control" placeholder="Optional comments..." required></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold w-100">Submit Rating</button>
                                </form>
                            <?php else: 
                                $fb = mysqli_fetch_assoc($check_fb); ?>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div class="fs-4 fw-bold text-primary"><?= $fb['rating'] ?>.0</div>
                                    <div class="text-warning">
                                        <?php for($i=1;$i<=5;$i++) echo "<i class='fa".($i <= $fb['rating'] ? "s":"r")." fa-star fs-6'></i>"; ?>
                                    </div>
                                </div>
                                <p class="mb-0 text-muted small italic">"<?= htmlspecialchars($fb['comments']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Timeline & Side Actions -->
    <div class="col-lg-4">
        <!-- Reopen Logic -->
        <?php if ($complaint['status_id'] == 3 || $complaint['status_id'] == 4): ?>
            <div class="card shadow-sm border-0 bg-danger-light mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-danger mb-2"><i class="fas fa-lock-open me-2"></i>Unsatisfied?</h6>
                    <p class="small text-danger opacity-75 mb-3">You can request to reopen this case if the resolution was incomplete.</p>
                    <form method="POST">
                        <textarea name="reopen_reason" rows="2" class="form-control mb-3 border-danger bg-white" placeholder="Reason for reopening..." required></textarea>
                        <div class="alert alert-info py-2 small mb-3 border-0">
                            <i class="fas fa-user-shield me-2"></i>Note: Case will require <strong>Supervisor Approval</strong>.
                        </div>
                        <button type="submit" name="reopen" class="btn btn-danger btn-sm rounded-pill w-100 fw-bold py-2">Request Reopen</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

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