<?php
// Include header
include 'includes/header.php';

// Get dashboard stats
$stats = [];

// Total restaurants
$sql = "SELECT COUNT(*) as total FROM restaurants";
$result = $conn->query($sql);
$stats['restaurants'] = $result->fetch_assoc()['total'];

// Total users
$sql = "SELECT COUNT(*) as total FROM users";
$result = $conn->query($sql);
$stats['users'] = $result->fetch_assoc()['total'];

// Total reviews
$sql = "SELECT COUNT(*) as total FROM reviews";
$result = $conn->query($sql);
$stats['reviews'] = $result->fetch_assoc()['total'];

// Average rating
$sql = "SELECT AVG(overall_rating) as avg_rating FROM reviews";
$result = $conn->query($sql);
$avg_rating = $result->fetch_assoc()['avg_rating'];
$stats['avg_rating'] = number_format($avg_rating, 1);

// Get recent reviews
$sql = "SELECT r.review_id, r.overall_rating, r.comment, r.created_at, 
               u.username, u.profile_image, 
               res.name as restaurant_name 
        FROM reviews r
        JOIN users u ON r.user_id = u.user_id
        JOIN restaurants res ON r.restaurant_id = res.restaurant_id
        ORDER BY r.created_at DESC LIMIT 5";
$recent_reviews = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get recent users
$sql = "SELECT user_id, username, email, profile_image, role, created_at
        FROM users
        ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get top rated restaurants
$sql = "SELECT res.restaurant_id, res.name, res.image, res.cuisine_type,
               AVG(r.overall_rating) as avg_rating,
               COUNT(r.review_id) as total_reviews
        FROM restaurants res
        LEFT JOIN reviews r ON res.restaurant_id = r.restaurant_id
        GROUP BY res.restaurant_id
        ORDER BY avg_rating DESC
        LIMIT 5";
