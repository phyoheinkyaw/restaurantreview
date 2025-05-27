<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user = getUserData($user_id);

// Get user's reviews
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.*, 
               rv.review_id,
               rv.cleanliness_rating,
               rv.taste_rating,
               rv.service_rating,
               rv.price_rating,
               rv.parking_rating,
               rv.overall_rating,
               rv.comment,
               rv.images,
               rv.created_at as review_created_at,
               rv.updated_at as review_updated_at
        FROM reviews rv
        JOIN restaurants r ON rv.restaurant_id = r.restaurant_id
        WHERE rv.user_id = :user_id
        ORDER BY rv.created_at DESC
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    $reviews = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - Restaurant Review</title>
    
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-4">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle img-fluid mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center mx-auto mb-3" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h5>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <span>Points</span>
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($user['points']); ?></span>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <a href="profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a href="reservations.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar me-2"></i> My Reservations
                        </a>
                        <a href="reviews.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-star me-2"></i> My Reviews
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Review Summary Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Review Summary</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="icon-wrapper bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-star text-primary fs-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo count($reviews); ?></h3>
                                        <p class="text-muted mb-0">Total Reviews</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <div class="icon-wrapper bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-gem text-success fs-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo $user['points']; ?></h3>
                                        <p class="text-muted mb-0">Total Points</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <?php
                                // Calculate average rating
                                $total_rating = 0;
                                $count = count($reviews);
                                foreach ($reviews as $review) {
                                    $total_rating += $review['overall_rating'];
                                }
                                $avg_rating = $count > 0 ? $total_rating / $count : 0;
                                ?>
                                <div class="d-flex align-items-center">
                                    <div class="icon-wrapper bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                                        <i class="fas fa-award text-warning fs-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?php echo number_format($avg_rating, 1); ?></h3>
                                        <p class="text-muted mb-0">Average Rating</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">My Reviews</h4>
                        <a href="index.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus me-1"></i> Write a New Review
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reviews)): ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i> You haven't written any reviews yet. 
                                <a href="index.php" class="alert-link">Find a restaurant</a> to review!
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="col-12">
                                        <div class="card border">
                                            <div class="card-body">
                                                <!-- Restaurant Info -->
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($review['image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($review['image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($review['name']); ?>" 
                                                                 class="rounded me-3" 
                                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                                 style="width: 60px; height: 60px;">
                                                                <i class="fas fa-utensils text-muted fs-4"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h5 class="mb-1"><?php echo htmlspecialchars($review['name']); ?></h5>
                                                            <div class="d-flex align-items-center text-muted">
                                                                <i class="fas fa-utensils me-2"></i>
                                                                <span><?php echo htmlspecialchars($review['cuisine_type'] ?? 'N/A'); ?></span>
                                                                <i class="fas fa-map-marker-alt ms-3 me-2"></i>
                                                                <span><?php echo htmlspecialchars($review['address']); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <a href="restaurant.php?id=<?php echo $review['restaurant_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt me-1"></i> View
                                                        </a>
                                                    </div>
                                                </div>
                                                
                                                <!-- Overall Rating -->
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-warning bg-opacity-10 rounded px-3 py-2 me-3">
                                                        <span class="fw-bold text-warning fs-4"><?php echo number_format($review['overall_rating'], 1); ?></span>
                                                    </div>
                                                    <div class="rating">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <?php if ($i <= floor($review['overall_rating'])): ?>
                                                                <i class="fas fa-star text-warning"></i>
                                                            <?php elseif ($i - 0.5 <= $review['overall_rating']): ?>
                                                                <i class="fas fa-star-half-alt text-warning"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-star text-warning"></i>
                                                            <?php endif; ?>
                                                        <?php endfor; ?>
                                                        <span class="ms-2 text-muted">Overall Rating</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Detailed Ratings -->
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>Taste</span>
                                                            <div class="rating small">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $review['taste_rating']): ?>
                                                                        <i class="fas fa-star text-success"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star text-success"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>Service</span>
                                                            <div class="rating small">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $review['service_rating']): ?>
                                                                        <i class="fas fa-star text-primary"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star text-primary"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>Price</span>
                                                            <div class="rating small">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $review['price_rating']): ?>
                                                                        <i class="fas fa-star text-info"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star text-info"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span>Cleanliness</span>
                                                            <div class="rating small">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <?php if ($i <= $review['cleanliness_rating']): ?>
                                                                        <i class="fas fa-star text-warning"></i>
                                                                    <?php else: ?>
                                                                        <i class="far fa-star text-warning"></i>
                                                                    <?php endif; ?>
                                                                <?php endfor; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Review Comment -->
                                                <div class="review-comment mb-3">
                                                    <h6 class="fw-bold mb-2">My Review</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                                </div>
                                                
                                                <!-- Review Images -->
                                                <?php if (!empty($review['images'])): ?>
                                                    <?php $images = json_decode($review['images'], true); ?>
                                                    <?php if (!empty($images)): ?>
                                                        <div class="review-images mb-3">
                                                            <h6 class="fw-bold mb-2">Photos</h6>
                                                            <div class="row g-2">
                                                                <?php foreach ($images as $image): ?>
                                                                    <div class="col-4 col-md-3 col-lg-2">
                                                                        <a href="<?php echo htmlspecialchars($image); ?>" target="_blank">
                                                                            <img src="<?php echo htmlspecialchars($image); ?>" 
                                                                                 class="img-fluid rounded" 
                                                                                 alt="Review Image">
                                                                        </a>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                
                                                <!-- Review Footer -->
                                                <div class="d-flex justify-content-between align-items-center text-muted small">
                                                    <div>
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php 
                                                        $date = new DateTime($review['review_created_at']);
                                                        echo $date->format('F j, Y \a\t g:i A'); 
                                                        ?>
                                                    </div>
                                                    <?php if ($review['review_updated_at'] != $review['review_created_at']): ?>
                                                        <span class="badge bg-info text-dark">
                                                            <i class="fas fa-edit me-1"></i> Edited
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
