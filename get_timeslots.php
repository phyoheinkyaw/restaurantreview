<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set header to return JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if restaurant_id and date are provided
if (!isset($_GET['restaurant_id']) || !isset($_GET['date'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$restaurant_id = intval($_GET['restaurant_id']);
$date = htmlspecialchars(trim($_GET['date']));

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Get restaurant operating hours
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM restaurants WHERE restaurant_id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();
    
    if (!$restaurant) {
        echo json_encode(['error' => 'Restaurant not found']);
        exit;
    }
    
    // Get opening hours from JSON
    $opening_hours = json_decode($restaurant['opening_hours'], true);
    $day_of_week = strtolower(date('l', strtotime($date)));
    
    if (!$opening_hours || !isset($opening_hours[$day_of_week])) {
        echo json_encode(['error' => 'No opening hours set for this day']);
        exit;
    }
    
    // Get opening hours for this day
    $open_time = $opening_hours[$day_of_week]['open'] ?? '09:00';
    $close_time = $opening_hours[$day_of_week]['close'] ?? '22:00';
    
    // Get existing reservations for this date to exclude occupied times
    $stmt = $db->prepare("
        SELECT reservation_time, party_size 
        FROM reservations 
        WHERE restaurant_id = ? AND reservation_date = ? AND status != 'cancelled'
    ");
    $stmt->execute([$restaurant_id, $date]);
    $existing_reservations = $stmt->fetchAll();
    
    // Get blocked slots for this date
    $stmt = $db->prepare("
        SELECT block_time_start, block_time_end, reason 
        FROM blocked_slots 
        WHERE restaurant_id = ? AND block_date = ?
    ");
    $stmt->execute([$restaurant_id, $date]);
    $blocked_slots = $stmt->fetchAll();
    
    // Generate time slots (every 30 minutes)
    $start = strtotime($open_time);
    $end = strtotime($close_time);
    
    if (!$start || !$end) {
        echo json_encode(['error' => 'Invalid opening hours format']);
        exit;
    }
    
    $interval = 30 * 60; // 30 minutes in seconds
    
    // Create result array with both available and blocked slots
    $result = [];
    
    for ($time = $start; $time <= $end - $interval; $time += $interval) {
        $timeStr = date('H:i', $time);
        $slot = ['time' => $timeStr, 'available' => true, 'reason' => null];
        
        // Check if this time slot is occupied by a reservation
        foreach ($existing_reservations as $reservation) {
            $reservation_time = substr($reservation['reservation_time'], 0, 5); // Extract HH:MM from time
            if ($reservation_time === $timeStr) {
                $slot['available'] = false;
                $slot['reason'] = 'Already booked';
                break;
            }
        }
        
        // Check if this time slot falls within a blocked period
        foreach ($blocked_slots as $blockedSlot) {
            $start_time = substr($blockedSlot['block_time_start'], 0, 5); // Extract HH:MM from time
            $end_time = substr($blockedSlot['block_time_end'], 0, 5); // Extract HH:MM from time
            if ($timeStr >= $start_time && $timeStr < $end_time) {
                $slot['available'] = false;
                $slot['reason'] = $blockedSlot['reason'] ? $blockedSlot['reason'] : 'Unavailable';
                break;
            }
        }
        
        // Filter out time slots that are in the past for today
        if ($date === date('Y-m-d') && $timeStr <= date('H:i')) {
            $slot['available'] = false;
            $slot['reason'] = 'Past time';
        }
        
        $result[] = $slot;
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    error_log("Database error in get_timeslots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("General error in get_timeslots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    exit;
} 