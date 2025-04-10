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
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get all restaurants for dropdown
$stmt = $conn->prepare("SELECT restaurant_id, name FROM restaurants ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
$restaurants = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10; // reviews per page
$offset = ($page - 1) * $limit;

// Base query
$query = "
    SELECT r.*, 
           u.username, u.email, u.first_name, u.last_name, u.user_id,
           res.name as restaurant_name, res.restaurant_id, res.cuisine_type
    FROM reviews r
    JOIN users u ON r.user_id = u.user_id
    JOIN restaurants res ON r.restaurant_id = res.restaurant_id
    WHERE 1=1
";

// For counting total reviews - create a separate count query
$count_query = "
    SELECT COUNT(*) as total
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
    $count_query .= " AND (u.username LIKE ? OR u.email LIKE ? OR r.comment LIKE ? OR res.name LIKE ?)";
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
    $count_query .= " AND r.overall_rating = ?";
    $params[] = $rating_filter;
    $types .= "i";
}

// Add restaurant filter
if ($restaurant_filter > 0) {
    $query .= " AND r.restaurant_id = ?";
    $count_query .= " AND r.restaurant_id = ?";
    $params[] = $restaurant_filter;
    $types .= "i";
}

// Add date filters
if (!empty($date_from)) {
    $query .= " AND DATE(r.created_at) >= ?";
    $count_query .= " AND DATE(r.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(r.created_at) <= ?";
    $count_query .= " AND DATE(r.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Count total reviews for pagination
$count_stmt = $conn->prepare($count_query);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_reviews = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_reviews / $limit);

// Order by and limit for pagination
$query .= " ORDER BY r.created_at DESC LIMIT ?, ?";
$params[] = $offset;
$params[] = $limit;
$types .= "ii";

// Get reviews
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$reviews = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Function to display star rating
function displayStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $html .= '<i class="far fa-star text-warning"></i>';
        }
    }
    return $html;
}
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Review Management</h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Review Management</li>
            </ol>
        </div>
        <div>
            <a href="reviews.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> Simple View
            </a>
        </div>
    </div>
    
    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-filter me-1"></i>
            Filter Reviews
        </div>
        <div class="card-body">
            <form action="manage_reviews.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Search by username, email, comment, or restaurant" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="manage_reviews.php" class="btn btn-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reviews Cards -->
    <div class="row">
        <?php foreach ($reviews as $review): 
            // Format the username for display
            $display_name = !empty($review['first_name']) ? 
                htmlspecialchars($review['first_name'] . ' ' . $review['last_name']) : 
                htmlspecialchars($review['username']);
                
            // Get review age
            $review_date = new DateTime($review['created_at']);
            $now = new DateTime();
            $review_age = $review_date->diff($now);
            $review_age_text = '';
            
            if ($review_age->y > 0) {
                $review_age_text .= $review_age->y . ' year' . ($review_age->y > 1 ? 's' : '') . ' ago';
            } elseif ($review_age->m > 0) {
                $review_age_text .= $review_age->m . ' month' . ($review_age->m > 1 ? 's' : '') . ' ago';
            } elseif ($review_age->d > 0) {
                $review_age_text .= $review_age->d . ' day' . ($review_age->d > 1 ? 's' : '') . ' ago';
            } elseif ($review_age->h > 0) {
                $review_age_text .= $review_age->h . ' hour' . ($review_age->h > 1 ? 's' : '') . ' ago';
            } else {
                $review_age_text .= $review_age->i . ' minute' . ($review_age->i > 1 ? 's' : '') . ' ago';
            }
        ?>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-star text-warning me-1"></i>
                        <strong><?php echo htmlspecialchars($review['restaurant_name']); ?></strong>
                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($review['cuisine_type']); ?></span>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                id="dropdownMenuButton<?php echo $review['review_id']; ?>" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?php echo $review['review_id']; ?>">
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
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="review-rating me-2">
                            <?php echo displayStars($review['overall_rating']); ?>
                        </div>
                        <span class="badge bg-primary"><?php echo $review['overall_rating']; ?>/5</span>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex mb-2">
                            <div class="me-3">
                                <small class="text-muted">Cleanliness: <?php echo $review['cleanliness_rating']; ?>/5</small>
                            </div>
                            <div class="me-3">
                                <small class="text-muted">Taste: <?php echo $review['taste_rating']; ?>/5</small>
                            </div>
                            <div class="me-3">
                                <small class="text-muted">Service: <?php echo $review['service_rating']; ?>/5</small>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="me-3">
                                <small class="text-muted">Value: <?php echo $review['price_rating']; ?>/5</small>
                            </div>
                            <div>
                                <small class="text-muted">Parking: <?php echo $review['parking_rating']; ?>/5</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="review-content p-3 bg-light rounded mb-3">
                        <p class="mb-0">
                            <?php 
                            $comment = htmlspecialchars($review['comment']);
                            echo strlen($comment) > 150 ? substr($comment, 0, 150) . '...' : $comment;
                            ?>
                            <?php if (strlen($review['comment']) > 150): ?>
                                <a href="view_review.php?id=<?php echo $review['review_id']; ?>" class="ms-1">Read More</a>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="reviewer-info">
                            <small class="text-muted">Reviewed by <a href="view_user.php?id=<?php echo $review['user_id']; ?>"><?php echo $display_name; ?></a></small>
                        </div>
                        <div class="review-date">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i> <?php echo $review_age_text; ?>
                                <span class="ms-1">(<?php echo date('M d, Y', strtotime($review['created_at'])); ?>)</span>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="view_review.php?id=<?php echo $review['review_id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye me-1"></i> View Details
                    </a>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                            data-bs-target="#deleteModal<?php echo $review['review_id']; ?>">
                        <i class="fas fa-trash-alt me-1"></i> Delete
                    </button>
                </div>
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
                            <p><strong>User:</strong> <?php echo $display_name; ?></p>
                            <p><strong>Rating:</strong> <?php echo $review['overall_rating']; ?> stars</p>
                            <p><strong>Comment:</strong> <?php echo htmlspecialchars(substr($review['comment'], 0, 100)) . (strlen($review['comment']) > 100 ? '...' : ''); ?></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <a href="manage_reviews.php?action=delete&id=<?php echo $review['review_id']; ?>&page=<?php echo $page; ?><?php 
                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                                echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                            ?>" class="btn btn-danger">
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (empty($reviews)): ?>
        <div class="alert alert-info mt-4">No reviews found matching your criteria.</div>
    <?php endif; ?>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="manage_reviews.php?page=1<?php 
                            echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                            echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                            echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                            echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                            echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                        ?>">First</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="manage_reviews.php?page=<?php echo $page - 1; ?><?php 
                            echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                            echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                            echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                            echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                            echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                        ?>">Previous</a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="manage_reviews.php?page=<?php echo $i; ?><?php 
                            echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                            echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                            echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                            echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                            echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                        ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="manage_reviews.php?page=<?php echo $page + 1; ?><?php 
                            echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                            echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                            echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                            echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                            echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                        ?>">Next</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="manage_reviews.php?page=<?php echo $total_pages; ?><?php 
                            echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                            echo $rating_filter > 0 ? '&rating=' . $rating_filter : '';
                            echo $restaurant_filter > 0 ? '&restaurant=' . $restaurant_filter : '';
                            echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                            echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                        ?>">Last</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 