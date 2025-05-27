<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Updating Restaurant Opening Hours</h1>";

// Default opening hours template
$default_hours = [
    'monday' => ['open' => '09:00', 'close' => '22:00'],
    'tuesday' => ['open' => '09:00', 'close' => '22:00'],
    'wednesday' => ['open' => '09:00', 'close' => '22:00'],
    'thursday' => ['open' => '09:00', 'close' => '22:00'],
    'friday' => ['open' => '09:00', 'close' => '23:00'],
    'saturday' => ['open' => '10:00', 'close' => '23:00'],
    'sunday' => ['open' => '10:00', 'close' => '21:00']
];

try {
    $db = getDB();
    
    // Get all restaurants
    $stmt = $db->query("SELECT restaurant_id, name, opening_hours FROM restaurants");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Found " . count($restaurants) . " restaurants to process.</p>";
    
    foreach ($restaurants as $restaurant) {
        echo "<h2>" . htmlspecialchars($restaurant['name']) . " (ID: " . $restaurant['restaurant_id'] . ")</h2>";
        
        $current_hours = json_decode($restaurant['opening_hours'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($current_hours)) {
            echo "<p>Invalid or empty opening hours JSON. Will use default template.</p>";
            $new_hours = $default_hours;
        } else {
            echo "<p>Current hours found. Merging with default template to ensure all days are present.</p>";
            
            // Start with default hours
            $new_hours = $default_hours;
            
            // Merge with existing hours where available
            foreach ($current_hours as $day => $hours) {
                if (isset($new_hours[$day]) && isset($hours['open']) && isset($hours['close'])) {
                    $new_hours[$day]['open'] = $hours['open'];
                    $new_hours[$day]['close'] = $hours['close'];
                }
            }
        }
        
        // Format new hours JSON
        $new_hours_json = json_encode($new_hours, JSON_PRETTY_PRINT);
        
        echo "<pre>";
        echo htmlspecialchars($new_hours_json);
        echo "</pre>";
        
        // Update the database
        $update_stmt = $db->prepare("UPDATE restaurants SET opening_hours = ? WHERE restaurant_id = ?");
        $result = $update_stmt->execute([$new_hours_json, $restaurant['restaurant_id']]);
        
        if ($result) {
            echo "<p style='color:green'>Successfully updated opening hours.</p>";
        } else {
            echo "<p style='color:red'>Error updating opening hours: " . implode(', ', $update_stmt->errorInfo()) . "</p>";
        }
        
        echo "<hr>";
    }
    
    echo "<p>Operation completed.</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?> 