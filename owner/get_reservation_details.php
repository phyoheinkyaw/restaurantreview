<?php
require_once 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'])) {
    $reservation_id = $_POST['reservation_id'];
    $restaurant_id = $_POST['restaurant_id'];
    
    // Get reservation details
    $sql = "SELECT r.*, u.username, u.email, u.phone 
            FROM reservations r 
            JOIN users u ON r.user_id = u.user_id 
            WHERE r.reservation_id = ? AND r.restaurant_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $reservation_id, $restaurant_id);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reservation) {
        echo '<div class="reservation-info">
                <h6 class="mb-2">Customer Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> ' . htmlspecialchars($reservation['username']) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($reservation['email']) . '</p>
                        <p><strong>Phone:</strong> ' . htmlspecialchars($reservation['phone']) . '</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong> <span class="status-badge status-badge-' . $reservation['status'] . '">' . ucfirst($reservation['status']) . '</span></p>
                        <p><strong>Created At:</strong> ' . date('F j, Y g:i A', strtotime($reservation['created_at'])) . '</p>
                        <p><strong>Updated At:</strong> ' . date('F j, Y g:i A', strtotime($reservation['updated_at'])) . '</p>
                    </div>
                </div>
            </div>
            
            <div class="reservation-info">
                <h6 class="mb-2">Reservation Details</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($reservation['reservation_date'])) . '</p>
                        <p><strong>Time:</strong> ' . date('g:i A', strtotime($reservation['reservation_time'])) . '</p>
                        <p><strong>Party Size:</strong> ' . $reservation['party_size'] . '</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Special Requests:</strong></p>
                        <p class="mb-0">' . nl2br(htmlspecialchars($reservation['special_requests'] ?? 'No special requests')) . '</p>
                    </div>
                </div>
            </div>
            
            <div class="reservation-info">
                <h6 class="mb-2">Actions</h6>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="top" title="Send Confirmation Email">
                        <i class="fas fa-envelope"></i> Send Email
                    </button>
                    <button class="btn btn-sm btn-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Send SMS">
                        <i class="fas fa-sms"></i> Send SMS
                    </button>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="tooltip" data-bs-placement="top" title="Print Receipt">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>';
    }
}
