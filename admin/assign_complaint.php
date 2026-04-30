<?php
include("../config/db.php");
$conn = getDBConnection();
include("../includes/auth.php");
require_once("../includes/status_lookup.php");
require_once("../includes/workflow_helper.php");
require_once("../includes/sla_escalation.php");
require_once("../includes/csrf_helper.php");
require_once("../includes/assignment_helper.php");

if ($_SESSION['role_id'] != 1) {
    header("Location: ../auth/login.php");
    exit;
}

include("../includes/header.php");
include_once("../includes/flash_messages.php");
include_once("../includes/status_helper.php");

// Auto-escalate overdue complaints (Feature #2)
run_sla_escalation($conn);

$ID_PENDING    = get_status_id_or($conn, "Pending", 1);
$ID_ASSIGNED   = get_status_id_or($conn, "Assigned", 2);
$ID_RESOLVED   = get_status_id_or($conn, "Resolved", 3);
$ID_CLOSED     = get_status_id_or($conn, "Closed", 4);
$ID_REOPEN_AP  = get_status_id_or($conn, "Reopened - Pending Approval", 5);
$ID_REOPEN_AS  = get_status_id_or($conn, "Reopened - Assigned", 6);
$ID_VERIFIED   = get_status_id_or($conn, "Verified", 7);
$ID_ESCALATED  = get_status_id_or($conn, "Escalated", 8);
$ID_DECLINED   = get_status_id_or($conn, "Declined", 9);
$ID_IN_PROGRESS = get_status_id_or($conn, "In Progress", 10);

ensure_assignment_active_schema($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign'])) {
    require_csrf_token();
    $complaint_id = (int)$_POST['complaint_id'];
    $staff_id = (int)$_POST['staff_id'];
    $admin_id = $_SESSION['user_id'];

    // Re-check current status (Feature #4 + #5)
    $curr_status = get_complaint_status_id($conn, $complaint_id);

    if ($curr_status === null) {
        set_flash_message('error', 'Complaint not found.');
    } elseif ($curr_status === $ID_PENDING) {
        set_flash_message('error', 'Please verify the complaint before assignment.');
    } elseif ($curr_status === $ID_REOPEN_AP) {
        set_flash_message('error', 'Reopened complaint requires approval before assignment.');
    } elseif (in_array($curr_status, [$ID_RESOLVED, $ID_CLOSED, $ID_DECLINED], true)) {
        set_flash_message('error', 'This complaint is not eligible for assignment.');
    } elseif (get_complaint_assignment($conn, $complaint_id)) {
        set_flash_message('error', 'Complaint already assigned');
    } else {
        $assignmentResult = assign_complaint_to_staff($conn, $complaint_id, $staff_id, $admin_id);
        if ($assignmentResult['ok']) {
            $new_status = in_array($curr_status, [$ID_VERIFIED, $ID_REOPEN_AS, $ID_ESCALATED], true) ? $ID_ASSIGNED : $curr_status;
            if (update_complaint_status_with_history($conn, $complaint_id, $new_status, $admin_id, 'Assigned to staff')) {
                set_flash_message('success', $assignmentResult['message']);
            } else {
                set_flash_message('error', 'Assignment was saved, but status history could not be updated.');
            }
        } else {
            set_flash_message('error', $assignmentResult['message']);
        }
    }
}

// Verify complaint (Feature #4)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify'])) {
    require_csrf_token();
    $complaint_id = (int)$_POST['complaint_id'];
    $admin_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ? AND status_id = ?");
    if ($stmt) {
        $stmt->bind_param("iii", $ID_VERIFIED, $complaint_id, $ID_PENDING);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed > 0) {
            add_complaint_history($conn, $complaint_id, $ID_VERIFIED, $admin_id, "Verified by Admin");
            set_flash_message('success', 'Complaint verified successfully.');
        } else {
            set_flash_message('error', 'Complaint is not in Pending state (or already verified).');
        }
    }
}

