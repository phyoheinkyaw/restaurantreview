<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to make a reservation');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get the input data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['restaurant_id']) || !isset($data['date']) || !isset($data['time']) || !isset($data['party_size'])) {
        throw new Exception('Missing required parameters');
    }

    $user_id = $_SESSION['user_id'];
    $restaurant_id = $data['restaurant_id'];
    $date = $data['date'];
    $time = $data['time'];
    $party_size = $data['party_size'];
    $special_requests = isset($data['special_requests']) ? $data['special_requests'] : '';

    // Check availability
    $stmt = $db->prepare("
        SELECT capacity FROM restaurants WHERE restaurant_id = ?
    ");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();
    if (!$restaurant) {
        throw new Exception('Restaurant not found');
    }

    $capacity = $restaurant['capacity'];

    $stmt = $db->prepare("
        SELECT SUM(party_size) as total_guests
        FROM reservations
        WHERE restaurant_id = ?
        AND reservation_date = ?
        AND reservation_time = ?
        AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$restaurant_id, $date, $time]);
    $current_guests = $stmt->fetch()['total_guests'] ?? 0;

    if (($current_guests + $party_size) > $capacity) {
        throw new Exception('Sorry, this time slot is fully booked');
    }

    // Check if the slot is blocked
    $stmt = $db->prepare("
        SELECT * FROM blocked_slots
        WHERE restaurant_id = ?
        AND block_date = ?
        AND ? >= block_time_start
        AND ? < block_time_end
    ");
    $stmt->execute([$restaurant_id, $date, $time, $time]);
    if ($stmt->fetch()) {
        throw new Exception('Sorry, this time slot is unavailable due to maintenance or a private event.');
    }

    // Create the reservation
    $stmt = $db->prepare("
        INSERT INTO reservations 
        (user_id, restaurant_id, reservation_date, reservation_time, party_size, special_requests)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $restaurant_id,
        $date,
        $time,
        $party_size,
        $special_requests
    ]);

    $reservation_id = $db->lastInsertId();

    // Update user points
    $points = POINTS_PER_RESERVATION;
    $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE user_id = ?");
    $stmt->execute([$points, $user_id]);

    echo json_encode([
        'success' => true,
        'reservation_id' => $reservation_id,
        'message' => 'Reservation created successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
