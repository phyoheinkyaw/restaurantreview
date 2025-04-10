<?php
// Database connection instance
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db->getConnection();
}

// Function to sanitize input data
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to get featured restaurants
function getFeaturedRestaurants() {
    try {
        $db = getDB();
        $query = "SELECT r.*, 
                    (SELECT AVG(overall_rating) FROM reviews WHERE restaurant_id = r.restaurant_id) as avg_rating,
                    (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.restaurant_id) as review_count
                 FROM restaurants r 
                 WHERE r.is_featured = 1
                 ORDER BY avg_rating DESC, review_count DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching featured restaurants: " . $e->getMessage());
        return [];
    }
}

// Function to format price range
function formatPriceRange($priceRange) {
    return str_repeat('$', strlen($priceRange));
}

// Function to format rating
function formatRating($rating) {
    return number_format($rating, 1);
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get user data
function getUserData($userId) {
    try {
        $db = getDB();
        $query = "SELECT * FROM users WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
        return false;
    }
}

// Function to get restaurant data
function getRestaurantData($restaurantId) {
    try {
        $db = getDB();
        $query = "SELECT r.*, 
                    (SELECT AVG(overall_rating) FROM reviews WHERE restaurant_id = r.restaurant_id) as avg_rating,
                    (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.restaurant_id) as review_count
                 FROM restaurants r 
                 WHERE restaurant_id = :restaurant_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching restaurant data: " . $e->getMessage());
        return false;
    }
}

// Function to truncate text
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

// Function to generate random string
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

// Function to handle file upload
function handleFileUpload($file, $destination, $allowedTypes = ['image/jpeg', 'image/png']) {
    try {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid parameters.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if (!in_array($file['type'], $allowedTypes)) {
            throw new RuntimeException('Invalid file format.');
        }

        $fileName = generateRandomString() . '_' . basename($file['name']);
        $filePath = $destination . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        return $fileName;
    } catch (RuntimeException $e) {
        error_log("File upload error: " . $e->getMessage());
        return false;
    }
}

// Function to calculate user points
function calculateUserPoints($userId) {
    try {
        $db = getDB();
        $query = "SELECT 
                    (SELECT COUNT(*) * :points_per_reservation FROM reservations WHERE user_id = :user_id AND status = 'completed') +
                    (SELECT COUNT(*) * :points_per_review FROM reviews WHERE user_id = :user_id) as total_points";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':points_per_reservation', POINTS_PER_RESERVATION, PDO::PARAM_INT);
        $stmt->bindValue(':points_per_review', 5, PDO::PARAM_INT); // 5 points per review
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_points'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error calculating user points: " . $e->getMessage());
        return 0;
    }
}

// Function to format date
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

// Function to check if restaurant is open
function isRestaurantOpen($openingHours) {
    $hours = json_decode($openingHours, true);
    if (!$hours) return false;

    $currentDay = strtolower(date('l'));
    $currentTime = date('H:i');

    if (!isset($hours[$currentDay])) return false;

    // Check if the restaurant is open during current time
    $dayHours = $hours[$currentDay];
    if (isset($dayHours['open']) && isset($dayHours['close'])) {
        return ($currentTime >= $dayHours['open'] && $currentTime <= $dayHours['close']);
    }

    return false;
}

// Function to get available time slots
function getAvailableTimeSlots($restaurantId, $date) {
    try {
        $db = getDB();
        
        // Get restaurant opening hours
        $stmt = $db->prepare("
            SELECT opening_hours
            FROM restaurants
            WHERE restaurant_id = :restaurant_id
        ");
        $stmt->bindParam(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->execute();
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$restaurant) {
            return [];
        }
        
        $hours = json_decode($restaurant['opening_hours'], true);
        $day = strtolower(date('l', strtotime($date)));
        
        if (!isset($hours[$day])) {
            return [];
        }
        
        // Get existing reservations for the date
        $stmt = $db->prepare("
            SELECT reservation_time
            FROM reservations
            WHERE restaurant_id = :restaurant_id
            AND reservation_date = :date
            AND status != 'cancelled'
        ");
        $stmt->bindParam(':restaurant_id', $restaurantId, PDO::PARAM_INT);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $reserved_times = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Generate time slots
        $slots = [];
        $start_time = strtotime($hours[$day]['open']);
        $end_time = strtotime($hours[$day]['close']);
        $interval = 30 * 60; // 30 minutes in seconds
        
        for ($time = $start_time; $time < $end_time; $time += $interval) {
            $time_str = date('H:i', $time);
            if (!in_array($time_str, $reserved_times)) {
                $slots[] = $time_str;
            }
        }
        
        return $slots;
    } catch (PDOException $e) {
        error_log("Error getting available time slots: " . $e->getMessage());
        return [];
    }
}

/**
 * Get menu items for a specific restaurant
 * @param int $restaurant_id The ID of the restaurant
 * @return array Array of menu items
 */
function getRestaurantMenu($restaurant_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT menu_id as id, name, description, price, category, image as image_url, 
                   is_available, 0 as is_vegetarian, 0 as is_spicy
            FROM menus
            WHERE restaurant_id = :restaurant_id
            ORDER BY category, name
        ");
        
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching restaurant menu: " . $e->getMessage());
        return [];
    }
}

/**
 * Get reviews for a specific restaurant
 * @param int $restaurant_id The ID of the restaurant
 * @return array Array of reviews
 */
function getRestaurantReviews($restaurant_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.*, u.username 
            FROM reviews r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.restaurant_id = :restaurant_id
            ORDER BY r.created_at DESC
        ");
        
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching restaurant reviews: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate average ratings for a restaurant
 * @param int $restaurant_id The ID of the restaurant
 * @return array Array of average ratings
 */
function calculateAverageRatings($restaurant_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT 
                COALESCE(AVG(overall_rating), 0) as overall,
                COALESCE(AVG(cleanliness_rating), 0) as cleanliness,
                COALESCE(AVG(taste_rating), 0) as taste,
                COALESCE(AVG(service_rating), 0) as service,
                COALESCE(AVG(price_rating), 0) as price,
                COALESCE(AVG(parking_rating), 0) as parking
            FROM reviews
            WHERE restaurant_id = :restaurant_id
        ");
        
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->execute();
        $ratings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Round all ratings to 1 decimal place
        foreach ($ratings as $key => $value) {
            $ratings[$key] = round((float)$value, 1);
        }
        
        return $ratings;
    } catch (PDOException $e) {
        error_log("Error calculating average ratings: " . $e->getMessage());
        return [
            'overall' => 0,
            'cleanliness' => 0,
            'taste' => 0,
            'service' => 0,
            'price' => 0,
            'parking' => 0
        ];
    }
}

/**
 * Get similar restaurants based on cuisine type
 * @param int $restaurant_id The ID of the current restaurant
 * @param string $cuisine_type The cuisine type to match
 * @param int $limit Number of similar restaurants to return
 * @return array Array of similar restaurants
 */
function getSimilarRestaurants($restaurant_id, $cuisine_type, $limit = 3) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT r.*, 
                COALESCE((SELECT AVG(overall_rating) FROM reviews WHERE restaurant_id = r.restaurant_id), 0) as avg_rating
            FROM restaurants r
            WHERE r.cuisine_type = :cuisine_type 
            AND r.restaurant_id != :restaurant_id
            ORDER BY avg_rating DESC, RAND()
            LIMIT :limit
        ");
        
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->bindParam(':cuisine_type', $cuisine_type, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching similar restaurants: " . $e->getMessage());
        return [];
    }
}

/**
 * Get the average rating for a restaurant
 * @param int $restaurant_id The ID of the restaurant
 * @return float|null The average rating or null if no reviews exist
 */
function getRestaurantAverageRating($restaurant_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COALESCE(AVG(overall_rating), 0) as average_rating 
            FROM reviews 
            WHERE restaurant_id = :restaurant_id
        ");
        
        $stmt->bindParam(':restaurant_id', $restaurant_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['average_rating'] !== null ? (float)$result['average_rating'] : 0;
    } catch (PDOException $e) {
        error_log("Error getting restaurant average rating: " . $e->getMessage());
        return 0;
    }
}

// Function to format time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    if ($time_difference < 60) {
        return 'Just now';
    } elseif ($time_difference < 3600) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 86400) {
        $hours = floor($time_difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 604800) {
        $days = floor($time_difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 2592000) {
        $weeks = floor($time_difference / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif ($time_difference < 31536000) {
        $months = floor($time_difference / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($time_difference / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}