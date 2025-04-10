<?php
ob_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Ensure no output is sent before headers
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get all restaurants with their coordinates
    $stmt = $db->prepare("SELECT 
        restaurant_id, 
        name, 
        cuisine_type, 
        address, 
        latitude, 
        longitude, 
        phone, 
        price_range,
        image 
        FROM restaurants");
    
    $stmt->execute();
    $restaurants = $stmt->fetchAll();

    // Clear any previous output
    ob_clean();
    
    // Output JSON
    echo json_encode($restaurants);
} catch(PDOException $e) {
    http_response_code(500);
    // Clear any previous output
    ob_clean();
    
    // Output error as JSON
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(500);
    // Clear any previous output
    ob_clean();
    
    // Output error as JSON
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}

// Make sure no more output is sent
ob_end_flush();
exit;
