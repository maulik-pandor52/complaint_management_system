<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/sla_escalation.php");

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../auth/login.php");
    exit;
}

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

include("../includes/header.php");

$user_id = $_SESSION['user_id'];

// Get counts
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE user_id='$user_id'"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE user_id='$user_id' AND status_id NOT IN (3, 4, 9)"))['c'];
$resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE user_id='$user_id' AND status_id IN (3, 4, 9)"))['c'];
?>

<!-- User Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">My Dashboard</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </nav>
    </div>
    <a href="register_complaint.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
        <i class="fas fa-plus me-2"></i>Register New Complaint
    </a>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-5">
    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
            <span class="stat-value text-primary"><?= $total ?></span>
            <span class="stat-label">Total Submissions</span>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <span class="stat-value text-warning"><?= $pending ?></span>
            <span class="stat-label">Waiting Resolution</span>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <span class="stat-value text-success"><?= $resolved ?></span>
            <span class="stat-label">Resolved Complaints</span>
        </div>
    </div>
</div>

<!-- Content Row -->
<div class="row g-4">
    <!-- Status Overview Chart -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 py-3">
                <h5 class="mb-0 fw-bold text-gray-800"><i class="fas fa-chart-pie me-2 text-primary"></i>Current Status Overview</h5>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="myPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom-0 py-3">
                <h5 class="mb-0 fw-bold text-gray-800"><i class="fas fa-bolt-lightning me-2 text-primary"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <a href="register_complaint.php" class="btn btn-outline-primary text-start border-0 bg-primary-light p-3 rounded-lg hover-translate">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-pen-to-square fa-2x me-3"></i>
                            <div>
                                <div class="fw-bold fs-5">Lodge Complaint</div>
                                <small class="text-muted">Fill out a new submission form</small>
                            </div>
                        </div>
                    </a>
                    <a href="my_complaints.php" class="btn btn-outline-secondary text-start border-0 bg-light p-3 rounded-lg hover-translate">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock-rotate-left fa-2x me-3 text-secondary"></i>
                            <div>
                                <div class="fw-bold fs-5 text-dark">Track Status</div>
                                <small class="text-muted">View progress of existing requests</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Chart -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById("myPieChart");
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ["Pending", "Resolved"],
            datasets: [{
                data: [<?= $pending ?>, <?= $resolved ?>],
                backgroundColor: ['#f59e0b', '#22c55e'],
                hoverBackgroundColor: ['#d97706', '#16a34a'],
                borderWidth: 5,
                borderColor: '#ffffff'
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, font: { family: 'Outfit', size: 13, weight: '600' } }
                }
            },
            cutout: '75%',
        },
    });
});
</script>

<?php include("../includes/footer.php"); ?>
