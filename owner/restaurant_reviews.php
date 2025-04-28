<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'includes/db_connect.php';

// Get owner information
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'owner'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    header("Location: ../login.php");
    exit;
}

// Get restaurant information
$restaurant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    header("Location: restaurants.php");
    exit;
}

// Get reviews with user information
$sql = "SELECT r.*, u.username, u.profile_image 
        FROM reviews r 
        LEFT JOIN users u ON r.user_id = u.user_id 
        WHERE r.restaurant_id = ? 
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate review statistics
$positive_reviews = 0;
$total_reviews = count($reviews);
$total_rating = 0;

foreach ($reviews as $review) {
    $total_rating += $review['overall_rating'];
    if ($review['overall_rating'] >= 4) {
        $positive_reviews++;
    }
}

$average_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
$positive_percentage = $total_reviews > 0 ? round(($positive_reviews / $total_reviews) * 100) : 0;

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Review Analysis - <?php echo htmlspecialchars($restaurant['name']); ?></h5>
                    <a href="restaurants.php" class="btn btn-secondary">Back to Restaurants</a>
                </div>
                <div class="card-body">
                    <!-- Review Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Reviews</h5>
                                    <h2 class="card-text"><?php echo $total_reviews; ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Average Rating</h5>
                                    <h2 class="card-text"><?php echo $average_rating; ?>/5</h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Positive Reviews</h5>
                                    <h2 class="card-text"><?php echo $positive_percentage; ?>%</h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Review List -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Review Date</th>
                                    <th>Overall Rating</th>
                                    <th>Food Rating</th>
                                    <th>Service Rating</th>
                                    <th>Atmosphere Rating</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($review['profile_image'])): ?>
                                                <img src="../uploads/profile/<?php echo htmlspecialchars($review['profile_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($review['username']); ?>" 
                                                     class="rounded-circle" 
                                                     style="width: 30px; height: 30px; margin-right: 8px;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($review['username']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas <?php echo $i <= $review['overall_rating'] ? 'fa-star' : 'fa-star-o'; ?> text-warning"></i>
                                        <?php endfor; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                    <td><?php echo $review['overall_rating']; ?>/5</td>
                                    <td><?php echo $review['food_rating']; ?>/5</td>
                                    <td><?php echo $review['service_rating']; ?>/5</td>
                                    <td><?php echo $review['atmosphere_rating']; ?>/5</td>
                                    <td><?php echo htmlspecialchars($review['comment']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Review Analysis Chart -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Review Distribution</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="reviewChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    // Initialize review distribution chart
    const ctx = document.getElementById('reviewChart').getContext('2d');
    const ratings = {
        1: 0, 2: 0, 3: 0, 4: 0, 5: 0
    };
    
    <?php foreach ($reviews as $review): ?>
        ratings[<?php echo $review['overall_rating']; ?>]++;
    <?php endforeach; ?>
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
            datasets: [{
                label: 'Number of Reviews',
                data: [ratings[1], ratings[2], ratings[3], ratings[4], ratings[5]],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(40, 167, 69, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(40, 167, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
