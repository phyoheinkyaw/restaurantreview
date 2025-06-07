<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
// Also check for 'q' parameter for backward compatibility
if (empty($search) && isset($_GET['q'])) {
    $search = sanitize($_GET['q']);
}

// Debug search parameter
error_log("Search parameter: " . $search);

$cuisine = isset($_GET['cuisine']) ? sanitize($_GET['cuisine']) : '';
$price_range = isset($_GET['price_range']) ? sanitize($_GET['price_range']) : '';
$rating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'rating';
$feature = isset($_GET['feature']) ? sanitize($_GET['feature']) : '';

// Get location parameters if provided
$userLat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$userLng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
$isLocationSearch = ($userLat !== null && $userLng !== null);

// Automatically set sort to distance when location search is active
if ($isLocationSearch && !isset($_GET['sort'])) {
    $sort = 'distance';
}

// Build the query
$query = "SELECT r.*, 
            COALESCE(AVG(rev.overall_rating), 0) as avg_rating,
            COUNT(rev.review_id) as review_count";

// Add distance calculation if location search is active
if ($isLocationSearch) {
    $query .= ", (
                6371 * acos(
                    cos(radians(:user_lat)) * 
                    cos(radians(r.latitude)) * 
                    cos(radians(r.longitude) - radians(:user_lng)) + 
                    sin(radians(:user_lat)) * 
                    sin(radians(r.latitude))
                )
            ) AS distance";
}

$query .= " FROM restaurants r
          LEFT JOIN reviews rev ON r.restaurant_id = rev.restaurant_id
          WHERE 1=1";

$params = [];

if ($isLocationSearch) {
    $params[':user_lat'] = $userLat;
    $params[':user_lng'] = $userLng;
}

if (!empty($search)) {
    // Simplify search to make it more forgiving
    $query .= " AND (r.name LIKE :search 
                OR r.description LIKE :search 
                OR r.cuisine_type LIKE :search 
                OR r.address LIKE :search)";
    $params[':search'] = "%$search%";
    
    // Log the search query and parameter
    error_log("Search query condition: " . $query);
    error_log("Search parameter value: " . $params[':search']);
}

if (!empty($cuisine)) {
    $query .= " AND r.cuisine_type = :cuisine";
    $params[':cuisine'] = $cuisine;
}

if (!empty($price_range)) {
    $query .= " AND r.price_range = :price_range";
    $params[':price_range'] = $price_range;
}

// Handle specific features
if ($feature === 'outdoor') {
    $query .= " AND r.has_outdoor_seating = 1";
}

$query .= " GROUP BY r.restaurant_id";

if ($rating > 0) {
    $query .= " HAVING avg_rating >= :rating";
    $params[':rating'] = $rating;
}

// Add sorting
if ($isLocationSearch && $sort === 'distance') {
    $query .= " ORDER BY distance ASC";
} else {
    switch ($sort) {
        case 'price_low':
            $query .= " ORDER BY r.price_range ASC, avg_rating DESC";
            break;
        case 'price_high':
            $query .= " ORDER BY r.price_range DESC, avg_rating DESC";
            break;
        case 'newest':
            $query .= " ORDER BY r.created_at DESC";
            break;
        case 'rating':
        default:
            $query .= " ORDER BY avg_rating DESC, review_count DESC";
    }
}

try {
    $db = getDB();
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    $restaurants = [];
}

