<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get restaurant ID from URL
$restaurant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get restaurant details
$restaurant = getRestaurantData($restaurant_id);
if (!$restaurant) {
    header('Location: index.php');
    exit();
}

// Get restaurant menu
$menu = getRestaurantMenu($restaurant_id);

// Get restaurant reviews
$reviews = getRestaurantReviews($restaurant_id);

// Get average ratings
$avgRatings = calculateAverageRatings($restaurant_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant['name']); ?> - Restaurant Review</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A==" crossorigin=""/>
    <style>
        .rating-input {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .rating-input .stars {
            display: flex;
            flex-direction: row-reverse;
            gap: 0.25rem;
        }
        .rating-input input[type="radio"] {
            display: none;
        }
        .rating-input label {
            cursor: pointer;
            color: #dee2e6;
            font-size: 1.5rem;
            transition: color 0.2s ease;
        }
        .rating-input label:hover,
        .rating-input label:hover ~ label,
        .rating-input input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
        .rating-text {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Restaurant Header -->
    <section class="restaurant-header pt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="search.php">Restaurants</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($restaurant['name']); ?></li>
                        </ol>
                    </nav>
                    
                    <h1 class="restaurant-name"><?php echo htmlspecialchars($restaurant['name']); ?></h1>
                    
                    <div class="restaurant-meta">
                        <span class="cuisine-type">
                            <i class="fas fa-utensils"></i> <?php echo htmlspecialchars($restaurant['cuisine_type']); ?>
                        </span>
                        <span class="price-range">
                            <i class="fas fa-dollar-sign"></i> <?php echo formatPriceRange($restaurant['price_range']); ?>
                        </span>
                        <span class="rating">
                            <i class="fas fa-star text-warning"></i> <?php echo formatRating($avgRatings['overall']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="col-lg-4 text-lg-end">
                    <div class="action-buttons">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="reservation.php?id=<?php echo $restaurant_id; ?>" class="btn btn-primary me-2">
                                <i class="fas fa-calendar-alt me-2"></i>Make Reservation
                            </a>
                            <a href="#reviews-section" class="btn btn-outline-primary">
                                <i class="fas fa-star me-2"></i>Write Review
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary me-2">
                                <i class="fas fa-calendar-alt me-2"></i>Make Reservation
                            </a>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="fas fa-star me-2"></i>Write Review
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Restaurant Content -->
    <section class="restaurant-content py-5">
        <div class="container">
            <?php if (isset($_GET['reservation_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Your reservation has been successfully made! You can view it in your <a href="reservations.php" class="alert-link">reservations page</a>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <!-- Restaurant Images -->
                    <div class="restaurant-images mb-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <img src="<?php echo $restaurant['image'] ?? 'https://placehold.co/600x400/2dc2a3/333333?text=' . urlencode(htmlspecialchars($restaurant['name'])); ?>" 
                                     class="img-fluid rounded main-image" alt="<?php echo htmlspecialchars($restaurant['name']); ?>" 
                                     style="width: 100%; height: 400px; object-fit: cover;">
                            </div>
                            <div class="col-md-4">
                                <div class="row g-3">
                                    <div class="col-6 col-md-12">
                                        <img src="https://placehold.co/600x400/2dc2a3/333333?text=Restaurant+Interior" class="img-fluid rounded" alt="Restaurant Interior">
                                    </div>
                                    <div class="col-6 col-md-12">
                                        <img src="https://placehold.co/600x400/2dc2a3/333333?text=Restaurant+Food" class="img-fluid rounded" alt="Restaurant Food">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Restaurant Info -->
                    <div class="restaurant-info mb-5">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title mb-4">About This Restaurant</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($restaurant['description'])); ?></p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <h6 class="mb-3">Location & Contact</h6>
                                        <p><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($restaurant['address']); ?></p>
                                        <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($restaurant['phone']); ?></p>
                                        <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($restaurant['email']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3">Opening Hours</h6>
                                        <?php
                                        $hours = json_decode($restaurant['opening_hours'], true);
                                        foreach ($hours as $day => $time) {
                                            echo "<p><strong>" . ucfirst($day) . ":</strong> {$time['open']} - {$time['close']}</p>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menu Section -->
                    <div class="menu-section mb-5">
                        <h3 class="section-title mb-4">Menu</h3>
                        <?php if (!empty($menu)): ?>
                        <div class="row">
                            <?php foreach ($menu as $item): ?>
                            <div class="col-md-6 mb-4">
                                <div class="menu-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="d-flex">
                                            <div class="menu-item-image me-3">
                                                <img src="<?php echo $item['image_url'] ?? 'https://placehold.co/80x80/2dc2a3/333333?text=' . htmlspecialchars($item['name']); ?>" 
                                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     style="width: 80px; height: 80px; object-fit: cover;">
                                            </div>
                                            <div>
                                                <h5 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h5>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($item['description']); ?></p>
                                            </div>
                                        </div>
                                        <span class="price">$<?php echo number_format($item['price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            No menu items available for this restaurant.
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Reviews Section -->
                    <div class="reviews-section" id="reviews-section">
                        <h3 class="section-title mb-4">Reviews</h3>
                        
                        <!-- Rating Summary -->
                        <div class="rating-summary mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="overall-rating text-center">
                                        <h2 class="mb-0"><?php echo formatRating($avgRatings['overall']); ?></h2>
                                        <div class="stars mb-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $avgRatings['overall']): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php elseif ($i - 0.5 <= $avgRatings['overall']): ?>
                                                <i class="fas fa-star-half-alt text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        </div>
                                        <p class="text-muted mb-0"><?php echo count($reviews); ?> reviews</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="rating-details">
                                        <?php
                                        $ratingCategories = ['Cleanliness', 'Taste', 'Service', 'Price', 'Parking'];
                                        foreach ($ratingCategories as $category) {
                                            $rating = $avgRatings[strtolower($category)];
                                            echo "<div class='rating-bar mb-2'>";
                                            echo "<div class='d-flex justify-content-between'>";
                                            echo "<span>$category</span>";
                                            echo "<span>" . formatRating($rating) . "</span>";
                                            echo "</div>";
                                            echo "<div class='progress' style='height: 6px;'>";
                                            echo "<div class='progress-bar bg-warning' style='width: " . ($rating * 20) . "%'></div>";
                                            echo "</div>";
                                            echo "</div>";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Write Review Section -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="write-review-section mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Write a Review</h5>
                                    <form id="reviewForm" action="process_review.php" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant_id; ?>">
                                        
                                        <div class="row g-3">
                                            <?php
                                            $ratingCategories = [
                                                'cleanliness' => 'Cleanliness',
                                                'taste' => 'Taste',
                                                'service' => 'Service',
                                                'price' => 'Price',
                                                'parking' => 'Parking'
                                            ];
                                            foreach ($ratingCategories as $key => $label):
                                            ?>
                                            <div class="col-md-4">
                                                <div class="rating-input">
                                                    <div class="stars">
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                            <input type="radio" id="<?php echo $key; ?>_<?php echo $i; ?>" 
                                                                   name="<?php echo $key; ?>_rating" value="<?php echo $i; ?>" required>
                                                            <label for="<?php echo $key; ?>_<?php echo $i; ?>">
                                                                <i class="fas fa-star"></i>
                                                            </label>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <span class="rating-text"><?php echo $label; ?></span>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="mt-3">
                                            <textarea class="form-control" name="review_text" rows="4" 
                                                      placeholder="Share your experience..." required></textarea>
                                        </div>

                                        <div class="mt-3">
                                            <div class="photo-upload-section">
                                                <label class="btn btn-outline-primary">
                                                    <i class="fas fa-camera"></i> Add Photos
                                                    <input type="file" name="images[]" multiple accept="image/*" class="d-none">
                                                </label>
                                                <div class="photo-preview mt-2 d-flex flex-wrap gap-2"></div>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Review
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="write-review-section mb-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Want to write a review?</h5>
                                    <p class="card-text">Please <a href="login.php">login</a> to share your experience.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Reviews List -->
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): ?>
                            <div class="review-item mb-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($review['username']); ?></h5>
                                        <div class="stars mb-1">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['overall_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?php echo formatDate($review['created_at']); ?></small>
                                    </div>
                                    <div class="rating-details">
                                        <span class="badge bg-light text-dark me-1">Clean: <?php echo formatRating($review['cleanliness_rating']); ?></span>
                                        <span class="badge bg-light text-dark me-1">Taste: <?php echo formatRating($review['taste_rating']); ?></span>
                                        <span class="badge bg-light text-dark">Service: <?php echo formatRating($review['service_rating']); ?></span>
                                        <span class="badge bg-light text-dark">Price: <?php echo formatRating($review['price_rating']); ?></span>
                                        <span class="badge bg-light text-dark">Parking: <?php echo formatRating($review['parking_rating']); ?></span>
                                    </div>
                                </div>
                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <?php if (!empty($review['images'])): ?>
                                <div class="review-images d-flex flex-wrap gap-2 mb-2">
                                    <?php foreach (json_decode($review['images'], true) as $index => $image): ?>
                                    <a href="<?php echo htmlspecialchars($image); ?>" data-bs-toggle="modal" data-bs-target="#imageModal<?php echo $review['review_id']; ?>-<?php echo $index; ?>" class="review-image-thumbnail">
                                        <img src="<?php echo htmlspecialchars($image); ?>" class="img-thumbnail" alt="Review Image" style="width: 100px; height: 100px; object-fit: cover;">
                                    </a>
                                    <!-- Modal for this image -->
                                    <div class="modal fade" id="imageModal<?php echo $review['review_id']; ?>-<?php echo $index; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-body p-0">
                                                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2 bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    <img src="<?php echo htmlspecialchars($image); ?>" class="img-fluid w-100" alt="Review Image">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <hr/>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Map -->
                    <div class="card mb-4">
                        <div class="card-body p-0">
                            <div id="map" style="height: 300px; width: 100%;"></div>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Quick Info</h5>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo isRestaurantOpen($restaurant['opening_hours']) ? 
                                        '<span class="text-success">Open Now</span>' : 
                                        '<span class="text-danger">Closed</span>'; ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-car me-2"></i>
                                    <?php echo $restaurant['has_parking'] ? 'Parking Available' : 'No Parking'; ?>
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-wheelchair me-2"></i>
                                    Wheelchair Accessible
                                </li>
                                <li>
                                    <i class="fas fa-wifi me-2"></i>
                                    Free WiFi
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Similar Restaurants -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Similar Restaurants</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $similarRestaurants = getSimilarRestaurants($restaurant_id, $restaurant['cuisine_type'], 10);
                            if (!empty($similarRestaurants)):
                            ?>
                                <div class="swiper similar-restaurants-swiper">
                                    <div class="swiper-wrapper">
                                        <?php foreach ($similarRestaurants as $similar): ?>
                                            <div class="swiper-slide">
                                                <div class="similar-restaurant-card">
                                                    <div class="similar-restaurant-image">
                                                        <img src="<?php echo htmlspecialchars($similar['image'] ?? 'https://placehold.co/600x400/e9ecef/495057?text='.urlencode(htmlspecialchars($similar['name']))); ?>" 
                                                             alt="<?php echo htmlspecialchars($similar['name']); ?>">
                                                    </div>
                                                    <div class="similar-restaurant-content">
                                                        <h6 class="mb-2"><?php echo htmlspecialchars($similar['name']); ?></h6>
                                                        <div class="d-flex align-items-center mb-2">
                                                            <span class="cuisine-badge me-2">
                                                                <i class="fas fa-utensils"></i> <?php echo htmlspecialchars($similar['cuisine_type'] ?? 'Unknown'); ?>
                                                            </span>
                                                            <span class="price-badge">
                                                                <i class="fas fa-dollar-sign"></i> <?php echo htmlspecialchars($similar['price_range'] ?? 'N/A'); ?>
                                                            </span>
                                                        </div>
                                                        <div class="rating mb-2">
                                                            <?php
                                                            // Get the average rating from the reviews table
                                                            $similarRating = getRestaurantAverageRating($similar['restaurant_id']);
                                                            $rating = $similarRating !== null ? round($similarRating, 1) : 0;
                                                            
                                                            // Display stars based on rating
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $rating) {
                                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                                } elseif ($i - 0.5 <= $rating) {
                                                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                                } else {
                                                                    echo '<i class="far fa-star text-warning"></i>';
                                                                }
                                                            }
                                                            
                                                            // Display rating number only if there are reviews
                                                            if ($similarRating !== null) {
                                                                echo " <span class='ms-1'>(" . number_format($rating, 1) . ")</span>";
                                                            } else {
                                                                echo " <span class='ms-1 text-muted'>(No reviews)</span>";
                                                            }
                                                            ?>
                                                        </div>
                                                        <a href="restaurant.php?id=<?php echo $similar['restaurant_id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm w-100">
                                                            View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="swiper-pagination"></div>
                                    <div class="swiper-button-prev"></div>
                                    <div class="swiper-button-next"></div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No similar restaurants found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        // Make restaurant_id available to JavaScript
        window.restaurantId = <?php echo json_encode($restaurant_id); ?>;
    </script>
    <script src="assets/js/reservation.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js" integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize map
        document.addEventListener('DOMContentLoaded', function() {
            var map = L.map('map').setView([<?php echo $restaurant['latitude']; ?>, <?php echo $restaurant['longitude']; ?>], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '  OpenStreetMap contributors'
            }).addTo(map);
            
            // Add marker
            L.marker([<?php echo $restaurant['latitude']; ?>, <?php echo $restaurant['longitude']; ?>])
                .addTo(map)
                .bindPopup('<?php echo htmlspecialchars($restaurant['name']); ?>')
                .openPopup();

            // Initialize Swiper
            const swiper = new Swiper('.similar-restaurants-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    renderBullet: function (index, className) {
                        return '<span class="' + className + '"></span>';
                    },
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                effect: 'slide',
                speed: 800
            });

            // Auto-expand textarea
            const textarea = document.querySelector('textarea[name="review_text"]');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }

            // Update rating text when stars are selected
            document.querySelectorAll('.rating-input').forEach(input => {
                const stars = input.querySelectorAll('input[type="radio"]');
                const text = input.querySelector('.rating-text');
                
                stars.forEach(star => {
                    star.addEventListener('change', function() {
                        const rating = this.value;
                        const category = this.name.replace('_rating', '');
                        const categoryText = category.charAt(0).toUpperCase() + category.slice(1);
                        text.textContent = `${categoryText}`;
                    });
                });
            });

            // Handle file input and preview
            const fileInput = document.querySelector('input[type="file"]');
            const previewContainer = document.querySelector('.photo-preview');
            const uploadLabel = fileInput.previousElementSibling;

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    previewContainer.innerHTML = '';
                    
                    if (this.files.length > 0) {           
                        Array.from(this.files).forEach(file => {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const preview = document.createElement('div');
                                preview.className = 'photo-preview-item';
                                preview.innerHTML = `
                                    <img src="${e.target.result}" alt="Preview">
                                    <button type="button" class="remove-photo">
                                        <i class="fas fa-times"></i>
                                    </button>
                                `;
                                previewContainer.appendChild(preview);
                                
                                // Add remove functionality
                                preview.querySelector('.remove-photo').addEventListener('click', function() {
                                    preview.remove();
                                    // Update file input
                                    const dt = new DataTransfer();
                                    const { files } = fileInput;
                                    for (let i = 0; i < files.length; i++) {
                                        const f = files[i];
                                        if (f !== file) {
                                            dt.items.add(f);
                                        }
                                    }
                                    fileInput.files = dt.files;
                                });
                            };
                            reader.readAsDataURL(file);
                        });
                    } else {
                        uploadLabel.innerHTML = 'Add Photos';
                    }
                });
            }

            // Add styles for photo preview
            const style = document.createElement('style');
            style.textContent = `
                .photo-preview-item {
                    position: relative;
                    width: 100px;
                    height: 100px;
                }
                .photo-preview-item img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    border-radius: 4px;
                }
                .photo-preview-item .remove-photo {
                    position: absolute;
                    top: -8px;
                    right: -8px;
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    background: #dc3545;
                    color: white;
                    border: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    font-size: 12px;
                    padding: 0;
                }
                .photo-preview-item .remove-photo:hover {
                    background: #c82333;
                }
            `;
            document.head.appendChild(style);

            // Form validation and submission
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) {
                reviewForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Check if all required fields are filled
                    const requiredFields = this.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (isValid) {
                        // Show loading state
                        const submitBtn = this.querySelector('button[type="submit"]');
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                        
                        // Submit form
                        fetch('process_review.php', {
                            method: 'POST',
                            body: new FormData(this)
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Response:', data);
                            if (data.success) {
                                alert('Review submitted successfully!');
                                // Reset form
                                this.reset();
                                // Reset textarea height
                                textarea.style.height = 'auto';
                                // Reset file input label
                                if (fileInput) {
                                    fileInput.previousElementSibling.innerHTML = '<i class="fas fa-camera"></i> Add Photos';
                                    fileInput.previousElementSibling.classList.remove('btn-primary');
                                    fileInput.previousElementSibling.classList.add('btn-outline-primary');
                                }
                                // Reset rating texts
                                document.querySelectorAll('.rating-text').forEach(text => {
                                    text.textContent = text.textContent.split(':')[0];
                                });
                                // Reload page to show new review
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                alert(data.message || 'Error submitting review');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error submitting review: ' + error.message);
                        })
                        .finally(() => {
                            // Reset button state
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        });
                    } else {
                        alert('Please fill in all required fields');
                    }
                });
            }
        });
    </script>
</body>
</html>