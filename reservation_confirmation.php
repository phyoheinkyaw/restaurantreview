<?php
// Don't start a session here since it's already started in config.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if reservation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$reservation_id = intval($_GET['id']);

// Get reservation details
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, res.name as restaurant_name, res.address, res.phone, res.email, 
                   res.deposit_required, res.deposit_amount 
            FROM reservations r 
            JOIN restaurants res ON r.restaurant_id = res.restaurant_id 
            WHERE r.reservation_id = ? AND r.user_id = ?");
    
    $stmt->execute([$reservation_id, $user_id]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header("Location: index.php?error=reservation_not_found");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching reservation: " . $e->getMessage());
    header("Location: index.php?error=database_error");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Confirmation</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-md-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            <h1 class="mt-3">Reservation <?php echo ($reservation['deposit_status'] === 'pending') ? 'Submitted' : 'Confirmed'; ?></h1>
                            <?php if ($reservation['deposit_status'] === 'pending'): ?>
                                <p class="text-muted">Your reservation is pending deposit verification</p>
                            <?php else: ?>
                                <p class="text-muted">Your reservation has been confirmed</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Reservation Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Restaurant:</strong> <?php echo htmlspecialchars($reservation['restaurant_name']); ?></p>
                                        <p class="mb-1"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($reservation['reservation_date'])); ?></p>
                                        <p class="mb-1"><strong>Time:</strong> <?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></p>
                                        <p class="mb-1"><strong>Party Size:</strong> <?php echo $reservation['party_size']; ?> <?php echo $reservation['party_size'] === 1 ? 'person' : 'people'; ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Reservation ID:</strong> #<?php echo $reservation['reservation_id']; ?></p>
                                        <p class="mb-1"><strong>Status:</strong> 
                                            <?php 
                                            $status_class = '';
                                            switch ($reservation['status']) {
                                                case 'pending':
                                                    $status_class = 'text-warning';
                                                    break;
                                                case 'confirmed':
                                                    $status_class = 'text-success';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'text-danger';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'text-info';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?> fw-bold">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </p>
                                        <?php if (!empty($reservation['special_requests'])): ?>
                                            <p class="mb-1"><strong>Special Requests:</strong> <?php echo htmlspecialchars($reservation['special_requests']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($reservation['deposit_status'] === 'pending'): ?>
                            <div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Deposit Verification Pending</h5>
                                <p class="mb-0">Your deposit payment of $<?php echo number_format($reservation['deposit_amount'], 2); ?> is pending verification. The restaurant will review your payment slip and confirm your reservation shortly.</p>
                            </div>
                            
                            <?php if (!empty($reservation['deposit_payment_slip'])): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Your Payment Slip</h5>
                                </div>
                                <div class="card-body text-center">
                                    <img src="<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>" 
                                         alt="Payment Slip" class="img-fluid rounded mb-3" 
                                         style="max-height: 300px;">
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-info-circle me-1"></i>
                                        This is the payment slip you submitted. The restaurant will verify this payment.
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php elseif ($reservation['deposit_status'] === 'verified'): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Deposit Verified</h5>
                                <p class="mb-0">Your deposit payment of $<?php echo number_format($reservation['deposit_amount'], 2); ?> has been verified. Your reservation is confirmed.</p>
                            </div>
                        <?php elseif ($reservation['deposit_status'] === 'rejected'): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-times-circle me-2"></i>Deposit Rejected</h5>
                                <p>Your deposit payment was rejected. Reason: <?php echo htmlspecialchars($reservation['deposit_rejection_reason']); ?></p>
                                <p class="mb-0">Please contact the restaurant directly to resolve this issue.</p>
                            </div>
                        <?php elseif ($reservation['deposit_status'] === 'not_required' || empty($reservation['deposit_status'])): ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle me-2"></i>No Deposit Required</h5>
                                <p class="mb-0">This restaurant does not require a deposit for reservations.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Restaurant Information</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-primary"></i><?php echo htmlspecialchars($reservation['address']); ?></p>
                                <p class="mb-1"><i class="fas fa-phone me-2 text-primary"></i><?php echo htmlspecialchars($reservation['phone']); ?></p>
                                <?php if (!empty($reservation['email'])): ?>
                                    <p class="mb-1"><i class="fas fa-envelope me-2 text-primary"></i><?php echo htmlspecialchars($reservation['email']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="reservations.php" class="btn btn-primary me-2">
                                <i class="fas fa-list me-2"></i>View My Reservations
                            </a>
                            <a href="restaurant.php?id=<?php echo $reservation['restaurant_id']; ?>&reservation_success=1" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Return to Restaurant
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 