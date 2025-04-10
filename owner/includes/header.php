<?php
// Include database connection and other required files
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/db_connect.php'; // Use the mysqli connection
require_once __DIR__ . '/../../includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

// Get owner user info
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'owner'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    // Redirect if not an owner
    header("Location: ../login.php");
    exit;
}

// Get owner's restaurants
$sql = "SELECT restaurant_id, name FROM restaurants WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$owner_restaurants = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if owner has any restaurants
$has_restaurants = !empty($owner_restaurants);

// Get the current restaurant ID (if viewing a specific restaurant)
$current_restaurant_id = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 
                         (isset($_SESSION['current_restaurant_id']) ? $_SESSION['current_restaurant_id'] : 0);

// If owner has restaurants but no current restaurant is set, use the first one
if ($has_restaurants && $current_restaurant_id == 0) {
    $current_restaurant_id = $owner_restaurants[0]['restaurant_id'];
    $_SESSION['current_restaurant_id'] = $current_restaurant_id;
}

// Get pending notifications (new reservations, new reviews)
$notifications = [];
$notification_count = 0;

if ($has_restaurants) {
    // Get restaurant IDs as a comma-separated string for SQL IN clause
    $restaurant_ids = implode(',', array_map(function($r) { 
        return $r['restaurant_id']; 
    }, $owner_restaurants));
    
    // Get new reservations (in the last 24 hours)
    $sql = "SELECT r.reservation_id, r.user_id, u.username, res.name as restaurant_name, 
            r.reservation_date, r.reservation_time, r.created_at
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            JOIN restaurants res ON r.restaurant_id = res.restaurant_id
            WHERE r.restaurant_id IN ($restaurant_ids)
            AND r.created_at > NOW() - INTERVAL 24 HOUR
            AND r.status = 'pending'
            ORDER BY r.created_at DESC
            LIMIT 5";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'type' => 'reservation',
                'id' => $row['reservation_id'],
                'message' => "New reservation from {$row['username']} at {$row['restaurant_name']}",
                'time' => $row['created_at'],
                'link' => "reservations.php?id={$row['reservation_id']}"
            ];
            $notification_count++;
        }
    }
    
    // Get new reviews (in the last 24 hours)
    $sql = "SELECT r.review_id, r.user_id, u.username, res.name as restaurant_name, 
            r.overall_rating, r.created_at
            FROM reviews r
            JOIN users u ON r.user_id = u.user_id
            JOIN restaurants res ON r.restaurant_id = res.restaurant_id
            WHERE r.restaurant_id IN ($restaurant_ids)
            AND r.created_at > NOW() - INTERVAL 24 HOUR
            ORDER BY r.created_at DESC
            LIMIT 5";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'type' => 'review',
                'id' => $row['review_id'],
                'message' => "New {$row['overall_rating']}-star review from {$row['username']} for {$row['restaurant_name']}",
                'time' => $row['created_at'],
                'link' => "reviews.php?id={$row['review_id']}"
            ];
            $notification_count++;
        }
    }
    
    // Sort notifications by time (most recent first)
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // Limit to 5 most recent
    $notifications = array_slice($notifications, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Owner Panel - Restaurant Review</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
    <!-- AlertifyJS CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">
    <!-- Owner Custom CSS -->
    <link rel="stylesheet" href="css/owner.css">
    
    <!-- Prevent automatic scrolling -->
    <script>
    // Store scroll position before page operations
    let lastScrollPosition = { x: 0, y: 0 };
    
    function saveScrollPosition() {
        lastScrollPosition = { 
            x: window.scrollX || window.pageXOffset, 
            y: window.scrollY || window.pageYOffset 
        };
    }
    
    function restoreScrollPosition() {
        window.scrollTo(lastScrollPosition.x, lastScrollPosition.y);
    }
    
    // Save position before any potential scroll-triggering operations
    document.addEventListener('DOMContentLoaded', function() {
        saveScrollPosition();
        
        // Restore position after charts or other elements are initialized
        setTimeout(restoreScrollPosition, 100);
        
        // Also restore after any window resize events (which can trigger layout shifts)
        window.addEventListener('resize', function() {
            setTimeout(restoreScrollPosition, 100);
        });
    });
    </script>
    <style>
        /* Base styling (same as admin but with different primary color) */
        :root {
            --primary-color: #2e7d32; /* Green color for owner panel */
            --sidebar-width: 280px;
            --topbar-height: 60px;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .admin-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Sidebar Styling */
        .admin-sidebar {
            width: var(--sidebar-width);
            background-color: #343a40;
            color: #fff;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }
        
        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding-top: 1rem;
        }
        
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .admin-sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .admin-sidebar .nav-link i {
            width: 24px;
            margin-right: 0.5rem;
        }
        
        .admin-sidebar .nav-header {
            color: rgba(255, 255, 255, 0.5);
            padding: 1rem 1rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Content Styling */
        .admin-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            height: 100vh;
            overflow-y: auto;
        }
        
        .admin-navbar {
            height: var(--topbar-height);
            background-color: #fff;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 90;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: #343a40;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }
        
        .sidebar-toggle:hover {
            background-color: #f8f9fa;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
        }
        
        .admin-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Restaurant Selector Styling */
        .restaurant-selector {
            position: relative;
            margin-right: 15px;
        }
        
        .restaurant-selector .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .admin-sidebar {
                left: -280px;
            }
            
            .admin-content {
                margin-left: 0;
                width: 100%;
            }
            
            .admin-sidebar.show {
                left: 0;
            }
            
            .admin-content-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 99;
            }
            
            .admin-content-overlay.show {
                display: block;
            }
        }
        
        /* DataTables fixes */
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
                    <button class="sidebar-toggle me-3" type="button" onclick="toggleSidebar(event)">
                        <i class="fas fa-bars"></i>
                    </button>
                    <script>
                    function toggleSidebar(e) {
                        e.preventDefault();
                        document.querySelector('.admin-sidebar').classList.toggle('show');
                        document.querySelector('.admin-content-overlay').classList.toggle('show');
                    }
                    </script>
                    <h5 class="mb-0">
                        <?php 
                        // Set page title based on current page
                        $current_page = basename($_SERVER['PHP_SELF']);
                        
                        switch($current_page) {
                            case 'index.php':
                                echo 'Dashboard';
                                break;
                            case 'restaurant.php':
                                echo 'My Restaurant';
                                break;
                            case 'menu.php':
                                echo 'Manage Menu';
                                break;
                            case 'reservations.php':
                                echo 'Reservations';
                                break;
                            case 'reviews.php':
                                echo 'Customer Reviews';
                                break;
                            case 'settings.php':
                                echo 'Settings';
                                break;
                            default:
                                echo 'Owner Panel';
                        }
                        ?>
                    </h5>
                </div>
                
                <div class="d-flex align-items-center">
                    <?php if ($has_restaurants && count($owner_restaurants) > 1): ?>
                    <div class="restaurant-selector dropdown me-3">
                        <button class="btn dropdown-toggle" type="button" id="restaurantDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php 
                            // Find the current restaurant name
                            $current_restaurant_name = "Select Restaurant";
                            foreach ($owner_restaurants as $restaurant) {
                                if ($restaurant['restaurant_id'] == $current_restaurant_id) {
                                    $current_restaurant_name = $restaurant['name'];
                                    break;
                                }
                            }
                            echo htmlspecialchars($current_restaurant_name);
                            ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="restaurantDropdown">
                            <?php foreach ($owner_restaurants as $restaurant): ?>
                                <li>
                                    <a class="dropdown-item <?php echo ($restaurant['restaurant_id'] == $current_restaurant_id) ? 'active' : ''; ?>" 
                                       href="?restaurant_id=<?php echo $restaurant['restaurant_id']; ?>">
                                        <?php echo htmlspecialchars($restaurant['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dropdown me-3">
                        <button class="btn btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell position-relative">
                                <?php if ($notification_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $notification_count; ?>
                                </span>
                                <?php endif; ?>
                            </i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if (empty($notifications)): ?>
                                <div class="dropdown-item">
                                    <p class="mb-0 text-muted">No new notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <a class="dropdown-item" href="<?php echo $notification['link']; ?>">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <?php if ($notification['type'] == 'reservation'): ?>
                                                    <i class="fas fa-calendar-alt text-primary"></i>
                                                <?php elseif ($notification['type'] == 'review'): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                <small class="text-muted"><?php echo timeAgo($notification['time']); ?></small>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center" href="notifications.php">View all notifications</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="admin-profile">
                                <?php if (!empty($owner['profile_image'])): ?>
                                    <img src="../uploads/profile/<?php echo $owner['profile_image']; ?>" alt="Owner">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 40px; height: 40px;">
                                        <?php echo strtoupper(substr($owner['first_name'] ?: $owner['username'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="ms-2 d-none d-md-block">
                                    <div class="fw-semibold">
                                        <?php 
                                        if (!empty($owner['first_name']) && !empty($owner['last_name'])) {
                                            echo $owner['first_name'] . ' ' . $owner['last_name'];
                                        } else {
                                            echo $owner['username'];
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">Restaurant Owner</small>
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
