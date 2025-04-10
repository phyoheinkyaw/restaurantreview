<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get user's reviews
try {
    $stmt = $db->prepare("
        SELECT r.*, 
               rv.cleanliness_rating,
               rv.taste_rating,
               rv.service_rating,
               rv.price_rating,
               rv.parking_rating,
               rv.overall_rating,
               rv.comment,
               rv.created_at as review_created_at,
               rv.updated_at as review_updated_at,
               u.username,
               u.points
        FROM reviews rv
        JOIN restaurants r ON rv.restaurant_id = r.restaurant_id
        JOIN users u ON rv.user_id = u.user_id
        WHERE rv.user_id = ?
        ORDER BY rv.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    $reviews = [];
}

// Calculate total points earned
$total_points = 0;
foreach ($reviews as $review) {
    $total_points += 100; // Each review earns 100 points
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - Restaurant Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-4">
        <div class="row">
            <!-- Points Summary -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="h3 mb-3">Review Summary</h3>
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-star text-warning me-3"></i>
                            <h4 class="mb-0"><?php echo count($reviews); ?></h4>
                            <small class="text-muted ms-2">Total Reviews</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-gem text-primary me-3"></i>
                            <h4 class="mb-0"><?php echo $total_points; ?></h4>
                            <small class="text-muted ms-2">Total Points</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h3 mb-4">My Reviews</h2>
                        
                        <?php if (empty($reviews)): ?>
                            <div class="alert alert-info">
                                You haven't written any reviews yet. <a href="index.php">Find a restaurant</a> to review!
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <!-- Header with Restaurant Info -->
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($review['name']); ?></h5>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($review['cuisine_type']); ?>
                                                    <span class="mx-2">•</span>
                                                    <?php echo htmlspecialchars($review['address']); ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2 align-items-center">
                                                <a href="restaurant.php?id=<?php echo $review['restaurant_id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-external-link-alt me-1"></i>View Restaurant
                                                </a>
                                                <?php if ($review['review_updated_at'] != $review['review_created_at']): ?>
                                                    <span class="badge bg-info text-dark">Edited</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Overall Rating with Number and Stars -->
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="h4 mb-0 me-2"><?php echo round($review['overall_rating'], 1); ?></span>
                                            <div class="star-rating" style="font-size: 24px;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= ceil($review['overall_rating'])): ?>
                                                        <i class="fas fa-star text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-warning"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <small class="text-muted ms-2">Overall Rating</small>
                                        </div>

                                        <!-- Individual Ratings with Icons -->
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-utensils text-success me-2"></i>
                                                    <div class="star-rating" style="font-size: 16px;">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= ceil($review['taste_rating'])): ?>
                                                                <i class="fas fa-star text-success"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-success"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted ms-2">Taste</small>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-user-tie text-primary me-2"></i>
                                                    <div class="star-rating" style="font-size: 16px;">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= ceil($review['service_rating'])): ?>
                                                                <i class="fas fa-star text-primary"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-primary"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted ms-2">Service</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-money-bill-wave text-info me-2"></i>
                                                    <div class="star-rating" style="font-size: 16px;">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= ceil($review['price_rating'])): ?>
                                                                <i class="fas fa-star text-info"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-info"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted ms-2">Price</small>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-parking text-warning me-2"></i>
                                                    <div class="star-rating" style="font-size: 16px;">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= ceil($review['parking_rating'])): ?>
                                                                <i class="fas fa-star text-warning"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-warning"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <small class="text-muted ms-2">Parking</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Review Text with Better Styling -->
                                        <div class="review-text mb-4">
                                            <p class="lead fw-normal mb-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                            <div class="d-flex align-items-center gap-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php
                                                    $date = new DateTime($review['review_created_at']);
                                                    echo $date->format('F j, Y \a\t g:i A');
                                                    ?>
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Points Earned -->
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-gem text-primary me-2"></i>
                                            <span class="text-muted">Earned 100 points for this review</span>
                                        </div>

                                        <!-- Restaurant Details -->
                                        <div class="d-flex flex-column gap-2">
                                            <div class="d-flex align-items-center">
                                                <span class="me-2">Price Range:</span>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo htmlspecialchars($review['price_range']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

</body>
</html>
