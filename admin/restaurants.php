<?php
// Include header
include 'includes/header.php';

// Process actions (delete, feature/unfeature)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $restaurant_id = (int)$_GET['id'];
    
    if ($action === 'delete') {
        // Delete restaurant
        $sql = "DELETE FROM restaurants WHERE restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $restaurant_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Restaurant deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to delete restaurant.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    } elseif ($action === 'feature') {
        // Feature/unfeature restaurant
        $featured = ($_GET['feature'] == 1) ? 1 : 0;
        $sql = "UPDATE restaurants SET is_featured = ? WHERE restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $featured, $restaurant_id);
        
        if ($stmt->execute()) {
            $status = $featured ? 'featured' : 'unfeatured';
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Restaurant ' . $status . ' successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to update restaurant status.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$cuisine = isset($_GET['cuisine']) ? $conn->real_escape_string($_GET['cuisine']) : '';
$featured = isset($_GET['featured']) ? (int)$_GET['featured'] : -1;

// Get all cuisines for filter dropdown
$cuisines = [];
$sql = "SELECT DISTINCT cuisine_type FROM restaurants WHERE cuisine_type IS NOT NULL AND cuisine_type != '' ORDER BY cuisine_type";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cuisines[] = $row['cuisine_type'];
    }
}

// Build query with filters
$query = "SELECT r.*, 
            u.username as owner_name,
            (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.restaurant_id) as review_count,
            (SELECT AVG(overall_rating) FROM reviews WHERE restaurant_id = r.restaurant_id) as avg_rating
          FROM restaurants r
          LEFT JOIN users u ON r.owner_id = u.user_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (r.name LIKE ? OR r.description LIKE ? OR r.address LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

if (!empty($cuisine)) {
    $query .= " AND r.cuisine_type = ?";
    $params[] = $cuisine;
    $types .= "s";
}

if ($featured >= 0) {
    $query .= " AND r.is_featured = ?";
    $params[] = $featured;
    $types .= "i";
}

$query .= " ORDER BY r.name ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Manage Restaurants</h1>
    <a href="add_restaurant.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i> Add New Restaurant
    </a>
</div>

<!-- Restaurant Stats -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-primary-light text-primary mb-3">
                <i class="fas fa-store"></i>
            </div>
            <h6 class="card-title">Total Restaurants</h6>
            <h2 class="card-value"><?php echo count($restaurants); ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-success-light text-success mb-3">
                <i class="fas fa-star"></i>
            </div>
            <h6 class="card-title">Featured Restaurants</h6>
            <h2 class="card-value">
                <?php 
                $featured_count = 0;
                foreach ($restaurants as $r) {
                    if ($r['is_featured']) $featured_count++;
                }
                echo $featured_count;
                ?>
            </h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-info-light text-info mb-3">
                <i class="fas fa-utensils"></i>
            </div>
            <h6 class="card-title">Cuisine Types</h6>
            <h2 class="card-value"><?php echo count($cuisines); ?></h2>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, description, address..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="cuisine" class="form-label">Cuisine Type</label>
                <select class="form-select" id="cuisine" name="cuisine">
                    <option value="">All Cuisines</option>
                    <?php foreach ($cuisines as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($cuisine === $c) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="featured" class="form-label">Featured Status</label>
                <select class="form-select" id="featured" name="featured">
                    <option value="-1" <?php echo ($featured === -1) ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo ($featured === 1) ? 'selected' : ''; ?>>Featured</option>
                    <option value="0" <?php echo ($featured === 0) ? 'selected' : ''; ?>>Not Featured</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Restaurants Table -->
<div class="data-table">
    <div class="data-table-header d-flex justify-content-between align-items-center">
        <h5 class="data-table-title">Restaurants</h5>
        <span class="badge bg-primary"><?php echo count($restaurants); ?> restaurants</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Restaurant</th>
                    <th>Cuisine</th>
                    <th>Owner</th>
                    <th>Rating</th>
                    <th>Reviews</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($restaurants)): ?>
                <tr>
                    <td colspan="7" class="text-center">No restaurants found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($restaurants as $restaurant): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($restaurant['image'])): ?>
                                <img src="../<?php echo $restaurant['image']; ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>" class="rounded me-2" width="40" height="40" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded me-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-utensils"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($restaurant['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($restaurant['address'], 0, 30) . (strlen($restaurant['address']) > 30 ? '...' : '')); ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($restaurant['cuisine_type'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($restaurant['owner_name'] ?? 'N/A'); ?></td>
                    <td>
                        <div class="rating">
                            <?php
                            $rating = round($restaurant['avg_rating'] ?? 0);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $rating) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-muted"></i>';
                                }
                            }
                            ?>
                            <span class="ms-1"><?php echo number_format($restaurant['avg_rating'] ?? 0, 1); ?></span>
                        </div>
                    </td>
                    <td><?php echo $restaurant['review_count'] ?? 0; ?></td>
                    <td>
                        <?php if ($restaurant['is_featured']): ?>
                            <span class="badge bg-success">Featured</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Featured</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="edit_restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>">
                                    <i class="fas fa-edit me-2 text-primary"></i> Edit
                                </a></li>
                                <li><a class="dropdown-item" href="../restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>" target="_blank">
                                    <i class="fas fa-eye me-2 text-info"></i> View
                                </a></li>
                                <li><a class="dropdown-item" href="menus.php?restaurant_id=<?php echo $restaurant['restaurant_id']; ?>">
                                    <i class="fas fa-utensils me-2 text-success"></i> Manage Menu
                                </a></li>
                                <li>
                                    <?php if ($restaurant['is_featured']): ?>
                                        <a class="dropdown-item" href="?action=feature&id=<?php echo $restaurant['restaurant_id']; ?>&feature=0">
                                            <i class="fas fa-star me-2 text-warning"></i> Unfeature
                                        </a>
                                    <?php else: ?>
                                        <a class="dropdown-item" href="?action=feature&id=<?php echo $restaurant['restaurant_id']; ?>&feature=1">
                                            <i class="far fa-star me-2 text-warning"></i> Feature
                                        </a>
                                    <?php endif; ?>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $restaurant['restaurant_id']; ?>" data-confirm="Are you sure you want to delete this restaurant? This action cannot be undone.">
                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                </a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Initialize DataTable with custom options
$(document).ready(function() {
    $('.data-table table').DataTable({
        responsive: true,
        autoWidth: true,
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search restaurants...",
            lengthMenu: "Show _MENU_ restaurants",
            info: "Showing _START_ to _END_ of _TOTAL_ restaurants",
            infoEmpty: "Showing 0 to 0 of 0 restaurants",
            infoFiltered: "(filtered from _MAX_ total restaurants)"
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 