<?php
include("../config/db.php");
include("../includes/auth.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");

// Complex queries for Reports & SLAs
// 1. Complaints by Category
$cat_data = [];
$cat_res = mysqli_query($conn, "SELECT cat.category_name, COUNT(c.complaint_id) as total FROM complaints c JOIN complaint_categories cat ON c.category_id = cat.category_id GROUP BY cat.category_id");
while($r = mysqli_fetch_assoc($cat_res)) { $cat_data[] = $r; }

// 2. Complaints SLA Status (assuming 30 hours is Resolution SLA)
$sla_passed = 0;
$sla_within = 0;
$sla_res = mysqli_query($conn, "SELECT created_at, status_id FROM complaints");
while($r = mysqli_fetch_assoc($sla_res)) {  
    if ($r['status_id'] < 5) { // Unresolved
        $hours_passed = (time() - strtotime($r['created_at'])) / 3600;
        if ($hours_passed > 30) { $sla_passed++; } else { $sla_within++; }
    }
}
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Reports & Analytics</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Diagnostics Report</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-white border rounded-pill shadow-sm px-3">
            <i class="fas fa-print me-2 text-primary"></i>Print Report
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Category Distribution Chart -->
    <div class="col-xl-7 col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-chart-column me-2 text-primary"></i>Performance Analysis: Avg. Resolution Time</h5>
            </div>
            <div class="card-body">
                <div style="height: 380px; max-width: 800px; margin: 0 auto;">
                    <canvas id="catChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- SLA Breach Report -->
    <div class="col-xl-5 col-lg-6">
        <div class="card shadow-sm border-0 h-100 bg-light">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-danger"><i class="fas fa-gauge-high me-2"></i>SLA Health Diagnostics</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-around align-items-center text-center py-4 mb-4">
                    <div>
                        <div class="display-4 fw-bold text-success mb-0"><?= $sla_within ?></div>
                        <div class="text-muted small fw-bold text-uppercase">Within Limit</div>
                    </div>
                    <div style="width: 1px; height: 60px; background: #cbd5e1;"></div>
                    <div>
                        <div class="display-4 fw-bold text-danger mb-0"><?= $sla_passed ?></div>
                        <div class="text-muted small fw-bold text-uppercase">SLA Breached</div>
                    </div>
                </div>
                
                <div class="p-3 bg-white rounded-3 shadow-sm border-start border-4 border-danger">
                    <p class="small text-muted mb-0">
                        <strong>Threshold Alert:</strong> Tickets breached (30 Hrs+) must be prioritized immediately by the assigned staff to avoid system-wide escalation.
                    </p>
                </div>

                <div class="mt-4">
                    <label class="small fw-bold text-muted mb-2">SLA Adherence Rate</label>
                    <?php 
                        $total_tracked = ($sla_within + $sla_passed);
                        $rate = $total_tracked > 0 ? round(($sla_within / $total_tracked) * 100, 1) : 100;
                    ?>
                    <div class="progress" style="height: 10px; border-radius: 5px;">
                        <div class="progress-bar bg-<?= $rate > 80 ? 'success' : ($rate > 50 ? 'warning' : 'danger') ?>" 
                             role="progressbar" style="width: <?= $rate ?>%" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="text-end small text-muted mt-1 fw-bold"><?= $rate ?>% Perfect Adherence</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Resolution Time Table -->
<?php
$avg_res_time = [];
$avg_query = "SELECT cat.category_name, AVG(TIMESTAMPDIFF(HOUR, c.created_at, h.updated_at)) as avg_hours 
              FROM complaints c 
              JOIN complaint_categories cat ON c.category_id = cat.category_id 
              JOIN complaint_history h ON c.complaint_id = h.complaint_id 
              WHERE h.status_id IN (3, 4) 
              GROUP BY cat.category_id";
$avg_r = mysqli_query($conn, $avg_query);
if ($avg_r) while($row = mysqli_fetch_assoc($avg_r)) { $avg_res_time[] = $row; }
?>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="fas fa-hourglass-half me-2 text-primary"></i>Average Resolution Time Efficiency</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-container mb-0 border-0 shadow-none">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Problem Category</th>
                        <th class="text-end pe-4">Avg. Time to Resolution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($avg_res_time)) {
                        echo "<tr><td colspan='2' class='text-center py-5 text-muted'>No resolution data available for analysis.</td></tr>";
                    } else {
                        foreach ($avg_res_time as $avg) {
                            $hrs = round($avg['avg_hours'], 1);
                            echo "<tr>
                                    <td class='ps-4 fw-semibold'>".htmlspecialchars($avg['category_name'])."</td>
                                    <td class='text-end pe-4 fw-bold text-primary fs-5'>{$hrs} <span class='fs-7 fw-normal text-muted'>hrs</span></td>
                                  </tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Data for Average Resolution Time Chart
        const avgResData = <?= json_encode($avg_res_time) ?>;
        const labels = avgResData.map(d => d.category_name);
        const dataVals = avgResData.map(d => Math.round(d.avg_hours * 10) / 10);

        new Chart(document.getElementById('catChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Avg. Resolution Time (Hours)',
                    data: dataVals,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' Hours';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Hours', font: { weight: 'bold' } },
                        grid: { borderDash: [5, 5] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    });
</script>

<?php include("../includes/footer.php"); ?>