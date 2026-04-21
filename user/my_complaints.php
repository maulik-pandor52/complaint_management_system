<?php
include("../config/db.php");
include("../includes/auth.php");

if ($_SESSION['role_id'] != 3 && !isset($_SESSION['role_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");

$user_id = $_SESSION['user_id'];
$result = mysqli_query($conn, "SELECT c.*, s.status_name FROM complaints c LEFT JOIN status_master s ON c.status_id = s.status_id WHERE c.user_id='$user_id' ORDER BY c.created_at DESC");
?>

<!-- Complaint History Header -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Complaint History</h2>
        <p class="text-muted mb-0">List of all your submitted complaints and their current status</p>
    </div>
    <a href="register_complaint.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
        <i class="fas fa-plus me-2"></i>New Submission
    </a>
</div>

<div class="row g-4">
    <?php
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="card shadow-sm border-0 h-100 hover-translate">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="text-muted fw-bold small">#<?= $row['complaint_id'] ?></span>
                            <?= render_status_badge($row['status_name']) ?>
                        </div>
                        <h5 class="card-title fw-bold mb-2"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="text-muted small mb-4">
                            <i class="fas fa-clock-rotate-left me-2 text-primary"></i>
                            Reported on <?= date('M d, Y', strtotime($row['created_at'])) ?>
                        </p>
                        <div class="d-grid">
                            <a href="view_complaint.php?id=<?= $row['complaint_id'] ?>" class="btn btn-light rounded-pill fw-bold">
                                View Details <i class="fas fa-arrow-right ms-2 small"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="col-12 text-center py-5">
            <div class="card border-0 shadow-sm bg-light p-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3 opacity-20"></i>
                <h4 class="fw-bold">No Records Found</h4>
                <p class="text-muted">You haven't submitted any complaints yet.</p>
                <div class="mt-3">
                    <a href="register_complaint.php" class="btn btn-primary rounded-pill px-4">Lodge Your First Complaint</a>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
</div>

<?php include("../includes/footer.php"); ?>