<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$cuisine = isset($_GET['cuisine']) ? sanitize($_GET['cuisine']) : '';
$price_range = isset($_GET['price_range']) ? sanitize($_GET['price_range']) : '';
$rating = isset($_GET['rating']) ? (float)$_GET['rating'] : 0;
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'rating';

// Build the query
$query = "SELECT r.*, 
            COALESCE(AVG(rev.overall_rating), 0) as avg_rating,
            COUNT(rev.review_id) as review_count
          FROM restaurants r
          LEFT JOIN reviews rev ON r.restaurant_id = rev.restaurant_id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (r.name LIKE :search OR r.description LIKE :search OR r.cuisine_type LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($cuisine)) {
    $query .= " AND r.cuisine_type = :cuisine";
    $params[':cuisine'] = $cuisine;
}

if (!empty($price_range)) {
    $query .= " AND r.price_range = :price_range";
    $params[':price_range'] = $price_range;
}

if ($rating > 0) {
    $query .= " HAVING avg_rating >= :rating";
    $params[':rating'] = $rating;
}

$query .= " GROUP BY r.restaurant_id";

// Add sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY r.price_range ASC, avg_rating DESC";
        break;
    case 'price_high':
        $query .= " ORDER BY r.price_range DESC, avg_rating DESC";
        break;
    case 'rating':
    default:
        $query .= " ORDER BY avg_rating DESC, review_count DESC";
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
            height: 80vh;
            width: 95%;
            position: sticky;
            bottom: 0;
            border-radius: 0;
            margin: 0 auto;
            display: block;
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
        }
        .restaurant-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .restaurant-card.active {
            border: 2px solid var(--primary);
        }
        .content-wrapper {
            height: 100vh;
            overflow-y: auto;
            padding: 1rem;
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-form .form-group {
            flex: 1;
            min-width: 200px;
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
        
        .restaurant-marker {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            border: 2px solid #fff;
            font-size: 16px;
            transition: transform 0.2s ease;
            position: relative;
        }
        
        .restaurant-marker:hover {
            transform: scale(1.2);
            z-index: 1000 !important;
        }
        
        .restaurant-marker.bg-success {
            background-color: var(--success-color) !important;
        }
        
        .restaurant-marker.bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .restaurant-marker.bg-warning {
            background-color: var(--warning-color) !important;
            color: #664d03 !important;
        }
        
        .restaurant-marker.bg-danger {
            background-color: var(--danger-color) !important;
        }
        
        .restaurant-marker.pulse {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7);
            }
            70% {
                transform: scale(1.1);
                box-shadow: 0 0 0 10px rgba(0, 123, 255, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
            }
        }
        
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
                                <div class="form-group">
                                    <label class="form-label">Search</label>
                                    <input type="text" class="form-control" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="Search restaurants...">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Cuisine Type</label>
                                    <select class="form-select" name="cuisine">
                                        <option value="">All Cuisines</option>
                                        <?php foreach ($cuisine_types as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type); ?>"
                                                    <?php echo $cuisine === $type ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Price Range</label>
                                    <select class="form-select" name="price_range">
                                        <option value="">Any Price</option>
                                        <option value="$" <?php echo $price_range === '$' ? 'selected' : ''; ?>>$</option>
                                        <option value="$$" <?php echo $price_range === '$$' ? 'selected' : ''; ?>>$$</option>
                                        <option value="$$$" <?php echo $price_range === '$$$' ? 'selected' : ''; ?>>$$$</option>
                                        <option value="$$$$" <?php echo $price_range === '$$$$' ? 'selected' : ''; ?>>$$$$</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Minimum Rating</label>
                                    <select class="form-select" name="rating">
                                        <option value="0">Any Rating</option>
                                        <option value="4" <?php echo $rating === 4 ? 'selected' : ''; ?>>4+ Stars</option>
                                        <option value="3" <?php echo $rating === 3 ? 'selected' : ''; ?>>3+ Stars</option>
                                        <option value="2" <?php echo $rating === 2 ? 'selected' : ''; ?>>2+ Stars</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Sort By</label>
                                    <select class="form-select" name="sort">
                                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Best Rated</option>
                                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-2"></i>Search
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
                No restaurants found matching your criteria.
            </div>
        </div>
    <?php else: ?>
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
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($restaurant['name']); ?></h5>
                        <p class="card-text text-muted">
                            <i class="fas fa-utensils me-2"></i><?php echo htmlspecialchars($restaurant['cuisine_type']); ?>
                        </p>
                        <p class="card-text">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($restaurant['address']); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $restaurant['avg_rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2">(<?php echo $restaurant['review_count']; ?>)</span>
                            </div>
                            <span class="price-range">
                                <?php echo str_repeat('$', strlen($restaurant['price_range'])); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer bg-transparent border-0">
                        <a href="restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                           class="btn btn-outline-primary w-100 rounded-pill">
                            View Details
                        </a>
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
        const map = L.map('map').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Store markers
        const markers = [];
        let activeMarker = null;
        let bounds = L.latLngBounds([]);

        // Custom marker icons based on rating
        function getMarkerIcon(rating, isActive = false) {
            let color, icon;
            
            if (rating >= 4.5) {
                color = 'success';
                icon = 'fa-award';
            } else if (rating >= 4.0) {
                color = 'primary';
                icon = 'fa-utensils';
            } else if (rating >= 3.5) {
                color = 'warning';
                icon = 'fa-utensils';
            } else {
                color = 'danger';
                icon = 'fa-utensils';
            }
            
            const className = isActive ? 
                `restaurant-marker bg-${color} pulse` : 
                `restaurant-marker bg-${color}`;
                
            return L.divIcon({
                className: className,
                html: `<i class="fas ${icon}"></i>`,
                iconSize: [36, 36],
                iconAnchor: [18, 18],
                popupAnchor: [0, -18]
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
            const marker = L.marker([restaurant.lat, restaurant.lng], {
                icon: getMarkerIcon(restaurant.rating || 3)
            })
            .bindPopup(createPopupContent(restaurant), {
                maxWidth: 300,
                className: 'restaurant-popup'
            });
            
            // Store additional data with marker
            marker.restaurantData = restaurant;
            
            marker.addTo(map);
            markers.push(marker);
            bounds.extend([restaurant.lat, restaurant.lng]);
            
            // Add click handler to marker
            marker.on('click', function() {
                highlightRestaurant(restaurant.id);
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
        function highlightRestaurant(id) {
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
            });
            
            // Find the card and marker for this restaurant
            const card = document.querySelector(`.restaurant-card[data-id="${id}"]`);
            const marker = markers.find(m => m.restaurantData.id === id);
            
            if (card && marker) {
                // Highlight card
                card.classList.add('active');
                card.querySelector('.card').classList.add('border-primary');
                
                // Highlight marker
                marker.setIcon(getMarkerIcon(marker.restaurantData.rating, true));
                marker.setZIndexOffset(1000);
                marker.openPopup();
                
                // Center map on selected restaurant
                map.setView(marker.getLatLng(), 15);
                
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
                highlightRestaurant(id);
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
</body>
</html> 