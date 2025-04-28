<?php
// get_blocked_times.php: returns blocked time intervals for a given date (AJAX)
require_once 'includes/header.php';
require_once 'includes/db_connect.php';
header('Content-Type: application/json');
if (!isset($_SESSION['current_restaurant_id'])) {
    echo json_encode(['error' => 'No restaurant selected']);
    exit;
}
$restaurant_id = $_SESSION['current_restaurant_id'];
$date = $_GET['date'] ?? null;
if (!$date) {
    echo json_encode(['error' => 'No date provided']);
    exit;
}
$stmt = $conn->prepare("SELECT block_time_start, block_time_end FROM blocked_slots WHERE restaurant_id = ? AND block_date = ?");
$stmt->bind_param("is", $restaurant_id, $date);
$stmt->execute();
$result = $stmt->get_result();
$blocks = [];
while ($row = $result->fetch_assoc()) {
    $blocks[] = $row;
}
$stmt->close();
echo json_encode(['blocks' => $blocks]);
