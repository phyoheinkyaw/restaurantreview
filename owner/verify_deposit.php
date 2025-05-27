<?php
// Don't start a session here - it's already started in config.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a restaurant owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

$owner_id = $_SESSION['user_id'];

// Check if restaurant is selected
if (!isset($_SESSION['current_restaurant_id'])) {
    header("Location: restaurants.php");
    exit;
}

$restaurant_id = $_SESSION['current_restaurant_id'];

// Check if reservation ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: deposit_settings.php?error=invalid_id");
    exit;
}

$reservation_id = intval($_GET['id']);

// Verify that this reservation belongs to a restaurant owned by this owner
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, res.name as restaurant_name, u.first_name, u.last_name, u.email, u.phone 
            FROM reservations r 
            JOIN restaurants res ON r.restaurant_id = res.restaurant_id 
            JOIN users u ON r.user_id = u.user_id 
            WHERE r.reservation_id = ? AND res.owner_id = ? AND r.restaurant_id = ? AND r.deposit_status = 'pending'");
    
    $stmt->execute([$reservation_id, $owner_id, $restaurant_id]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        header("Location: deposit_settings.php?error=not_found");
        exit;
    }
} catch (PDOException $e) {
    error_log("Error fetching reservation data: " . $e->getMessage());
    header("Location: deposit_settings.php?error=database_error");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify') {
        try {
            // Verify the deposit
            $stmt = $db->prepare("UPDATE reservations SET 
                    deposit_status = 'verified',
                    deposit_verification_date = NOW(),
                    deposit_verified_by = ?,
                    status = 'confirmed'
                    WHERE reservation_id = ? AND restaurant_id = ?");
            
            $stmt->execute([$owner_id, $reservation_id, $restaurant_id]);
            
            // Send confirmation email to customer
            // (This would be implemented in a production environment)
            
            header("Location: deposit_settings.php?success=verified");
            exit;
        } catch (PDOException $e) {
            error_log("Error verifying deposit: " . $e->getMessage());
            $error_message = "Error verifying deposit: " . $e->getMessage();
        }
    } else if ($action === 'reject') {
        $rejection_reason = htmlspecialchars(trim(strip_tags($_POST['rejection_reason'] ?? '')));
        
        try {
            // Reject the deposit and cancel the reservation
            $stmt = $db->prepare("UPDATE reservations SET 
                    deposit_status = 'rejected',
                    deposit_rejection_reason = ?,
                    deposit_verification_date = NOW(),
                    deposit_verified_by = ?,
                    status = 'cancelled'
                    WHERE reservation_id = ? AND restaurant_id = ?");
            
            $stmt->execute([$rejection_reason, $owner_id, $reservation_id, $restaurant_id]);
            
            // Send rejection email to customer
            // (This would be implemented in a production environment)
            
            header("Location: deposit_settings.php?success=rejected");
            exit;
        } catch (PDOException $e) {
            error_log("Error rejecting deposit: " . $e->getMessage());
            $error_message = "Error rejecting deposit: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Deposit - Restaurant Owner Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/owner-dashboard.css">
    <style>
        .payment-slip-container {
            max-width: 100%;
            max-height: 500px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .payment-slip-container img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Verify Deposit Payment</h1>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Reservation Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Customer Information</h6>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
                                <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($reservation['phone']); ?></p>
                                <p class="mb-1"><strong>Party Size:</strong> <?php echo $reservation['party_size']; ?> people</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Reservation Information</h6>
                                <p class="mb-1"><strong>Restaurant:</strong> <?php echo htmlspecialchars($reservation['restaurant_name']); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($reservation['reservation_date'])); ?></p>
                                <p class="mb-1"><strong>Time:</strong> <?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></p>
                                <p class="mb-1"><strong>Special Requests:</strong> <?php echo htmlspecialchars($reservation['special_requests'] ?: 'None'); ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6>Deposit Information</h6>
                                <p class="mb-1"><strong>Amount:</strong> $<?php echo number_format($reservation['deposit_amount'], 2); ?></p>
                                <p class="mb-1"><strong>Payment Date:</strong> <?php echo date('F d, Y h:i A', strtotime($reservation['deposit_payment_date'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <h6>Payment Slip</h6>
                                <?php if (!empty($reservation['deposit_payment_slip'])): ?>
                                    <div class="payment-slip-container">
                                        <img src="../<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>" alt="Payment Slip" class="img-fluid border">
                                    </div>
                                    <div class="d-flex justify-content-center mb-3">
                                        <a href="../<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                            <i class="fas fa-external-link-alt me-1"></i> View Full Image
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-danger">No payment slip uploaded</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Verification Action</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <div class="card h-100 bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-success mb-3">Verify Payment</h5>
                                        <p>Confirm that the deposit payment is valid and approve the reservation.</p>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-check-circle me-1"></i> Approve Deposit
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title text-danger mb-3">Reject Payment</h5>
                                        <p>Reject the deposit if there's an issue with the payment slip.</p>
                                        <form method="POST" action="">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="mb-3">
                                                <textarea class="form-control" name="rejection_reason" rows="2" placeholder="Reason for rejection (required)" required></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-danger">
                                                <i class="fas fa-times-circle me-1"></i> Reject Deposit
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="deposit_settings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Deposits
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 