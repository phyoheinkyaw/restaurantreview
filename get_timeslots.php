<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set headers to prevent caching and indicate JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get parameters
$restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Validate inputs
if (!$restaurant_id) {
    echo json_encode([]);
    exit;
}

if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode([]);
    exit;
}

try {
    // Get available time slots
    $available_slots = getAvailableTimeSlots($restaurant_id, $date);
    
    // Return as JSON
    echo json_encode($available_slots);
} catch (Exception $e) {
    error_log("Error getting time slots: " . $e->getMessage());
    echo json_encode([]);
} 