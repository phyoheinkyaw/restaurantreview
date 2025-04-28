<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'includes/db_connect.php';

// Get owner information
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'owner'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    header("Location: ../login.php");
    exit;
}

// Get restaurant information
$restaurant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    header("Location: restaurants.php");
    exit;
}

// Handle reservation status update
if (isset($_GET['update_status'])) {
    $reservation_id = $_GET['reservation_id'];
    $status = $_GET['status'];
    
    // Get reservation details
    $sql = "SELECT * FROM reservations WHERE reservation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reservation && $reservation['restaurant_id'] == $restaurant_id) {
        // Update reservation status
        $sql = "UPDATE reservations SET status = ? WHERE reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $reservation_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Reservation status updated successfully!";
    } else {
        $_SESSION['status_message'] = "Invalid reservation.";
    }
    
    header("Location: restaurant_reservations.php?id=" . $restaurant_id);
    exit;
}

// Get reservations with user information
$sql = "SELECT r.*, u.username, u.profile_image 
        FROM reservations r 
        LEFT JOIN users u ON r.user_id = u.user_id 
        WHERE r.restaurant_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Reservations - <?php echo htmlspecialchars($restaurant['name']); ?></h5>
                    <a href="restaurants.php" class="btn btn-secondary">Back to Restaurants</a>
                </div>
                <div class="card-body">
                    <!-- Reservation Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Reservations</h5>
                                    <h2 class="card-text"><?php echo count($reservations); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Reservations</h5>
                                    <h2 class="card-text"><?php echo array_reduce($reservations, function($carry, $item) {
                                        return $carry + ($item['status'] === 'pending' ? 1 : 0);
                                    }, 0); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Confirmed Reservations</h5>
                                    <h2 class="card-text"><?php echo array_reduce($reservations, function($carry, $item) {
                                        return $carry + ($item['status'] === 'confirmed' ? 1 : 0);
                                    }, 0); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reservation List -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Reservation Date</th>
                                    <th>Reservation Time</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Special Requests</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($reservation['profile_image'])): ?>
                                                <img src="../uploads/profile/<?php echo htmlspecialchars($reservation['profile_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($reservation['username']); ?>" 
                                                     class="rounded-circle" 
                                                     style="width: 30px; height: 30px; margin-right: 8px;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($reservation['username']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></td>
                                    <td><?php echo $reservation['party_size']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $reservation['status'] === 'pending' ? 'warning' : ($reservation['status'] === 'confirmed' ? 'success' : 'danger'); ?>">
                                            <?php echo ucfirst($reservation['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($reservation['special_requests']) ? htmlspecialchars($reservation['special_requests']) : '-'; ?></td>
                                    <td>
                                        <?php if ($reservation['status'] === 'pending'): ?>
                                        <form method="GET" class="d-inline status-form">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($reservation['status'] !== 'cancelled'): ?>
                                        <form method="GET" class="d-inline status-form">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['reservation_id']; ?>">
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Status Change Confirmation -->
                    <script>
                    $(document).ready(function() {
                        // Show status message if exists
                        <?php if (isset($_SESSION['status_message'])): ?>
                            <?php if (strpos($_SESSION['status_message'], 'Cannot') !== false): ?>
                                alertify.error("<?php echo $_SESSION['status_message']; ?>");
                            <?php else: ?>
                                alertify.success("<?php echo $_SESSION['status_message']; ?>");
                            <?php endif; ?>
                            <?php unset($_SESSION['status_message']); ?>
                        <?php endif; ?>

                        // Add confirmation dialog for status changes
                        $('.status-form').submit(function(e) {
                            e.preventDefault();
                            var form = $(this);
                            var newStatus = form.find('input[name="status"]').val();
                            var reservationId = form.find('input[name="reservation_id"]').val();
                            
                            var message = 'Are you sure you want to ' + (newStatus === 'confirmed' ? 'confirm' : 'cancel') + ' this reservation?';
                            
                            alertify.confirm(
                                'Confirm Reservation Status',
                                message,
                                function() {
                                    form.submit();
                                },
                                function() {
                                    alertify.error('Operation cancelled');
                                }
                            );
                        });
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
