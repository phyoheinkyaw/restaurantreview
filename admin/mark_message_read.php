<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle AJAX request to mark message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'])) {
    $message_id = intval($_POST['message_id']);
    
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1, updated_at = NOW() WHERE message_id = :id");
        $stmt->bindParam(':id', $message_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
        exit;
    } catch (PDOException $e) {
        error_log("Error marking message as read: " . $e->getMessage());
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
} 