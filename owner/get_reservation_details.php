<?php
require_once 'includes/db_connect.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $restaurant_id = $_POST['restaurant_id'];
    
    // Get restaurant info
    $sql_restaurant = "SELECT name, address, phone, email, deposit_required FROM restaurants WHERE restaurant_id = ?";
    $stmt_restaurant = $conn->prepare($sql_restaurant);
    $stmt_restaurant->bind_param("i", $restaurant_id);
    $stmt_restaurant->execute();
    $restaurant = $stmt_restaurant->get_result()->fetch_assoc();
    $stmt_restaurant->close();
    
    // Get reservation details with first and last name
    $sql = "SELECT r.*, u.username, u.first_name, u.last_name, u.email, u.phone 
            FROM reservations r 
            JOIN users u ON r.user_id = u.user_id 
            WHERE r.reservation_id = ? AND r.restaurant_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $reservation_id, $restaurant_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reservation && $restaurant) {
        // Format date and time
        $formatted_date = date('l, F j, Y', strtotime($reservation['reservation_date']));
        $formatted_time = date('g:i A', strtotime($reservation['reservation_time']));
        
        // Get customer name (first + last name, or username as fallback)
        $customer_name = (!empty($reservation['first_name']) && !empty($reservation['last_name'])) 
            ? htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) 
            : htmlspecialchars($reservation['username']);
        
        // Prepare email subject and body
        $email_subject = "Reservation #" . $reservation_id . " at " . $restaurant['name'];
        $email_body = "Dear " . trim(html_entity_decode($customer_name)) . ",\n\n";
        $email_body .= "Your reservation details:\n";
        $email_body .= "Date: " . $formatted_date . "\n";
        $email_body .= "Time: " . $formatted_time . "\n";
        $email_body .= "Party Size: " . $reservation['party_size'] . "\n";
        $email_body .= "Status: " . ucfirst($reservation['status']) . "\n\n";
        
        // Add deposit information to email if applicable
        if (isset($reservation['deposit_status']) && $reservation['deposit_status'] != 'not_required') {
            $email_body .= "Deposit: $" . number_format($reservation['deposit_amount'], 2) . "\n";
            $email_body .= "Deposit Status: " . ucfirst($reservation['deposit_status']) . "\n\n";
        }
        
        if (!empty($reservation['special_requests'])) {
            $email_body .= "Special Requests: " . $reservation['special_requests'] . "\n\n";
        }
        
        $email_body .= "Thank you for choosing " . $restaurant['name'] . ".\n";
        $email_body .= "If you need to modify your reservation, please contact us at " . $restaurant['phone'] . ".";
        
        // Prepare mail link - replace + with %20 for spaces
        $email_body_encoded = str_replace('+', '%20', urlencode($email_body));
        $email_subject_encoded = str_replace('+', '%20', urlencode($email_subject));
        
        $mail_link = "mailto:" . urlencode($reservation['email']) . 
                    "?subject=" . $email_subject_encoded . 
                    "&body=" . $email_body_encoded;
                    
        // Status class
        $status_class = '';
        switch ($reservation['status']) {
            case 'pending': $status_class = 'bg-warning text-dark'; break;
            case 'confirmed': $status_class = 'bg-success text-white'; break;
            case 'cancelled': $status_class = 'bg-danger text-white'; break;
            case 'completed': $status_class = 'bg-info text-white'; break;
            default: $status_class = 'bg-secondary text-white';
        }
        
        // Deposit status class and text
        $deposit_status_class = '';
        $deposit_status_text = 'Not Required';
        if (isset($reservation['deposit_status'])) {
            switch ($reservation['deposit_status']) {
                case 'pending':
                    $deposit_status_class = 'bg-warning text-dark';
                    $deposit_status_text = 'Pending';
                    break;
                case 'verified':
                    $deposit_status_class = 'bg-success text-white';
                    $deposit_status_text = 'Verified';
                    break;
                case 'rejected':
                    $deposit_status_class = 'bg-danger text-white';
                    $deposit_status_text = 'Rejected';
                    break;
                case 'not_required':
                    $deposit_status_class = 'bg-secondary text-white';
                    $deposit_status_text = 'Not Required';
                    break;
                default:
                    $deposit_status_class = 'bg-secondary text-white';
                    $deposit_status_text = 'Not Required';
            }
        }

        // Output HTML
        ?>
        <div id="reservation-details-content">
            <!-- Reservation Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="mb-1">Reservation #<?php echo $reservation_id; ?></h5>
                    <p class="mb-0 text-muted">Created on <?php echo date('M d, Y g:i A', strtotime($reservation['created_at'])); ?></p>
                </div>
                <span class="badge <?php echo $status_class; ?> px-3 py-2">
                    <?php echo ucfirst($reservation['status']); ?>
                </span>
            </div>
            
            <div class="row">
                <!-- Customer Information -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title border-bottom pb-2"><i class="fas fa-user me-2"></i>Customer Information</h6>
                            <p class="mb-2"><strong>Name:</strong> <?php echo $customer_name; ?></p>
                            <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
                            <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($reservation['phone']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Information -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title border-bottom pb-2"><i class="fas fa-calendar-alt me-2"></i>Reservation Details</h6>
                            <p class="mb-2"><strong>Date:</strong> <?php echo $formatted_date; ?></p>
                            <p class="mb-2"><strong>Time:</strong> <?php echo $formatted_time; ?></p>
                            <p class="mb-0"><strong>Party Size:</strong> <?php echo $reservation['party_size']; ?> people</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($reservation['deposit_status']) && $reservation['deposit_status'] != 'not_required'): ?>
            <!-- Deposit Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title border-bottom pb-2">
                                <i class="fas fa-dollar-sign me-2"></i>Deposit Information
                                <span class="badge <?php echo $deposit_status_class; ?> ms-2"><?php echo $deposit_status_text; ?></span>
                            </h6>
                            <p class="mb-2"><strong>Amount:</strong> $<?php echo number_format($reservation['deposit_amount'], 2); ?></p>
                            <?php if (!empty($reservation['deposit_payment_date'])): ?>
                                <p class="mb-0"><strong>Payment Date:</strong> <?php echo date('F j, Y', strtotime($reservation['deposit_payment_date'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($reservation['deposit_payment_slip'])): ?>
                                <div class="mt-3">
                                    <p class="mb-2"><strong>Payment Slip:</strong></p>
                                    <a href="../<?php echo htmlspecialchars($reservation['deposit_payment_slip']); ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View Payment Slip
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Special Requests -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title border-bottom pb-2"><i class="fas fa-comment-alt me-2"></i>Special Requests</h6>
                            <p class="card-text mb-0"><?php echo !empty($reservation['special_requests']) ? nl2br(htmlspecialchars($reservation['special_requests'])) : '<em class="text-muted">No special requests</em>'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="d-flex justify-content-center gap-3 mt-2 mb-2">
                <a href="<?php echo $mail_link; ?>" class="btn btn-primary px-4" id="sendEmailBtn">
                    <i class="fas fa-envelope me-2"></i>Send Email
                </a>
                <button type="button" class="btn btn-info px-4" id="printReservationBtn" onclick="printReservation()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>

        <!-- Print Template (hidden, will be used for printing) -->
        <div id="print-template" style="display: none;">
            <div style="max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif;">
                <div style="text-align: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd;">
                    <h1 style="color: #2e7d32; margin-bottom: 5px;"><?php echo SITE_NAME; ?></h1>
                    <h2 style="margin-top: 0;"><?php echo htmlspecialchars($restaurant['name']); ?></h2>
                    <p><?php echo htmlspecialchars($restaurant['address']); ?><br>
                    Phone: <?php echo htmlspecialchars($restaurant['phone']); ?><br>
                    Email: <?php echo htmlspecialchars($restaurant['email']); ?></p>
                </div>
                
                <h3 style="text-align: center; margin-bottom: 20px;">Reservation Confirmation</h3>
                
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Reservation #</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $reservation_id; ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Status</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo ucfirst($reservation['status']); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Customer</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $customer_name; ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Contact</th>
                        <td style="padding: 10px; border: 1px solid #ddd;">
                            Email: <?php echo htmlspecialchars($reservation['email']); ?><br>
                            Phone: <?php echo htmlspecialchars($reservation['phone']); ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Date</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $formatted_date; ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Time</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $formatted_time; ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Party Size</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $reservation['party_size']; ?> people</td>
                    </tr>
                    <?php if (isset($reservation['deposit_status']) && $reservation['deposit_status'] != 'not_required'): ?>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Deposit Amount</th>
                        <td style="padding: 10px; border: 1px solid #ddd;">$<?php echo number_format($reservation['deposit_amount'], 2); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Deposit Status</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $deposit_status_text; ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th style="text-align: left; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">Special Requests</th>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo !empty($reservation['special_requests']) ? nl2br(htmlspecialchars($reservation['special_requests'])) : 'None'; ?></td>
                    </tr>
                </table>
                
                <div style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #666;">
                    <p>Thank you for choosing <?php echo htmlspecialchars($restaurant['name']); ?>.<br>
                    We look forward to serving you!</p>
                    <p style="margin-top: 30px; font-size: 0.8em;">
                        Printed on <?php echo date('F j, Y g:i A'); ?><br>
                        <?php echo SITE_NAME; ?> &copy; <?php echo date('Y'); ?>
                    </p>
                </div>
            </div>
        </div>

        <script>
        // Print functionality
        function printReservation() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Write the print template to the new window
            printWindow.document.write('<html><head><title>Print Reservation</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(document.getElementById('print-template').innerHTML);
            printWindow.document.write('</body></html>');
            
            // Close the document
            printWindow.document.close();
            
            // Focus on the new window
            printWindow.focus();
            
            // Print the window content
            setTimeout(function() {
                printWindow.print();
                // Close the window after printing (optional)
                printWindow.close();
            }, 500);
        }
        </script>
        <?php
    } else {
        echo '<div class="alert alert-danger">Reservation details not found.</div>';
    }
}
?>
