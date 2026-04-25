<?php
include("../config/db.php");
include("../includes/auth.php");
require_once("../includes/csrf_helper.php");
include("../includes/header.php");
include_once("../includes/flash_messages.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_csrf_token();
    if (isset($_POST['add'])) {
        $level1 = trim($_POST['level1'] ?? '');
        $level2 = trim($_POST['level2'] ?? '');
        $level3 = trim($_POST['level3'] ?? '');

        $stmt = $conn->prepare("INSERT INTO area_master (level1, level2, level3, status) VALUES (?, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param("sss", $level1, $level2, $level3);
            if ($stmt->execute()) {
                set_flash_message('success', 'Area added successfully!');
            } else {
                set_flash_message('error', 'Failed to add area.');
            }
            $stmt->close();
        } else {
            set_flash_message('error', 'Failed to add area.');
        }
    } elseif (isset($_POST['update'])) {
        // Feature #8: Edit/update area
        $id = isset($_POST['area_id']) ? (int)$_POST['area_id'] : 0;
        $level1 = trim($_POST['level1'] ?? '');
        $level2 = trim($_POST['level2'] ?? '');
        $level3 = trim($_POST['level3'] ?? '');

        if ($id <= 0 || $level1 === '' || $level2 === '') {
            set_flash_message('error', 'Invalid area update.');
        } else {
            $stmt = $conn->prepare("UPDATE area_master SET level1 = ?, level2 = ?, level3 = ? WHERE area_id = ?");
            if ($stmt) {
                $stmt->bind_param("sssi", $level1, $level2, $level3, $id);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'Area updated successfully!');
            } else {
                set_flash_message('error', 'Database error while updating area.');
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['area_id'];
        $curr = (int)$_POST['current_status'];
        $new_st = $curr == 1 ? 0 : 1;
        $stmt = $conn->prepare("UPDATE area_master SET status = ? WHERE area_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $new_st, $id);
            if ($stmt->execute()) {
                set_flash_message('success', 'Status toggled successfully!');
            }
            $stmt->close();
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
                    <?= csrf_input() ?>
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
                                            <button type='button' class='btn btn-sm btn-light border rounded-pill px-3 fw-bold me-2'
                                                data-bs-toggle='modal' data-bs-target='#editAreaModal'
                                                data-id='{$r['area_id']}'
                                                data-l1=\"" . htmlspecialchars($r['level1'], ENT_QUOTES) . "\"
                                                data-l2=\"" . htmlspecialchars($r['level2'], ENT_QUOTES) . "\"
                                                data-l3=\"" . htmlspecialchars($r['level3'], ENT_QUOTES) . "\">
                                                <i class='fas fa-pen me-1'></i> Edit
                                            </button>
                                              <form method='POST' class='m-0 d-inline-block'>
                                                " . csrf_input() . "
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

<!-- Edit Area Modal (Feature #8) -->
<div class="modal fade" id="editAreaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" class="m-0">
        <?= csrf_input() ?>
        <div class="modal-header">
          <h5 class="modal-title">Edit Area</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="area_id" id="editAreaId">
          <div class="mb-3">
            <label class="form-label small fw-bold">Campus (Level 1)</label>
            <input type="text" class="form-control" name="level1" id="editLevel1" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Building (Level 2)</label>
            <input type="text" class="form-control" name="level2" id="editLevel2" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Spot (Level 3)</label>
            <input type="text" class="form-control" name="level3" id="editLevel3">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editAreaModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('editAreaId').value = button.getAttribute('data-id');
        document.getElementById('editLevel1').value = button.getAttribute('data-l1');
        document.getElementById('editLevel2').value = button.getAttribute('data-l2');
        document.getElementById('editLevel3').value = button.getAttribute('data-l3');
    });
});
</script>

<?php include("../includes/footer.php"); ?>
