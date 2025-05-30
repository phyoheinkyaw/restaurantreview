<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

// Check if we have the required POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type']) && isset($_POST['id']) && isset($_POST['restaurant_id'])) {
    $type = $_POST['type'];
    $id = intval($_POST['id']);
    $restaurant_id = intval($_POST['restaurant_id']);
    
    // Set the restaurant ID in session
    $_SESSION['current_restaurant_id'] = $restaurant_id;
    
    // Include database connection
    require_once 'includes/db_connect.php';
    
    // Get owner info
    $owner_id = $_SESSION['user_id'];
    
    // Mark notification as read based on type
    if ($type === 'review') {
        // Mark the review as read
        $sql = "UPDATE reviews SET is_read = 1 
                WHERE review_id = ? AND 
                restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $owner_id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to reviews page
        header("Location: reviews.php");
        exit;
        
    } elseif ($type === 'reservation') {
        // Mark the reservation as read
        $sql = "UPDATE reservations SET is_read = 1 
                WHERE reservation_id = ? AND 
                restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $owner_id);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to reservations page
        header("Location: reservations.php");
        exit;
    }
}

// If we get here, something went wrong - redirect to dashboard
header("Location: index.php");
exit;

// Flush the output buffer
ob_end_flush();
?> 