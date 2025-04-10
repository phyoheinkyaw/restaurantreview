<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Restaurant Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <!-- Hero Section -->
            <div class="col-12 text-center mb-5">
                <h1 class="display-4 fw-bold">About Restaurant Review</h1>
                <p class="lead text-muted">Your trusted companion in discovering amazing dining experiences</p>
            </div>

            <!-- Mission Section -->
            <div class="col-lg-8 mx-auto mb-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h2 class="h3 mb-4">Our Mission</h2>
                        <p class="lead">At Restaurant Review, our mission is to connect food lovers with exceptional dining experiences. We're passionate about helping people discover amazing restaurants, share their experiences, and make informed dining decisions.</p>
                        <p>We believe that every meal deserves to be memorable, and our platform is designed to help you find the perfect restaurant for any occasion.</p>
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="col-12 mb-5">
                <h2 class="h3 mb-4">What We Offer</h2>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-search fa-3x text-primary mb-3"></i>
                                <h3 class="h5">Discover</h3>
                                <p class="text-muted">Find restaurants based on cuisine, location, and ratings. Our advanced search helps you discover the perfect dining spot.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-star fa-3x text-primary mb-3"></i>
                                <h3 class="h5">Review</h3>
                                <p class="text-muted">Share your dining experiences with detailed reviews. Rate food quality, service, ambiance, and value for money.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                                <h3 class="h5">Reserve</h3>
                                <p class="text-muted">Make reservations directly through our platform. Check availability, select your preferred time, and confirm your booking.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Points System Section -->
            <div class="col-12 mb-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h2 class="h3 mb-4">Points System</h2>
                        <p class="lead">Earn points for your activities and redeem them for special rewards!</p>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="height: 80px;">
                                        <span class="h1 text-primary">100</span>
                                    </div>
                                    <p class="text-muted">Points for writing a detailed review</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="height: 80px;">
                                        <span class="h1 text-primary">50</span>
                                    </div>
                                    <p class="text-muted">Points for making a reservation</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="d-flex align-items-center justify-content-center mb-3" style="height: 80px;">
                                        <span class="h1 text-primary">25</span>
                                    </div>
                                    <p class="text-muted">Points for rating a restaurant</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="col-12 mb-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h2 class="h3 mb-4">Contact Us</h2>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h4>Email Support</h4>
                                <p class="text-muted">Have questions or need help? Our support team is here to assist you.</p>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-envelope fa-2x text-primary me-3"></i>
                                    <p class="mb-0">support@restaurantreview.com</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Follow Us</h4>
                                <p class="text-muted">Stay updated with our latest features and restaurant recommendations.</p>
                                <div class="d-flex gap-3">
                                    <a href="#" class="text-primary text-decoration-none">
                                        <i class="fab fa-facebook fa-2x"></i>
                                    </a>
                                    <a href="#" class="text-primary text-decoration-none">
                                        <i class="fab fa-twitter fa-2x"></i>
                                    </a>
                                    <a href="#" class="text-primary text-decoration-none">
                                        <i class="fab fa-instagram fa-2x"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
