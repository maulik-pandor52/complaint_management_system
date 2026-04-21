<?php
session_start();
include("../config/db.php");

// Redirect if already logged in
if (isset($_SESSION['role_id'])) {
    header("Location: ../index.php");
    exit();
}

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // By default, public registration creates a regular "User" (Role 3)
    $role = 3; 

    if (empty($name) || empty($email) || empty($password)) {
        $error_msg = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        
        // Prevent SQL Injection - Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error_msg = "Email is already registered. Please login.";
            } else {
                $stmt->close();
                
                // Hash Password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert User securely
                $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
                $insert_stmt->bind_param("sssi", $name, $email, $hashed_password, $role);
                
                if ($insert_stmt->execute()) {
                    $success_msg = "Account created successfully! Redirecting to login...";
                    echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>";
                } else {
                    $error_msg = "Registration failed. Please try again.";
                }
                $insert_stmt->close();
            }
        } else {
            // Failsafe for missing DB or UI preview
            $error_msg = "Database connection error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | ResolveX</title>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .register-card {
            max-width: 480px;
            width: 100%;
            padding: 2.5rem;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.7);
        }
        .logo-area { text-align: center; margin-bottom: 2rem; }
        .logo-area .icon-box {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
            box-shadow: 0 8px 15px -3px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body>

    <div class="register-card">
        <div class="logo-area">
            <div class="icon-box"><i class="fas fa-user-plus"></i></div>
            <h2 class="fw-bold text-dark mb-1">Create Account</h2>
            <p class="text-muted small">Join ResolveX to track and resolve local issues</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="fas fa-circle-exclamation me-2"></i>
                <div class="small fw-medium"><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4">
                <i class="fas fa-circle-check me-2"></i>
                <div class="small fw-medium"><?= htmlspecialchars($success_msg) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group mb-3">
                <label for="name" class="form-label small fw-bold">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" placeholder="John Doe">
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label small fw-bold">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" placeholder="name@example.com">
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="password" class="form-label small fw-bold">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="confirm_password" class="form-label small fw-bold">Confirm</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>
            </div>
            
            <p class="small text-muted mb-4 mt-2">
                By registering, you agree to our <a href="#" class="text-decoration-none">Privacy Policy</a> and <a href="#" class="text-decoration-none">Terms of Service</a>.
            </p>

            <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-id-card me-2"></i>Create My Account
            </button>
        </form>

        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-muted mb-0">Already have an account? 
                <a href="login.php" class="text-primary fw-bold text-decoration-none ms-1">Log in here</a>
            </p>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
