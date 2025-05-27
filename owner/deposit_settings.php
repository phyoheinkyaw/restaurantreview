<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

$owner_id = $_SESSION['user_id'];

// Handle restaurant selection
if (isset($_GET['restaurant_id'])) {
    $_SESSION['current_restaurant_id'] = intval($_GET['restaurant_id']);
    header("Location: deposit_settings.php");
    exit;
}

// Check if restaurant is selected
if (!isset($_SESSION['current_restaurant_id'])) {
    // Get the first restaurant owned by this owner
    $sql = "SELECT restaurant_id FROM restaurants WHERE owner_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Owner doesn't have a restaurant
        header("Location: index.php?error=no_restaurant");
        exit;
    }
    
    $_SESSION['current_restaurant_id'] = $result->fetch_assoc()['restaurant_id'];
}

$restaurant_id = $_SESSION['current_restaurant_id'];

// Get restaurant information
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Restaurant not found or not owned by this user
    header("Location: restaurants.php");
    exit;
}

$restaurant = $result->fetch_assoc();
$stmt->close();

// Check if the deposit columns exist in the restaurants table
$check_columns = $conn->query("SHOW COLUMNS FROM restaurants LIKE 'deposit_required'");
if ($check_columns->num_rows === 0) {
    // Add deposit columns if they don't exist
    $alter_sql = "ALTER TABLE restaurants 
                ADD COLUMN deposit_required TINYINT(1) NOT NULL DEFAULT 0,
                ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                ADD COLUMN deposit_account_name VARCHAR(255) NULL DEFAULT '',
                ADD COLUMN deposit_account_number VARCHAR(50) NULL DEFAULT '',
                ADD COLUMN deposit_bank_name VARCHAR(255) NULL DEFAULT '',
                ADD COLUMN deposit_payment_instructions TEXT NULL";
    $conn->query($alter_sql);
    
    // Refresh restaurant data after adding columns
    $sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $restaurant_id, $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $restaurant = $result->fetch_assoc();
    $stmt->close();
}

// Check if the deposit columns exist in the reservations table
$check_reservations_columns = $conn->query("SHOW COLUMNS FROM reservations LIKE 'deposit_payment_date'");
if ($check_reservations_columns->num_rows === 0) {
    // Add deposit columns if they don't exist
    $alter_reservations_sql = "ALTER TABLE reservations 
                ADD COLUMN deposit_status ENUM('pending', 'verified', 'rejected') NULL,
                ADD COLUMN deposit_amount DECIMAL(10,2) NULL,
                ADD COLUMN deposit_payment_date DATETIME NULL,
                ADD COLUMN deposit_payment_slip VARCHAR(255) NULL,
                ADD COLUMN deposit_verification_date DATETIME NULL,
                ADD COLUMN deposit_verified_by INT NULL,
                ADD COLUMN deposit_rejection_reason TEXT NULL";
    $conn->query($alter_reservations_sql);
}

// Set default values if any fields are not initialized
if (!isset($restaurant['deposit_required'])) $restaurant['deposit_required'] = 0;
if (!isset($restaurant['deposit_amount'])) $restaurant['deposit_amount'] = 0;
if (!isset($restaurant['deposit_account_name'])) $restaurant['deposit_account_name'] = '';
if (!isset($restaurant['deposit_account_number'])) $restaurant['deposit_account_number'] = '';
if (!isset($restaurant['deposit_bank_name'])) $restaurant['deposit_bank_name'] = '';
if (!isset($restaurant['deposit_payment_instructions'])) $restaurant['deposit_payment_instructions'] = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $deposit_required = isset($_POST['deposit_required']) ? 1 : 0;
    $deposit_amount = isset($_POST['deposit_amount']) ? filter_var($_POST['deposit_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : 0;
    $deposit_account_name = isset($_POST['deposit_account_name']) ? htmlspecialchars(trim(strip_tags($_POST['deposit_account_name']))) : '';
    $deposit_account_number = isset($_POST['deposit_account_number']) ? htmlspecialchars(trim(strip_tags($_POST['deposit_account_number']))) : '';
    $deposit_bank_name = isset($_POST['deposit_bank_name']) ? htmlspecialchars(trim(strip_tags($_POST['deposit_bank_name']))) : '';
    $deposit_payment_instructions = isset($_POST['deposit_payment_instructions']) ? htmlspecialchars(trim(strip_tags($_POST['deposit_payment_instructions']))) : '';
    
    // Update the restaurant record
    $sql = "UPDATE restaurants SET 
            deposit_required = ?,
            deposit_amount = ?,
            deposit_account_name = ?,
            deposit_account_number = ?,
            deposit_bank_name = ?,
            deposit_payment_instructions = ?
            WHERE restaurant_id = ? AND owner_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idssssis", $deposit_required, $deposit_amount, $deposit_account_name, 
                      $deposit_account_number, $deposit_bank_name, $deposit_payment_instructions, 
                      $restaurant_id, $owner_id);
    
    if ($stmt->execute()) {
        $success_message = "Deposit settings updated successfully!";
        
        // Refresh restaurant data
        $sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $restaurant_id, $owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $restaurant = $result->fetch_assoc();
        $stmt->close();
    } else {
        $error_message = "Error updating deposit settings: " . $conn->error;
    }
}

