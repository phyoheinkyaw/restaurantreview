<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include header
require_once 'includes/header.php';

// Get statistics for the current restaurant
if ($has_restaurants) {
    // Get restaurant details
    $sql = "SELECT * FROM restaurants WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $restaurant = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get total reservations
    $sql = "SELECT 
            COUNT(*) as total_reservations,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reservations,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_reservations,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_reservations
            FROM reservations 
            WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $reservation_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get review statistics
    $sql = "SELECT 
            COUNT(*) as total_reviews,
            ROUND(AVG(overall_rating), 1) as average_rating,
            COUNT(CASE WHEN overall_rating >= 4 THEN 1 END) as positive_reviews
            FROM reviews 
            WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $review_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get menu statistics
    $sql = "SELECT 
            COUNT(*) as total_items,
            COUNT(CASE WHEN is_available = 1 THEN 1 END) as available_items,
            COUNT(DISTINCT category) as categories,
            ROUND(AVG(price), 2) as average_price
            FROM menus 
            WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $menu_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get recent reservations
    $sql = "SELECT r.*, u.username, u.first_name, u.last_name 
            FROM reservations r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.restaurant_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $recent_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get recent reviews
    $sql = "SELECT r.*, u.username, u.first_name, u.last_name 
            FROM reviews r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.restaurant_id = ?
            ORDER BY r.created_at DESC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $recent_reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get monthly reservation counts for chart
    $sql = "SELECT 
            DATE_FORMAT(reservation_date, '%Y-%m') as month,
            COUNT(*) as count
            FROM reservations
            WHERE restaurant_id = ?
            AND reservation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(reservation_date, '%Y-%m')
            ORDER BY month";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $monthly_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get rating distribution for chart
    $sql = "SELECT 
            overall_rating,
            COUNT(*) as count
            FROM reviews
            WHERE restaurant_id = ?
            GROUP BY overall_rating
            ORDER BY overall_rating";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_restaurant_id);
    $stmt->execute();
    $rating_distribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!-- Dashboard Content -->
<?php if (!$has_restaurants): ?>
    <!-- No Restaurant View -->
    <div class="text-center py-5">
        <div class="display-1 text-muted mb-4">
            <i class="fas fa-store"></i>
        </div>
        <h2 class="mb-4">Welcome to Restaurant Review!</h2>
        <p class="lead text-muted mb-4">Get started by adding your restaurant to our platform.</p>
        <a href="add_restaurant.php" class="btn btn-primary btn-lg">
            <i class="fas fa-plus-circle me-2"></i>
            Add Your Restaurant
        </a>
    </div>
<?php else: ?>
    <!-- Restaurant Dashboard -->
    <div class="row g-4">
        <!-- Statistics Cards -->
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm bg-primary-subtle">
                                <i class="fas fa-calendar-check fa-lg text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Total Reservations</h6>
                            <h4 class="mb-0"><?php echo number_format($reservation_stats['total_reservations'] ?? 0); ?></h4>
                            <small class="text-muted">
                                <?php echo number_format($reservation_stats['pending_reservations'] ?? 0); ?> pending
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm bg-warning-subtle">
                                <i class="fas fa-star fa-lg text-warning"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Average Rating</h6>
                            <h4 class="mb-0"><?php echo number_format($review_stats['average_rating'] ?? 0, 1); ?></h4>
                            <small class="text-muted">
                                from <?php echo number_format($review_stats['total_reviews'] ?? 0); ?> reviews
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm bg-success-subtle">
                                <i class="fas fa-utensils fa-lg text-success"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Menu Items</h6>
                            <h4 class="mb-0"><?php echo number_format($menu_stats['total_items'] ?? 0); ?></h4>
                            <small class="text-muted">
                                in <?php echo number_format($menu_stats['categories'] ?? 0); ?> categories
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-sm bg-info-subtle">
                                <i class="fas fa-dollar-sign fa-lg text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Average Price</h6>
                            <h4 class="mb-0">$<?php echo number_format($menu_stats['average_price'] ?? 0, 2); ?></h4>
                            <small class="text-muted">
                                per menu item
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Reservation Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="reservationChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Rating Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="ratingChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Reservations</h5>
                    <a href="reservations.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_reservations)): ?>
                                <?php foreach ($recent_reservations as $reservation): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            echo !empty($reservation['first_name']) ? 
                                                htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) :
                                                htmlspecialchars($reservation['username']);
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                                        <td><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending' => 'bg-warning',
                                                'confirmed' => 'bg-success',
                                                'completed' => 'bg-info',
                                                'cancelled' => 'bg-danger'
                                            ][$reservation['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No recent reservations</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Reviews</h5>
                    <a href="reviews.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_reviews)): ?>
                        <?php foreach ($recent_reviews as $review): ?>
                            <div class="d-flex mb-4">
                                <div class="flex-shrink-0">
                                    <div class="avatar avatar-sm bg-primary-subtle text-primary rounded">
                                        <?php echo strtoupper(substr($review['first_name'] ?: $review['username'], 0, 1)); ?>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex align-items-center mb-1">
                                        <h6 class="mb-0">
                                            <?php 
                                            echo !empty($review['first_name']) ? 
                                                htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) :
                                                htmlspecialchars($review['username']);
                                            ?>
                                        </h6>
                                        <div class="ms-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['overall_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted ms-auto">
                                            <?php echo timeAgo($review['created_at']); ?>
                                        </small>
                                    </div>
                                    <p class="mb-0"><?php echo htmlspecialchars($review['comment']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted">No recent reviews</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Initialize Charts -->
    <script>
    window.addEventListener('load', function() {
        // Prepare data for reservation chart
        const reservationData = {
            labels: <?php echo json_encode(array_map(function($item) {
                return date('M Y', strtotime($item['month'] . '-01'));
            }, $monthly_reservations ?? [])); ?>,
            datasets: [{
                label: 'Reservations',
                data: <?php echo json_encode(array_map(function($item) {
                    return $item['count'];
                }, $monthly_reservations ?? [])); ?>,
                fill: true,
                backgroundColor: 'rgba(46, 125, 50, 0.1)',
                borderColor: 'rgb(46, 125, 50)',
                tension: 0.4
            }]
        };
        
        // Initialize reservation chart
        const reservationCtx = document.getElementById('reservationChart');
        if (reservationCtx) {
            new Chart(reservationCtx, {
                type: 'line',
                data: reservationData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        // Prepare data for rating chart
        const ratingData = {
            labels: ['1★', '2★', '3★', '4★', '5★'],
            datasets: [{
                data: <?php 
                    $ratings = array_fill(0, 5, 0);
                    if (!empty($rating_distribution)) {
                        foreach ($rating_distribution as $rating) {
                            if (isset($rating['overall_rating']) && $rating['overall_rating'] >= 1 && $rating['overall_rating'] <= 5) {
                                $rating_index = intval($rating['overall_rating']) - 1;
                                if ($rating_index >= 0 && $rating_index < 5) {
                                    $ratings[$rating_index] = $rating['count'];
                                }
                            }
                        }
                    }
                    echo json_encode($ratings);
                ?>,
                backgroundColor: [
                    '#dc3545',
                    '#fd7e14',
                    '#ffc107',
                    '#20c997',
                    '#198754'
                ]
            }]
        };
        
        // Initialize rating chart
        const ratingCtx = document.getElementById('ratingChart');
        if (ratingCtx) {
            new Chart(ratingCtx, {
                type: 'doughnut',
                data: ratingData,
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });
    </script>
<?php endif; ?>
    <?php 
// Include footer with absolute path
require_once __DIR__ . '/includes/footer.php'; 
?>    
<!-- Fix for scrolling and sidebar toggle issues -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fix for sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('.admin-sidebar').classList.toggle('show');
            document.querySelector('.admin-content-overlay').classList.toggle('show');
        });
    }
    
    // Fix for content overlay
    const contentOverlay = document.querySelector('.admin-content-overlay');
    if (contentOverlay) {
        contentOverlay.addEventListener('click', function() {
            document.querySelector('.admin-sidebar').classList.remove('show');
            document.querySelector('.admin-content-overlay').classList.remove('show');
        });
    }
    
    // Prevent automatic scrolling
    window.scrollTo(window.scrollX, window.scrollY);
});
</script>
