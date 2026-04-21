<?php
include("../config/db.php");
include("../includes/auth.php");
include("../includes/upload_helper.php");
include("../includes/header.php");
include_once("../includes/flash_messages.php");

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 3) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize input
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $category = (int)$_POST['category'];
    $area = (int)$_POST['area'];
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $user_id = $_SESSION['user_id'];

    // Calculate SLA properties based on U=38 rules (6 hours initial, 30 hours resolution)
    $initial_sla = date('Y-m-d H:i:s', strtotime('+6 hours'));
    $resolution_sla = date('Y-m-d H:i:s', strtotime('+30 hours'));

    // Insert complaint with status 1 (Pending)
    $query = "INSERT INTO complaints 
    (title, description, category_id, area_id, user_id, priority, status_id, initial_sla_due, resolution_sla_due)
    VALUES ('$title','$desc','$category','$area','$user_id','$priority', 1, '$initial_sla', '$resolution_sla')";

    if (mysqli_query($conn, $query)) {
        $complaint_id = mysqli_insert_id($conn);

        // File Upload
        if (!empty($_FILES['file']['name'])) {
            $upload = uploadFile($_FILES['file']);
            if ($upload['status']) {
                $path = mysqli_real_escape_string($conn, $upload['path']);
                mysqli_query($conn, "INSERT INTO complaint_attachments (complaint_id, file_path) VALUES ('$complaint_id','$path')");
            }
        }
        
        set_flash_message('success', 'Complaint Registered Successfully! ID: #' . $complaint_id);
    } else {
        set_flash_message('error', 'Error: ' . mysqli_error($conn));
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-2">
    <div>
        <h2 class="mb-1">Register Complaint</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">New Registry</li>
            </ol>
        </nav>
    </div>
</div>

<?php display_flash_message(); ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-file-signature me-2"></i>Complaint Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="complaintForm">
                    <div class="form-group">
                        <label class="form-label">Complaint Title</label>
                        <input type="text" name="title" class="form-control" placeholder="What is the main issue?" required>
                        <div class="invalid-feedback">A title is required.</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Detailed Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Provide as much detail as possible..." required></textarea>
                        <div class="invalid-feedback">Description cannot be empty.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" id="category" class="form-control" required>
                                    <option value="">Choose category...</option>
                                    <?php
                                    $cat_res = mysqli_query($conn, "SELECT * FROM complaint_categories WHERE status=1");
                                    while ($c = mysqli_fetch_assoc($cat_res)) {
                                        echo "<option value='{$c['category_id']}'>" . htmlspecialchars($c['category_name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Location (Campus - Building - Spot)</label>
                                <select name="area" id="area" class="form-control" required>
                                    <option value="">Select location...</option>
                                    <?php
                                    $area_res = mysqli_query($conn, "SELECT * FROM area_master WHERE status=1");
                                    while ($a = mysqli_fetch_assoc($area_res)) {
                                        $area_display = htmlspecialchars($a['level1']) . " - " . htmlspecialchars($a['level2']);
                                        if (!empty($a['level3'])) {
                                            $area_display .= " - " . htmlspecialchars($a['level3']);
                                        }
                                        echo "<option value='{$a['area_id']}'>" . $area_display . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div id="duplicateAlert" class="alert alert-warning d-none" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-circle-exclamation fs-4 me-3"></i>
                            <div>
                                <span id="duplicateMsg"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Priority Label</label>
                                <select name="priority" class="form-control" required>
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label text-muted small fw-bold">Supporting Evidence (JPG, PNG, PDF)</label>
                                <div class="file-upload-wrapper" id="fileUploadWrapper">
                                    <input type="file" name="file" class="file-upload-input" id="formFile" accept=".jpg,.jpeg,.png,.pdf">
                                    <div class="file-upload-design">
                                        <div class="file-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                                        <div class="file-upload-text">Drop file here or click to browse</div>
                                        <div class="file-name-info d-none" id="fileNameDisplay"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary py-3 rounded-pill shadow-sm fw-bold" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 bg-primary-light h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-shield-halved me-2"></i>Helpful Guidelines</h5>
                <ul class="list-unstyled d-flex flex-column gap-3">
                    <li class="d-flex align-items-start gap-3">
                        <div class="bg-primary text-white rounded-circle p-1 px-2 small">1</div>
                        <p class="small text-dark mb-0"><strong>Precision:</strong> High-quality descriptions lead to 40% faster resolution times.</p>
                    </li>
                    <li class="d-flex align-items-start gap-3">
                        <div class="bg-primary text-white rounded-circle p-1 px-2 small">2</div>
                        <p class="small text-dark mb-0"><strong>Photos:</strong> Attaching clear photos of the issue is highly recommended.</p>
                    </li>
                    <li class="d-flex align-items-start gap-3">
                        <div class="bg-primary text-white rounded-circle p-1 px-2 small">3</div>
                        <p class="small text-dark mb-0"><strong>Uniqueness:</strong> Check existing complaints to avoid reporting duplicates.</p>
                    </li>
                    <li class="d-flex align-items-start gap-3">
                        <div class="bg-primary text-white rounded-circle p-1 px-2 small">4</div>
                        <p class="small text-dark mb-0"><strong>Priority:</strong> Only mark as "Critical" if there is an immediate safety risk.</p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Form Validation
    var forms = document.querySelectorAll('.needs-validation')
    Array.prototype.slice.call(forms).forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        } else {
            // Add loading spinner to button
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Submitting...';
            btn.disabled = true;
        }
        form.classList.add('was-validated')
      }, false)
    });

    // Real-time Duplicate Checking
    const catSelect = document.getElementById('category');
    const areaSelect = document.getElementById('area');
    const dupAlert = document.getElementById('duplicateAlert');
    const dupMsg = document.getElementById('duplicateMsg');

    function checkDuplicate() {
        if(catSelect.value && areaSelect.value) {
            fetch(`../ajax/check_duplicate.php?category=${catSelect.value}&area=${areaSelect.value}`)
                .then(response => response.text())
                .then(data => {
                    if (data.includes("Duplicate")) {
                        dupAlert.classList.remove("d-none");
                        dupMsg.innerText = "Warning: A similar complaint for this category and area was filed within the last 7 days.";
                    } else {
                        dupAlert.classList.add("d-none");
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }

    catSelect.addEventListener('change', checkDuplicate);
    areaSelect.addEventListener('change', checkDuplicate);
});
</script>

<?php include("../includes/footer.php"); ?>