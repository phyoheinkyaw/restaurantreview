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
    $_SESSION['current_restaurant_id'] = intval($_POST['restaurant_id']);
}

// Redirect back to the previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
