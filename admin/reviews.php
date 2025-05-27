<?php
include 'includes/header.php';

// Process actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $review_id = intval($_GET['id']);
    
    // Delete review
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->bind_param("i", $review_id);
        if ($stmt->execute()) {
            $success_msg = "Review deleted successfully.";
        } else {
            $error_msg = "Failed to delete review.";
        }
        $stmt->close();
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$restaurant_filter = isset($_GET['restaurant']) ? intval($_GET['restaurant']) : 0;

// Base query
$query = "
    SELECT r.review_id, r.overall_rating, r.comment, r.created_at, 
           u.username, u.email, u.user_id,
           res.name as restaurant_name, res.restaurant_id
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN restaurants res ON r.restaurant_id = res.restaurant_id
    WHERE 1=1
";

$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR r.comment LIKE ? OR res.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Add rating filter
if ($rating_filter > 0) {
    $query .= " AND r.overall_rating = ?";
    $params[] = $rating_filter;
    $types .= "i";
}

// Add restaurant filter
if ($restaurant_filter > 0) {
    $query .= " AND r.restaurant_id = ?";
    $params[] = $restaurant_filter;
    $types .= "i";
}

// Order by
$query .= " ORDER BY r.created_at DESC";

// Execute query
$reviews = [];
if ($stmt = $conn->prepare($query)) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get review statistics
$total_reviews = 0;
$avg_rating = 0;

$result = $conn->query("SELECT COUNT(*) as total FROM reviews");
if ($result && $row = $result->fetch_assoc()) {
    $total_reviews = $row['total'];
}

$result = $conn->query("SELECT AVG(overall_rating) as avg_rating FROM reviews");
if ($result && $row = $result->fetch_assoc()) {
    $avg_rating = round($row['avg_rating'], 1);
}

