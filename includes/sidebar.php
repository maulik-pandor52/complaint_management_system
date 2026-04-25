<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role_id'] ?? 3;

// Helper function to check active state
function is_active($page, $current) {
    return ($page == $current) ? 'active' : '';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-shield-halved"></i>
            <span>Resolve<span class="x-blue">X</span></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main Menu</div>
        <ul>
            <?php if ($role == 1): // Admin ?>
                <li>
                    <a href="../admin/dashboard.php" class="<?= is_active('dashboard.php', $current_page) ?>">
                        <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/assign_complaint.php" class="<?= is_active('assign_complaint.php', $current_page) ?>">
                        <i class="fas fa-tasks"></i> <span>Complaints Queue</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/escalated.php" class="<?= is_active('escalated.php', $current_page) ?>">
                        <i class="fas fa-triangle-exclamation"></i> <span>Escalated</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/manage_users.php" class="<?= is_active('manage_users.php', $current_page) ?>">
                        <i class="fas fa-users-gear"></i> <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/manage_categories.php" class="<?= is_active('manage_categories.php', $current_page) ?>">
                        <i class="fas fa-tags"></i> <span>Categories</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/manage_areas.php" class="<?= is_active('manage_areas.php', $current_page) ?>">
                        <i class="fas fa-map-location-dot"></i> <span>Areas</span>
                    </a>
                </li>
                <li>
                    <a href="../admin/reports.php" class="<?= is_active('reports.php', $current_page) ?>">
                        <i class="fas fa-file-invoice"></i> <span>Analytics</span>
                    </a>
                </li>

            <?php elseif ($role == 2): // Staff ?>
                <li>
                    <a href="../staff/dashboard.php" class="<?= is_active('dashboard.php', $current_page) ?>">
                        <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="../staff/my_complaints.php" class="<?= is_active('my_complaints.php', $current_page) ?>">
                        <i class="fas fa-list-check"></i> <span>My Tasks</span>
                    </a>
                </li>
                <li>
                    <a href="../staff/escalated.php" class="<?= is_active('escalated.php', $current_page) ?>">
                        <i class="fas fa-fire-flame-curved"></i> <span>Escalated</span>
                    </a>
                </li>

            <?php else: // User ?>
                <li>
                    <a href="../user/dashboard.php" class="<?= is_active('dashboard.php', $current_page) ?>">
                        <i class="fas fa-shapes"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="../user/register_complaint.php" class="<?= is_active('register_complaint.php', $current_page) ?>">
                        <i class="fas fa-circle-plus"></i> <span>Lodge Complaint</span>
                    </a>
                </li>
                <li>
                    <a href="../user/my_complaints.php" class="<?= is_active('my_complaints.php', $current_page) ?>">
                        <i class="fas fa-clock-rotate-left"></i> <span>My History</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="nav-section-label">Account</div>
        <ul>
            <li>
                <a href="../auth/logout.php" class="text-danger-hover">
                    <i class="fas fa-right-from-bracket"></i> <span>Sign Out</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