// Approve reopen (Feature #5)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_reopen'])) {
    require_csrf_token();
    $complaint_id = (int)$_POST['complaint_id'];
    $admin_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ? AND status_id = ?");
    if ($stmt) {
        $stmt->bind_param("iii", $ID_VERIFIED, $complaint_id, $ID_REOPEN_AP);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed > 0) {
            add_complaint_history($conn, $complaint_id, $ID_VERIFIED, $admin_id, "Reopen approved by Admin");
            set_flash_message('success', 'Reopen approved. You can assign it now.');
        } else {
            set_flash_message('error', 'This complaint is not waiting for reopen approval.');
        }
    }
}

// Decline complaint (Feature #4)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['decline'])) {
    require_csrf_token();
    $complaint_id = (int)$_POST['complaint_id'];
    $admin_id = $_SESSION['user_id'];
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Declined by Admin';

    if (empty($reason)) $reason = 'Declined by Admin';

    $stmt = $conn->prepare("UPDATE complaints SET status_id = ? WHERE complaint_id = ? AND status_id IN (?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiii", $ID_DECLINED, $complaint_id, $ID_PENDING, $ID_REOPEN_AP);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed > 0) {
            add_complaint_history($conn, $complaint_id, $ID_DECLINED, $admin_id, $reason);
            set_flash_message('success', 'Complaint declined and user notified.');
        } else {
            set_flash_message('error', 'Action failed. Complaint may have been updated already.');
        }
    }
}

// Fetch Staff List
$staff_arr = [];
$res_staff = mysqli_query($conn, "SELECT user_id, name FROM users WHERE role_id=2");
while ($s = mysqli_fetch_assoc($res_staff)) {
    $staff_arr[] = $s;
}
?>

