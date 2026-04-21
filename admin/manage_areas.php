<?php
include("../config/db.php");
include("../includes/auth.php");
include("../includes/header.php");
include_once("../includes/flash_messages.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $level1 = mysqli_real_escape_string($conn, $_POST['level1']);
        $level2 = mysqli_real_escape_string($conn, $_POST['level2']);
        $level3 = mysqli_real_escape_string($conn, $_POST['level3']);
        $q = "INSERT INTO area_master (level1, level2, level3, status) VALUES ('$level1', '$level2', '$level3', 1)";
        if (mysqli_query($conn, $q)) {
            set_flash_message('success', 'Area added successfully!');
        } else {
            set_flash_message('error', 'Failed to add area.');
        }
    } elseif (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['area_id'];
        $curr = (int)$_POST['current_status'];
        $new_st = $curr == 1 ? 0 : 1;
        if (mysqli_query($conn, "UPDATE area_master SET status='$new_st' WHERE area_id='$id'")) {
            set_flash_message('success', 'Status toggled successfully!');
        }
    }
}
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Manage Areas & Branches</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Area Management</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Add Area Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-location-dot me-2"></i>Provision New Area</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="form-group mb-3">
                        <label class="form-label small fw-bold">Level 1 (Campus)</label>
                        <input type="text" name="level1" class="form-control" required placeholder="e.g. Main Campus">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label small fw-bold">Level 2 (Building)</label>
                        <input type="text" name="level2" class="form-control" required placeholder="e.g. Block A">
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold">Level 3 (Spot)</label>
                        <input type="text" name="level3" class="form-control" placeholder="e.g. Ground Floor">
                    </div>
                    <button type="submit" name="add" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-2"></i>Add Area
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Area List Table -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list-check me-2 text-primary"></i>Operational Regions</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-container mb-0 border-0 shadow-none">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">UID</th>
                                <th>Location Hierarchy (Campus &rarr; Building &rarr; Spot)</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Compliance Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM area_master");
                            while ($r = mysqli_fetch_assoc($res)) {
                                $is_active = $r['status'] == 1;
                                $status_badge = $is_active ? 'badge-resolved' : 'badge-rejected';
                                $status_text = $is_active ? 'Active' : 'Inactive';
                                
                                echo "<tr>
                                        <td class='ps-4 fw-bold text-muted'>#{$r['area_id']}</td>
                                        <td>
                                            <div class='fw-bold'>" . htmlspecialchars($r['level1']) . " &raquo; " . htmlspecialchars($r['level2']) . "</div>
                                            <div class='small text-muted'>" . htmlspecialchars($r['level3']) . "</div>
                                        </td>
                                        <td><span class='badge {$status_badge} rounded-pill px-3'>{$status_text}</span></td>
                                        <td class='text-end pe-4'>
                                            <form method='POST' class='m-0 d-inline-block'>
                                                <input type='hidden' name='area_id' value='{$r['area_id']}'>
                                                <input type='hidden' name='current_status' value='{$r['status']}'>
                                                <button type='submit' name='toggle_status' class='btn btn-sm btn-light border rounded-pill px-3 fw-bold " . ($is_active ? 'text-danger confirm-action' : 'text-success') . "' 
                                                    data-confirm='" . ($is_active ? 'Are you sure you want to DEACTIVATE this area? New complaints cannot be filed here.' : 'Activate this area?') . "'
                                                    data-bs-toggle='tooltip' title='" . ($is_active ? 'Deactivate' : 'Activate') . "'>
                                                    <i class='fas fa-power-off me-1'></i> " . ($is_active ? 'Disable' : 'Enable') . "
                                                </button>
                                            </form>
                                        </td>
                                      </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>