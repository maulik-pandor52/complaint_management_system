<?php

/**
 * ResolveX Admin Reports & Analytics
 * Features: Avg Resolution Time (Chart), SLA Diagnostics (Cards), Category Performance Table
 */

include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/app_helper.php");
require_once("../includes/report_summary_helper.php");

// Only Admin can access
if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit();
}

include("../includes/header.php");

// --- 1. Fetch Analytics Data ---
$summaryData = get_reports_summary_data($conn);
$adherence_rate = $summaryData['sla_adherence_rate'] ?? 0;
$in_sla_count = $summaryData['complaints_within_sla'];
$breached_count = $summaryData['sla_breached_complaints'];
$category_stats = $summaryData['category_stats'];
$chart_data = [];
foreach ($category_stats as $row) {
    $chart_data[$row['category_name']] = $row['avg_hours'];
}

// C. Active Breaches Alert (Unresolved and > 30 hours)
$sql_active_breach = "SELECT COUNT(*) as c FROM complaints WHERE status_id NOT IN (3, 4) AND created_at < DATE_SUB(NOW(), INTERVAL 30 HOUR)";
$active_breach_count = mysqli_fetch_assoc(mysqli_query($conn, $sql_active_breach))['c'];
?>

<div class="reports-container">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-2">
        <div>
            <h2 class="mb-1 text-dark fw-bold">Reports & Analytics</h2>
            <p class="text-muted mb-0">Diagnostic overview of system performance and SLA health</p>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <a href="sla_report.php" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-stopwatch me-2"></i>View Live SLA Report
            </a>
            <a href="export_reports_summary_pdf.php" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-file-pdf me-2"></i>Export Summary PDF
            </a>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-5">
        <!-- SLA Adherence Rate -->
        <div class="col-md-4">
            <div class="stat-card accent-blue">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <span class="stat-value text-primary"><?= $adherence_rate ?>%</span>
                <span class="stat-label">SLA Adherence Rate</span>
                <div class="progress mt-3" style="height: 6px;">
                    <div class="progress-bar bg-primary" style="width: <?= $adherence_rate ?>%"></div>
                </div>
            </div>
        </div>
        <!-- Within SLA -->
        <div class="col-md-4">
            <div class="stat-card accent-green">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <span class="stat-value text-success"><?= $in_sla_count ?></span>
                <span class="stat-label">Complaints Within SLA</span>
            </div>
        </div>
        <!-- SLA Breached -->
        <div class="col-md-4">
            <div class="stat-card accent-red">
                <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                <span class="stat-value text-danger"><?= $breached_count ?></span>
                <span class="stat-label">SLA Breached Complaints</span>
            </div>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if ($active_breach_count > 0): ?>
        <div class="alert alert-custom-breach mb-5 d-flex align-items-center animate__animated animate__pulse animate__infinite d-print-none shadow-sm">
            <div class="alert-icon-wrap me-3">
                <i class="fas fa-triangle-exclamation fs-4"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold">Urgent Action Required</h6>
                <p class="mb-0 small opacity-75">There are <?= $active_breach_count ?> tickets breached (30 Hrs+) that must be prioritized immediately.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-5">
        <!-- Performance Chart -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-hourglass-half me-2 text-primary"></i>Resolution Time by Category</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px; width:100%">
                        <canvas id="resolutionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <!-- Table Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-table me-2 text-primary"></i>Performance Table</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Category</th>
                                    <th class="text-end pe-4">Avg. Time (Hrs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($category_stats)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4 text-muted">No data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($category_stats as $stat): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($stat['category_name'] ?: 'Uncategorized') ?></div>
                                                <div class="small text-muted"><?= $stat['total_complaints'] ?> complaints</div>
                                            </td>
                                            <td class="text-end pe-4">
                                                <span class="badge rounded-pill <?= $stat['avg_hours'] > 12 ? 'bg-danger-light text-danger' : 'bg-success-light text-success' ?> py-2 px-3 fw-bold">
                                                    <?= round($stat['avg_hours'], 1) ?> hrs
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom Dashboard Styles */
    .reports-container {
        padding-bottom: 2rem;
    }

    .stat-card {
        background: #fff;
        border-radius: 1.5rem;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.03);
        position: relative;
        overflow: hidden;
        height: 100%;
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        font-size: 2.5rem;
        opacity: 0.1;
    }

    .stat-value {
        display: block;
        font-size: 2.5rem;
        font-weight: 800;
        font-family: 'Outfit', sans-serif;
        letter-spacing: -1px;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 1px;
    }

    .accent-blue {
        border-bottom: 5px solid #0d6efd;
    }

    .accent-green {
        border-bottom: 5px solid #198754;
    }

    .accent-red {
        border-bottom: 5px solid #dc3545;
    }

    .alert-custom-breach {
        background: #fff5f5;
        border-left: 5px solid #dc3545;
        color: #dc3545;
        padding: 1.25rem 2rem;
        border-radius: 1rem;
    }

    .alert-icon-wrap {
        width: 50px;
        height: 50px;
        background: rgba(220, 53, 69, 0.1);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bg-danger-light {
        background-color: rgba(220, 53, 69, 0.1) !important;
    }

    .bg-success-light {
        background-color: rgba(25, 135, 84, 0.1) !important;
    }

    @media print {
        .d-print-none {
            display: none !important;
        }

        #wrapper {
            display: block !important;
        }

        #page-content-wrapper {
            padding: 0 !important;
            border: 0 !important;
        }

        .stat-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.02);
        }

        100% {
            transform: scale(1);
        }
    }

    .animate__pulse {
        animation: pulse 2s infinite ease-in-out;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('resolutionChart').getContext('2d');

        const chartLabels = <?= json_encode(array_keys($chart_data)) ?>;
        const chartValues = <?= json_encode(array_values($chart_data)) ?>;

        if (chartLabels.length === 0) {
            // Show placeholder or empty state
        }

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Avg Resolution Time (Hours)',
                    data: chartValues,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: '#0d6efd',
                    borderWidth: 2,
                    borderRadius: 10,
                    hoverBackgroundColor: 'rgba(13, 110, 253, 0.9)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        padding: 15,
                        backgroundColor: '#1e293b',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                weight: '500'
                            },
                            callback: function(value) {
                                return value + 'h';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php include("../includes/footer.php"); ?>