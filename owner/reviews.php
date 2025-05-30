<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Handle restaurant selection (same as reservations.php)
if (isset($_GET['restaurant_id'])) {
    $_SESSION['current_restaurant_id'] = intval($_GET['restaurant_id']);
    
    // Always redirect without parameters to avoid reprocessing
    header("Location: reviews.php");
    exit;
}

// Check if restaurant is selected
if (!isset($_SESSION['current_restaurant_id'])) {
    header("Location: restaurants.php");
    exit;
}

$restaurant_id = $_SESSION['current_restaurant_id'];

// Check if a specific review ID is provided (from notification)
$highlight_review_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If coming from notification, mark this review as read
if ($highlight_review_id > 0) {
    $mark_read_sql = "UPDATE reviews SET is_read = 1 
                      WHERE review_id = ? AND 
                      restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)";
    $stmt = $conn->prepare($mark_read_sql);
    $stmt->bind_param("ii", $highlight_review_id, $owner['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Redirect to clean URL after processing the ID
    header("Location: reviews.php");
    exit;
}

// Get restaurant information
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    unset($_SESSION['current_restaurant_id']);
    header("Location: restaurants.php");
    exit;
}

// Get reviews for the restaurant
$sql = "SELECT r.*, u.username, u.first_name, u.last_name, u.profile_image FROM reviews r LEFT JOIN users u ON r.user_id = u.user_id WHERE r.restaurant_id = ? ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Analytics calculations
$total_reviews = count($reviews);
$total_rating = 0;
$rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($reviews as $review) {
    $rating = intval($review['overall_rating']);
    $total_rating += $rating;
    if (isset($rating_counts[$rating])) {
        $rating_counts[$rating]++;
    }
}
$average_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;

?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Reviews Analytics - <?php echo htmlspecialchars($restaurant['name']); ?></h2>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-success text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Average Rating</h5>
                            <h2 class="display-4"><?php echo $average_rating; ?> <i class="fas fa-star text-warning"></i></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Total Reviews</h5>
                            <h2 class="display-4"><?php echo $total_reviews; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <canvas id="ratingChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Filters and Search -->
            <div class="row mb-3 align-items-end">
                <div class="col-md-4 mb-2">
                    <label for="searchInput" class="form-label">Search Reviews</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by user or comment...">
                </div>
                <div class="col-md-3 mb-2">
                    <label for="ratingFilter" class="form-label">Filter by Rating</label>
                    <select id="ratingFilter" class="form-select">
                        <option value="">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button id="clearFilters" class="btn btn-outline-secondary w-100">Clear Filters</button>
                </div>
            </div>
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">All Reviews</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reviews)): ?>
                        <div class="alert alert-info">No reviews for this restaurant yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle" id="reviewsTable">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Rating</th>
                                        <th>Comment</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr data-review-id="<?php echo $review['review_id']; ?>">
                                            <td class="review-username">
                                                <?php if (!empty($review['profile_image'])): ?>
                                                    <img src="../uploads/profile/<?php echo htmlspecialchars($review['profile_image']); ?>" alt="User" class="rounded-circle me-2" width="40" height="40">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle fa-2x text-secondary me-2"></i>
                                                <?php endif; ?>
                                                <span>
                                                    <?php 
                                                    echo htmlspecialchars(
                                                        (!empty($review['first_name']) && !empty($review['last_name'])) ? 
                                                        $review['first_name'] . ' ' . $review['last_name'] : 
                                                        $review['username']
                                                    ); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="review-rating" data-rating="<?php echo (int)round($review['overall_rating']); ?>">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-star <?php echo $i <= round($review['overall_rating']) ? 'fas text-warning' : 'far text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ms-2 fw-bold"><?php echo (int)round($review['overall_rating']); ?></span>
                                            </td>
                                            <td class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></td>
                                            <td class="review-date"><?php echo date('Y-m-d H:i', strtotime($review['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('ratingChart').getContext('2d');
    var ratingCounts = <?php echo json_encode(array_values($rating_counts)); ?>;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
            datasets: [{
                label: 'Number of Reviews',
                data: ratingCounts,
                backgroundColor: [
                    '#e57373', '#ffb74d', '#fff176', '#81c784', '#64b5f6'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: {
                    display: true,
                    text: 'Rating Distribution'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            }
        }
    });

    // --- Review Table Filter & Search ---
    const searchInput = document.getElementById('searchInput');
    const ratingFilter = document.getElementById('ratingFilter');
    const clearFilters = document.getElementById('clearFilters');
    const table = document.getElementById('reviewsTable');
    const rows = table ? table.getElementsByTagName('tbody')[0].getElementsByTagName('tr') : [];

    function filterTable() {
        const search = searchInput.value.toLowerCase();
        const rating = ratingFilter.value;
        for (let i = 0; i < rows.length; i++) {
            const username = rows[i].querySelector('.review-username').innerText.toLowerCase();
            const comment = rows[i].querySelector('.review-comment').innerText.toLowerCase();
            const stars = rows[i].querySelector('.review-rating').getAttribute('data-rating');
            let show = true;
            if (search && !(username.includes(search) || comment.includes(search))) {
                show = false;
            }
            if (rating && stars !== rating) {
                show = false;
            }
            rows[i].style.display = show ? '' : 'none';
        }
    }
    if (searchInput && ratingFilter) {
        searchInput.addEventListener('input', filterTable);
        ratingFilter.addEventListener('change', filterTable);
        clearFilters.addEventListener('click', function() {
            searchInput.value = '';
            ratingFilter.value = '';
            filterTable();
        });
    }
});
</script>

<?php if ($highlight_review_id > 0): ?>
<!-- Add red dot indicator for the highlighted review -->
<style>
.highlight-indicator {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #dc3545;
    margin-right: 10px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find the row containing the target review ID
    const reviewId = <?php echo $highlight_review_id; ?>;
    
    // Look for the review
    setTimeout(function() {
        const row = document.querySelector('tr[data-review-id="' + reviewId + '"]');
        if (row) {
            // Add a relative position to the row for absolute positioning of the dot
            row.style.position = 'relative';
            
            // Create and add the red dot indicator
            const indicator = document.createElement('div');
            indicator.className = 'highlight-indicator';
            row.appendChild(indicator);
            
            // Scroll to the row
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Remove the indicator after 5 seconds
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 5000);
        }
    }, 800);
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
<?php 
// Flush the output buffer and send content to browser
ob_end_flush(); 
?>
