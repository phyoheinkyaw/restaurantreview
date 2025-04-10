<?php
// Include header
include 'includes/header.php';

// Check if restaurant ID is provided
$restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

// Process actions (delete, toggle availability)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $menu_id = (int)$_GET['id'];
    
    if ($action === 'delete') {
        // Delete menu item
        $sql = "DELETE FROM menus WHERE menu_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Menu item deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to delete menu item.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    } elseif ($action === 'toggle_availability') {
        // Toggle menu item availability
        $available = ($_GET['available'] == 1) ? 1 : 0;
        $sql = "UPDATE menus SET is_available = ? WHERE menu_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $available, $menu_id);
        
        if ($stmt->execute()) {
            $status = $available ? 'available' : 'unavailable';
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Menu item marked as ' . $status . ' successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to update menu item availability.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    }
}

// Get all restaurants for dropdown
$restaurants = [];
$sql = "SELECT restaurant_id, name FROM restaurants ORDER BY name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}

// Get all menu categories for filter dropdown
$categories = [];
$sql = "SELECT DISTINCT category FROM menus WHERE category IS NOT NULL AND category != '' ORDER BY category";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$available = isset($_GET['available']) ? (int)$_GET['available'] : -1;

// Build query with filters
$query = "SELECT m.*, r.name as restaurant_name 
          FROM menus m
          JOIN restaurants r ON m.restaurant_id = r.restaurant_id
          WHERE 1=1";

$params = [];
$types = "";

if ($restaurant_id > 0) {
    $query .= " AND m.restaurant_id = ?";
    $params[] = $restaurant_id;
    $types .= "i";
}

if (!empty($search)) {
    $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if (!empty($category)) {
    $query .= " AND m.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($available >= 0) {
    $query .= " AND m.is_available = ?";
    $params[] = $available;
    $types .= "i";
}

$query .= " ORDER BY r.name, m.category, m.name";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$menus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get restaurant name if restaurant_id is provided
$restaurant_name = '';
if ($restaurant_id > 0) {
    $sql = "SELECT name FROM restaurants WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $restaurant_name = $row['name'];
    }
    $stmt->close();
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">
        <?php if (!empty($restaurant_name)): ?>
            Manage Menu for <?php echo htmlspecialchars($restaurant_name); ?>
        <?php else: ?>
            Manage All Menus
        <?php endif; ?>
    </h1>
    <div>
        <?php if ($restaurant_id > 0): ?>
            <a href="add_menu.php?restaurant_id=<?php echo $restaurant_id; ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add Menu Item
            </a>
            <a href="restaurants.php" class="btn btn-secondary ms-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Restaurants
            </a>
        <?php else: ?>
            <a href="add_menu.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add Menu Item
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <?php if ($restaurant_id > 0): ?>
                <input type="hidden" name="restaurant_id" value="<?php echo $restaurant_id; ?>">
            <?php else: ?>
                <div class="col-md-3">
                    <label for="restaurant_id" class="form-label">Restaurant</label>
                    <select class="form-select" id="restaurant_id" name="restaurant_id">
                        <option value="">All Restaurants</option>
                        <?php foreach ($restaurants as $r): ?>
                            <option value="<?php echo $r['restaurant_id']; ?>" <?php echo ($restaurant_id == $r['restaurant_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search menu items..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($category === $c) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="available" class="form-label">Availability</label>
                <select class="form-select" id="available" name="available">
                    <option value="-1" <?php echo ($available === -1) ? 'selected' : ''; ?>>All</option>
                    <option value="1" <?php echo ($available === 1) ? 'selected' : ''; ?>>Available</option>
                    <option value="0" <?php echo ($available === 0) ? 'selected' : ''; ?>>Unavailable</option>
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

<!-- Menu Items Table -->
<div class="data-table">
    <div class="data-table-header d-flex justify-content-between align-items-center">
        <h5 class="data-table-title">Menu Items</h5>
        <span class="badge bg-primary"><?php echo count($menus); ?> items</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Item</th>
                    <?php if ($restaurant_id == 0): ?>
                        <th>Restaurant</th>
                    <?php endif; ?>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($menus)): ?>
                <tr>
                    <td colspan="<?php echo ($restaurant_id == 0) ? 6 : 5; ?>" class="text-center">No menu items found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($menus as $menu): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php if (!empty($menu['image'])): ?>
                                <img src="../<?php echo $menu['image']; ?>" alt="<?php echo htmlspecialchars($menu['name']); ?>" class="rounded me-2" width="40" height="40" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center bg-secondary text-white rounded me-2" style="width: 40px; height: 40px;">
                                    <i class="fas fa-utensils"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($menu['name']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($menu['description'] ?? '', 0, 50) . (strlen($menu['description'] ?? '') > 50 ? '...' : '')); ?></small>
                            </div>
                        </div>
                    </td>
                    <?php if ($restaurant_id == 0): ?>
                        <td><?php echo htmlspecialchars($menu['restaurant_name']); ?></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($menu['category'] ?? 'Uncategorized'); ?></td>
                    <td><?php echo '$' . number_format($menu['price'], 2); ?></td>
                    <td>
                        <?php if ($menu['is_available']): ?>
                            <span class="badge bg-success">Available</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Unavailable</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="edit_menu.php?id=<?php echo $menu['menu_id']; ?>">
                                    <i class="fas fa-edit me-2 text-primary"></i> Edit
                                </a></li>
                                <li>
                                    <?php if ($menu['is_available']): ?>
                                        <a class="dropdown-item" href="?action=toggle_availability&id=<?php echo $menu['menu_id']; ?>&available=0<?php echo $restaurant_id ? '&restaurant_id=' . $restaurant_id : ''; ?>">
                                            <i class="fas fa-eye-slash me-2 text-warning"></i> Mark as Unavailable
                                        </a>
                                    <?php else: ?>
                                        <a class="dropdown-item" href="?action=toggle_availability&id=<?php echo $menu['menu_id']; ?>&available=1<?php echo $restaurant_id ? '&restaurant_id=' . $restaurant_id : ''; ?>">
                                            <i class="fas fa-eye me-2 text-success"></i> Mark as Available
                                        </a>
                                    <?php endif; ?>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $menu['menu_id']; ?><?php echo $restaurant_id ? '&restaurant_id=' . $restaurant_id : ''; ?>" data-confirm="Are you sure you want to delete this menu item? This action cannot be undone.">
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

<!-- Menu Stats -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-primary-light text-primary mb-3">
                <i class="fas fa-utensils"></i>
            </div>
            <h6 class="card-title">Total Menu Items</h6>
            <h2 class="card-value"><?php echo count($menus); ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-success-light text-success mb-3">
                <i class="fas fa-check-circle"></i>
            </div>
            <h6 class="card-title">Available Items</h6>
            <h2 class="card-value">
                <?php 
                $available_count = 0;
                foreach ($menus as $m) {
                    if ($m['is_available']) $available_count++;
                }
                echo $available_count;
                ?>
            </h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="dashboard-card">
            <div class="card-icon bg-info-light text-info mb-3">
                <i class="fas fa-tags"></i>
            </div>
            <h6 class="card-title">Categories</h6>
            <h2 class="card-value"><?php echo count($categories); ?></h2>
        </div>
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
            searchPlaceholder: "Search menu items...",
            lengthMenu: "Show _MENU_ items",
            info: "Showing _START_ to _END_ of _TOTAL_ items",
            infoEmpty: "Showing 0 to 0 of 0 items",
            infoFiltered: "(filtered from _MAX_ total items)"
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 