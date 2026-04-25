<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/sla_escalation.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

// Stats
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints"))['c'];
$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE status_id IN (1, 2, 5, 6 , 7, 10)"))['c'];
$resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM complaints WHERE status_id IN (3, 4, 8, 9)"))['c'];

include("../includes/header.php");  
?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">Admin Dashboard</h2>
        <p class="text-muted mb-0">Overview of system status and quick actions</p>
    </div>
    <div class="d-flex gap-2">
        <a href="reports.php" class="btn btn-primary shadow-sm rounded-pill px-4">
            <i class="fas fa-file-chart-column me-2"></i>View Reports
        </a>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="card shadow-sm border-0 mb-4 p-3 bg-light">
    <div class="d-flex flex-wrap gap-2">
        <a href="assign_complaint.php" class="btn btn-white border shadow-sm px-4">
            <i class="fas fa-tasks me-2 text-primary"></i>Assign Complaints
        </a>
        <a href="manage_users.php" class="btn btn-white border shadow-sm px-4">
            <i class="fas fa-users-gear me-2 text-primary"></i>Manage Users
        </a>
        <a href="manage_categories.php" class="btn btn-white border shadow-sm px-4">
            <i class="fas fa-tags me-2 text-primary"></i>Categories
        </a>
        <a href="manage_areas.php" class="btn btn-white border shadow-sm px-4">
            <i class="fas fa-map-location-dot me-2 text-primary"></i>Areas
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            <span class="stat-value text-primary"><?= htmlspecialchars($total) ?></span>
            <span class="stat-label">Total Submissions</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock-rotate-left"></i></div>
            <span class="stat-value text-warning"><?= htmlspecialchars($pending) ?></span>
            <span class="stat-label">Pending Review</span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
            <span class="stat-value text-success"><?= htmlspecialchars($resolved) ?></span>
            <span class="stat-label">Successfully Resolved</span>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 overflow-hidden">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="fas fa-chart-simple me-2 text-primary"></i>Complaint Statistics</h5>
    </div>
    <div class="card-body">
        <div style="height: 350px; max-width: 800px; margin: 0 auto;">
            <canvas id="chart"></canvas>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let ctx = document.getElementById('chart').getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Complaints', 'Pending Review', 'Resolved'],
                datasets: [{
                    label: 'Complaints',
                    data: [<?= $total ?>, <?= $pending ?>, <?= $resolved ?>],
                    backgroundColor: [
                        'rgba(79, 70, 229, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(16, 185, 129, 0.7)'
                    ],
                    borderColor: [
                        'rgba(79, 70, 229, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(16, 185, 129, 1)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    barThickness: 60
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5], color: '#e2e8f0' },
                        ticks: { font: { family: 'Outfit', size: 12 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit', weight: '600', size: 13 } }
                    }
                }
            }
        });
    });
</script>

<?php include("../includes/footer.php"); ?>