$top_restaurants = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!-- Dashboard Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h3">Admin Dashboard</h1>
        <p class="text-muted">Welcome back, <?php echo $admin['first_name']; ?>! Here's what's happening today.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-calendar me-2"></i> Today
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">Today</a></li>
                <li><a class="dropdown-item" href="#">Yesterday</a></li>
                <li><a class="dropdown-item" href="#">Last 7 Days</a></li>
                <li><a class="dropdown-item" href="#">Last 30 Days</a></li>
                <li><a class="dropdown-item" href="#">This Month</a></li>
                <li><a class="dropdown-item" href="#">Last Month</a></li>
            </ul>
        </div>
        <a href="reports.php" class="btn btn-primary ms-2">
            <i class="fas fa-download me-2"></i> Export Report
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="row">
    <div class="col-md-6 col-lg-3">
        <div class="dashboard-card">
            <div class="card-icon bg-primary-light text-primary mb-3">
                <i class="fas fa-store"></i>
            </div>
            <h6 class="card-title">Total Restaurants</h6>
            <h2 class="card-value"><?php echo $stats['restaurants']; ?></h2>
            <div class="card-trend up">
                <i class="fas fa-arrow-up me-1"></i> 12% from last month
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="dashboard-card">
            <div class="card-icon bg-success-light text-success mb-3">
                <i class="fas fa-users"></i>
            </div>
            <h6 class="card-title">Total Users</h6>
            <h2 class="card-value"><?php echo $stats['users']; ?></h2>
            <div class="card-trend up">
                <i class="fas fa-arrow-up me-1"></i> 8% from last month
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="dashboard-card">
            <div class="card-icon bg-warning-light text-warning mb-3">
                <i class="fas fa-star"></i>
            </div>
            <h6 class="card-title">Total Reviews</h6>
            <h2 class="card-value"><?php echo $stats['reviews']; ?></h2>
            <div class="card-trend up">
                <i class="fas fa-arrow-up me-1"></i> 15% from last month
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="dashboard-card">
            <div class="card-icon bg-info-light text-info mb-3">
                <i class="fas fa-chart-line"></i>
            </div>
            <h6 class="card-title">Average Rating</h6>
            <h2 class="card-value"><?php echo $stats['avg_rating']; ?>/5</h2>
            <div class="card-trend up">
                <i class="fas fa-arrow-up me-1"></i> 0.2 from last month
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mt-4">
    <div class="col-md-8">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="chart-title mb-0">Reviews Overview</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary active">Monthly</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary">Weekly</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary">Daily</button>
                </div>
            </div>
            <div style="height: 300px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="chart-title mb-0">Rating Distribution</h5>
            </div>
            <div style="height: 300px;">
                <canvas id="reviewsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity and Top Restaurants -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="data-table">
            <div class="data-table-header d-flex justify-content-between align-items-center">
                <h5 class="data-table-title">Recent Reviews</h5>
                <a href="reviews.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Restaurant</th>
                            <th>Rating</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_reviews as $review): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($review['profile_image'])): ?>
                                        <img src="../<?php echo $review['profile_image']; ?>" alt="User" class="rounded-circle me-2" width="32" height="32">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-2" style="width: 32px; height: 32px;">
                                            <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span><?php echo $review['username']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $review['restaurant_name']; ?></td>
                            <td>
                                <div class="rating">
                                    <?php
                                    $rating = round($review['overall_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-muted"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="reviews.php?id=<?php echo $review['review_id']; ?>">View</a></li>
                                        <li><a class="dropdown-item" href="reviews.php?action=edit&id=<?php echo $review['review_id']; ?>">Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="reviews.php?action=delete&id=<?php echo $review['review_id']; ?>" data-confirm="Are you sure you want to delete this review?">Delete</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="data-table">
            <div class="data-table-header d-flex justify-content-between align-items-center">
                <h5 class="data-table-title">Top Rated Restaurants</h5>
                <a href="restaurants.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Restaurant</th>
                            <th>Cuisine</th>
                            <th>Rating</th>
                            <th>Reviews</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_restaurants as $restaurant): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($restaurant['image'])): ?>
                                        <img src="../<?php echo $restaurant['image']; ?>" alt="Restaurant" class="rounded me-2" width="40" height="40" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded me-2" style="width: 40px; height: 40px;">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span><?php echo $restaurant['name']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $restaurant['cuisine_type']; ?></td>
                            <td>
                                <div class="rating">
                                    <?php
                                    $rating = round($restaurant['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-muted"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td><?php echo $restaurant['total_reviews']; ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="restaurants.php?id=<?php echo $restaurant['restaurant_id']; ?>">View</a></li>
                                        <li><a class="dropdown-item" href="restaurants.php?action=edit&id=<?php echo $restaurant['restaurant_id']; ?>">Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="restaurants.php?action=delete&id=<?php echo $restaurant['restaurant_id']; ?>" data-confirm="Are you sure you want to delete this restaurant?">Delete</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Menu Statistics -->
<div class="row mt-4">
    <div class="col-12">
        <div class="data-table">
            <div class="data-table-header d-flex justify-content-between align-items-center">
                <h5 class="data-table-title">Menu Statistics</h5>
                <a href="menus.php" class="btn btn-sm btn-outline-primary">Manage All Menus</a>
            </div>
            <div class="row">
                <?php
                // Get menu statistics
                $sql = "SELECT COUNT(*) as total_items FROM menus";
                $total_items = $conn->query($sql)->fetch_assoc()['total_items'];
                
                $sql = "SELECT COUNT(*) as available_items FROM menus WHERE is_available = 1";
                $available_items = $conn->query($sql)->fetch_assoc()['available_items'];
                
                $sql = "SELECT COUNT(DISTINCT category) as total_categories FROM menus WHERE category IS NOT NULL AND category != ''";
                $total_categories = $conn->query($sql)->fetch_assoc()['total_categories'];
                
                $sql = "SELECT AVG(price) as avg_price FROM menus";
                $avg_price = $conn->query($sql)->fetch_assoc()['avg_price'];
                
                $sql = "SELECT r.name as restaurant_name, COUNT(m.menu_id) as menu_count 
                        FROM restaurants r 
                        JOIN menus m ON r.restaurant_id = m.restaurant_id 
                        GROUP BY r.restaurant_id 
                        ORDER BY menu_count DESC 
                        LIMIT 1";
                $result = $conn->query($sql);
                $most_items = $result->num_rows > 0 ? $result->fetch_assoc() : ['restaurant_name' => 'N/A', 'menu_count' => 0];
                ?>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="card-icon bg-primary-light text-primary mb-3">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h6 class="card-title">Total Menu Items</h6>
                        <h2 class="card-value"><?php echo $total_items; ?></h2>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="card-icon bg-success-light text-success mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h6 class="card-title">Available Items</h6>
                        <h2 class="card-value"><?php echo $available_items; ?></h2>
                        <div class="card-trend">
                            <?php echo round(($available_items / ($total_items ?: 1)) * 100); ?>% of total
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="card-icon bg-info-light text-info mb-3">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h6 class="card-title">Menu Categories</h6>
                        <h2 class="card-value"><?php echo $total_categories; ?></h2>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="dashboard-card">
                        <div class="card-icon bg-warning-light text-warning mb-3">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h6 class="card-title">Average Price</h6>
                        <h2 class="card-value">$<?php echo number_format($avg_price, 2); ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 p-3 bg-light rounded">
                <p class="mb-0">
                    <strong><?php echo htmlspecialchars($most_items['restaurant_name']); ?></strong> has the most menu items with 
                    <span class="badge bg-primary"><?php echo $most_items['menu_count']; ?> items</span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="row mt-4">
    <div class="col-12">
        <div class="data-table">
            <div class="data-table-header d-flex justify-content-between align-items-center">
                <h5 class="data-table-title">Recent Users</h5>
                <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Join Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../<?php echo $user['profile_image']; ?>" alt="User" class="rounded-circle me-2" width="32" height="32">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle me-2" style="width: 32px; height: 32px;">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span><?php echo $user['username']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($user['role'] == 'admin') ? 'danger' : (($user['role'] == 'owner') ? 'primary' : 'secondary'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td><span class="status-badge active">Active</span></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="users.php?id=<?php echo $user['user_id']; ?>">View</a></li>
                                        <li><a class="dropdown-item" href="users.php?action=edit&id=<?php echo $user['user_id']; ?>">Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" data-confirm="Are you sure you want to delete this user?">Delete</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.bg-primary-light {
    background-color: rgba(234, 158, 11, 0.1);
}
.bg-success-light {
    background-color: rgba(45, 194, 163, 0.1);
}
.bg-warning-light {
    background-color: rgba(246, 184, 60, 0.1);
}
.bg-info-light {
    background-color: rgba(90, 216, 191, 0.1);
}
.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
</style>

<script>
// Initialize charts
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 