<!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Assign Complaints</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php">Admin Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Assignment Management</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<!-- Assignment Table Section -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-people-arrows me-2"></i>Assignment Management</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-container mb-0 border-0 shadow-none">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Complaint Details</th>
                        <th>Status</th>
                        <th>Assigned Staff</th>
                        <th class="text-end pe-4">Assignment Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch all open complaints with their permanent assignment owner.
                    $query = "SELECT c.*, cat.category_name, s.status_name, 
                              assignment_info.staff_id AS assigned_staff_id,
                              assignment_info.assigned_staff
                              FROM complaints c 
                              LEFT JOIN complaint_categories cat ON c.category_id=cat.category_id
                              LEFT JOIN status_master s ON c.status_id=s.status_id
                              LEFT JOIN (
                                  SELECT a.complaint_id, a.staff_id, u.name AS assigned_staff
                                  FROM assignments a
                                  JOIN users u ON a.staff_id = u.user_id
                              ) assignment_info ON assignment_info.complaint_id = c.complaint_id
                              WHERE c.status_id NOT IN ($ID_RESOLVED, $ID_CLOSED, $ID_DECLINED)
                              ORDER BY c.created_at DESC";
                    
                    $res = mysqli_query($conn, $query);
                    if(mysqli_num_rows($res) > 0) {
                        while ($r = mysqli_fetch_assoc($res)) {
                            $hasAssignment = !empty($r['assigned_staff_id']);
                            $assigned = $hasAssignment ? htmlspecialchars($r['assigned_staff']) : "<span class='text-danger fw-bold small'><i class='fas fa-triangle-exclamation me-1'></i>Unassigned</span>";
                            $cid = (int)$r['complaint_id'];
                            $sid = (int)$r['status_id'];
                            
                            $display_status = $r['status_name'];
                            if ($sid === $ID_VERIFIED && !empty($r['initial_sla_due']) && strtotime($r['initial_sla_due']) < time()) {
                                $display_status = 'Delayed';
                            }

                            echo "<tr>
                                    <td class='ps-4 fw-bold text-muted'>#{$r['complaint_id']}</td>
                                    <td>
                                        <div class='fw-bold'>" . htmlspecialchars($r['title']) . "</div>
                                        <div class='small text-muted'>" . htmlspecialchars($r['category_name']) . " &bull; Reported " . date('M d', strtotime($r['created_at'])) . "</div>
                                    </td>
                                    <td>" . render_status_badge($display_status) . "</td>
                                    <td class='small'>" . ($hasAssignment ? "<div class='fw-bold text-primary'>Current: {$assigned}</div>" : $assigned) . "</td>
                                    <td class='text-end pe-4'>
                                        <div class='d-flex gap-2 justify-content-end align-items-center'>
                                            <a href='view_complaint.php?id={$r['complaint_id']}' class='btn btn-light btn-sm rounded-pill px-3 fw-bold' data-bs-toggle='tooltip' title='View Details'>
                                                <i class='fas fa-eye'></i>
                                            </a>
                                            ";

                            // Actions based on status (Feature #4 + #5)
                            if ($sid === $ID_PENDING) {
                                echo "<div class='d-flex gap-2 justify-content-end'>
                                        <form method='POST' class='m-0'>
                                            " . csrf_input() . "
                                            <input type='hidden' name='complaint_id' value='{$cid}'>
                                            <button type='submit' name='verify' class='btn btn-warning btn-sm rounded-pill px-3 fw-bold confirm-action' data-confirm='Verify this complaint before assignment?'>
                                                <i class='fas fa-check me-1'></i> Verify
                                            </button>
                                        </form>
                                        <button type='button' class='btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold' onclick='handleDecline({$cid})'>
                                            <i class='fas fa-times me-1'></i> Decline
                                        </button>
                                      </div>";
                            } elseif ($sid === $ID_REOPEN_AP) {
                                echo "<div class='d-flex gap-2 justify-content-end'>
                                        <form method='POST' class='m-0'>
                                            " . csrf_input() . "
                                            <input type='hidden' name='complaint_id' value='{$cid}'>
                                            <button type='submit' name='approve_reopen' class='btn btn-info btn-sm rounded-pill px-3 fw-bold confirm-action' data-confirm='Approve this reopened complaint for assignment?'>
                                                <i class='fas fa-user-shield me-1'></i> Approve
                                            </button>
                                        </form>
                                        <button type='button' class='btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold' onclick='handleDecline({$cid})'>
                                            <i class='fas fa-times me-1'></i> Decline
                                        </button>
                                      </div>";
                            } elseif ($hasAssignment) {
                                echo "<span class='badge rounded-pill bg-light text-muted border px-3 py-2'>
                                        <i class='fas fa-lock me-1'></i>Already Assigned
                                      </span>";
                            } else {
                                // First-time assignment only.
                                echo "<form method='POST' class='d-flex gap-2 m-0'>
                                        " . csrf_input() . "
                                        <input type='hidden' name='complaint_id' value='{$cid}'>
                                        <select name='staff_id' class='form-control form-control-sm border-primary' style='min-width: 130px; border-radius: 20px;' required>
                                            <option value=''>Assign to...</option>";
                                foreach ($staff_arr as $st) {
                                    echo "<option value='{$st['user_id']}'>" . htmlspecialchars($st['name']) . "</option>";
                                }
                                echo "          </select>
                                        <button type='submit' name='assign' class='btn btn-primary btn-sm rounded-pill px-3 fw-bold' data-bs-toggle='tooltip' title='Assign Staff'>
                                            <i class='fas fa-user-plus me-1'></i>Assign Staff
                                        </button>
                                      </form>";
                            }

                            echo "
                                        </div>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>
                                <i class='fas fa-clipboard-check fa-3x mb-3 d-block opacity-20'></i>
                                All clear! No pending assignments.
                              </td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("../includes/footer.php"); ?>

<script>
const csrfToken = <?= json_encode(csrf_token()) ?>;

function handleDecline(complaintId) {
    const reason = prompt("Enter reason for declining this complaint:", "Invalid or duplicate complaint");
    if (reason === null) return; // User cancelled
    
    if (reason.trim() === "") {
        alert("A reason is mandatory for declining.");
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="${csrfToken}">
        <input type="hidden" name="complaint_id" value="${complaintId}">
        <input type="hidden" name="decline" value="1">
        <input type="hidden" name="reason" value="${reason.replace(/"/g, '&quot;')}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
