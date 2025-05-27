<?php
// Include database connection and required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Use the existing getFeaturedRestaurants function from functions.php
    $restaurants = getFeaturedRestaurants();
    
    // Output JSON
    echo json_encode($restaurants);
    
} catch (Exception $e) {
    // Return error in JSON format
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 