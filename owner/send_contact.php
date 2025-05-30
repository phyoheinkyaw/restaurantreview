<?php
// Include necessary files
require_once 'includes/db_connect.php';
// No need to include functions.php if it doesn't exist

// Redirect if not accessed via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) { // Changed from 'owner_id' to 'user_id'
    header("Location: login.php");
    exit;
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : 'Contact Form Submission';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate form data
if (empty($name) || empty($email) || empty($message)) {
    $_SESSION['error'] = "Please fill all required fields.";
    header("Location: profile.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Please provide a valid email address.";
    header("Location: profile.php");
    exit;
}

try {
    // Insert message into the contact_messages table
    $sql = "INSERT INTO contact_messages (name, email, subject, message, is_read) VALUES (?, ?, ?, ?, 0)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $subject, $message);
    
    if ($stmt->execute()) {
        // Set success message
        $_SESSION['success'] = "Your message has been sent. The admin team will contact you shortly.";
        
        // Optionally, send an email notification to admin
        // This requires a mail server configuration
        $admin_email = "admin@example.com";
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Uncomment to enable email sending - make sure your server has mail capability
        // mail($admin_email, "New Contact: $subject", $message, $headers);
        
    } else {
        $_SESSION['error'] = "Failed to send your message. Please try again later.";
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
}

// Redirect back to profile page
header("Location: profile.php");
exit;
?> 