<?php
// Don't start a session here - it's already started in includes/config.php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

// Process verification action if submitted
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $reservation_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'verify') {
        // Verify the deposit
        $sql = "UPDATE reservations SET 
                deposit_status = 'verified',
                deposit_verification_date = NOW(),
                deposit_verified_by = ?,
                status = 'confirmed'
                WHERE reservation_id = ? AND deposit_status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $admin_id, $reservation_id);
        
        if ($stmt->execute()) {
            $success_message = "Deposit for reservation #$reservation_id has been verified.";
        } else {
            $error_message = "Error verifying deposit: " . $conn->error;
        }
    } elseif ($action === 'reject' && isset($_POST['rejection_reason'])) {
        $rejection_reason = filter_var($_POST['rejection_reason'], FILTER_SANITIZE_STRING);
        
        // Reject the deposit
        $sql = "UPDATE reservations SET 
                deposit_status = 'rejected',
                deposit_rejection_reason = ?,
                deposit_verification_date = NOW(),
                deposit_verified_by = ?
                WHERE reservation_id = ? AND deposit_status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $rejection_reason, $admin_id, $reservation_id);
        
        if ($stmt->execute()) {
            $success_message = "Deposit for reservation #$reservation_id has been rejected.";
        } else {
            $error_message = "Error rejecting deposit: " . $conn->error;
        }
    }
}

// Get pending deposit verifications
$sql = "SELECT r.*, 
               res.name as restaurant_name, 
               u.first_name, u.last_name, u.email, u.phone,
               o.first_name as owner_first_name, o.last_name as owner_last_name
        FROM reservations r 
        JOIN restaurants res ON r.restaurant_id = res.restaurant_id 
        JOIN users u ON r.user_id = u.user_id 
        JOIN users o ON res.owner_id = o.user_id
        WHERE r.deposit_status = 'pending'
        ORDER BY r.reservation_date ASC, r.reservation_time ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$pending_deposits = $stmt->get_result();

// Get recent verifications (last 10)
$sql = "SELECT r.*, 
               res.name as restaurant_name, 
               u.first_name, u.last_name,
               a.first_name as admin_first_name, a.last_name as admin_last_name
        FROM reservations r 
        JOIN restaurants res ON r.restaurant_id = res.restaurant_id 
        JOIN users u ON r.user_id = u.user_id 
        JOIN users a ON r.deposit_verified_by = a.user_id
        WHERE r.deposit_status IN ('verified', 'rejected') 
        ORDER BY r.deposit_verification_date DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->execute();
$recent_verifications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Verifications - Admin Panel</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .payment-slip-thumbnail {
            max-height: 50px;
            max-width: 50px;
            cursor: pointer;
        }
        .modal-image {
            max-width: 100%;
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
                    <h1 class="h2">Deposit Verifications</h1>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Pending Deposit Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($pending_deposits->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="pendingDepositsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Customer</th>
                                            <th>Restaurant</th>
                                            <th>Date & Time</th>
                                            <th>Amount</th>
                                            <th>Payment Slip</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($reservation = $pending_deposits->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo $reservation['reservation_id']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($reservation['email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($reservation['restaurant_name']); ?><br>
                                                    <small class="text-muted">Owner: <?php echo htmlspecialchars($reservation['owner_first_name'] . ' ' . $reservation['owner_last_name']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?><br>
                                                    <small class="text-muted"><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></small>
                                                </td>
                                                <td>$<?php echo number_format($reservation['deposit_amount'], 2); ?></td>
                                                <td>
                                                    <?php if (!empty($reservation['deposit_payment_slip'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>" 
                                                             alt="Payment Slip" 
                                                             class="payment-slip-thumbnail"
                                                             data-bs-toggle="modal" 
                                                             data-bs-target="#imageModal" 
                                                             data-img-src="../<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>">
                                                    <?php else: ?>
                                                        <span class="text-danger">No image</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="?action=verify&id=<?php echo $reservation['reservation_id']; ?>" 
                                                           class="btn btn-sm btn-success"
                                                           onclick="return confirm('Are you sure you want to verify this deposit?');">
                                                            <i class="fas fa-check me-1"></i> Verify
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#rejectModal"
                                                                data-reservation-id="<?php echo $reservation['reservation_id']; ?>">
                                                            <i class="fas fa-times me-1"></i> Reject
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No pending deposit verifications</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Verifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_verifications->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="recentVerificationsTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Customer</th>
                                            <th>Restaurant</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <th>Verified By</th>
                                            <th>Verification Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($verification = $recent_verifications->fetch_assoc()): ?>
                                            <tr>
                                                <td>#<?php echo $verification['reservation_id']; ?></td>
                                                <td><?php echo htmlspecialchars($verification['first_name'] . ' ' . $verification['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($verification['restaurant_name']); ?></td>
                                                <td>
                                                    <?php if ($verification['deposit_status'] === 'verified'): ?>
                                                        <span class="badge bg-success">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($verification['deposit_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($verification['admin_first_name'] . ' ' . $verification['admin_last_name']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($verification['deposit_verification_date'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No recent verifications</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Payment Slip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Payment Slip" class="modal-image" id="modalImage">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="reservation_id" id="rejectionReservationId">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="rejectModalLabel">Reject Deposit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required></textarea>
                            <div class="form-text">Please provide a clear reason for rejecting this deposit payment.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirmRejectBtn">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // The DataTable initialization is now handled centrally in admin.js
        
        $(document).ready(function() {
            // Handle image modal
            $('#imageModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var imgSrc = button.data('img-src');
                var modal = $(this);
                modal.find('#modalImage').attr('src', imgSrc);
            });
            
            // Handle reject modal
            $('#rejectModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var reservationId = button.data('reservation-id');
                var modal = $(this);
                
                // Set the reservation ID for the form action
                modal.find('#rejectionReservationId').val(reservationId);
                modal.find('form').attr('action', '?action=reject&id=' + reservationId);
            });
        });
    </script>
</body>
</html> 