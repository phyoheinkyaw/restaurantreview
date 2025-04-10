<?php
// Include database connection and other required files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/db_connect.php'; // Use the mysqli connection
require_once __DIR__ . '/../../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get admin user info
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    // Redirect if not an admin
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Restaurant Review</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- AlertifyJS CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">
    <!-- Admin Custom CSS -->
    <link rel="stylesheet" href="css/admin.css">
    <style>
        /* Additional DataTables fixes */
        .dataTables_wrapper .row {
            margin: 0;
            width: 100%;
        }
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_paginate {
            padding: 10px;
        }
        .table-responsive {
            overflow-x: hidden;
        }
        
        /* Improve column sizing */
        .dataTable {
            width: 100% !important;
            table-layout: auto !important;
        }
        
        /* Ensure columns size to content */
        .dataTable th, 
        .dataTable td {
            box-sizing: content-box;
        }
        
        /* Fix for Bootstrap row margins in DataTables */
        .dataTables_wrapper .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: 0;
            margin-left: 0;
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Navbar -->
            <div class="admin-navbar">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle me-3">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h5 class="mb-0">
                        <?php 
                        // Set page title based on current page
                        $current_page = basename($_SERVER['PHP_SELF']);
                        
                        switch($current_page) {
                            case 'index.php':
                                echo 'Dashboard';
                                break;
                            case 'restaurants.php':
                                echo 'Restaurants';
                                break;
                            case 'users.php':
                                echo 'Users';
                                break;
                            case 'reviews.php':
                                echo 'Reviews';
                                break;
                            case 'reservations.php':
                                echo 'Reservations';
                                break;
                            case 'settings.php':
                                echo 'Settings';
                                break;
                            default:
                                echo 'Admin Panel';
                        }
                        ?>
                    </h5>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell position-relative">
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    3
                                </span>
                            </i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">Notifications</h6>
                            <a class="dropdown-item" href="#">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-user-circle text-primary"></i>
                                    </div>
                                    <div>
                                        <p class="mb-0 fw-semibold">New user registered</p>
                                        <small class="text-muted">5 minutes ago</small>
                                    </div>
                                </div>
                            </a>
                            <a class="dropdown-item" href="#">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                    <div>
                                        <p class="mb-0 fw-semibold">New review submitted</p>
                                        <small class="text-muted">1 hour ago</small>
                                    </div>
                                </div>
                            </a>
                            <a class="dropdown-item" href="#">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                    </div>
                                    <div>
                                        <p class="mb-0 fw-semibold">System alert</p>
                                        <small class="text-muted">2 hours ago</small>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">View all notifications</a>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="admin-profile">
                                <?php if (!empty($admin['profile_image'])): ?>
                                    <img src="../<?php echo $admin['profile_image']; ?>" alt="Admin">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($admin['first_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ms-2 d-none d-md-block">
                                    <div class="fw-semibold"><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></div>
                                    <small class="text-muted">Administrator</small>
                                </div>
                            </div>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Page Content Container -->
            <div class="container-fluid p-4">
                <!-- Content will be placed here -->
            