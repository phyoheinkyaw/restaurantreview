<?php
include 'includes/header.php';

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">No user ID provided.</div>';
    echo '<div class="text-center mt-3"><a href="users.php" class="btn btn-primary">Back to Users</a></div>';
    include 'includes/footer.php';
    exit;
}

$user_id = intval($_GET['id']);

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    echo '<div class="text-center mt-3"><a href="users.php" class="btn btn-primary">Back to Users</a></div>';
    include 'includes/footer.php';
    exit;
}

// Get user's activity statistics
$stmt = $conn->prepare("SELECT COUNT(*) as reservation_count FROM reservations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation_count = $result->fetch_assoc()['reservation_count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as review_count FROM reviews WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$review_count = $result->fetch_assoc()['review_count'];
$stmt->close();

// Favorite count functionality will be added later
$favorite_count = 0;

// Get restaurant count for owners and admins
$restaurant_count = 0;
if ($user['role'] === 'owner' || $user['role'] === 'admin') {
    if ($user['role'] === 'owner') {
        $stmt = $conn->prepare("SELECT COUNT(*) as restaurant_count FROM restaurants WHERE owner_id = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        // Admin can see all restaurants
        $stmt = $conn->prepare("SELECT COUNT(*) as restaurant_count FROM restaurants");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $restaurant_count = $result->fetch_assoc()['restaurant_count'];
    $stmt->close();
}

// Get recent reservations
$stmt = $conn->prepare("
    SELECT r.*, res.name as restaurant_name 
    FROM reservations r
    JOIN restaurants res ON r.restaurant_id = res.restaurant_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 15
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get recent reviews
$stmt = $conn->prepare("
    SELECT r.*, res.name as restaurant_name 
    FROM reviews r
    JOIN restaurants res ON r.restaurant_id = res.restaurant_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 15
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_reviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Format role for display
$role_badges = [
    'admin' => '<span class="badge bg-danger">Admin</span>',
    'owner' => '<span class="badge bg-warning text-dark">Owner</span>',
    'user' => '<span class="badge bg-info">User</span>'
];

// Get account age
$created_date = new DateTime($user['created_at']);
$now = new DateTime();
$account_age = $created_date->diff($now);
$account_age_text = '';
if ($account_age->y > 0) {
    $account_age_text .= $account_age->y . ' year' . ($account_age->y > 1 ? 's' : '') . ' ';
}
if ($account_age->m > 0) {
    $account_age_text .= $account_age->m . ' month' . ($account_age->m > 1 ? 's' : '') . ' ';
}
if ($account_age->d > 0 || $account_age_text == '') {
    $account_age_text .= $account_age->d . ' day' . ($account_age->d > 1 ? 's' : '');
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">User Profile</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                <li class="breadcrumb-item active">View User</li>
            </ol>
        </div>
        <div>
            <a href="users.php" class="btn btn-outline-primary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Users
            </a>
            <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i> Edit User
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    User Information
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($user['profile_image']) && file_exists('../uploads/profile/' . $user['profile_image'])): ?>
                            <img src="../uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="Profile Image" class="img-fluid rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="profile-initial rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 150px; height: 150px; font-size: 60px;">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="mt-3"><?php echo htmlspecialchars($user['username']); ?></h3>
                        <p><?php echo $role_badges[$user['role']]; ?></p>
                    </div>

                    <div class="mb-3">
                        <h5>Account Information</h5>
                        <table class="table">
                            <tr>
                                <th>User ID:</th>
                                <td><?php echo $user['user_id']; ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Role:</th>
                                <td><?php echo $role_badges[$user['role']]; ?></td>
                            </tr>
                            <tr>
                                <th>Points:</th>
                                <td><?php echo $user['points']; ?></td>
                            </tr>
                            <tr>
                                <th>Joined:</th>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Account Age:</th>
                                <td><?php echo $account_age_text; ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="mb-3">
                        <h5>Personal Information</h5>
                        <table class="table">
                            <tr>
                                <th>First Name:</th>
                                <td><?php echo !empty($user['first_name']) ? htmlspecialchars($user['first_name']) : '<em>Not provided</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>Last Name:</th>
                                <td><?php echo !empty($user['last_name']) ? htmlspecialchars($user['last_name']) : '<em>Not provided</em>'; ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<em>Not provided</em>'; ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="text-center">
                        <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit User
                        </a>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    User Statistics
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="dashboard-card">
                                <div class="card-icon bg-primary-light text-primary mb-3">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <h6 class="card-title">Reservations</h6>
                                <h2 class="card-value"><?php echo $reservation_count; ?></h2>
                                <div class="card-trend">
                                    <i class="fas fa-clock me-1"></i> All time
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="dashboard-card">
                                <div class="card-icon bg-success-light text-success mb-3">
                                    <i class="fas fa-star"></i>
                                </div>
                                <h6 class="card-title">Reviews</h6>
                                <h2 class="card-value"><?php echo $review_count; ?></h2>
                                <div class="card-trend">
                                    <i class="fas fa-clock me-1"></i> All time
                                </div>
                            </div>
                        </div>
                        <?php if ($user['role'] === 'owner' || $user['role'] === 'admin'): ?>
                        <div class="col-md-3 mb-4">
                            <div class="dashboard-card">
                                <div class="card-icon bg-warning-light text-warning mb-3">
                                    <i class="fas fa-store"></i>
                                </div>
                                <h6 class="card-title">Restaurants</h6>
                                <h2 class="card-value"><?php echo $restaurant_count; ?></h2>
                                <div class="card-trend">
                                    <i class="fas fa-building me-1"></i> <?php echo $user['role'] === 'owner' ? 'Owned' : 'All restaurants'; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3 mb-4">
                            <div class="dashboard-card">
                                <div class="card-icon bg-danger-light text-danger mb-3">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <h6 class="card-title">Favorites</h6>
                                <h2 class="card-value">Coming Soon</h2>
                                <div class="card-trend">
                                    <i class="fas fa-info-circle me-1"></i> Feature in development
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reservations -->
                    <div class="mb-4">
                        <h5>Recent Reservations</h5>
                        <?php if (count($recent_reservations) > 0): ?>
                            <div class="table-responsive">
                                <table id="reservationsTable" class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th>Restaurant</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Party Size</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_reservations as $reservation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reservation['restaurant_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                                                <td><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></td>
                                                <td><?php echo $reservation['party_size']; ?></td>
                                                <td>
                                                    <?php 
                                                    $status_badges = [
                                                        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
                                                        'confirmed' => '<span class="badge bg-success">Confirmed</span>',
                                                        'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
                                                        'completed' => '<span class="badge bg-info">Completed</span>'
                                                    ];
                                                    echo $status_badges[$reservation['status']] ?? '<span class="badge bg-secondary">Unknown</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No reservations found for this user.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Reviews -->
                    <div>
                        <h5>Recent Reviews</h5>
                        <?php if (count($recent_reviews) > 0): ?>
                            <div class="table-responsive">
                                <table id="reviewsTable" class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th>Restaurant</th>
                                            <th>Rating</th>
                                            <th>Comment</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_reviews as $review): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($review['restaurant_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        if ($i <= $review['overall_rating']) {
                                                            echo '<i class="fas fa-star text-warning"></i>';
                                                        } else {
                                                            echo '<i class="far fa-star text-warning"></i>';
                                                        }
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($review['comment'], 0, 100)) . (strlen($review['comment']) > 100 ? '...' : ''); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No reviews found for this user.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables for reservations
    $('#reservationsTable').DataTable({
        responsive: true,
        order: [[1, 'desc']], // Sort by date column
        pageLength: 5, // Show 5 entries per page
        lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
        columnDefs: [
            { responsivePriority: 1, targets: [0, 1, 4] }, // Keep these columns visible on smaller screens
            { responsivePriority: 2, targets: [2, 3] }
        ]
    });
    
    // Initialize DataTables for reviews
    $('#reviewsTable').DataTable({
        responsive: true,
        order: [[3, 'desc']], // Sort by date column
        pageLength: 5, // Show 5 entries per page
        lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
        columnDefs: [
            { responsivePriority: 1, targets: [0, 1, 3] }, // Keep these columns visible on smaller screens
            { responsivePriority: 2, targets: [2] }
        ]
    });
});
</script>

<?php include 'includes/footer.php'; ?> 