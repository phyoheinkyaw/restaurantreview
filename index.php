<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Review Platform - Find and Review the Best Restaurants</title>
    <meta name="description" content="Discover the best restaurants in your area, read reviews, make reservations, and share your dining experiences.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section position-relative">
        <div class="container">
            <div class="row min-vh-75 align-items-center py-5">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-4 fw-bold mb-4">Find Your Next Favorite Restaurant</h1>
                    <p class="lead mb-4">Discover new dining experiences, read authentic reviews, and book your table instantly.</p>
                    
                    <!-- Search Form -->
                    <form action="search.php" method="GET" class="search-form mb-4">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" placeholder="Search restaurants, cuisines, or locations...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search me-1"></i> Search
                            </button>
                        </div>
                        
                        <!-- Quick Filters -->
                        <div class="quick-filters mt-3 d-flex flex-wrap">
                            <a href="search.php?cuisine=italian" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Italian</a>
                            <a href="search.php?cuisine=japanese" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Japanese</a>
                            <a href="search.php?cuisine=mexican" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Mexican</a>
                            <a href="search.php?cuisine=indian" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Indian</a>
                            <a href="search.php?feature=outdoor" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Outdoor Seating</a>
                            <a href="search.php?sort=newest" class="badge rounded-pill bg-light text-dark mb-2 px-3 py-2">Newly Added</a>
                        </div>
                </form>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="assets/images/hero-image.jpg" alt="Restaurant dining experience" class="img-fluid rounded-3 shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container-fluid py-5">
        <!-- Map with Restaurant Locations -->
        <section class="mb-5">
            <div class="container">
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="section-title">Explore Restaurants Near You</h2>
                        <p class="text-muted">Discover restaurants on the map. Click on markers for more details.</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="card map-legend-card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i>Map Legend</h5>
                            </div>
                            <div class="card-body">
                                <div class="map-filters mb-3">
                                    <h6>Filter by Cuisine:</h6>
                                    <div id="cuisine-filters" class="d-flex flex-wrap gap-2 mb-3">
                                        <!-- Will be populated by JavaScript -->
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                    
                                    <h6>Filter by Price Range:</h6>
                                    <div id="price-filters" class="btn-group mb-3" role="group">
                                        <button type="button" class="btn btn-outline-secondary" data-price="$">$</button>
                                        <button type="button" class="btn btn-outline-secondary" data-price="$$">$$</button>
                                        <button type="button" class="btn btn-outline-secondary" data-price="$$$">$$$</button>
                                        <button type="button" class="btn btn-outline-secondary" data-price="$$$$">$$$$</button>
                                    </div>
                                    
                                    <h6>Marker Colors:</h6>
                                    <div class="marker-colors">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="marker-dot bg-success me-2"></span>
                                            <span>4.5+ Rating</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="marker-dot bg-primary me-2"></span>
                                            <span>4.0-4.4 Rating</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="marker-dot bg-warning me-2"></span>
                                            <span>3.5-3.9 Rating</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <span class="marker-dot bg-danger me-2"></span>
                                            <span>Below 3.5 Rating</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="recent-activity">
                                    <h6 class="border-top pt-3">Recent Activity:</h6>
                                    <div id="recent-activity-list">
                                        <!-- Will be populated by JavaScript -->
                                        <p class="text-muted small">Loading recent activity...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="map-container">
                            <div id="map" class="rounded shadow"></div>
                            <div class="map-controls mt-2 d-flex justify-content-end">
                                <button id="locate-me" class="btn btn-sm near-me-btn me-2" title="Find restaurants near me">
                                    <i class="fas fa-location-arrow"></i> Near Me
                                </button>
                                <button id="reset-map" class="btn btn-sm btn-secondary" title="Reset map view">
                                    <i class="fas fa-redo-alt"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Featured Restaurants -->
        <section class="mb-5">
            <div class="container">
                <div class="row mb-4">
                    <div class="col">
                        <h2 class="section-title">Featured Restaurants</h2>
                        <p class="text-muted">Handpicked recommendations for exceptional dining experiences</p>
                    </div>
                </div>
                
                <div class="row" id="featured-restaurants">
                    <!-- Will be populated by JavaScript -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading featured restaurants...</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Testimonials Section -->
        <section class="bg-light py-5 mb-5">
            <div class="container">
                <div class="row mb-4 text-center">
                    <div class="col-12">
                        <h2 class="section-title">What Our Users Say</h2>
                        <p class="text-muted">Join thousands of satisfied diners who trust our platform</p>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="card h-100 testimonial-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="testimonial-avatar me-3">
                                        <img src="assets/images/testimonial-1.jpg" alt="User testimonial" class="rounded-circle">
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Sarah Johnson</h5>
                                        <div class="text-warning">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="card-text">"This platform helped me discover amazing restaurants I never knew existed in my neighborhood. The reviews are honest and the reservation system is seamless!"</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-4 mb-md-0">
                        <div class="card h-100 testimonial-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="testimonial-avatar me-3">
                                        <img src="assets/images/testimonial-2.jpg" alt="User testimonial" class="rounded-circle">
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Michael Chen</h5>
                                        <div class="text-warning">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star-half-alt"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="card-text">"As a foodie, I appreciate the detailed reviews and photos. The map feature makes it easy to plan dining experiences when I'm traveling to new cities."</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card h-100 testimonial-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="testimonial-avatar me-3">
                                        <img src="assets/images/testimonial-3.jpg" alt="User testimonial" class="rounded-circle">
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Emma Rodriguez</h5>
                                        <div class="text-warning">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                    </div>
                                </div>
                                <p class="card-text">"I love how the platform connects me with authentic local eateries. The filters help me find restaurants that accommodate my dietary preferences easily."</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Call to Action -->
        <section class="mb-5">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="card bg-primary text-white">
                            <div class="card-body p-5 text-center">
                                <h2 class="mb-3">Ready to discover your next dining adventure?</h2>
                                <p class="lead mb-4">Join our community of food lovers and start exploring the best restaurants in your area.</p>
                                <div class="d-flex justify-content-center flex-wrap gap-2">
                                    <a href="register.php" class="btn btn-light btn-lg">Sign Up for Free</a>
                                    <a href="search.php" class="btn btn-outline-light btn-lg">Explore Restaurants</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/map-enhanced.js"></script>
    <script src="assets/js/home.js"></script>
</body>
</html>