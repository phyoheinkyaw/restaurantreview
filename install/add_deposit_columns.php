<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "You must be an admin to run this script.";
    exit;
}

// Check if deposit columns exist in restaurants table
$result = $conn->query("SHOW COLUMNS FROM restaurants LIKE 'deposit_required'");
$depositColumnsExist = $result->num_rows > 0;

// Add deposit columns to restaurants table if they don't exist
if (!$depositColumnsExist) {
    $alterRestaurants = "ALTER TABLE restaurants 
                        ADD COLUMN deposit_required TINYINT(1) NOT NULL DEFAULT 0,
                        ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        ADD COLUMN deposit_account_name VARCHAR(255) NULL,
                        ADD COLUMN deposit_account_number VARCHAR(50) NULL,
                        ADD COLUMN deposit_bank_name VARCHAR(255) NULL,
                        ADD COLUMN deposit_payment_instructions TEXT NULL";
    
    if ($conn->query($alterRestaurants)) {
        echo "Successfully added deposit columns to restaurants table.<br>";
    } else {
        echo "Error adding deposit columns to restaurants table: " . $conn->error . "<br>";
    }
}

// Check if deposit columns exist in reservations table
$result = $conn->query("SHOW COLUMNS FROM reservations LIKE 'deposit_status'");
$depositStatusExists = $result->num_rows > 0;

// Add deposit columns to reservations table if they don't exist
if (!$depositStatusExists) {
    $alterReservations = "ALTER TABLE reservations 
                        ADD COLUMN deposit_status ENUM('pending', 'verified', 'rejected') NULL,
                        ADD COLUMN deposit_amount DECIMAL(10,2) NULL,
                        ADD COLUMN deposit_payment_date DATETIME NULL,
                        ADD COLUMN deposit_payment_slip VARCHAR(255) NULL,
                        ADD COLUMN deposit_verification_date DATETIME NULL,
                        ADD COLUMN deposit_verified_by INT NULL,
                        ADD COLUMN deposit_rejection_reason TEXT NULL,
                        ADD INDEX (deposit_status),
                        ADD FOREIGN KEY (deposit_verified_by) REFERENCES users(user_id)";
    
    if ($conn->query($alterReservations)) {
        echo "Successfully added deposit columns to reservations table.<br>";
    } else {
        echo "Error adding deposit columns to reservations table: " . $conn->error . "<br>";
    }
}

echo "Database setup completed.";
?> 