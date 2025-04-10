<?php
include 'includes/header.php';

// Check if review ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">No review ID provided.</div>';
    echo '<div class="text-center mt-3"><a href="reviews.php" class="btn btn-primary">Back to Reviews</a></div>';
    include 'includes/footer.php';
    exit;
}

$review_id = intval($_GET['id']);

// Get detailed review information with all ratings
$stmt = $conn->prepare("
    SELECT r.*, 
           u.username, u.email, u.first_name, u.last_name, u.profile_image, u.user_id,
           res.name as restaurant_name, res.address, res.phone, res.cuisine_type, res.restaurant_id,
           res.price_range, res.opening_hours, res.image as restaurant_image
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN restaurants res ON r.restaurant_id = res.restaurant_id
    WHERE r.review_id = ?
");

$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();
$review = $result->fetch_assoc();
$stmt->close();

if (!$review) {
    echo '<div class="alert alert-danger">Review not found.</div>';
    echo '<div class="text-center mt-3"><a href="reviews.php" class="btn btn-primary">Back to Reviews</a></div>';
    include 'includes/footer.php';
    exit;
}

// Get user's other reviews count
$stmt = $conn->prepare("SELECT COUNT(*) as review_count FROM reviews WHERE user_id = ? AND review_id != ?");
$stmt->bind_param("ii", $review['user_id'], $review_id);
$stmt->execute();
$result = $stmt->get_result();
$other_reviews_count = $result->fetch_assoc()['review_count'];
$stmt->close();

// Get restaurant's review statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        ROUND(AVG(overall_rating), 1) as avg_rating,
        ROUND(AVG(cleanliness_rating), 1) as avg_cleanliness,
        ROUND(AVG(taste_rating), 1) as avg_taste,
        ROUND(AVG(service_rating), 1) as avg_service,
        ROUND(AVG(price_rating), 1) as avg_price,
        ROUND(AVG(parking_rating), 1) as avg_parking
    FROM reviews 
    WHERE restaurant_id = ?
");
$stmt->bind_param("i", $review['restaurant_id']);
$stmt->execute();
$result = $stmt->get_result();
$restaurant_stats = $result->fetch_assoc();
$stmt->close();

// Get user's review statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        ROUND(AVG(overall_rating), 1) as avg_rating
    FROM reviews 
    WHERE user_id = ?
");
$stmt->bind_param("i", $review['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user_stats = $result->fetch_assoc();
$stmt->close();

// Format the username for display
$display_name = !empty($review['first_name']) ? 
    htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) : 
    htmlspecialchars($review['username']);

// Get review age
$review_date = new DateTime($review['created_at']);
$now = new DateTime();
$review_age = $review_date->diff($now);
$review_age_text = '';

if ($review_age->y > 0) {
    $review_age_text .= $review_age->y . ' year' . ($review_age->y > 1 ? 's' : '') . ' ago';
} elseif ($review_age->m > 0) {
    $review_age_text .= $review_age->m . ' month' . ($review_age->m > 1 ? 's' : '') . ' ago';
} elseif ($review_age->d > 0) {
    $review_age_text .= $review_age->d . ' day' . ($review_age->d > 1 ? 's' : '') . ' ago';
} elseif ($review_age->h > 0) {
    $review_age_text .= $review_age->h . ' hour' . ($review_age->h > 1 ? 's' : '') . ' ago';
} else {
    $review_age_text .= $review_age->i . ' minute' . ($review_age->i > 1 ? 's' : '') . ' ago';
}

// Function to display star rating
function displayStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star text-warning"></i>';
        } else if ($i - 0.5 <= $rating) {
            $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
        } else {
            $html .= '<i class="far fa-star text-warning"></i>';
        }
    }
    return $html . ' <span class="rating-value">(' . $rating . ')</span>';
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Review Details</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="reviews.php">Reviews</a></li>
                <li class="breadcrumb-item active">View Review</li>
            </ol>
        </div>
        <div>
            <?php
            // Check which page the user came from
            $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $back_url = (strpos($referrer, 'manage_reviews.php') !== false) 
                ? 'manage_reviews.php' 
                : 'reviews.php';
            ?>
            <a href="<?php echo $back_url; ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Reviews
            </a>
            <a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteReviewModal">
                <i class="fas fa-trash-alt me-1"></i> Delete Review
            </a>
        </div>
    </div>

    <!-- Review Details Section -->
    <div class="row">
        <!-- Left column: Review content and rating details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-comment-alt me-1"></i>
                        Review Content
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?>
                        (<?php echo $review_age_text; ?>)
                    </div>
                </div>
                <div class="card-body">
                    <!-- Overall rating display -->
                    <div class="mb-4">
                        <h5 class="d-flex align-items-center">
                            <span class="me-3">Overall Rating:</span>
                            <span class="h4 mb-0 text-warning">
                                <?php echo displayStars($review['overall_rating']); ?>
                            </span>
                        </h5>
                    </div>

                    <!-- Review comment -->
                    <div class="p-3 bg-light rounded mb-4">
                        <h5>Review Comment:</h5>
                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                        </div>
                    </div>

                    <!-- Detailed category ratings -->
                    <h5 class="mb-3">Category Ratings</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-broom me-2"></i> Cleanliness:</span>
                                    <span><?php echo displayStars($review['cleanliness_rating']); ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo ($review['cleanliness_rating']/5)*100; ?>%"
                                        aria-valuenow="<?php echo $review['cleanliness_rating']; ?>" aria-valuemin="0"
                                        aria-valuemax="5"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-utensils me-2"></i> Taste:</span>
                                    <span><?php echo displayStars($review['taste_rating']); ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo ($review['taste_rating']/5)*100; ?>%"
                                        aria-valuenow="<?php echo $review['taste_rating']; ?>" aria-valuemin="0"
                                        aria-valuemax="5"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-concierge-bell me-2"></i> Service:</span>
                                    <span><?php echo displayStars($review['service_rating']); ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo ($review['service_rating']/5)*100; ?>%"
                                        aria-valuenow="<?php echo $review['service_rating']; ?>" aria-valuemin="0"
                                        aria-valuemax="5"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-dollar-sign me-2"></i> Value for Money:</span>
                                    <span><?php echo displayStars($review['price_rating']); ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo ($review['price_rating']/5)*100; ?>%"
                                        aria-valuenow="<?php echo $review['price_rating']; ?>" aria-valuemin="0"
                                        aria-valuemax="5"></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><i class="fas fa-parking me-2"></i> Parking:</span>
                                    <span><?php echo displayStars($review['parking_rating']); ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-success" role="progressbar"
                                        style="width: <?php echo ($review['parking_rating']/5)*100; ?>%"
                                        aria-valuenow="<?php echo $review['parking_rating']; ?>" aria-valuemin="0"
                                        aria-valuemax="5"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($review['images'])): 
                        $images = json_decode($review['images'], true);
                        if ($images && count($images) > 0): ?>
                    <!-- Review images -->
                    <h5 class="mt-4 mb-3">Review Images</h5>
                    <div class="row">
                        <?php foreach ($images as $image): ?>
                        <div class="col-md-3 mb-3">
                            <a href="../<?php echo htmlspecialchars($image); ?>" target="_blank">
                                <img src="../<?php echo htmlspecialchars($image); ?>" class="img-fluid rounded"
                                    alt="Review Image">
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Restaurant statistics compared to this review -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i>
                    Comparison with Restaurant Averages
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>This Review</th>
                                    <th>Restaurant Average</th>
                                    <th>Comparison</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-star text-warning me-1"></i> Overall</td>
                                    <td><?php echo $review['overall_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_rating']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['overall_rating'] - $restaurant_stats['avg_rating'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-broom me-1"></i> Cleanliness</td>
                                    <td><?php echo $review['cleanliness_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_cleanliness']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['cleanliness_rating'] - $restaurant_stats['avg_cleanliness'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-utensils me-1"></i> Taste</td>
                                    <td><?php echo $review['taste_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_taste']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['taste_rating'] - $restaurant_stats['avg_taste'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-concierge-bell me-1"></i> Service</td>
                                    <td><?php echo $review['service_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_service']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['service_rating'] - $restaurant_stats['avg_service'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-dollar-sign me-1"></i> Value for Money</td>
                                    <td><?php echo $review['price_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_price']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['price_rating'] - $restaurant_stats['avg_price'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-parking me-1"></i> Parking</td>
                                    <td><?php echo $review['parking_rating']; ?>/5</td>
                                    <td><?php echo $restaurant_stats['avg_parking']; ?>/5</td>
                                    <td>
                                        <?php 
                                        $diff = $review['parking_rating'] - $restaurant_stats['avg_parking'];
                                        if ($diff > 0) {
                                            echo '<span class="text-success"><i class="fas fa-arrow-up me-1"></i> ' . number_format(abs($diff), 1) . ' above average</span>';
                                        } elseif ($diff < 0) {
                                            echo '<span class="text-danger"><i class="fas fa-arrow-down me-1"></i> ' . number_format(abs($diff), 1) . ' below average</span>';
                                        } else {
                                            echo '<span class="text-muted"><i class="fas fa-equals me-1"></i> Same as average</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right column: Sidebar with User and Restaurant Info -->
        <div class="col-lg-4">
            <!-- User Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-user me-1"></i>
                    Reviewer Information
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($review['profile_image']) && file_exists('../uploads/profile/' . $review['profile_image'])): ?>
                        <img src="../uploads/profile/<?php echo htmlspecialchars($review['profile_image']); ?>"
                            alt="Profile Image" class="img-fluid rounded-circle mb-2"
                            style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                        <div class="profile-initial rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-2"
                            style="width: 100px; height: 100px; font-size: 36px;">
                            <?php echo strtoupper(substr($review['username'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <h5><?php echo $display_name; ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($review['email']); ?></p>
                    </div>

                    <div class="mb-3">
                        <div class="dashboard-card bg-light py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Reviews</h6>
                                    <div class="small text-muted">Contributed by this user</div>
                                </div>
                                <h3 class="card-value mb-0"><?php echo $user_stats['total_reviews']; ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="dashboard-card bg-light py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Average Rating</h6>
                                    <div class="small text-muted">Given by this user</div>
                                </div>
                                <h3 class="card-value mb-0">
                                    <?php echo $user_stats['avg_rating']; ?>
                                    <small class="text-warning">★</small>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="view_user.php?id=<?php echo $review['user_id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-user me-1"></i> View Full Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Restaurant Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-store me-1"></i>
                    Restaurant Information
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($review['restaurant_image']) && file_exists('../uploads/restaurants/' . $review['restaurant_image'])): ?>
                        <img src="../uploads/restaurants/<?php echo htmlspecialchars($review['restaurant_image']); ?>"
                            alt="Restaurant Image" class="img-fluid rounded mb-2" style="max-height: 150px;">
                        <?php else: ?>
                        <div class="bg-light rounded p-4 mb-2 text-center">
                            <i class="fas fa-utensils fa-3x text-muted"></i>
                            <p class="mt-2 mb-0 text-muted">No image available</p>
                        </div>
                        <?php endif; ?>
                        <h5><?php echo htmlspecialchars($review['restaurant_name']); ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($review['cuisine_type']); ?></p>
                    </div>

                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-map-marker-alt me-2"></i> Address</span>
                            <span class="text-muted"><?php echo htmlspecialchars($review['address']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-phone me-2"></i> Phone</span>
                            <span class="text-muted"><?php echo htmlspecialchars($review['phone']); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-dollar-sign me-2"></i> Price Range</span>
                            <span class="text-muted">
                                <?php 
                                $price_range = '';
                                for ($i = 0; $i < intval($review['price_range']); $i++) {
                                    $price_range .= '$';
                                }
                                echo $price_range; 
                                ?>
                            </span>
                        </li>
                    </ul>

                    <div class="mb-3">
                        <div class="dashboard-card bg-light py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Reviews</h6>
                                    <div class="small text-muted">For this restaurant</div>
                                </div>
                                <h3 class="card-value mb-0"><?php echo $restaurant_stats['total_reviews']; ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="dashboard-card bg-light py-2 px-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Average Rating</h6>
                                    <div class="small text-muted">For this restaurant</div>
                                </div>
                                <h3 class="card-value mb-0">
                                    <?php echo $restaurant_stats['avg_rating']; ?>
                                    <small class="text-warning">★</small>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="edit_restaurant.php?id=<?php echo $review['restaurant_id']; ?>"
                            class="btn btn-primary btn-sm">
                            <i class="fas fa-store me-1"></i> View Restaurant Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Review Modal -->
<div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-labelledby="deleteReviewModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteReviewModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this review?</p>
                <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($review['restaurant_name']); ?></p>
                <p><strong>User:</strong> <?php echo $display_name; ?></p>
                <p><strong>Rating:</strong> <?php echo $review['overall_rating']; ?> stars</p>
                <p><strong>Comment:</strong>
                    <?php echo htmlspecialchars(substr($review['comment'], 0, 100)) . (strlen($review['comment']) > 100 ? '...' : ''); ?>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="<?php echo $back_url; ?>?action=delete&id=<?php echo $review_id; ?>" class="btn btn-danger">
                    Delete
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>