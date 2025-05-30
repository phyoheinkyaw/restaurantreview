<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a review']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $restaurant_id = isset($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
    $user_id = $_SESSION['user_id'];
    $cleanliness_rating = isset($_POST['cleanliness_rating']) ? (float)$_POST['cleanliness_rating'] : 0;
    $taste_rating = isset($_POST['taste_rating']) ? (float)$_POST['taste_rating'] : 0;
    $service_rating = isset($_POST['service_rating']) ? (float)$_POST['service_rating'] : 0;
    $price_rating = isset($_POST['price_rating']) ? (float)$_POST['price_rating'] : 0;
    $parking_rating = isset($_POST['parking_rating']) ? (float)$_POST['parking_rating'] : 0;
    $comment = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

    // Validate inputs
    if (!$restaurant_id || !$user_id) {
        throw new Exception('Invalid restaurant or user ID');
    }

    if (!$cleanliness_rating || !$taste_rating || !$service_rating || !$price_rating || !$parking_rating) {
        throw new Exception('All ratings are required');
    }

    if (empty($comment)) {
        throw new Exception('Review text is required');
    }

    // Calculate overall rating
    $overall_rating = round(($cleanliness_rating + $taste_rating + $service_rating + $price_rating + $parking_rating) / 5, 1);

    // Handle image uploads
    $images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = 'uploads/reviews/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_extension = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                $new_filename = uniqid('review_') . '.' . $file_extension;
                $destination = $upload_dir . $new_filename;

                // Check if it's a valid image
                if (!getimagesize($tmp_name)) {
                    throw new Exception('Invalid image file');
                }

                // Move uploaded file
                if (move_uploaded_file($tmp_name, $destination)) {
                    $images[] = $destination;
                }
            }
        }
    }

    $images_json = !empty($images) ? json_encode($images) : null;

    // Get database connection
    $db = getDB();
    
    // Begin transaction
    $db->beginTransaction();

    // Insert review
    $sql = "INSERT INTO reviews (
        restaurant_id, 
        user_id, 
        overall_rating,
        cleanliness_rating,
        taste_rating,
        service_rating,
        price_rating,
        parking_rating,
        comment,
        images,
        is_read,
        created_at
    ) VALUES (
        :restaurant_id, 
        :user_id, 
        :overall_rating,
        :cleanliness_rating,
        :taste_rating,
        :service_rating,
        :price_rating,
        :parking_rating,
        :comment,
        :images,
        0,
        NOW()
    )";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement');
    }

    $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':overall_rating', $overall_rating, PDO::PARAM_STR);
    $stmt->bindParam(':cleanliness_rating', $cleanliness_rating, PDO::PARAM_STR);
    $stmt->bindParam(':taste_rating', $taste_rating, PDO::PARAM_STR);
    $stmt->bindParam(':service_rating', $service_rating, PDO::PARAM_STR);
    $stmt->bindParam(':price_rating', $price_rating, PDO::PARAM_STR);
    $stmt->bindParam(':parking_rating', $parking_rating, PDO::PARAM_STR);
    $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
    $stmt->bindParam(':images', $images_json, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert review');
    }

    // Get the inserted review ID
    $review_id = $db->lastInsertId();

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully',
        'review_id' => $review_id
    ]);

} catch (Exception $e) {
    // Rollback transaction if started
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error
    error_log("Review submission error: " . $e->getMessage());
} 