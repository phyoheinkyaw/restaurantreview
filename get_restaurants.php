<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Ensure no output is sent before headers
header('Content-Type: application/json');

try {
    // Use the getDB function from functions.php
    $db = getDB();

    // Get all restaurants with their coordinates
    $stmt = $db->prepare("SELECT 
        r.restaurant_id, 
        r.name, 
        r.cuisine_type, 
        r.address, 
        r.latitude, 
        r.longitude, 
        r.phone, 
        r.price_range,
        r.image,
        COALESCE(AVG(rv.overall_rating), 0) as avg_rating,
        COUNT(rv.review_id) as review_count
        FROM restaurants r
        LEFT JOIN reviews rv ON r.restaurant_id = rv.restaurant_id
        WHERE r.is_active = 1
        GROUP BY r.restaurant_id
        ORDER BY r.name");
    
    $stmt->execute();
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output JSON
    echo json_encode($restaurants);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}

// Make sure no more output is sent
ob_end_flush();
exit;
