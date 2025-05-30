<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Ensure necessary files are included
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Check if owner is logged in
if (!isset($owner) || empty($owner)) {
    header("Location: login.php");
    exit;
}

$user_id = $owner['user_id'];

// Handle marking all notifications as read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    // Mark all reviews as read
    $stmt = $conn->prepare("UPDATE reviews SET is_read = 1 
                           WHERE restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Mark all reservations as read
    $stmt = $conn->prepare("UPDATE reservations SET is_read = 1 
                           WHERE restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Redirect to remove POST data
    header("Location: notifications.php");
    exit;
}

// Handle marking a single notification as read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read']) && isset($_POST['type']) && isset($_POST['id'])) {
    $type = $_POST['type'];
    $id = intval($_POST['id']);
    
    if ($type === 'review') {
        // Mark review as read
        $stmt = $conn->prepare("UPDATE reviews SET is_read = 1 
                               WHERE review_id = ? AND 
                               restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
    } 
    elseif ($type === 'reservation' || $type === 'deposit') {
        // Mark reservation as read (deposits are handled through reservations)
        $stmt = $conn->prepare("UPDATE reservations SET is_read = 1 
                               WHERE reservation_id = ? AND 
                               restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
    }
    
    // Redirect to remove POST data
    header("Location: notifications.php");
    exit;
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$show_unread_only = isset($_GET['unread']) && $_GET['unread'] == '1';

// Get all reviews for this owner's restaurants
$reviews_where = "res.owner_id = ?";
if ($show_unread_only) {
    $reviews_where .= " AND r.is_read = 0";
}

$reviews_query = "SELECT r.review_id, r.restaurant_id, res.name AS restaurant_name, 
                     u.username, u.first_name, u.last_name, r.overall_rating, r.created_at,
                     r.is_read
                FROM reviews r 
                JOIN restaurants res ON r.restaurant_id = res.restaurant_id
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE $reviews_where
                ORDER BY r.created_at DESC";

$stmt = $conn->prepare($reviews_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = [];
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}

// Get all reservations for this owner's restaurants
$reservations_where = "res.owner_id = ?";
if ($show_unread_only) {
    $reservations_where .= " AND r.is_read = 0";
}

$reservations_query = "SELECT r.reservation_id, r.restaurant_id, res.name AS restaurant_name, 
                        u.username, u.first_name, u.last_name, r.party_size, 
                        r.reservation_date, r.reservation_time, r.status, r.created_at,
                        r.is_read, r.deposit_status, r.deposit_amount, r.deposit_payment_date
                      FROM reservations r 
                      JOIN restaurants res ON r.restaurant_id = res.restaurant_id
                      LEFT JOIN users u ON r.user_id = u.user_id
                      WHERE $reservations_where
                      ORDER BY r.created_at DESC";

$stmt = $conn->prepare($reservations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations_result = $stmt->get_result();
$reservations = [];
while ($row = $reservations_result->fetch_assoc()) {
    $reservations[] = $row;
}

// Count total unread notifications
$unread_reviews = 0;
$unread_reservations = 0;

foreach ($reviews as $review) {
    if (!$review['is_read']) $unread_reviews++;
}

foreach ($reservations as $reservation) {
    if (!$reservation['is_read']) $unread_reservations++;
}

$unread_count = $unread_reviews + $unread_reservations;

// Combine all notifications into one array for display
$all_notifications = [];

// Add reviews
foreach ($reviews as $review) {
    $reviewer_name = !empty($review['first_name']) ? 
                    $review['first_name'] . ' ' . $review['last_name'] : 
                    $review['username'];
    
    $all_notifications[] = [
        'id' => $review['review_id'],
        'type' => 'review',
        'message' => "New " . $review['overall_rating'] . "-star review from " . $reviewer_name,
        'is_read' => $review['is_read'],
        'created_at' => $review['created_at'],
        'restaurant_id' => $review['restaurant_id'],
        'restaurant_name' => $review['restaurant_name']
    ];
}

// Add reservations and deposit notifications
foreach ($reservations as $reservation) {
    $customer_name = !empty($reservation['first_name']) ? 
                    $reservation['first_name'] . ' ' . $reservation['last_name'] : 
                    $reservation['username'];
    
    $date = date('M j, Y', strtotime($reservation['reservation_date']));
    $time = date('g:i A', strtotime($reservation['reservation_time']));
    
    // Add reservation notification
    $all_notifications[] = [
        'id' => $reservation['reservation_id'],
        'type' => 'reservation',
        'message' => "New reservation from " . $customer_name . " for " . $reservation['party_size'] . 
                     " people on " . $date . " at " . $time,
        'is_read' => $reservation['is_read'],
        'created_at' => $reservation['created_at'],
        'restaurant_id' => $reservation['restaurant_id'],
        'restaurant_name' => $reservation['restaurant_name'],
        'status' => $reservation['status']
    ];
    
    // If this reservation has a deposit, add a deposit notification
    if ($reservation['deposit_status'] != 'not_required' && $reservation['deposit_amount'] > 0) {
        // Use deposit payment date if available, otherwise use reservation created date
        $deposit_date = !empty($reservation['deposit_payment_date']) ? 
                      $reservation['deposit_payment_date'] : $reservation['created_at'];
        
        $all_notifications[] = [
            'id' => $reservation['reservation_id'],
            'type' => 'deposit',
            'message' => "Deposit of $" . number_format($reservation['deposit_amount'], 2) . " for reservation from " . $customer_name,
            'is_read' => $reservation['is_read'],
            'created_at' => $deposit_date,
            'restaurant_id' => $reservation['restaurant_id'],
            'restaurant_name' => $reservation['restaurant_name'],
            'status' => $reservation['deposit_status']
        ];
    }
}

// Sort all notifications by created_at (newest first)
usort($all_notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get total count for pagination
$total_notifications = count($all_notifications);
$total_pages = ceil($total_notifications / $per_page);

// Slice the array based on pagination
$all_notifications = array_slice($all_notifications, $offset, $per_page);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-3">
                <i class="fas fa-bell me-2"></i> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </h2>
            <p class="text-muted">View notifications about reservations, reviews, and other updates for your restaurants.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-9">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Your Notifications</h5>
                    </div>
                    <div class="d-flex gap-2">
                        <!-- Unread filter -->
                        <a href="notifications.php<?php echo $show_unread_only ? '' : '?unread=1'; ?>" class="btn btn-sm <?php echo $show_unread_only ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-filter me-1"></i> <?php echo $show_unread_only ? 'Show All' : 'Show Unread Only'; ?>
                        </a>
                        
                        <!-- Mark all as read button -->
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-check-double me-1"></i> Mark all as read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($all_notifications)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-bell-slash fa-4x mb-3 text-muted"></i>
                            <h4>No notifications</h4>
                            <p class="text-muted">
                                <?php echo $show_unread_only ? 'You don\'t have any unread notifications.' : 'You don\'t have any notifications yet. They will appear here when you receive them.'; ?>
                            </p>
                            <?php if ($show_unread_only && $total_notifications > 0): ?>
                                <a href="notifications.php" class="btn btn-primary mt-2">Show All Notifications</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($all_notifications as $notification): ?>
                                <div class="list-group-item <?php echo !$notification['is_read'] ? 'bg-light border-start border-3 border-primary' : ''; ?>">
                                    <div class="d-flex">
                                        <!-- Notification icon -->
                                        <div class="me-3">
                                            <?php
                                            $icon_class = '';
                                            $icon = '<i class="fas fa-bell"></i>';
                                            $bg_color = '#6c757d'; // default secondary color
                                            
                                            switch ($notification['type']) {
                                                case 'reservation':
                                                    $icon = '<i class="fas fa-calendar-check"></i>';
                                                    $bg_color = '#28a745'; // success green
                                                    break;
                                                case 'review':
                                                    $icon = '<i class="fas fa-star"></i>';
                                                    $bg_color = '#ffc107'; // warning yellow
                                                    break;
                                                case 'deposit':
                                                    $icon = '<i class="fas fa-money-bill-wave"></i>';
                                                    $bg_color = '#17a2b8'; // info blue
                                                    break;
                                            }
                                            ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                                                 style="width: 40px; height: 40px; background-color: <?php echo $bg_color; ?>;">
                                                <?php echo $icon; ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" 
                                                          style="margin-top: -3px; margin-left: -10px;">
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Notification content -->
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="mb-1">
                                                    <?php
                                                    $type_label = 'Notification';
                                                    switch ($notification['type']) {
                                                        case 'reservation':
                                                            $type_label = 'New Reservation';
                                                            if (isset($notification['status']) && $notification['status'] != 'pending') {
                                                                $type_label = ucfirst($notification['status']) . ' Reservation';
                                                            }
                                                            break;
                                                        case 'review':
                                                            $type_label = 'New Review';
                                                            break;
                                                        case 'deposit':
                                                            $type_label = 'Deposit Payment';
                                                            if (isset($notification['status']) && $notification['status'] != 'pending') {
                                                                $type_label = ucfirst($notification['status']) . ' Deposit';
                                                            }
                                                            break;
                                                    }
                                                    echo $type_label;
                                                    ?>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-primary ms-2">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php 
                                                    $time_diff = time() - strtotime($notification['created_at']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' min ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M j, Y g:i A', strtotime($notification['created_at']));
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            
                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            
                                            <?php if (isset($notification['restaurant_name'])): ?>
                                                <small class="text-muted d-block mb-2">
                                                    <i class="fas fa-utensils me-1"></i>
                                                    <?php echo htmlspecialchars($notification['restaurant_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <?php if (!$notification['is_read']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="mark_read" value="1">
                                                        <input type="hidden" name="type" value="<?php echo $notification['type']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary me-2">
                                                            <i class="fas fa-check me-1"></i> Mark as read
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($notification['type'] == 'reservation'): ?>
                                                    <form method="POST" action="notification_redirect.php" class="d-inline">
                                                        <input type="hidden" name="type" value="reservation">
                                                        <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $notification['restaurant_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye me-1"></i> View Details
                                                        </button>
                                                    </form>
                                                <?php elseif ($notification['type'] == 'deposit'): ?>
                                                    <form method="POST" action="notification_redirect.php" class="d-inline">
                                                        <input type="hidden" name="type" value="deposit">
                                                        <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $notification['restaurant_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-money-bill-wave me-1"></i> View Deposit
                                                        </button>
                                                    </form>
                                                <?php elseif ($notification['type'] == 'review'): ?>
                                                    <form method="POST" action="notification_redirect.php" class="d-inline">
                                                        <input type="hidden" name="type" value="review">
                                                        <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $notification['restaurant_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye me-1"></i> View Review
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="p-3">
                            <nav aria-label="Notification pages">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="notifications.php?page=<?php echo $page - 1; ?><?php echo $show_unread_only ? '&unread=1' : ''; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="notifications.php?page=<?php echo $i; ?><?php echo $show_unread_only ? '&unread=1' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="notifications.php?page=<?php echo $page + 1; ?><?php echo $show_unread_only ? '&unread=1' : ''; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notification Flow Explanation Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">How Notifications Work</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-star text-warning me-2"></i>Review Notifications</h6>
                            <p>You'll receive a notification whenever a customer leaves a new review for one of your restaurants. Reviews are automatically shown here until you mark them as read.</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar-check text-success me-2"></i>Reservation Notifications</h6>
                            <p>New reservation notifications appear when customers make reservations at your restaurants. You can manage these reservations from the Reservations section.</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6><i class="fas fa-money-bill-wave text-info me-2"></i>Deposit Notifications</h6>
                            <p>When customers make deposits for reservations, you'll be notified so you can verify their payment and confirm their booking.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Notification Summary</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-star text-warning me-2"></i> Reviews</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $unread_reviews; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-check text-success me-2"></i> Reservations</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $unread_reservations; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-bell me-2"></i> Total Unread</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $unread_count; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="index.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                    <a href="reservations.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-calendar-alt me-2"></i> Manage Reservations
                        <?php if ($unread_reservations > 0): ?>
                            <span class="badge bg-danger rounded-pill float-end"><?php echo $unread_reservations; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reviews.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-star me-2"></i> View Reviews
                        <?php if ($unread_reviews > 0): ?>
                            <span class="badge bg-danger rounded-pill float-end"><?php echo $unread_reviews; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="deposit_settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave me-2"></i> Deposit Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<?php 
// Flush the output buffer and send content to browser
ob_end_flush(); 
?> 