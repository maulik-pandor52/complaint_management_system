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
        $name = mysqli_real_escape_string($conn, $_POST['category_name']);
        if (mysqli_query($conn, "INSERT INTO complaint_categories (category_name, status) VALUES ('$name', 1)")) {
            set_flash_message('success', 'Category added successfully!');
        } else {
            set_flash_message('error', 'Failed to add category.');
        }
    } elseif (isset($_POST['update'])) {
        // Feature #8: Edit/update category
        $id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $name = trim($_POST['category_name'] ?? '');

        if ($id <= 0 || $name === '') {
            set_flash_message('error', 'Invalid category update.');
        } else {
            $stmt = $conn->prepare("UPDATE complaint_categories SET category_name = ? WHERE category_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'Category updated successfully!');
            } else {
                set_flash_message('error', 'Database error while updating category.');
            }
        }
    } elseif (isset($_POST['toggle_status'])) {
        $id = (int)$_POST['category_id'];
        $curr = (int)$_POST['current_status'];
        $new_st = $curr == 1 ? 0 : 1;
        if (mysqli_query($conn, "UPDATE complaint_categories SET status='$new_st' WHERE category_id='$id'")) {
            set_flash_message('success', 'Status toggled successfully!');
        }
    }
}
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Manage Complaint Categories</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Category Management</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Add Category Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-tags me-2"></i>New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold">Classification Name</label>
                        <input type="text" name="category_name" class="form-control" required placeholder="e.g. Electrical Maintenance">
                    </div>
                    <button type="submit" name="add" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-2"></i>Register Category
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Category Table -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list me-2 text-primary"></i>Active Classifications</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-container mb-0 border-0 shadow-none">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">UID</th>
                                <th>Category Name</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Compliance Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM complaint_categories");
                            while ($r = mysqli_fetch_assoc($res)) {
                                $is_active = $r['status'] == 1;
                                $status_badge = $is_active ? 'badge-resolved' : 'badge-rejected';
                                $status_text = $is_active ? 'Active' : 'Inactive';
                                echo "<tr>
                                        <td class='ps-4 fw-bold text-muted'>#{$r['category_id']}</td>
                                    <td class='fw-bold'>" . htmlspecialchars($r['category_name']) . "</td>
                                    <td><span class='badge {$status_badge} rounded-pill px-3'>{$status_text}</span></td>
                                    <td class='text-end pe-4'>
                                            <button type='button' class='btn btn-sm btn-light border rounded-pill px-3 fw-bold me-2'
                                                data-bs-toggle='modal' data-bs-target='#editCategoryModal'
                                                data-id='{$r['category_id']}'
                                                data-name=\"" . htmlspecialchars($r['category_name'], ENT_QUOTES) . "\">
                                                <i class='fas fa-pen me-1'></i> Edit
                                            </button>
                                            <form method='POST' class='m-0 d-inline-block'>
                                                <input type='hidden' name='category_id' value='{$r['category_id']}'>
                                                <input type='hidden' name='current_status' value='{$r['status']}'>
                                                <button type='submit' name='toggle_status' class='btn btn-sm btn-light border rounded-pill px-3 fw-bold " . ($is_active ? 'text-danger confirm-action' : 'text-success') . "'
                                                    data-confirm='" . ($is_active ? 'Deactivate this category?' : 'Activate this category?') . "'>
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

<!-- Edit Category Modal (Feature #8) -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" class="m-0">
        <div class="modal-header">
          <h5 class="modal-title">Edit Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="category_id" id="editCategoryId">
          <div class="mb-3">
            <label class="form-label fw-bold small">Category Name</label>
            <input type="text" class="form-control" name="category_name" id="editCategoryName" required>
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
    const modal = document.getElementById('editCategoryModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('editCategoryId').value = button.getAttribute('data-id');
        document.getElementById('editCategoryName').value = button.getAttribute('data-name');
    });
});
</script>

<?php include("../includes/footer.php"); ?>
