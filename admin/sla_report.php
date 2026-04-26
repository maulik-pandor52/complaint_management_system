<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/app_helper.php");
require_once("../includes/sla_report_helper.php");
require_once("../includes/sla_escalation.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

run_sla_escalation($conn);
$reportRows = get_live_sla_report_rows($conn);
$generatedAt = date('d M Y, h:i A');

include("../includes/header.php");
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Live SLA Compliance Report</h2>
        <p class="text-muted mb-0">Real-time complaint-level audit of initial response, resolution deadlines, and escalation status</p>
    </div>
    <div class="d-flex gap-2">
        <a href="reports.php" class="btn btn-light rounded-pill px-4 shadow-sm border fw-bold text-muted">
            <i class="fas fa-arrow-left me-2"></i>Back to Reports
        </a>
        <a href="export_sla_report_pdf.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-file-pdf me-2"></i>Export SLA PDF
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <div class="small text-muted text-uppercase fw-bold mb-1">Report Snapshot</div>
            <div class="fw-semibold">Generated at <?= htmlspecialchars($generatedAt) ?></div>
        </div>
        <div class="text-lg-end">
            <div class="small text-muted text-uppercase fw-bold mb-1">Live Current Time</div>
            <div class="fw-semibold fs-5" id="liveCurrentTime"><?= htmlspecialchars(date('d M Y, h:i:s A')) ?></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold"><i class="fas fa-stopwatch me-2 text-primary"></i>SLA Audit Table</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 sla-report-table">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Complaint</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Created / Staff</th>
                        <th>Initial SLA</th>
                        <th>Resolution SLA</th>
                        <th class="pe-4">Overall SLA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No complaints available for SLA audit.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $row): ?>
                            <?php
                            $initialClass = $row['initial_sla_status'] === 'Within SLA' ? 'text-success' : 'text-warning';
                            $resolutionClass = $row['resolution_sla_status'] === 'Within SLA' ? 'text-success' : 'text-danger';
                            $overallBadgeClass = 'bg-success-light text-success';
                            if ($row['overall_sla_badge'] === 'Delayed') {
                                $overallBadgeClass = 'bg-warning text-dark';
                            } elseif ($row['overall_sla_badge'] === 'Escalated') {
                                $overallBadgeClass = 'bg-danger-light text-danger';
                            }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold">#<?= $row['complaint_id'] ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($row['title']) ?></div>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($row['description']) ?></div>
                                </td>
                                <td>
                                    <div class="small"><strong>Category:</strong> <?= htmlspecialchars($row['category']) ?></div>
                                    <div class="small"><strong>Campus:</strong> <?= htmlspecialchars($row['campus']) ?></div>
                                    <div class="small"><strong>Building:</strong> <?= htmlspecialchars($row['building']) ?></div>
                                    <div class="small"><strong>Spot:</strong> <?= htmlspecialchars($row['spot']) ?></div>
                                    <div class="small"><strong>Priority:</strong> <?= htmlspecialchars($row['priority']) ?></div>
                                </td>
                                <td>
                                    <div class="small text-muted text-uppercase fw-bold mb-1">Complaint</div>
                                    <?= render_status_badge($row['status_name']) ?>
                                </td>
                                <td>
                                    <div class="small"><strong>Created:</strong> <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></div>
                                    <div class="small"><strong>Assigned Staff:</strong> <?= htmlspecialchars($row['assigned_staff']) ?></div>
                                    <!-- <div class="small"><strong>Current Time:</strong> <span class="live-time-cell"><?= htmlspecialchars(date('d M Y, h:i:s A')) ?></span></div> -->
                                </td>
                                <td>
                                    <div class="small"><strong>Start:</strong> <?= date('d M Y, h:i A', strtotime($row['initial_sla_start'])) ?></div>
                                    <div class="small"><strong>Deadline:</strong> <?= date('d M Y, h:i A', strtotime($row['initial_sla_deadline'])) ?></div>
                                    <div class="small">
                                        <strong>Timer:</strong>
                                        <span
                                            class="sla-timer <?= $initialClass ?>"
                                            data-deadline="<?= htmlspecialchars($row['initial_sla_deadline']) ?>"
                                            data-live="<?= !empty($row['initial_timer']['is_live']) ? '1' : '0' ?>"
                                        ><?= htmlspecialchars($row['initial_timer']['label']) ?></span>
                                    </div>
                                    <div class="small">
                                        <strong>Status:</strong>
                                        <span class="badge rounded-pill px-3 py-2 <?= $initialClass === 'text-success' ? 'bg-success-light text-success' : 'bg-warning text-dark' ?>">
                                            <?= htmlspecialchars($row['initial_sla_display_status']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="small"><strong>Start:</strong> <?= date('d M Y, h:i A', strtotime($row['resolution_sla_start'])) ?></div>
                                    <div class="small"><strong>Deadline:</strong> <?= date('d M Y, h:i A', strtotime($row['resolution_sla_deadline'])) ?></div>
                                    <div class="small">
                                        <strong>Timer:</strong>
                                        <span
                                            class="sla-timer <?= $resolutionClass ?>"
                                            data-deadline="<?= htmlspecialchars($row['resolution_sla_deadline']) ?>"
                                            data-live="<?= !empty($row['resolution_timer']['is_live']) ? '1' : '0' ?>"
                                        ><?= htmlspecialchars($row['resolution_timer']['label']) ?></span>
                                    </div>
                                    <div class="small">
                                        <strong>Status:</strong>
                                        <span class="badge rounded-pill px-3 py-2 <?= $resolutionClass === 'text-success' ? 'bg-success-light text-success' : 'bg-danger-light text-danger' ?>">
                                            <?= htmlspecialchars($row['resolution_sla_status']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="pe-4">
                                    <span class="badge rounded-pill px-3 py-2 <?= $overallBadgeClass ?>">
                                        <?= htmlspecialchars($row['overall_sla_badge']) ?>
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

<style>
.sla-report-table th,
.sla-report-table td {
    vertical-align: top;
    font-size: 0.92rem;
}
</style>

<script>
function formatDuration(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    return `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m`;
}

function updateLiveCurrentTime() {
    const now = new Date();
    const formatted = now.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });

    const headerClock = document.getElementById('liveCurrentTime');
    if (headerClock) {
        headerClock.textContent = formatted;
    }

    document.querySelectorAll('.live-time-cell').forEach(el => {
        el.textContent = formatted;
    });
}

function updateSlaTimers() {
    const now = new Date();
    document.querySelectorAll('.sla-timer').forEach(el => {
        if (el.getAttribute('data-live') !== '1') {
            return;
        }
        const deadlineRaw = el.getAttribute('data-deadline');
        const deadline = new Date(deadlineRaw.replace(' ', 'T'));
        if (Number.isNaN(deadline.getTime())) {
            return;
        }

        const diffSeconds = Math.floor((deadline.getTime() - now.getTime()) / 1000);
        if (diffSeconds >= 0) {
            el.textContent = `${formatDuration(diffSeconds)} remaining`;
            el.classList.remove('text-danger');
            el.classList.add('text-success');
        } else {
            el.textContent = `${formatDuration(Math.abs(diffSeconds))} breached`;
            el.classList.remove('text-success');
            el.classList.add('text-danger');
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    updateLiveCurrentTime();
    updateSlaTimers();
    setInterval(updateLiveCurrentTime, 1000);
    setInterval(updateSlaTimers, 30000);
});
</script>

<?php include("../includes/footer.php"); ?>