// Get owner's restaurants for the dropdown
$sql = "SELECT restaurant_id, name FROM restaurants WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner_restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Deposit Settings for <?php echo htmlspecialchars($restaurant['name']); ?> - Owner Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                
                <?php
                // Display messages based on URL parameters
                if (isset($_GET['success'])) {
                    $success_type = $_GET['success'];
                    if ($success_type === 'verified') {
                        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i> Deposit has been successfully verified!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                    } elseif ($success_type === 'rejected') {
                        echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i> Deposit has been rejected. The customer will be notified.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                    }
                }
                
                if (isset($_GET['error'])) {
                    $error_type = $_GET['error'];
                    if ($error_type === 'invalid_id') {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> Invalid reservation ID.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                    } elseif ($error_type === 'not_found') {
                        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> Reservation not found or already processed.
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                    }
                }
                ?>
                
                <h2 class="mb-4">Deposit Settings - <?php echo htmlspecialchars($restaurant['name']); ?></h2>
                
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
                    <div class="card-header">
                        <h5 class="mb-0">Configure Reservation Deposit Requirements</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="deposit_required" name="deposit_required" <?php echo $restaurant['deposit_required'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="deposit_required">Require deposit for reservations</label>
                            </div>
                            
                            <div id="deposit-details" class="border rounded p-3 mb-3 <?php echo $restaurant['deposit_required'] ? '' : 'd-none'; ?>">
                                <div class="mb-3">
                                    <label for="deposit_amount" class="form-label">Deposit Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="deposit_amount" name="deposit_amount" step="0.01" min="0" value="<?php echo $restaurant['deposit_amount']; ?>" required>
                                    </div>
                                    <div class="form-text">Amount customers must pay as deposit when making a reservation</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="deposit_bank_name" class="form-label">Bank Name</label>
                                    <input type="text" class="form-control" id="deposit_bank_name" name="deposit_bank_name" value="<?php echo htmlspecialchars($restaurant['deposit_bank_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="deposit_account_name" class="form-label">Account Holder Name</label>
                                    <input type="text" class="form-control" id="deposit_account_name" name="deposit_account_name" value="<?php echo htmlspecialchars($restaurant['deposit_account_name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="deposit_account_number" class="form-label">Account Number</label>
                                    <input type="text" class="form-control" id="deposit_account_number" name="deposit_account_number" value="<?php echo htmlspecialchars($restaurant['deposit_account_number']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="deposit_payment_instructions" class="form-label">Payment Instructions</label>
                                    <textarea class="form-control" id="deposit_payment_instructions" name="deposit_payment_instructions" rows="4"><?php echo htmlspecialchars($restaurant['deposit_payment_instructions']); ?></textarea>
                                    <div class="form-text">Provide clear instructions on how customers should make the deposit payment</div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                    <div class="card-footer">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            When enabled, customers will be required to upload proof of deposit payment when making a reservation.
                        </small>
                    </div>
                </div>
                
                <!-- Add deposit summary for selected restaurant -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Deposit Summary for <?php echo htmlspecialchars($restaurant['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get deposit statistics for this restaurant
                        $stats = [
                            'pending' => 0,
                            'verified' => 0,
                            'rejected' => 0,
                            'total_amount' => 0,
                            'verified_amount' => 0
                        ];
                        
                        $sql = "SELECT 
                                    COUNT(CASE WHEN deposit_status = 'pending' THEN 1 END) as pending_count,
                                    COUNT(CASE WHEN deposit_status = 'verified' THEN 1 END) as verified_count,
                                    COUNT(CASE WHEN deposit_status = 'rejected' THEN 1 END) as rejected_count,
                                    SUM(CASE WHEN deposit_status = 'verified' THEN deposit_amount ELSE 0 END) as verified_amount,
                                    SUM(deposit_amount) as total_amount
                                FROM reservations 
                                WHERE restaurant_id = ? AND deposit_status IS NOT NULL";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $restaurant_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $stats_row = $result->fetch_assoc();
                            $stats['pending'] = $stats_row['pending_count'] ?? 0;
                            $stats['verified'] = $stats_row['verified_count'] ?? 0;
                            $stats['rejected'] = $stats_row['rejected_count'] ?? 0;
                            $stats['total_amount'] = $stats_row['total_amount'] ?? 0;
                            $stats['verified_amount'] = $stats_row['verified_amount'] ?? 0;
                        }
                        
                        // Get latest deposits
                        $sql = "SELECT r.*, u.first_name, u.last_name
                                FROM reservations r
                                JOIN users u ON r.user_id = u.user_id
                                WHERE r.restaurant_id = ? AND r.deposit_status IS NOT NULL
                                ORDER BY r.deposit_payment_date DESC
                                LIMIT 5";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $restaurant_id);
                        $stmt->execute();
                        $recent_deposits = $stmt->get_result();
                        ?>
                        
                        <?php if ($stats['pending'] > 0): ?>
                        <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>You have <?php echo $stats['pending']; ?> pending deposit(s) waiting for verification.</strong>
                            </div>
                            <a href="#pending-deposits" class="btn btn-primary">
                                <i class="fas fa-check-circle me-1"></i> Verify Pending Deposits
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-primary mb-0"><?php echo $stats['pending']; ?></h3>
                                        <p class="text-muted mb-0">Pending Deposits</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-success mb-0"><?php echo $stats['verified']; ?></h3>
                                        <p class="text-muted mb-0">Verified Deposits</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-danger mb-0"><?php echo $stats['rejected']; ?></h3>
                                        <p class="text-muted mb-0">Rejected Deposits</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="text-success mb-0">$<?php echo number_format($stats['verified_amount'], 2); ?></h3>
                                        <p class="text-muted mb-0">Total Verified Amount</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($recent_deposits->num_rows > 0): ?>
                        <div class="mb-4" id="pending-deposits">
                            <h6 class="mb-3">Pending Deposits Waiting for Verification</h6>
                            <?php 
                            // Get all pending deposits
                            $sql = "SELECT r.*, u.first_name, u.last_name
                                    FROM reservations r
                                    JOIN users u ON r.user_id = u.user_id
                                    WHERE r.restaurant_id = ? AND r.deposit_status = 'pending'
                                    ORDER BY r.reservation_date ASC";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $restaurant_id);
                            $stmt->execute();
                            $pending_deposits = $stmt->get_result();
                            
                            if ($pending_deposits->num_rows > 0):
                            ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                You have <?php echo $pending_deposits->num_rows; ?> pending deposit(s) waiting for verification.
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Reservation Date</th>
                                            <th>Payment Date</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($pending = $pending_deposits->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pending['first_name'] . ' ' . $pending['last_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($pending['reservation_date'])) . ' at ' . 
                                                date('h:i A', strtotime($pending['reservation_time'])); ?></td>
                                            <td><?php echo !empty($pending['deposit_payment_date']) ? 
                                                date('M d, Y', strtotime($pending['deposit_payment_date'])) : 'N/A'; ?></td>
                                            <td>$<?php echo number_format($pending['deposit_amount'], 2); ?></td>
                                            <td>
                                                <a href="verify_deposit.php?id=<?php echo $pending['reservation_id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-check-circle me-1"></i> Verify Now
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                No pending deposits waiting for verification.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="mb-3">Recent Deposits</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($deposit = $recent_deposits->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($deposit['first_name'] . ' ' . $deposit['last_name']); ?></td>
                                        <td><?php echo !empty($deposit['deposit_payment_date']) ? date('M d, Y', strtotime($deposit['deposit_payment_date'])) : 'N/A'; ?></td>
                                        <td>$<?php echo number_format($deposit['deposit_amount'], 2); ?></td>
                                        <td>
                                            <?php if ($deposit['deposit_status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php elseif ($deposit['deposit_status'] == 'verified'): ?>
                                                <span class="badge bg-success">Verified</span>
                                            <?php elseif ($deposit['deposit_status'] == 'rejected'): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($deposit['deposit_status'] == 'pending'): ?>
                                                <a href="verify_deposit.php?id=<?php echo $deposit['reservation_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-check-circle"></i> Verify
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No deposit history found for this restaurant.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle deposit details when checkbox is clicked
        $(document).ready(function() {
            $('#deposit_required').change(function() {
                if ($(this).is(':checked')) {
                    $('#deposit-details').removeClass('d-none');
                } else {
                    $('#deposit-details').addClass('d-none');
                }
            });
        });
    </script>
</body>
</html> 