// Get restaurants for filter dropdown
$restaurants = [];
$result = $conn->query("SELECT restaurant_id, name FROM restaurants ORDER BY name");
if ($result) {
    $restaurants = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Manage Reviews</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reviews</li>
            </ol>
        </div>
        <div>
            <a href="manage_reviews.php" class="btn btn-outline-secondary">
                <i class="fas fa-th-large me-1"></i> Detailed View
            </a>
        </div>
    </div>
    
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>
    
    <!-- Review Statistics -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="dashboard-card">
                <div class="card-icon bg-primary-light text-primary mb-3">
                    <i class="fas fa-comments"></i>
                </div>
                <h6 class="card-title">Total Reviews</h6>
                <h2 class="card-value"><?php echo $total_reviews; ?></h2>
                <div class="card-trend">
                    <i class="fas fa-chart-line me-1"></i> All time
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="dashboard-card">
                <div class="card-icon bg-success-light text-success mb-3">
                    <i class="fas fa-star"></i>
                </div>
                <h6 class="card-title">Average Rating</h6>
                <h2 class="card-value">
                    <?php echo $avg_rating; ?>
                    <small>
                        <?php 
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= round($avg_rating)) {
                                echo '<i class="fas fa-star"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </small>
                </h2>
                <div class="card-trend">
                    <i class="fas fa-chart-line me-1"></i> Overall score
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="dashboard-card">
                <div class="card-icon bg-info-light text-info mb-3">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h6 class="card-title">Last Review</h6>
                <h2 class="card-value">
                    <?php 
                    $result = $conn->query("SELECT created_at FROM reviews ORDER BY created_at DESC LIMIT 1");
                    if ($result && $row = $result->fetch_assoc()) {
                        echo date('M d, Y', strtotime($row['created_at']));
                    } else {
                        echo "No reviews yet";
                    }
                    ?>
                </h2>
                <div class="card-trend">
                    <i class="fas fa-clock me-1"></i> Most recent
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Search and Filters
        </div>
        <div class="card-body">
            <form method="GET" action="reviews.php" class="row g-3">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by username, email, comment, or restaurant" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label for="rating" class="form-label">Rating</label>
                    <select class="form-select" id="rating" name="rating">
                        <option value="0">All Ratings</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $rating_filter === $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="restaurant" class="form-label">Restaurant</label>
                    <select class="form-select" id="restaurant" name="restaurant">
                        <option value="0">All Restaurants</option>
                        <?php foreach ($restaurants as $restaurant): ?>
                            <option value="<?php echo $restaurant['restaurant_id']; ?>" 
                                    <?php echo $restaurant_filter === $restaurant['restaurant_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($restaurant['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reviews Table -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Reviews List
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="reviewsTable" class="table table-bordered data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Restaurant</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td><?php echo $review['review_id']; ?></td>
                                <td>
                                    <a href="view_user.php?id=<?php echo $review['user_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($review['username']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                </td>
                                <td>
                                    <a href="edit_restaurant.php?id=<?php echo $review['restaurant_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($review['restaurant_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $review['overall_rating']) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-warning"></i>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $comment = htmlspecialchars($review['comment']);
                                    echo strlen($comment) > 100 ? substr($comment, 0, 100) . '...' : $comment;
                                    ?>
                                    <?php if (strlen($review['comment']) > 100): ?>
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1" 
                                                data-bs-toggle="modal" data-bs-target="#commentModal<?php echo $review['review_id']; ?>">
                                            Read More
                                        </button>
                                        
                                        <!-- Comment Modal -->
                                        <div class="modal fade" id="commentModal<?php echo $review['review_id']; ?>" tabindex="-1" 
                                             aria-labelledby="commentModalLabel<?php echo $review['review_id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="commentModalLabel<?php echo $review['review_id']; ?>">
                                                            Review Comment
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($review['restaurant_name']); ?></p>
                                                        <p><strong>User:</strong> <?php echo htmlspecialchars($review['username']); ?></p>
                                                        <p><strong>Rating:</strong> 
                                                            <?php 
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $review['overall_rating']) {
                                                                    echo '<i class="fas fa-star text-warning"></i>';
                                                                } else {
                                                                    echo '<i class="far fa-star text-warning"></i>';
                                                                }
                                                            }
                                                            ?>
                                                        </p>
                                                        <p><strong>Comment:</strong></p>
                                                        <div class="p-3 bg-light rounded">
                                                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                                        </div>
                                                        <p class="mt-2"><strong>Date:</strong> <?php echo date('M d, Y h:i A', strtotime($review['created_at'])); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" 
                                                id="dropdownMenuButton<?php echo $review['review_id']; ?>" 
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $review['review_id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="view_review.php?id=<?php echo $review['review_id']; ?>">
                                                    <i class="fas fa-eye text-primary"></i> View Details
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="view_user.php?id=<?php echo $review['user_id']; ?>">
                                                    <i class="fas fa-user text-info"></i> View User
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" 
                                                   data-bs-target="#deleteModal<?php echo $review['review_id']; ?>">
                                                    <i class="fas fa-trash-alt text-danger"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?php echo $review['review_id']; ?>" tabindex="-1" 
                                         aria-labelledby="deleteModalLabel<?php echo $review['review_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $review['review_id']; ?>">
                                                        Confirm Delete
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this review?</p>
                                                    <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($review['restaurant_name']); ?></p>
                                                    <p><strong>User:</strong> <?php echo htmlspecialchars($review['username']); ?></p>
                                                    <p><strong>Rating:</strong> <?php echo $review['overall_rating']; ?> stars</p>
                                                    <p><strong>Comment:</strong> <?php echo htmlspecialchars(substr($review['comment'], 0, 100)) . (strlen($review['comment']) > 100 ? '...' : ''); ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <a href="reviews.php?action=delete&id=<?php echo $review['review_id']; ?>" class="btn btn-danger">
                                                        Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// The DataTable initialization is now handled centrally in admin.js
</script>

<?php include 'includes/footer.php'; ?> 