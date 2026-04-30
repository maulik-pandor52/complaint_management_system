<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/csrf_helper.php");
include("../includes/header.php");
include_once("../includes/flash_messages.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    require_csrf_token();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $passwordPlain = $_POST['password'] ?? '';
    $role = (int)$_POST['role_id'];

    if ($name === '' || $email === '' || $passwordPlain === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Please enter valid user details.');
    } else {
        $password = password_hash($passwordPlain, PASSWORD_DEFAULT);
        $check = $conn->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
        if (!$check) {
            set_flash_message('error', 'Unable to validate email right now.');
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if ($exists) {
                set_flash_message('error', 'Email already in use.');
            } else {
                $insert = $conn->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                if ($insert) {
                    $insert->bind_param("sssi", $name, $email, $password, $role);
                    if ($insert->execute()) {
                        set_flash_message('success', 'User added successfully!');
                    } else {
                        set_flash_message('error', 'Failed to add user.');
                    }
                    $insert->close();
                } else {
                    set_flash_message('error', 'Failed to add user.');
                }
            }
        }
    }
}
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">User Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Users & Staff</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <!-- Add User Form -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-user-plus me-2"></i>Add System User</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_input() ?>
                    <div class="form-group mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Enter name">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="user@example.com">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label small fw-bold">Temporary Password</label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label small fw-bold">System Role</label>
                        <select name="role_id" class="form-control" required>
                            <?php
                            $role_res = mysqli_query($conn, "SELECT * FROM roles");
                            while ($r = mysqli_fetch_assoc($role_res)) {
                                echo "<option value='{$r['role_id']}'>" . htmlspecialchars($r['role_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                        <i class="fas fa-plus me-2"></i>Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- User List Table -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users-gear me-2 text-primary"></i>Active Personnel</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-container mb-0 border-0 shadow-none">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">UID</th>
                                <th>Name & Email</th>
                                <th>Access Role</th>
                                <th class="text-end pe-4">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id DESC");
                            while ($r = mysqli_fetch_assoc($res)) {
                                $role_color = 'primary';
                                if($r['role_id'] == 1) $role_color = 'danger';
                                if($r['role_id'] == 2) $role_color = 'indigo';
                                if($r['role_id'] == 3) $role_color = 'success';
                                
                                echo "<tr>
                                        <td class='ps-4 fw-bold text-muted'>#{$r['user_id']}</td>
                                        <td>
                                            <div class='fw-bold'>" . htmlspecialchars($r['name']) . "</div>
                                            <div class='small text-muted'>" . htmlspecialchars($r['email']) . "</div>
                                        </td>
                                        <td>
                                            <span class='badge bg-{$role_color}-light text-{$role_color} rounded-pill px-3'>
                                                " . htmlspecialchars($r['role_name']) . "
                                            </span>
                                        </td>
                                        <td class='text-end pe-4 small text-muted'>" . date('M d, Y', strtotime($r['created_at'])) . "</td>
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
