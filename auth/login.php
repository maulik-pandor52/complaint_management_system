<?php
session_start();
include("../config/db.php");

// Redirect if already logged in
if (isset($_SESSION['role_id'])) {
    header("Location: ../index.php");
    exit();
}

$error_msg = "";
$prefill_email = isset($_COOKIE['remembered_email']) ? $_COOKIE['remembered_email'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize input
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    if(empty($email) || empty($password)) {
        $error_msg = "Please enter both email and password.";
    } else {
        // PREVENT SQL INJECTION by using Prepared Statements
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password'])) {
                    
                    // Set sessions
                    $_SESSION['user_id'] = $row['user_id'] ?? $row['id']; // fallbacks depending on schema
                    $_SESSION['role_id'] = $row['role_id'];
                    $_SESSION['name'] = $row['name'] ?? 'User';

                    // Extra Feature: Cookie usage "Remember Me"
                    if ($remember) {
                        setcookie("remembered_email", $email, time() + (86400 * 30), "/"); // 30 days
                    } else {
                        // clear cookie if unchecked
                        setcookie("remembered_email", "", time() - 3600, "/"); 
                    }

                    header("Location: ../index.php");
                    exit();
                } else {
                    $error_msg = "Incorrect password.";
                }
            } else {
                $error_msg = "User account not found.";
            }
            $stmt->close();
        } else {
            // Failsafe if DB isn't connected yet for UI preview
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
    <title>Login | ResolveX</title>
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS (for layout utilities) -->
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
        }
        .login-card {
            max-width: 420px;
            width: 100%;
            padding: 2.5rem;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .logo-area { text-align: center; margin-bottom: 2rem; }
        .logo-area .icon-box {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }
        .form-check-input:checked { background-color: var(--primary); border-color: var(--primary); }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="logo-area">
            <div class="icon-box"><i class="fas fa-tower-broadcast"></i></div>
            <h2 class="fw-bold text-dark mb-1">ResolveX</h2>
            <p class="text-muted small">Complaint Tracking System Access</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4">
                <i class="fas fa-circle-exclamation me-2"></i>
                <div class="small fw-medium"><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group mb-3">
                <label for="email" class="form-label small fw-bold">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($prefill_email) ?>" required autocomplete="email" autofocus placeholder="name@example.com">
            </div>

            <div class="form-group mb-4">
                <label for="password" class="form-label small fw-bold">Password</label>
                <div class="position-relative">
                    <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password" placeholder="••••••••">
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="form-check">
                    <input class="form-check-input shadow-none" type="checkbox" name="remember" id="remember" <?= $prefill_email ? 'checked' : '' ?>>
                    <label class="form-check-label small text-muted" for="remember">Remember me</label>
                </div>
                <a href="#" class="small text-primary fw-medium text-decoration-none">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>

        <div class="text-center mt-4 pt-2 border-top">
            <p class="small text-muted mb-0">Don't have an account? 
                <a href="register.php" class="text-primary fw-bold text-decoration-none ms-1">Create Account</a>
            </p>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>