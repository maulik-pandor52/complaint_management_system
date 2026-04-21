<?php
include("../config/db.php");
include("../includes/auth.php");

if ($_SESSION['role_id'] != 2) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");

$user_id = $_SESSION['user_id'];
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">My Assigned Tasks</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Staff Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Assigned Complaints</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-tasks me-2"></i>Deployment Queue</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-container mb-0 border-0 shadow-none">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Complaint Details</th>
                        <th>User</th>
                        <th>Area</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT c.*, cat.category_name, a.level1, a.level2, s.status_name, u.name as user_name 
                              FROM complaints c
                              JOIN assignments asn ON c.complaint_id = asn.complaint_id
                              LEFT JOIN complaint_categories cat ON c.category_id = cat.category_id
                              LEFT JOIN area_master a ON c.area_id = a.area_id
                              LEFT JOIN status_master s ON c.status_id = s.status_id
                              LEFT JOIN users u ON c.user_id = u.user_id
                              WHERE asn.staff_id = '$user_id'
                              ORDER BY c.created_at DESC";

                    $res = mysqli_query($conn, $query);
                    if(mysqli_num_rows($res) > 0) {
                        while ($r = mysqli_fetch_assoc($res)) {
                            $badge_class = "badge-" . strtolower(str_replace(' ', '-', $r['status_name']));
                            echo "<tr>
                                    <td class='ps-4 fw-bold text-muted'>#{$r['complaint_id']}</td>
                                    <td>
                                        <div class='fw-bold'>" . htmlspecialchars($r['title']) . "</div>
                                        <div class='small text-muted'>" . htmlspecialchars($r['category_name']) . " &bull; " . date('M d', strtotime($r['created_at'])) . "</div>
                                    </td>
                                    <td class='small'>" . htmlspecialchars($r['user_name']) . "</td>
                                    <td class='small'>" . htmlspecialchars($r['level1'] . " (" . $r['level2'] . ")") . "</td>
                                    <td>" . render_status_badge($r['status_name']) . "</td>
                                    <td class='text-end pe-4'>
                                        <a href='view_complaint.php?id={$r['complaint_id']}' class='btn btn-primary btn-sm rounded-pill px-3'>
                                            Update Action
                                        </a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center py-5 text-muted'>
                                <i class='fas fa-clipboard-check fa-3x mb-3 d-block opacity-20'></i>
                                You have no assigned complaints at the moment.
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>