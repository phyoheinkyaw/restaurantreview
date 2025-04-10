<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the input data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['restaurant_id']) || !isset($data['date']) || !isset($data['time']) || !isset($data['party_size'])) {
        throw new Exception('Missing required parameters');
    }

    $restaurant_id = $data['restaurant_id'];
    $date = $data['date'];
    $time = $data['time'];
    $party_size = $data['party_size'];

    // Get restaurant capacity (assuming default capacity of 100 if not set)
    $stmt = $db->prepare("SELECT capacity FROM restaurants WHERE restaurant_id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();
    $capacity = $restaurant ? $restaurant['capacity'] : 100;

    // Calculate total reservations for this time slot
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

    // Check if there's enough capacity
    $available_seats = $capacity - $current_guests;
    $can_reserve = $available_seats >= $party_size;

    echo json_encode([
        'success' => true,
        'available_seats' => $available_seats,
        'can_reserve' => $can_reserve,
        'current_guests' => $current_guests,
        'capacity' => $capacity
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
