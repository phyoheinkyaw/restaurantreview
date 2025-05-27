<?php
// Start session
session_start();

// Include database connection
require_once 'includes/db_connect.php';

// Get owner information
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'owner'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    header("Location: ../login.php");
    exit;
}

// Update session with new restaurant ID
if (isset($_POST['restaurant_id'])) {
    $restaurant_id = intval($_POST['restaurant_id']);
    
    // Verify that this restaurant belongs to the owner
    $sql = "SELECT restaurant_id FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $restaurant_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['current_restaurant_id'] = $restaurant_id;
    }
}

// Get the referring page or default to index
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

// Redirect back to the previous page
header("Location: " . $redirect);
exit;
