<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Verify request
$input = json_decode(file_get_contents('php://input'), true);
$expected_token = md5(session_id() . $_SESSION['user_id']);

if (!isset($input['check_token']) || $input['check_token'] !== $expected_token) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Connect to database
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Update confirmed reservations that are past their time to completed
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'completed' 
        WHERE status = 'confirmed' 
        AND CONCAT(reservation_date, ' ', reservation_time) < NOW()
    ");
    $stmt->execute();
    
    $updated_count = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'updated' => $updated_count
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating reservation status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
} 