<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Review Submission Test</h1>";

// Check if config and database files are loaded
echo "<h2>Environment Check:</h2>";
echo "<p>Config File: " . (defined('DB_HOST') ? 'Loaded ✅' : 'Not Loaded ❌') . "</p>";
echo "<p>Database Connection: ";

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Connected ✅</p>";
    
    // Check if necessary tables exist
    echo "<h2>Database Tables Check:</h2>";
    
    $tables = [
        'users' => 'Check if users table exists',
        'restaurants' => 'Check if restaurants table exists',
        'reviews' => 'Check if reviews table exists'
    ];
    
    foreach ($tables as $table => $description) {
        $query = "SHOW TABLES LIKE '$table'";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>$description: " . ($result ? 'Exists ✅' : 'Missing ❌') . "</p>";
    }
    
    // Check the structure of the reviews table
    echo "<h2>Reviews Table Structure:</h2>";
    $query = "DESCRIBE reviews";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        foreach ($column as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Test form submission process
    echo "<h2>Form Submission Process Test:</h2>";
    
    // Check if user is logged in
    echo "<p>User Logged In: " . (isset($_SESSION['user_id']) ? 'Yes ✅ (ID: ' . $_SESSION['user_id'] . ')' : 'No ❌ - Login required to submit a review') . "</p>";
    
    // Check process_review.php file
    echo "<p>process_review.php File: " . (file_exists('process_review.php') ? 'Exists ✅' : 'Missing ❌') . "</p>";
    
    // Check if JavaScript is working (client-side only, will show a message)
    echo "<p>JavaScript Functionality: <span id='js-test'>Checking...</span></p>";
    
    // Check uploads directory
    $uploadDir = 'uploads/reviews/';
    echo "<p>Upload Directory: " . (file_exists($uploadDir) ? 'Exists ✅' : 'Missing ❌') . "</p>";
    
    if (!file_exists($uploadDir)) {
        echo "<p>Creating Upload Directory: ";
        $result = mkdir($uploadDir, 0777, true);
        echo ($result ? 'Success ✅' : 'Failed ❌') . "</p>";
    }
    
    // Check if directory is writable
    echo "<p>Upload Directory Writable: " . (is_writable($uploadDir) ? 'Yes ✅' : 'No ❌') . "</p>";
    
} catch (PDOException $e) {
    echo "Failed ❌</p>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Display a simplified review form for testing
if (isset($_SESSION['user_id'])) {
    echo "<h2>Test Review Form</h2>";
    echo "<p>Use this form to test submission:</p>";
    
    echo "<form id='testReviewForm' action='process_review.php' method='POST'>";
    echo "<input type='hidden' name='restaurant_id' value='1'>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Cleanliness Rating (1-5): </label>";
    echo "<input type='number' name='cleanliness_rating' min='1' max='5' required>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Taste Rating (1-5): </label>";
    echo "<input type='number' name='taste_rating' min='1' max='5' required>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Service Rating (1-5): </label>";
    echo "<input type='number' name='service_rating' min='1' max='5' required>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Price Rating (1-5): </label>";
    echo "<input type='number' name='price_rating' min='1' max='5' required>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Parking Rating (1-5): </label>";
    echo "<input type='number' name='parking_rating' min='1' max='5' required>";
    echo "</div>";
    
    echo "<div style='margin-bottom: 10px;'>";
    echo "<label>Review Text: </label>";
    echo "<textarea name='review_text' required></textarea>";
    echo "</div>";
    
    echo "<div>";
    echo "<button type='submit'>Submit Test Review</button>";
    echo "</div>";
    
    echo "</form>";
    
    echo "<div id='result'></div>";
    
    // Add JavaScript to handle form submission
    echo "<script>
    document.getElementById('js-test').textContent = 'Working ✅';
    
    document.getElementById('testReviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        document.getElementById('result').innerHTML = '<p>Submitting...</p>';
        
        fetch('process_review.php', {
            method: 'POST',
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            let resultHTML = '<h3>Submission Result:</h3>';
            
            if (data.success) {
                resultHTML += '<p style=\"color: green;\">Success ✅</p>';
                resultHTML += '<p>Message: ' + data.message + '</p>';
                resultHTML += '<p>Review ID: ' + data.review_id + '</p>';
            } else {
                resultHTML += '<p style=\"color: red;\">Error ❌</p>';
                resultHTML += '<p>Message: ' + data.message + '</p>';
            }
            
            document.getElementById('result').innerHTML = resultHTML;
        })
        .catch(error => {
            document.getElementById('result').innerHTML = '<p style=\"color: red;\">Error ❌: ' + error.message + '</p>';
        });
    });
    </script>";
} else {
    echo "<p>Please <a href='login.php'>login</a> to test the review submission form.</p>";
}
?> 