// Get unique cuisine types for filter
try {
    $stmt = $db->query("SELECT DISTINCT cuisine_type FROM restaurants ORDER BY cuisine_type");
    $cuisine_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching cuisine types: " . $e->getMessage());
    $cuisine_types = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Restaurants - Restaurant Review</title>
    
<style>
        #map {
            height: 96vh;
            width: 95%;
            position: sticky;
            bottom: 0;
            border-radius: 12px;
            margin: 0 auto;
            display: block;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .map-container {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            padding: 1rem;
        }
        .restaurant-card {
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        .restaurant-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .restaurant-card.active {
            border-left: 4px solid var(--primary);
        }
        .content-wrapper {
            height: 100vh;
            overflow-y: auto;
            padding: 1rem;
            scrollbar-width: thin;
            max-height: 100vh;
        }
        .content-wrapper::-webkit-scrollbar {
            width: 8px;
        }
        .content-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .content-wrapper::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        .content-wrapper::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        .filter-form {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        /* Enhanced form elements */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border-color: #e0e0e0;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        #nearMeBtn {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        .btn-group .btn:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        
        .btn-group .btn:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }
        
        #priceRangeButtons .btn, [data-rating].btn {
            font-weight: 600;
            padding: 10px 0;
        }
        
        /* Star rating buttons */
        [data-rating].btn {
            color: #664d03;
        }
        
        [data-rating].btn-outline-warning {
            color: #664d03;
        }
        
        [data-rating].btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #664d03;
        }
        
        /* Search card styling */
        .card {
            border-radius: 12px;
            transition: all 0.3s ease;
            overflow: hidden;
            border: none;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: #333;
        }
        
        .results-heading {
            position: relative;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            color: #333;
        }
        
        .results-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px;
        }
        
        /* Distance badge */
        .distance-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 1;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background-color: rgba(0,0,0,0.7);
            color: white;
            backdrop-filter: blur(4px);
        }
        
        /* Enhanced map pins */
        /* We are now using standard Leaflet markers with custom colors */
        
        /* Enhanced map popup styles */
        .restaurant-popup .leaflet-popup-content-wrapper {
            padding: 0;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .restaurant-popup .leaflet-popup-content {
            margin: 0;
            min-width: 300px;
            overflow: hidden;
        }
        
        .restaurant-popup .leaflet-popup-tip {
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        
        /* Ensure popup is properly positioned */
        .leaflet-popup {
            margin-bottom: 5px !important;
        }
        
        .restaurant-popup .leaflet-popup-tip-container {
            height: 10px !important;
        }
        
        /* Map popup styles from homepage */
        .marker-popup {
            min-width: 300px;
            padding: 0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .marker-popup-header {
            padding: 15px;
            background-color: var(--light-color);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            position: relative;
        }
        
        .marker-popup-body {
            padding: 15px;
        }
        
        .marker-popup-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-weight: 500;
        }
        
        .marker-image {
            width: 100%;
            height: 140px;
            object-fit: cover !important;
            display: block;
            border-radius: 8px 8px 0 0;
        }
        
        .marker-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .marker-info {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed rgba(0,0,0,0.05);
        }
        
        .marker-info:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .marker-info i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 10px;
            font-size: 12px;
        }
        
        .marker-info.cuisine i {
            background-color: rgba(23, 162, 184, 0.15);
            color: var(--info-color);
        }
        
        .marker-info.price i {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
        }
        
        .marker-info.address i {
            background-color: rgba(255, 193, 7, 0.15);
            color: var(--warning-color);
        }
        
        .marker-info.phone i {
            background-color: rgba(0, 123, 255, 0.15);
            color: var(--primary-color);
        }
        
        .restaurant-popup .leaflet-popup-close-button {
            z-index: 1000;
            color: #fff;
            opacity: 0.8;
            background: rgba(0,0,0,0.4);
            border-radius: 50%;
            width: 26px;
            height: 26px;
            font-size: 20px;
            padding: 0;
            line-height: 26px;
            text-align: center;
            top: 8px;
            right: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .restaurant-popup .leaflet-popup-close-button:hover {
            opacity: 1;
            background: rgba(0,0,0,0.6);
            transform: scale(1.1);
        }
        
        .marker-actions .btn {
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 500;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .marker-actions .btn-primary,
        .marker-actions .btn-success {
            color: white;
            border: none;
            background-image: linear-gradient(to right, #3a7bd5, #00d2ff);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }
        
        .marker-actions .btn-primary:hover,
        .marker-actions .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(0, 123, 255, 0.45);
        }
        
        .marker-actions .btn-success {
            background-image: linear-gradient(to right, #2ecc71, #27ae60);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4);
        }
        
        .marker-actions .btn-success:hover {
            box-shadow: 0 7px 15px rgba(46, 204, 113, 0.45);
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form .form-group {
                width: 100%;
            }
            #map {
                width: 95%;
                height: 30vh;
                position: relative;
                top: 0;
            }
            .map-container {
                height: auto;
                padding: 1rem 0;
            }
            .marker-popup {
                min-width: 250px;
            }
            .marker-image {
                height: 120px;
            }
        }
    </style>
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid pt-3">
        <div class="row g-0">
            <!-- Map Section -->
            <div class="col-lg-6">
                <div class="map-container">
                    <div id="map"></div>
                </div>
            </div>
            
            <!-- Content Section -->
            <div class="col-lg-6">
                <div class="content-wrapper">
                    <!-- Search Filters -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Search Filters</h5>
                            <form method="GET" action="" id="searchForm" class="filter-form">
                                <div class="form-group mb-3">
                                    <label class="form-label fw-bold mb-2">Search</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="searchInput" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="Search restaurants or cuisines...">
                                        <button type="button" id="nearMeBtn" class="btn btn-primary" title="Find restaurants near me">
                                            <i class="fas fa-location-arrow"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small text-muted mt-1">Type to search, results update automatically</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-2">Cuisine Type</label>
                                        <select class="form-select filter-select shadow-sm" name="cuisine" id="cuisineSelect">
                                            <option value="">All Cuisines</option>
                                            <?php foreach ($cuisine_types as $type): ?>
                                                <option value="<?php echo htmlspecialchars($type); ?>"
                                                        <?php echo $cuisine === $type ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold mb-2">Sort By</label>
                                        <select class="form-select filter-select shadow-sm" id="sortSelect" name="sort">
                                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Best Rated</option>
                                            <?php if ($isLocationSearch): ?>
                                            <option value="distance" <?php echo $sort === 'distance' ? 'selected' : ''; ?>>Nearest First</option>
                                            <?php endif; ?>
                                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Added</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold mb-2">Price Range</label>
                                    <div class="btn-group w-100 shadow-sm" role="group" id="priceRangeButtons">
                                        <input type="hidden" name="price_range" id="priceRangeInput" value="<?php echo htmlspecialchars($price_range); ?>">
                                        <button type="button" class="btn <?php echo $price_range === '$' ? 'btn-success' : 'btn-outline-success'; ?>" data-price="$">$</button>
                                        <button type="button" class="btn <?php echo $price_range === '$$' ? 'btn-success' : 'btn-outline-success'; ?>" data-price="$$">$$</button>
                                        <button type="button" class="btn <?php echo $price_range === '$$$' ? 'btn-success' : 'btn-outline-success'; ?>" data-price="$$$">$$$</button>
                                        <button type="button" class="btn <?php echo $price_range === '$$$$' ? 'btn-success' : 'btn-outline-success'; ?>" data-price="$$$$">$$$$</button>
                                        <button type="button" class="btn <?php echo empty($price_range) ? 'btn-success' : 'btn-outline-success'; ?>" data-price="">Any</button>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold mb-2">Minimum Rating</label>
                                    <div class="btn-group w-100 shadow-sm" role="group">
                                        <input type="hidden" name="rating" id="ratingInput" value="<?php echo $rating; ?>">
                                        <button type="button" class="btn <?php echo $rating == 0 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="0">Any</button>
                                        <button type="button" class="btn <?php echo $rating == 1 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="1">★</button>
                                        <button type="button" class="btn <?php echo $rating == 2 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="2">★★</button>
                                        <button type="button" class="btn <?php echo $rating == 3 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="3">★★★</button>
                                        <button type="button" class="btn <?php echo $rating == 4 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="4">★★★★</button>
                                        <button type="button" class="btn <?php echo $rating == 5 ? 'btn-warning' : 'btn-outline-warning'; ?>" data-rating="5">★★★★★</button>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" id="searchButton" class="btn btn-primary flex-grow-1">
                                        <i class="fas fa-search me-2"></i>Search
                                    </button>
                                    <button type="button" id="clearButton" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Restaurant Results -->
                    <div class="row">
    <?php if (empty($restaurants)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No restaurants found matching your search criteria.
            </div>
        </div>
    <?php else: ?>
        <div class="col-12 mb-3">
            <h4 class="results-heading">
                <?php 
                if ($isLocationSearch) {
                    echo 'Restaurants near you';
                } elseif (!empty($search)) {
                    echo 'Search results for "' . htmlspecialchars($search) . '"';
                } elseif (!empty($cuisine)) {
                    echo htmlspecialchars($cuisine) . ' Restaurants';
                } elseif ($feature === 'outdoor') {
                    echo 'Restaurants with Outdoor Seating';
                } elseif ($sort === 'newest') {
                    echo 'Newly Added Restaurants';
                } else {
                    echo 'All Restaurants';
                }
                ?>
                <span class="text-muted fs-6 ms-2">(<?php echo count($restaurants); ?> results)</span>
            </h4>
        </div>
        <?php foreach ($restaurants as $restaurant): ?>
            <div class="col-md-6 mb-4 restaurant-card" 
                 data-id="<?php echo $restaurant['restaurant_id']; ?>"
                 data-lat="<?php echo $restaurant['latitude']; ?>"
                 data-lng="<?php echo $restaurant['longitude']; ?>"
                 data-phone="<?php echo htmlspecialchars($restaurant['phone'] ?? 'N/A'); ?>">
                <div class="card h-100 border-0 shadow-sm">
                    <!-- Card Image -->
                    <div class="card-img-wrapper overflow-hidden position-relative">
                        <img src="<?php echo htmlspecialchars($restaurant['image'] ?? 'https://placehold.co/600x400/e9ecef/495057?text=' . urlencode(htmlspecialchars($restaurant['name']))); ?>" 
                             class="card-img-top" alt="<?php echo htmlspecialchars($restaurant['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                        <?php if (($restaurant['avg_rating'] ?? 0) >= 4.5): ?>
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-success">
                                    <i class="fas fa-star"></i> <?php echo formatRating($restaurant['avg_rating'] ?? 0); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($restaurant['distance'])): ?>
                        <div class="distance-badge">
                            <i class="fas fa-location-arrow me-1"></i>
                            <?php echo number_format($restaurant['distance'], 1); ?> km
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body py-3">
                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($restaurant['name']); ?></h5>
                        <p class="card-text text-muted small mb-2">
                            <i class="fas fa-utensils me-2"></i><?php echo htmlspecialchars($restaurant['cuisine_type']); ?>
                        </p>
                        <p class="card-text small mb-2">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($restaurant['address']); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $restaurant['avg_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2 small text-muted">(<?php echo $restaurant['review_count']; ?>)</span>
                            </div>
                            <span class="price-range fw-bold">
                                <?php echo str_repeat('$', strlen($restaurant['price_range'])); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                        <div class="d-flex gap-2">
                            <a href="restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                               class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </a>
                            <a href="reservation.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                               class="btn btn-sm btn-primary flex-grow-1">
                                <i class="fas fa-calendar-check me-1"></i> Reserve
                            </a>
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
    </div>

    <?php include 'includes/footer.php'; ?>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize map
        const map = L.map('map', {
            zoomSnap: 0.1,
            zoomDelta: 0.5,
            wheelPxPerZoomLevel: 120
        }).setView([0, 0], 2);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Disable default click behavior on popups to prevent auto-panning
        map.on('popupopen', function(e) {
            // Prevent auto-panning on popup open
            if (e.popup._source && e.popup._source.options) {
                e.popup._source.options.autoPan = false;
            }
        });

        // Store markers
        const markers = [];
        let activeMarker = null;
        let bounds = L.latLngBounds([]);

        // Custom marker icons based on rating
        function getMarkerIcon(rating, isActive = false) {
            // Define marker icon URL based on rating
            let iconUrl = 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/';
            
            if (rating >= 4.5) {
                iconUrl += 'marker-icon-2x-green.png'; // Top rated
            } else if (rating >= 4.0) {
                iconUrl += 'marker-icon-2x-blue.png'; // Very good
            } else if (rating >= 3.5) {
                iconUrl += 'marker-icon-2x-orange.png'; // Good
            } else {
                iconUrl += 'marker-icon-2x-red.png'; // Others
            }
            
            // Use default marker with custom colors for better positioning
            return L.icon({
                iconUrl: iconUrl,
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }
        
        // Generate HTML for rating stars
        function generateRatingStars(rating) {
            let starsHtml = '';
            const fullStars = Math.floor(rating);
            const halfStar = rating % 1 >= 0.5;
            
            for (let i = 0; i < fullStars; i++) {
                starsHtml += '<i class="fas fa-star text-warning"></i>';
            }
            
            if (halfStar) {
                starsHtml += '<i class="fas fa-star-half-alt text-warning"></i>';
            }
            
            const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
            for (let i = 0; i < emptyStars; i++) {
                starsHtml += '<i class="far fa-star text-warning"></i>';
            }
            
            return starsHtml;
        }
        
        // Get price range text description
        function getPriceRangeText(priceRange) {
            switch(priceRange) {
                case '$': return 'Inexpensive';
                case '$$': return 'Moderate';
                case '$$$': return 'Expensive';
                case '$$$$': return 'Very Expensive';
                default: return '';
            }
        }
        
        // Create popup content with enhanced UI
        function createPopupContent(restaurant) {
            const rating = parseFloat(restaurant.rating || restaurant.avg_rating) || 0;
            const ratingStars = generateRatingStars(rating);
            const reviewCount = restaurant.review_count || 0;
            
            // Get price range badge class
            let priceClass;
            switch(restaurant.price_range) {
                case '$': priceClass = 'bg-success text-white'; break;
                case '$$': priceClass = 'bg-info text-white'; break;
                case '$$$': priceClass = 'bg-primary text-white'; break;
                case '$$$$': priceClass = 'bg-danger text-white'; break;
                default: priceClass = 'bg-secondary text-white';
            }
            
            // Format image if available
            const imageSection = restaurant.image ? 
                `<img src="${restaurant.image}" alt="${restaurant.name}" class="marker-image">` : 
                `<div class="marker-image d-flex align-items-center justify-content-center bg-light">
                    <i class="fas fa-utensils fa-2x text-secondary"></i>
                 </div>`;
            
            const popupHTML = `
                <div class="marker-popup">
                    ${imageSection}
                    <div class="marker-popup-header">
                        <h5 class="mb-1">${restaurant.name}</h5>
                        <div>${ratingStars} <small class="text-muted">(${reviewCount})</small></div>
                        <span class="marker-popup-badge badge ${priceClass}">${restaurant.price_range}</span>
                    </div>
                    <div class="marker-popup-body">
                        <div class="marker-info cuisine">
                            <i class="fas fa-utensils"></i>
                            <span>${restaurant.cuisine_type}</span>
                        </div>
                        <div class="marker-info price">
                            <i class="fas fa-tag"></i>
                            <span>${restaurant.price_range} · ${getPriceRangeText(restaurant.price_range)}</span>
                        </div>
                        <div class="marker-info address">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${restaurant.address}</span>
                        </div>
                        <div class="marker-info phone">
                            <i class="fas fa-phone"></i>
                            <span>${restaurant.phone || 'N/A'}</span>
                        </div>
                        
                        <div class="marker-actions">
                            <a href="restaurant.php?id=${restaurant.id || restaurant.restaurant_id}" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fas fa-info-circle me-1"></i> Details
                            </a>
                            <a href="reservation.php?id=${restaurant.id || restaurant.restaurant_id}" class="btn btn-success btn-sm flex-grow-1">
                                <i class="fas fa-calendar-check me-1"></i> Reserve
                            </a>
                        </div>
                    </div>
                </div>
            `;
            return popupHTML;
        }

        // Function to add marker
        function addMarker(restaurant) {
            // Use custom marker icon with standard leaflet marker
            const marker = L.marker([restaurant.lat, restaurant.lng], {
                icon: getMarkerIcon(restaurant.rating || 3, false)
            })
            .bindPopup(createPopupContent(restaurant), {
                maxWidth: 300,
                className: 'restaurant-popup',
                autoPan: false // Default is no auto-pan for map clicks
            });
            
            // Store additional data with marker
            marker.restaurantData = restaurant;
            
            marker.addTo(map);
            markers.push(marker);
            bounds.extend([restaurant.lat, restaurant.lng]);
            
            // Add click handler to marker
            marker.on('click', function(e) {
                // When clicking on map marker, don't auto-pan
                highlightRestaurant(restaurant.id, false);
            });
            
            return marker;
        }

        // Add markers for all restaurants
        document.querySelectorAll('.restaurant-card').forEach(card => {
            const id = card.dataset.id;
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            const name = card.querySelector('.card-title').textContent;
            const cuisine_type = card.querySelector('.text-muted').textContent.trim();
            const address = card.querySelector('.card-text:not(.text-muted)').textContent.trim();
            const rating = parseFloat(card.querySelector('.rating').textContent) || 3;
            const price_range = card.querySelector('.price-range').textContent.trim();
            const review_count = card.querySelector('.rating span')?.textContent.match(/\d+/)[0] || 0;
            const image = card.querySelector('img')?.src;
            const phone = card.dataset.phone;
            
            addMarker({ 
                id, lat, lng, name, cuisine_type, address, 
                rating, price_range, review_count, image, phone
            });
        });

        // Fit map to show all markers
        if (!bounds.isValid()) {
            map.setView([0, 0], 2);
        } else {
            map.fitBounds(bounds, { padding: [50, 50] });
        }

        // Function to highlight a restaurant by ID
        function highlightRestaurant(id, enableAutoPan = true) {
            // Remove active class from all cards
            document.querySelectorAll('.restaurant-card').forEach(card => {
                card.classList.remove('active');
                card.querySelector('.card').classList.remove('border-primary');
            });
            
            // Reset all markers to default
            markers.forEach(marker => {
                const rating = marker.restaurantData.rating || 3;
                marker.setIcon(getMarkerIcon(rating, false));
                marker.setZIndexOffset(0);
                // Close any open popups
                if (marker.isPopupOpen()) {
                    marker.closePopup();
                }
            });
            
            // Find the card and marker for this restaurant
            const card = document.querySelector(`.restaurant-card[data-id="${id}"]`);
            const marker = markers.find(m => m.restaurantData.id === id);
            
            if (card && marker) {
                // Highlight card
                card.classList.add('active');
                card.querySelector('.card').classList.add('border-primary');
                
                // Highlight marker and open popup
                marker.setIcon(getMarkerIcon(marker.restaurantData.rating || 3, true));
                marker.setZIndexOffset(1000);
                
                // Get current popup and close it
                if (marker.getPopup()) {
                    marker.closePopup();
                }
                
                // Open popup with autoPan enabled only when clicking from results
                marker.bindPopup(createPopupContent(marker.restaurantData), {
                    maxWidth: 300,
                    className: 'restaurant-popup',
                    autoPan: enableAutoPan // Auto-pan only when selected from results
                }).openPopup();
                
                // Scroll the clicked card into view
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                
                // Store active marker
                activeMarker = marker;
            }
        }

        // Handle restaurant card clicks
        document.querySelectorAll('.restaurant-card').forEach(card => {
            card.addEventListener('click', function() {
                const id = this.dataset.id;
                // Enable auto-panning when clicking from results
                highlightRestaurant(id, true);
            });
        });

        // Auto-select first restaurant if there are results
        if (document.querySelector('.restaurant-card')) {
            document.querySelector('.restaurant-card').click();
        }

        // Handle map resize
        window.addEventListener('resize', function() {
            map.invalidateSize();
        });
    </script>

    <!-- Enhanced Search Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const cuisineSelect = document.getElementById('cuisineSelect');
            const priceRangeButtons = document.querySelectorAll('#priceRangeButtons button');
            const priceRangeInput = document.getElementById('priceRangeInput');
            const ratingSlider = document.getElementById('ratingSlider');
            const ratingInput = document.getElementById('ratingInput');
            const sortSelect = document.getElementById('sortSelect');
            const nearMeBtn = document.getElementById('nearMeBtn');
            const clearButton = document.getElementById('clearButton');
            
            let searchTimeout;
            const SEARCH_DELAY = 500; // ms delay for search to avoid excessive requests
            
            // Auto-search on input change with debounce
            function setupAutoSearch() {
                const filterElements = document.querySelectorAll('.filter-select, #ratingSlider');
                
                // Search input with debounce
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        searchForm.submit();
                    }, SEARCH_DELAY);
                });
                
                // Filter selects - immediate search
                filterElements.forEach(element => {
                    element.addEventListener('change', function() {
                        if (element.id === 'ratingSlider') {
                            ratingInput.value = element.value;
                        }
                        searchForm.submit();
                    });
                });
            }
            
            // Setup price range buttons
            function setupPriceRangeButtons() {
                priceRangeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        // Update visual state
                        priceRangeButtons.forEach(btn => {
                            btn.classList.remove('btn-success');
                            btn.classList.add('btn-outline-success');
                        });
                        
                        this.classList.remove('btn-outline-success');
                        this.classList.add('btn-success');
                        
                        // Update hidden input
                        priceRangeInput.value = this.dataset.price;
                        
                        // Submit form
                        searchForm.submit();
                    });
                });
            }
            
            // Setup rating buttons
            function setupRatingButtons() {
                const ratingButtons = document.querySelectorAll('[data-rating]');
                const ratingInput = document.getElementById('ratingInput');
                
                ratingButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const rating = parseInt(this.dataset.rating);
                        
                        // Update visual state
                        ratingButtons.forEach(btn => {
                            btn.classList.remove('btn-warning');
                            btn.classList.add('btn-outline-warning');
                        });
                        
                        // Update button state
                        this.classList.remove('btn-outline-warning');
                        this.classList.add('btn-warning');
                        
                        // Update hidden input
                        ratingInput.value = rating;
                        
                        // Submit form
                        searchForm.submit();
                    });
                });
            }
            
            // Setup location search (Near Me button)
            function setupLocationSearch() {
                nearMeBtn.addEventListener('click', function() {
                    if (navigator.geolocation) {
                        // Change button state to show loading
                        nearMeBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                        nearMeBtn.disabled = true;
                        
                        navigator.geolocation.getCurrentPosition(
                            function(position) {
                                // Add location parameters to the form
                                const latInput = document.createElement('input');
                                latInput.type = 'hidden';
                                latInput.name = 'lat';
                                latInput.value = position.coords.latitude;
                                
                                const lngInput = document.createElement('input');
                                lngInput.type = 'hidden';
                                lngInput.name = 'lng';
                                lngInput.value = position.coords.longitude;
                                
                                // Set sort order to distance
                                const sortInput = document.createElement('input');
                                sortInput.type = 'hidden';
                                sortInput.name = 'sort';
                                sortInput.value = 'distance';
                                
                                searchForm.appendChild(latInput);
                                searchForm.appendChild(lngInput);
                                searchForm.appendChild(sortInput);
                                
                                // Submit the form
                                searchForm.submit();
                            },
                            function(error) {
                                // Reset button
                                nearMeBtn.innerHTML = '<i class="fas fa-location-arrow"></i>';
                                nearMeBtn.disabled = false;
                                
                                // Show error
                                alertify.error("Error finding your location: " + error.message);
                            }
                        );
                    } else {
                        alertify.error("Geolocation is not supported by your browser");
                    }
                });
            }
            
            // Setup clear button
            function setupClearButton() {
                clearButton.addEventListener('click', function() {
                    window.location.href = 'search.php';
                });
            }
            
            // Initialize all interactive elements
            setupAutoSearch();
            setupPriceRangeButtons();
            setupRatingButtons();
            setupLocationSearch();
            setupClearButton();
            
            // Update restaurant cards on map interaction
            markers.forEach(marker => {
                marker.on('click', function() {
                    const id = this.restaurantData.id;
                    const card = document.querySelector(`.restaurant-card[data-id="${id}"]`);
                    if (card) {
                        // Scroll to the card with smooth animation
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            });
        });
    </script>
</body>
</html> 