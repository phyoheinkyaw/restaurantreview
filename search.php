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
                 data-lng="<?php echo $restaurant['longitude']; ?>">
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
        let bounds = L.latLngBounds([]);

        // Function to add marker
        function addMarker(restaurant) {
            const marker = L.marker([restaurant.lat, restaurant.lng])
                .bindPopup(`
                    <strong>${restaurant.name}</strong><br>
                    ${restaurant.cuisine_type}<br>
                    ${restaurant.address}
                `)
                .addTo(map);
            
            markers.push(marker);
            bounds.extend([restaurant.lat, restaurant.lng]);
        }

        // Add markers for all restaurants
        document.querySelectorAll('.restaurant-card').forEach(card => {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            const name = card.querySelector('.card-title').textContent;
            const cuisine = card.querySelector('.text-muted').textContent.trim();
            const address = card.querySelector('.card-text:not(.text-muted)').textContent.trim();
            
            addMarker({ lat, lng, name, cuisine, address });
        });

        // Fit map to show all markers
        if (!bounds.isValid()) {
            map.setView([0, 0], 2);
        } else {
            map.fitBounds(bounds, { padding: [50, 50] });
        }

        // Handle restaurant card clicks
        document.querySelectorAll('.restaurant-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove active class from all cards
                document.querySelectorAll('.restaurant-card').forEach(c => c.classList.remove('active'));
                // Add active class to clicked card
                this.classList.add('active');
                
                // Center map on selected restaurant
                const lat = parseFloat(this.dataset.lat);
                const lng = parseFloat(this.dataset.lng);
                map.setView([lat, lng], 15);
                
                // Highlight marker
                markers.forEach(marker => {
                    if (marker.getLatLng().lat === lat && marker.getLatLng().lng === lng) {
                        marker.openPopup();
                    }
                });

                // Scroll the clicked card into view
                this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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