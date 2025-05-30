<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['action']) && $_POST['action'] == 'cancel') {
        header("Location: login.php");
    } else {
        echo json_encode(['success' => false, 'message' => 'Please login to manage reservations']);
    }
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle cancellation request
if (isset($_POST['action']) && $_POST['action'] == 'cancel' && isset($_POST['reservation_id'])) {
    $reservation_id = intval($_POST['reservation_id']);
    
    try {
        $db = getDB();
        
        // Get reservation details to check time restriction
        $stmt = $db->prepare("SELECT r.*, res.name as restaurant_name 
                             FROM reservations r 
                             JOIN restaurants res ON r.restaurant_id = res.restaurant_id 
                             WHERE r.reservation_id = ? AND r.user_id = ?");
        $stmt->execute([$reservation_id, $user_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            $_SESSION['error_message'] = "Reservation not found or you don't have permission to cancel it.";
            header("Location: reservations.php");
            exit;
        }
        
        // Check if reservation is already cancelled or completed
        if ($reservation['status'] == 'cancelled') {
            $_SESSION['error_message'] = "This reservation is already cancelled.";
            header("Location: reservations.php");
            exit;
        }
        
        if ($reservation['status'] == 'completed') {
            $_SESSION['error_message'] = "Completed reservations cannot be cancelled.";
            header("Location: reservations.php");
            exit;
        }
        
        // Check if reservation is within 5 hours
        $reservation_datetime = new DateTime($reservation['reservation_date'] . ' ' . $reservation['reservation_time']);
        $now = new DateTime();
        $time_diff = $reservation_datetime->getTimestamp() - $now->getTimestamp();
        $hours_diff = $time_diff / 3600;
        
        if ($hours_diff < 5) {
            $_SESSION['error_message'] = "Reservations cannot be cancelled less than 5 hours before the scheduled time.";
            header("Location: reservations.php");
            exit;
        }
        
        // If we get here, cancellation is allowed
        $stmt = $db->prepare("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ? AND user_id = ?");
        if (!$stmt->execute([$reservation_id, $user_id])) {
            throw new Exception("Failed to cancel reservation.");
        }
        
        $_SESSION['success_message'] = "Your reservation at {$reservation['restaurant_name']} has been successfully cancelled.";
        header("Location: reservations.php");
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: reservations.php");
        exit;
    }
}

// Check if it's a POST request for creating a new reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        // Get form data
        $restaurant_id = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
        $user_id = $_SESSION['user_id'];
        $date = isset($_POST['date']) ? $_POST['date'] : '';
        $time = isset($_POST['time']) ? $_POST['time'] : '';
        $party_size = isset($_POST['party_size']) ? (int)$_POST['party_size'] : 0;
        $special_requests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';

        // Validate inputs
        if (!$restaurant_id) {
            throw new Exception('Invalid restaurant ID');
        }

        if (!$user_id) {
            throw new Exception('Invalid user ID');
        }

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception('Invalid date format');
        }

        if (empty($time) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new Exception('Invalid time format');
        }

        if ($party_size < 1 || $party_size > 20) {
            throw new Exception('Party size must be between 1 and 20');
        }

        // Check if the date is in the future
        $reservation_date = new DateTime($date);
        $now = new DateTime();
        $now->setTime(0, 0); // Set to beginning of day for date comparison
        
        if ($reservation_date < $now) {
            throw new Exception('Reservation date must be in the future');
        }

        // Check if the time slot is available
        $available_slots = getAvailableTimeSlots($restaurant_id, $date);
        if (!in_array($time, $available_slots)) {
            throw new Exception('The selected time slot is not available');
        }

        // Get database connection
        $db = getDB();
        
        // Begin transaction
        $db->beginTransaction();

        // Insert reservation
        $sql = "INSERT INTO reservations (
            restaurant_id, 
            user_id, 
            reservation_date,
            reservation_time,
            party_size,
            special_requests,
            status,
            is_read,
            created_at
        ) VALUES (
            :restaurant_id, 
            :user_id, 
            :reservation_date,
            :reservation_time,
            :party_size,
            :special_requests,
            'pending',
            0,
            NOW()
        )";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare statement');
        }

        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':reservation_date', $date, PDO::PARAM_STR);
        $stmt->bindParam(':reservation_time', $time, PDO::PARAM_STR);
        $stmt->bindParam(':party_size', $party_size, PDO::PARAM_INT);
        $stmt->bindParam(':special_requests', $special_requests, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert reservation');
        }

        // Get the inserted reservation ID
        $reservation_id = $db->lastInsertId();

        // Commit transaction
        $db->commit();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Reservation submitted successfully',
            'reservation_id' => $reservation_id
        ]);

    } catch (Exception $e) {
        // Rollback transaction if started
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }

        // Return error response
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        
        // Log the error
        error_log("Reservation submission error: " . $e->getMessage());
    }
} 