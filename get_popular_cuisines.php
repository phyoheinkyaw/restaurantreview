<?php
require_once 'includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Connect to database
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get popular cuisine types based on reservations
    // This query joins restaurants and reservations tables, groups by cuisine_type,
    // and counts the number of reservations for each cuisine type
    $query = "
        SELECT r.cuisine_type, COUNT(res.reservation_id) as reservation_count
        FROM restaurants r
        JOIN reservations res ON r.restaurant_id = res.restaurant_id
        GROUP BY r.cuisine_type
        ORDER BY reservation_count DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $popular_cuisines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If we don't have enough popular cuisines from reservations, get some more from the restaurants table
    if (count($popular_cuisines) < 6) {
        $existing_cuisines = array_column($popular_cuisines, 'cuisine_type');
        $existing_cuisines_placeholders = implode(',', array_fill(0, count($existing_cuisines), '?'));
        
        $additional_query = "
            SELECT DISTINCT cuisine_type
            FROM restaurants
            WHERE cuisine_type NOT IN (" . ($existing_cuisines ? $existing_cuisines_placeholders : "'dummy'") . ")
            ORDER BY RAND()
            LIMIT " . (5 - count($popular_cuisines));
        
        $stmt = $db->prepare($additional_query);
        if ($existing_cuisines) {
            foreach ($existing_cuisines as $i => $cuisine) {
                $stmt->bindValue($i + 1, $cuisine);
            }
        }
        $stmt->execute();
        
        $additional_cuisines = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($additional_cuisines as $cuisine) {
            $popular_cuisines[] = [
                'cuisine_type' => $cuisine,
                'reservation_count' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'cuisines' => $popular_cuisines
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching popular cuisines: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} 