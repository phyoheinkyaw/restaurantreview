<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Time Slots Debugging</h1>";

// Test database connection
try {
    $db = getDB();
    echo "<p>Database connection successful!</p>";
    
    // Test getting restaurant data
    $restaurant_id = 1; // Testing with restaurant ID 1
    
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE restaurant_id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();
    
    if (!$restaurant) {
        echo "<p>Error: Restaurant not found with ID $restaurant_id</p>";
        exit;
    }
    
    echo "<p>Found restaurant: " . htmlspecialchars($restaurant['name']) . "</p>";
    
    // Check opening_hours structure
    echo "<h2>Opening Hours Data:</h2>";
    echo "<pre>";
    var_dump($restaurant['opening_hours']);
    echo "</pre>";
    
    // Try to decode JSON
    $opening_hours = json_decode($restaurant['opening_hours'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p>Error parsing JSON: " . json_last_error_msg() . "</p>";
    } else {
        echo "<h2>Decoded Opening Hours:</h2>";
        echo "<pre>";
        print_r($opening_hours);
        echo "</pre>";
        
        // Check if we have data for each day
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        echo "<h2>Days Check:</h2>";
        echo "<ul>";
        foreach ($days as $day) {
            if (isset($opening_hours[$day])) {
                echo "<li>$day: ";
                if (isset($opening_hours[$day]['open']) && isset($opening_hours[$day]['close'])) {
                    echo "Open: " . $opening_hours[$day]['open'] . ", Close: " . $opening_hours[$day]['close'];
                    
                    // Test if we can convert to timestamps
                    $open_time = strtotime($opening_hours[$day]['open']);
                    $close_time = strtotime($opening_hours[$day]['close']);
                    
                    if (!$open_time || !$close_time) {
                        echo " <strong style='color:red'>ERROR: Cannot convert to timestamps!</strong>";
                    } else {
                        echo " (Timestamps OK)";
                    }
                } else {
                    echo "<strong style='color:red'>Missing open/close times</strong>";
                }
                echo "</li>";
            } else {
                echo "<li>$day: <strong style='color:orange'>Not set</strong></li>";
            }
        }
        echo "</ul>";
        
        // Test generating time slots for today
        echo "<h2>Today's Time Slots:</h2>";
        $today = date('Y-m-d');
        $day_of_week = strtolower(date('l'));
        
        if (isset($opening_hours[$day_of_week])) {
            $open_time = $opening_hours[$day_of_week]['open'] ?? '09:00';
            $close_time = $opening_hours[$day_of_week]['close'] ?? '22:00';
            
            $start = strtotime($open_time);
            $end = strtotime($close_time);
            $interval = 30 * 60; // 30 minutes
            
            if (!$start || !$end) {
                echo "<p style='color:red'>Error: Invalid time format. Start: $start, End: $end</p>";
            } else {
                echo "<p>Today ($day_of_week): $open_time - $close_time</p>";
                echo "<p>Time slots:</p><ul>";
                
                for ($time = $start; $time <= $end - $interval; $time += $interval) {
                    echo "<li>" . date('H:i', $time) . "</li>";
                }
                
                echo "</ul>";
            }
        } else {
            echo "<p>No hours set for $day_of_week</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?> 