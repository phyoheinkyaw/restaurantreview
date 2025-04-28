<?php
require_once 'includes/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id']) || !isset($_POST['status']) || !isset($_POST['restaurant_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$reservation_id = $_POST['reservation_id'];
$status = $_POST['status'];
$restaurant_id = $_POST['restaurant_id'];

// Valid status transitions
$valid_transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed'],
    'cancelled' => [],
    'completed' => []
];

// Get current status
$sql = "SELECT status FROM reservations WHERE reservation_id = ? AND restaurant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $reservation_id, $restaurant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Reservation not found']);
    exit;
}

$current_status = $result->fetch_assoc()['status'];
$stmt->close();

// Validate status transition
if (!isset($valid_transitions[$current_status]) || !in_array($status, $valid_transitions[$current_status])) {
    echo json_encode([
        'status' => 'error',
        'message' => "Cannot change status from {$current_status} to {$status}"
    ]);
    exit;
}

// Update status
$sql = "UPDATE reservations SET status = ?, updated_at = NOW() WHERE reservation_id = ? AND restaurant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $status, $reservation_id, $restaurant_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error updating status']);
}

$stmt->close();
?>
