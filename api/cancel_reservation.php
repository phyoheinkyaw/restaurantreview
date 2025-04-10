<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['reservation_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Reservation ID is required']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verify the reservation belongs to the user
    $stmt = $db->prepare("
        SELECT r.reservation_id, r.status, r.reservation_date, r.reservation_time
        FROM reservations r
        WHERE r.reservation_id = ?
        AND r.user_id = ?
    ");
    $stmt->execute([$data['reservation_id'], $_SESSION['user_id']]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Reservation not found or not authorized']);
        exit;
    }

    // Check if reservation can be cancelled
    $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
    $now = new DateTime();
    $cancellation_window = 24 * 60 * 60; // 24 hours in seconds

    if ($reservation['status'] !== 'pending' || 
        $reservation_datetime->getTimestamp() - $now->getTimestamp() < $cancellation_window) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot cancel this reservation']);
        exit;
    }

    // Update reservation status
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'cancelled', 
            updated_at = NOW()
        WHERE reservation_id = ?
    ");
    
    if ($stmt->execute([$data['reservation_id']])) {
        // Update user points for cancelling reservation
        $stmt = $db->prepare("
            UPDATE users 
            SET points = points - 50 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to cancel reservation']);
    }
} catch (PDOException $e) {
    error_log("Error cancelling reservation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
