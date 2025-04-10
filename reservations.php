<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user's reservations
try {
    $stmt = $db->prepare("
        SELECT r.*, 
               res.reservation_date,
               res.reservation_time,
               res.party_size,
               res.status as reservation_status,
               res.special_requests,
               res.created_at as reservation_created_at,
               res.reservation_id
        FROM restaurants r
        JOIN reservations res ON r.restaurant_id = res.restaurant_id
        WHERE res.user_id = ?
        ORDER BY res.reservation_date DESC, res.reservation_time DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
    $reservations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Restaurant Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <h2 class="mb-4">My Reservations</h2>

        <!-- Upcoming Reservations -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Upcoming Reservations</h3>
            </div>
            <div class="card-body">
                <?php if (empty($reservations)): ?>
                    <div class="alert alert-info">
                        You don't have any upcoming reservations.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Date & Time</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <?php
                                    $reservation_date = new DateTime($reservation['reservation_date']);
                                    $reservation_time = new DateTime($reservation['reservation_time']);
                                    $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
                                    $now = new DateTime();
                                    ?>
                                    <?php if ($reservation_datetime > $now): ?>
                                        <tr>
                                            <td>
                                                <h5 class="mb-0"><?php echo htmlspecialchars($reservation['name']); ?></h5>
                                                <small class="text-muted"><?php echo htmlspecialchars($reservation['cuisine_type']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo $reservation_date->format('M d, Y'); ?>
                                                <br>
                                                <?php echo $reservation_time->format('h:i A'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($reservation['party_size']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch ($reservation['reservation_status']) {
                                                        case 'pending': echo 'warning'; break;
                                                        case 'confirmed': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        case 'completed': echo 'info'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($reservation['reservation_status'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($reservation['reservation_status'] === 'pending' && $reservation_datetime > $now): ?>
                                                        <button type="button" 
                                                                class="btn btn-danger btn-sm cancel-reservation" 
                                                                data-reservation-id="<?php echo $reservation['reservation_id']; ?>">
                                                            Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" 
                                                            class="btn btn-secondary btn-sm" 
                                                            onclick="window.location.href='restaurant.php?id=<?php echo $reservation['restaurant_id']; ?>'">
                                                        View Restaurant
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Reservations -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h3 class="mb-0">Past Reservations</h3>
            </div>
            <div class="card-body">
                <?php if (empty($reservations)): ?>
                    <div class="alert alert-info">
                        You don't have any past reservations.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Restaurant</th>
                                    <th>Date & Time</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <?php
                                    $reservation_date = new DateTime($reservation['reservation_date']);
                                    $reservation_time = new DateTime($reservation['reservation_time']);
                                    $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
                                    $now = new DateTime();
                                    ?>
                                    <?php if ($reservation_datetime <= $now): ?>
                                        <tr>
                                            <td>
                                                <h5 class="mb-0"><?php echo htmlspecialchars($reservation['name']); ?></h5>
                                                <small class="text-muted"><?php echo htmlspecialchars($reservation['cuisine_type']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo $reservation_date->format('M d, Y'); ?>
                                                <br>
                                                <?php echo $reservation_time->format('h:i A'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($reservation['party_size']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch ($reservation['reservation_status']) {
                                                        case 'pending': echo 'warning'; break;
                                                        case 'confirmed': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        case 'completed': echo 'info'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($reservation['reservation_status'] ?? 'Unknown'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($reservation['reservation_status'] === 'completed'): ?>
                                                    <button type="button" 
                                                            class="btn btn-primary btn-sm write-review" 
                                                            data-restaurant-id="<?php echo $reservation['restaurant_id']; ?>">
                                                        Write Review
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/reservations.js"></script>
</body>
</html>
