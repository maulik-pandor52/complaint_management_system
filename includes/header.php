<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once __DIR__ . '/app_helper.php'; ?>
    <title><?= htmlspecialchars(app_name()) ?></title>
    <!-- Google Fonts: Poppins & Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- ChartJS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include_once("sidebar.php"); ?>
        <?php include_once("status_helper.php"); ?>

        <!-- Mobile Overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <!-- Page Content Wrapper -->
        <div id="page-content-wrapper" class="w-100 bg-light d-flex flex-column" style="min-height: 100vh; transition: all 0.3s;">
            <!-- Top Navigation -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm px-4 py-2">
                <div class="container-fluid p-0">
                    <button class="btn btn-outline-primary border-0 me-3 d-lg-none  " id="menu-toggle">
                        <i class="fas fa-bars-staggered fs-5"></i>
                    </button>

                    <a class="navbar-brand fw-bold text-primary" href="../index.php">Building Maintenance CMS</a>

                    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>

                    <div class="collapse navbar-collapse" id="navbarContent">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">    
                        </ul>

                        <div class="d-flex align-items-center gap-3">

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <div class="dropdown">
                                    <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-2 border-0 shadow-sm rounded-pill px-3" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <span class="fw-semibold d-none d-sm-inline"><?= htmlspecialchars($_SESSION['name'] ?? 'Account') ?></span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 p-2" aria-labelledby="userDropdown">
                                        <li><a class="dropdown-item py-2 rounded" href="#"><i class="fas fa-user-circle me-2 text-primary"></i> Profile</a></li>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item py-2 rounded text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <a href="../auth/login.php" class="btn btn-primary rounded-pill px-4">Login</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Main Content Area -->
            <main class="container-fluid p-4 flex-grow-1">