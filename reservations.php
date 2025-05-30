<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Random check to auto-update reservation statuses (1/10 chance to run)
if (rand(1, 10) === 1) {
    try {
        $db = getDB();
        
        // Mark past confirmed reservations as completed
        $stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'completed' 
            WHERE status = 'confirmed' 
            AND CONCAT(reservation_date, ' ', reservation_time) < NOW()
        ");
        $stmt->execute();
        
        // Log how many reservations were updated
        if ($stmt->rowCount() > 0) {
            error_log("Auto-updated " . $stmt->rowCount() . " past reservations to completed status");
        }
    } catch (PDOException $e) {
        error_log("Error in auto-update reservation status: " . $e->getMessage());
    }
}

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);

// Get user's reservations
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.*, 
               res.reservation_date,
               res.reservation_time,
               res.party_size,
               res.status as reservation_status,
               res.special_requests,
               res.created_at as reservation_created_at,
               res.reservation_id,
               res.deposit_status,
               res.deposit_amount,
               res.deposit_payment_slip,
               res.deposit_payment_date,
               res.deposit_rejection_reason
        FROM restaurants r
        JOIN reservations res ON r.restaurant_id = res.restaurant_id
        WHERE res.user_id = :user_id
        ORDER BY res.reservation_date DESC, res.reservation_time DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-4">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle img-fluid mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center mx-auto mb-3" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h5>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <span>Points</span>
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($user['points']); ?></span>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <a href="profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a href="reservations.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-calendar me-2"></i> My Reservations
                        </a>
                        <a href="reviews.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-star me-2"></i> My Reviews
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Upcoming Reservations -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Upcoming Reservations</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $upcoming_reservations = array_filter($reservations, function($reservation) {
                            $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
                            $now = new DateTime();
                            return $reservation_datetime > $now;
                        });
                        ?>
                        
                        <?php if (empty($upcoming_reservations)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i> You don't have any upcoming reservations.
                                <a href="index.php" class="alert-link">Find restaurants to make a reservation</a>.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Restaurant</th>
                                            <th>Date & Time</th>
                                            <th>Party Size</th>
                                            <th>Status</th>
                                            <th>Deposit</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_reservations as $reservation): ?>
                                            <?php
                                            $reservation_date = new DateTime($reservation['reservation_date']);
                                            $reservation_time = new DateTime($reservation['reservation_time']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($reservation['image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($reservation['image']); ?>" alt="<?php echo htmlspecialchars($reservation['name']); ?>" class="me-3 rounded" width="50" height="50" style="object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                <i class="fas fa-utensils text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($reservation['name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($reservation['cuisine_type'] ?? 'N/A'); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-medium"><?php echo $reservation_date->format('M d, Y'); ?></span>
                                                        <span class="text-muted"><?php echo $reservation_time->format('h:i A'); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($reservation['party_size']); ?> people</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($reservation['reservation_status']) {
                                                            'pending' => 'warning',
                                                            'confirmed' => 'success',
                                                            'cancelled' => 'danger',
                                                            'completed' => 'info',
                                                            default => 'secondary'
                                                        };
                                                    ?> rounded-pill px-3 py-2">
                                                        <?php echo ucfirst($reservation['reservation_status'] ?? 'Unknown'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservation['deposit_status'])): ?>
                                                        <?php if ($reservation['deposit_status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pending Verification</span>
                                                        <?php elseif ($reservation['deposit_status'] === 'verified'): ?>
                                                            <span class="badge bg-success">Verified</span>
                                                        <?php elseif ($reservation['deposit_status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Not Required</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not Required</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if (in_array($reservation['reservation_status'], ['pending', 'confirmed'])): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger cancel-reservation" 
                                                                    data-bs-toggle="modal" data-bs-target="#cancelModal"
                                                                    data-reservation-id="<?php echo $reservation['reservation_id']; ?>">
                                                                <i class="fas fa-times me-1"></i> Cancel
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary view-details" 
                                                                data-bs-toggle="modal" data-bs-target="#reservationDetailsModal"
                                                                data-reservation-id="<?php echo $reservation['reservation_id']; ?>"
                                                                data-restaurant-name="<?php echo htmlspecialchars($reservation['name']); ?>"
                                                                data-restaurant-image="<?php echo htmlspecialchars($reservation['image'] ?? ''); ?>"
                                                                data-cuisine-type="<?php echo htmlspecialchars($reservation['cuisine_type'] ?? 'N/A'); ?>"
                                                                data-address="<?php echo htmlspecialchars($reservation['address']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($reservation['phone']); ?>"
                                                                data-email="<?php echo htmlspecialchars($reservation['email'] ?? 'N/A'); ?>"
                                                                data-date="<?php echo $reservation_date->format('F d, Y'); ?>"
                                                                data-time="<?php echo $reservation_time->format('h:i A'); ?>"
                                                                data-party-size="<?php echo htmlspecialchars($reservation['party_size']); ?>"
                                                                data-status="<?php echo ucfirst($reservation['reservation_status']); ?>"
                                                                data-status-class="<?php echo match($reservation['reservation_status']) {
                                                                    'pending' => 'warning',
                                                                    'confirmed' => 'success',
                                                                    'cancelled' => 'danger',
                                                                    'completed' => 'info',
                                                                    default => 'secondary'
                                                                }; ?>"
                                                                data-deposit-status="<?php echo isset($reservation['deposit_status']) ? ucfirst($reservation['deposit_status']) : 'Not Required'; ?>"
                                                                data-deposit-amount="<?php echo isset($reservation['deposit_amount']) ? number_format($reservation['deposit_amount'], 2) : '0.00'; ?>"
                                                                data-special-requests="<?php echo htmlspecialchars($reservation['special_requests'] ?? 'None'); ?>"
                                                                data-restaurant-id="<?php echo $reservation['restaurant_id']; ?>"
                                                                data-payment-slip="<?php echo $reservation['deposit_payment_slip'] ?? ''; ?>"
                                                                data-rejection-reason="<?php echo htmlspecialchars($reservation['deposit_rejection_reason'] ?? ''); ?>">
                                                            <i class="fas fa-eye me-1"></i> View Details
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Past Reservations -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Past Reservations</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $past_reservations = array_filter($reservations, function($reservation) {
                            $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
                            $now = new DateTime();
                            return $reservation_datetime <= $now;
                        });
                        ?>
                        
                        <?php if (empty($past_reservations)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i> You don't have any past reservations.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Restaurant</th>
                                            <th>Date & Time</th>
                                            <th>Party Size</th>
                                            <th>Status</th>
                                            <th>Deposit</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($past_reservations as $reservation): ?>
                                            <?php
                                            $reservation_date = new DateTime($reservation['reservation_date']);
                                            $reservation_time = new DateTime($reservation['reservation_time']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($reservation['image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($reservation['image']); ?>" alt="<?php echo htmlspecialchars($reservation['name']); ?>" class="me-3 rounded" width="50" height="50" style="object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                <i class="fas fa-utensils text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($reservation['name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($reservation['cuisine_type'] ?? 'N/A'); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-medium"><?php echo $reservation_date->format('M d, Y'); ?></span>
                                                        <span class="text-muted"><?php echo $reservation_time->format('h:i A'); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($reservation['party_size']); ?> people</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($reservation['reservation_status']) {
                                                            'pending' => 'warning',
                                                            'confirmed' => 'success',
                                                            'cancelled' => 'danger',
                                                            'completed' => 'info',
                                                            default => 'secondary'
                                                        };
                                                    ?> rounded-pill px-3 py-2">
                                                        <?php echo ucfirst($reservation['reservation_status'] ?? 'Unknown'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if (isset($reservation['deposit_status'])): ?>
                                                        <?php if ($reservation['deposit_status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pending Verification</span>
                                                        <?php elseif ($reservation['deposit_status'] === 'verified'): ?>
                                                            <span class="badge bg-success">Verified</span>
                                                        <?php elseif ($reservation['deposit_status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger">Rejected</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Not Required</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Not Required</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($reservation['reservation_status'] === 'completed'): ?>
                                                        <?php
                                                        // Check if user has already reviewed this restaurant
                                                        $stmt = $db->prepare("SELECT review_id FROM reviews WHERE user_id = :user_id AND restaurant_id = :restaurant_id");
                                                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                                        $stmt->bindParam(':restaurant_id', $reservation['restaurant_id'], PDO::PARAM_INT);
                                                        $stmt->execute();
                                                        $has_review = $stmt->fetch(PDO::FETCH_ASSOC);
                                                        ?>
                                                        <?php if (!$has_review): ?>
                                                            <a href="review_test.php?restaurant_id=<?php echo $reservation['restaurant_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-star me-1"></i> Write Review
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check me-1"></i> Reviewed
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary view-details" 
                                                                data-bs-toggle="modal" data-bs-target="#reservationDetailsModal"
                                                                data-reservation-id="<?php echo $reservation['reservation_id']; ?>"
                                                                data-restaurant-name="<?php echo htmlspecialchars($reservation['name']); ?>"
                                                                data-restaurant-image="<?php echo htmlspecialchars($reservation['image'] ?? ''); ?>"
                                                                data-cuisine-type="<?php echo htmlspecialchars($reservation['cuisine_type'] ?? 'N/A'); ?>"
                                                                data-address="<?php echo htmlspecialchars($reservation['address']); ?>"
                                                                data-phone="<?php echo htmlspecialchars($reservation['phone']); ?>"
                                                                data-email="<?php echo htmlspecialchars($reservation['email'] ?? 'N/A'); ?>"
                                                                data-date="<?php echo $reservation_date->format('F d, Y'); ?>"
                                                                data-time="<?php echo $reservation_time->format('h:i A'); ?>"
                                                                data-party-size="<?php echo htmlspecialchars($reservation['party_size']); ?>"
                                                                data-status="<?php echo ucfirst($reservation['reservation_status']); ?>"
                                                                data-status-class="<?php echo match($reservation['reservation_status']) {
                                                                    'pending' => 'warning',
                                                                    'confirmed' => 'success',
                                                                    'cancelled' => 'danger',
                                                                    'completed' => 'info',
                                                                    default => 'secondary'
                                                                }; ?>"
                                                                data-deposit-status="<?php echo isset($reservation['deposit_status']) ? ucfirst($reservation['deposit_status']) : 'Not Required'; ?>"
                                                                data-deposit-amount="<?php echo isset($reservation['deposit_amount']) ? number_format($reservation['deposit_amount'], 2) : '0.00'; ?>"
                                                                data-special-requests="<?php echo htmlspecialchars($reservation['special_requests'] ?? 'None'); ?>"
                                                                data-restaurant-id="<?php echo $reservation['restaurant_id']; ?>"
                                                                data-payment-slip="<?php echo $reservation['deposit_payment_slip'] ?? ''; ?>"
                                                                data-rejection-reason="<?php echo htmlspecialchars($reservation['deposit_rejection_reason'] ?? ''); ?>">
                                                            <i class="fas fa-eye me-1"></i> View Details
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Reservation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancel Reservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Reservations cannot be cancelled less than 5 hours before the scheduled time.
                    </div>
                    <p>Are you sure you want to cancel this reservation? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <form id="cancelReservationForm" method="POST" action="process_reservation.php">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="reservation_id" id="cancelReservationId" value="">
                        <button type="submit" class="btn btn-danger">Cancel Reservation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Details Modal -->
    <div class="modal fade" id="reservationDetailsModal" tabindex="-1" aria-labelledby="reservationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="reservationDetailsModalLabel">Reservation Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">Restaurant Information</h5>
                            <div class="text-center mb-3">
                                <img id="restaurantImage" src="" alt="Restaurant Image" class="img-fluid rounded" style="max-height: 200px; display: none;">
                            </div>
                            <div class="mb-3">
                                <label for="restaurantName" class="form-label fw-bold">Restaurant Name</label>
                                <input type="text" class="form-control" id="restaurantName" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="cuisineType" class="form-label fw-bold">Cuisine Type</label>
                                <input type="text" class="form-control" id="cuisineType" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label fw-bold">Address</label>
                                <input type="text" class="form-control" id="address" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-bold">Phone</label>
                                <input type="text" class="form-control" id="phone" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Email</label>
                                <input type="text" class="form-control" id="email" readonly>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2 mb-3">Reservation Information</h5>
                            <div class="mb-3">
                                <label for="date" class="form-label fw-bold">Date</label>
                                <input type="text" class="form-control" id="date" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="time" class="form-label fw-bold">Time</label>
                                <input type="text" class="form-control" id="time" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="partySize" class="form-label fw-bold">Party Size</label>
                                <input type="text" class="form-control" id="partySize" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label fw-bold">Status</label>
                                <input type="text" class="form-control" id="status" readonly>
                                <div id="statusMessage" class="form-text mt-1" style="display: none;"></div>
                            </div>
                            <div class="mb-3">
                                <label for="depositStatus" class="form-label fw-bold">Deposit Status</label>
                                <input type="text" class="form-control" id="depositStatus" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="depositAmount" class="form-label fw-bold">Deposit Amount</label>
                                <input type="text" class="form-control" id="depositAmount" readonly>
                            </div>
                            <div class="mb-3 rejection-reason-section" style="display: none;">
                                <label for="rejectionReason" class="form-label fw-bold">Rejection Reason</label>
                                <textarea class="form-control bg-danger text-white" id="rejectionReason" readonly rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="specialRequests" class="form-label fw-bold">Special Requests</label>
                                <textarea class="form-control" id="specialRequests" readonly rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Slip Image Section -->
                    <div class="payment-slip-section mt-3" style="display: none;">
                        <h5 class="border-bottom pb-2 mb-3">Deposit Payment Slip</h5>
                        <div class="text-center">
                            <img id="paymentSlipImage" src="" alt="Payment Slip" class="img-fluid border rounded" style="max-height: 300px;">
                            <div class="mt-2">
                                <a id="viewFullSlipBtn" href="#" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-1"></i> View Full Image
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="restaurantLink" class="btn btn-primary">Visit Restaurant Page</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Handle cancellation modal
        document.querySelectorAll('.cancel-reservation').forEach(button => {
            button.addEventListener('click', function() {
                const reservationId = this.getAttribute('data-reservation-id');
                document.getElementById('cancelReservationId').value = reservationId;
            });
        });

        // Handle reservation details modal
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                // Get restaurant data from data attributes
                const restaurantName = this.getAttribute('data-restaurant-name');
                const restaurantImage = this.getAttribute('data-restaurant-image');
                const cuisineType = this.getAttribute('data-cuisine-type');
                const address = this.getAttribute('data-address');
                const phone = this.getAttribute('data-phone');
                const email = this.getAttribute('data-email');
                const date = this.getAttribute('data-date');
                const time = this.getAttribute('data-time');
                const partySize = this.getAttribute('data-party-size');
                const status = this.getAttribute('data-status');
                const depositStatus = this.getAttribute('data-deposit-status');
                const depositAmount = this.getAttribute('data-deposit-amount');
                const specialRequests = this.getAttribute('data-special-requests');
                const restaurantId = this.getAttribute('data-restaurant-id');
                const paymentSlip = this.getAttribute('data-payment-slip');
                const rejectionReason = this.getAttribute('data-rejection-reason');
                
                // Set values in modal
                document.getElementById('restaurantName').value = restaurantName;
                if (restaurantImage && restaurantImage !== '') {
                    document.getElementById('restaurantImage').src = restaurantImage;
                    document.getElementById('restaurantImage').style.display = 'block';
                } else {
                    document.getElementById('restaurantImage').style.display = 'none';
                }
                document.getElementById('cuisineType').value = cuisineType;
                document.getElementById('address').value = address || 'Not available';
                document.getElementById('phone').value = phone || 'Not available';
                document.getElementById('email').value = email;
                document.getElementById('date').value = date;
                document.getElementById('time').value = time;
                document.getElementById('partySize').value = partySize + ' people';
                
                // Set status with contextual color
                const statusInput = document.getElementById('status');
                statusInput.value = status;
                statusInput.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-info', 'bg-secondary', 'text-white');
                
                // Get the status message element
                const statusMessage = document.getElementById('statusMessage');
                statusMessage.style.display = 'none';
                
                if (status === 'Confirmed') {
                    statusInput.classList.add('bg-success', 'text-white');
                } else if (status === 'Pending') {
                    statusInput.classList.add('bg-warning');
                    statusMessage.innerHTML = '<i class="fas fa-info-circle me-1"></i> Your reservation is awaiting approval from the restaurant owner. You will receive a notification once it is confirmed.';
                    statusMessage.style.display = 'block';
                } else if (status === 'Cancelled') {
                    statusInput.classList.add('bg-danger', 'text-white');
                } else if (status === 'Completed') {
                    statusInput.classList.add('bg-info', 'text-white');
                } else {
                    statusInput.classList.add('bg-secondary', 'text-white');
                }
                
                // Set deposit status with contextual color
                const depositStatusInput = document.getElementById('depositStatus');
                depositStatusInput.value = depositStatus;
                depositStatusInput.classList.remove('bg-success', 'bg-warning', 'bg-danger', 'bg-secondary', 'text-white', 'text-dark');
                if (depositStatus === 'Verified') {
                    depositStatusInput.classList.add('bg-success', 'text-white');
                } else if (depositStatus === 'Pending') {
                    depositStatusInput.classList.add('bg-warning', 'text-dark');
                } else if (depositStatus === 'Rejected') {
                    depositStatusInput.classList.add('bg-danger', 'text-white');
                } else {
                    depositStatusInput.classList.add('bg-secondary', 'text-white');
                }
                
                // Handle deposit amount - don't show $ for Not Required
                if (depositStatus === 'Not Required') {
                    document.getElementById('depositAmount').value = 'Not Required';
                } else {
                    document.getElementById('depositAmount').value = '$' + depositAmount;
                }
                
                // Handle rejection reason section
                const rejectionReasonSection = document.querySelector('.rejection-reason-section');
                if (rejectionReason && depositStatus === 'Rejected') {
                    document.getElementById('rejectionReason').value = rejectionReason;
                    rejectionReasonSection.style.display = 'block';
                } else {
                    rejectionReasonSection.style.display = 'none';
                }
                
                document.getElementById('specialRequests').value = specialRequests;
                
                // Handle payment slip image
                const paymentSlipSection = document.querySelector('.payment-slip-section');
                if (paymentSlip && paymentSlip !== '') {
                    // Make sure path is correct (payment slips are stored with relative paths)
                    let slipPath = paymentSlip;
                    if (!slipPath.startsWith('http') && !slipPath.startsWith('/')) {
                        slipPath = './' + slipPath;
                    }
                    document.getElementById('paymentSlipImage').src = slipPath;
                    document.getElementById('viewFullSlipBtn').href = slipPath;
                    paymentSlipSection.style.display = 'block';
                } else {
                    paymentSlipSection.style.display = 'none';
                }
                
                // Set restaurant link
                document.getElementById('restaurantLink').href = 'restaurant.php?id=' + restaurantId;
            });
        });
    </script>
</body>
</html>
