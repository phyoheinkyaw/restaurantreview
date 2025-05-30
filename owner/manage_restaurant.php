<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Check if we're setting a specific restaurant from the URL
if (isset($_GET['set_restaurant']) && is_numeric($_GET['set_restaurant'])) {
    $_SESSION['current_restaurant_id'] = (int)$_GET['set_restaurant'];
}

// Include database connection and header
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Get restaurant ID from session
if (!isset($_SESSION['current_restaurant_id'])) {
    header("Location: restaurants.php");
    exit;
}

$restaurant_id = $_SESSION['current_restaurant_id'];

// Get restaurant information
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$restaurant) {
    // If restaurant doesn't exist or doesn't belong to owner, clear session and redirect
    unset($_SESSION['current_restaurant_id']);
    header("Location: restaurants.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle menu item updates
    if (isset($_POST['update_menu'])) {
        $menu_id = $_POST['menu_id'];
        $menu_name = $_POST['menu_name'];
        $menu_price = $_POST['menu_price'];
        $menu_description = $_POST['menu_description'];
        $menu_category = $_POST['menu_category'];
        $menu_available = isset($_POST['menu_available']) ? 1 : 0;
        
        // Get current menu item to check for existing image
        $sql = "SELECT image FROM menus WHERE menu_id = ? AND restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $menu_id, $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_menu = $result->fetch_assoc();
        $stmt->close();
        
        // Handle menu image upload
        $menu_image = $current_menu['image'] ?? ''; // Keep existing image by default
        if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['menu_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Get file extension
                $file_extension = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
                
                // Generate filename based on menu name and restaurant ID
                $base_filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\s]/', '', $menu_name)));
                $random_suffix = mt_rand(1000, 9999); // 4-digit random number
                $file_name = 'menu_' . $restaurant_id . '_' . $base_filename . '_' . $random_suffix . '.' . $file_extension;
                
                $upload_dir = '../uploads/menus/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $upload_path)) {
                    // Delete previous image if it exists
                    if (!empty($current_menu['image'])) {
                        $previous_image_path = $upload_dir . $current_menu['image'];
                        if (file_exists($previous_image_path)) {
                            unlink($previous_image_path);
                        }
                    }
                    
                    $menu_image = $file_name;
                }
            }
        }
        
        $sql = "UPDATE menus SET 
                name = ?, 
                price = ?, 
                description = ?, 
                category = ?, 
                image = ?,
                is_available = ? 
                WHERE menu_id = ? AND restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdsssiii", $menu_name, $menu_price, $menu_description, $menu_category, $menu_image, $menu_available, $menu_id, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Menu item updated successfully!";
    }
    
    // Handle add menu item
    if (isset($_POST['add_menu'])) {
        $menu_name = $_POST['menu_name'];
        $menu_price = $_POST['menu_price'];
        $menu_description = $_POST['menu_description'] ?? '';
        $menu_category = $_POST['menu_category'];
        $menu_available = isset($_POST['menu_available']) ? 1 : 0;
        
        // Handle menu image upload
        $menu_image = '';
        if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['menu_image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Get file extension
                $file_extension = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
                
                // Generate filename based on menu name and restaurant ID
                $base_filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\s]/', '', $menu_name)));
                $random_suffix = mt_rand(1000, 9999); // 4-digit random number
                $file_name = 'menu_' . $restaurant_id . '_' . $base_filename . '_' . $random_suffix . '.' . $file_extension;
                
                $upload_dir = '../uploads/menus/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $upload_path)) {
                    $menu_image = $file_name;
                }
            }
        }
        
        $sql = "INSERT INTO menus (restaurant_id, name, price, description, category, image, is_available) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isdsssi", $restaurant_id, $menu_name, $menu_price, $menu_description, $menu_category, $menu_image, $menu_available);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Menu item added successfully!";
    }
    
    // Handle restaurant settings updates
    if (isset($_POST['update_settings'])) {
        $name = $_POST['name'];
        $cuisine_type = $_POST['cuisine_type'];
        $address = $_POST['address'];
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $website = $_POST['website'];
        $description = $_POST['description'];
        $status = isset($_POST['is_active']) ? 1 : 0;
        $price_range = $_POST['price_range'];
        
        // Features
        $has_parking = isset($_POST['has_parking']) ? 1 : 0;
        $is_wheelchair_accessible = isset($_POST['is_wheelchair_accessible']) ? 1 : 0;
        $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
        
        // Handle image upload
        $image = $restaurant['image']; // Keep existing image by default
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Get file extension
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                
                // Generate filename based on restaurant name
                $base_filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\s]/', '', $name)));
                $random_suffix = mt_rand(1000, 9999); // 4-digit random number
                $file_name = $base_filename . '_' . $random_suffix . '.' . $file_extension;
                
                $upload_dir = '../uploads/restaurants/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete previous image if it exists
                    if (!empty($restaurant['image']) && $restaurant['image'] !== $file_name) {
                        $previous_image_path = $upload_dir . $restaurant['image'];
                        if (file_exists($previous_image_path)) {
                            unlink($previous_image_path);
                        }
                    }
                    
                    $image = $file_name;
                }
            }
        }
        
        $sql = "UPDATE restaurants SET 
                name = ?, 
                cuisine_type = ?, 
                address = ?,
                latitude = ?,
                longitude = ?, 
                phone = ?, 
                email = ?, 
                website = ?, 
                description = ?,
                image = ?, 
                is_active = ?,
                price_range = ?,
                has_parking = ?,
                is_wheelchair_accessible = ?,
                has_wifi = ?
                WHERE restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssddsssssisiiii", $name, $cuisine_type, $address, $latitude, $longitude, $phone, $email, $website, $description, $image, $status, $price_range, $has_parking, $is_wheelchair_accessible, $has_wifi, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Restaurant settings updated successfully!";
    }
    
    // Handle opening hours updates
    if (isset($_POST['update_hours'])) {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        $hours = [];
        foreach ($days as $day) {
            $hours[$day] = [
                'open' => $_POST[$day . '_open'] ?? '',
                'close' => $_POST[$day . '_close'] ?? ''
            ];
        }
        
        $sql = "UPDATE restaurants SET 
                opening_hours = ? 
                WHERE restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $hours_json = json_encode($hours);
        $stmt->bind_param("si", $hours_json, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Opening hours updated successfully!";
    }
    
    header("Location: manage_restaurant.php");
    exit;
}

// Get current page from URL parameter
$page = isset($_GET['menu_page']) ? (int)$_GET['menu_page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get total number of menu items for pagination
$sql = "SELECT COUNT(*) as total FROM menus WHERE restaurant_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_items / $items_per_page);

// Get menu items with pagination
$sql = "SELECT * FROM menus WHERE restaurant_id = ? ORDER BY category, name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $restaurant_id, $items_per_page, $offset);
$stmt->execute();
$menu_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Extract unique categories from menu items
$categories = [];
foreach ($menu_items as $item) {
    if (!empty($item['category']) && !in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
}
sort($categories); // Sort categories alphabetically

// Get opening hours from restaurant JSON field
$opening_hours = json_decode($restaurant['opening_hours'], true);

// Include header (already included at the top)
?>

<div class="container-fluid p-4">
    <!-- Status message if any -->
    <?php if (isset($_SESSION['status_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['status_message'];
            unset($_SESSION['status_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Restaurant Info Column -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Restaurant Settings</h5>
                    <a href="restaurants.php" class="btn btn-secondary">Back to Restaurants</a>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_settings" value="1">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restaurant Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($restaurant['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cuisine Type</label>
                                <input type="text" class="form-control" name="cuisine_type" value="<?php echo htmlspecialchars($restaurant['cuisine_type']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($restaurant['address']); ?></textarea>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($restaurant['phone']); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($restaurant['email']); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website" value="<?php echo htmlspecialchars($restaurant['website'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($restaurant['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price Range</label>
                                <select class="form-select" name="price_range" required>
                                    <option value="$" <?php echo ($restaurant['price_range'] === '$') ? 'selected' : ''; ?>>$ (Inexpensive)</option>
                                    <option value="$$" <?php echo ($restaurant['price_range'] === '$$') ? 'selected' : ''; ?>>$$ (Moderate)</option>
                                    <option value="$$$" <?php echo ($restaurant['price_range'] === '$$$') ? 'selected' : ''; ?>>$$$ (Expensive)</option>
                                    <option value="$$$$" <?php echo ($restaurant['price_range'] === '$$$$') ? 'selected' : ''; ?>>$$$$ (Very Expensive)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Features</label>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="has_parking" name="has_parking" <?php echo ($restaurant['has_parking'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_parking">Has Parking</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="is_wheelchair_accessible" name="is_wheelchair_accessible" <?php echo ($restaurant['is_wheelchair_accessible'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_wheelchair_accessible">Wheelchair Accessible</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" id="has_wifi" name="has_wifi" <?php echo ($restaurant['has_wifi'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_wifi">Free WiFi</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Restaurant Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                                <div class="form-text">Upload a high-quality image (JPG, JPEG, PNG only)</div>
                                <div id="imagePreview" class="mt-2">
                                    <?php if (!empty($restaurant['image'])): ?>
                                    <img src="../uploads/restaurants/<?php echo htmlspecialchars($restaurant['image']); ?>" alt="Restaurant Image" class="img-thumbnail" style="max-height: 200px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Latitude</label>
                                        <input type="number" step="any" class="form-control" name="latitude" value="<?php echo htmlspecialchars($restaurant['latitude'] ?? ''); ?>">
                                        <div class="form-text">Decimal format (e.g., 40.7128)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Longitude</label>
                                        <input type="number" step="any" class="form-control" name="longitude" value="<?php echo htmlspecialchars($restaurant['longitude'] ?? ''); ?>">
                                        <div class="form-text">Decimal format (e.g., -74.0060)</div>
                                    </div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="status" 
                                           <?php echo ($restaurant['is_active'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">Active Status</label>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Menu Management Column -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Menu Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['price']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-menu-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editMenuModal" 
                                                data-menu-item='<?php echo json_encode($item); ?>'>
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="my-3">
                        <nav aria-label="Menu pagination">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="manage_restaurant.php?menu_page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="manage_restaurant.php?menu_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="manage_restaurant.php?menu_page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                        Add Menu Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Menu Modal -->
    <div class="modal fade" id="addMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_menu" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Menu Name</label>
                            <input type="text" class="form-control" name="menu_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" class="form-control" name="menu_price" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="menu_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="addMenuCategory" name="menu_category" required>
                                <option value="" disabled selected>Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Add New Category</option>
                            </select>
                            <div id="addNewCategoryGroup" class="mt-2 d-none">
                                <input type="text" class="form-control" id="addNewCategory" placeholder="Enter new category name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="menu_image" accept="image/*" onchange="previewMenuImage(this, 'addMenuImagePreview')">
                            <div id="addMenuImagePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="menu_available" id="menuAvailable" checked>
                            <label class="form-check-label" for="menuAvailable">Available</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Add Menu Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Menu Modal -->
    <div class="modal fade" id="editMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_menu" value="1">
                        <input type="hidden" id="menuId" name="menu_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Menu Name</label>
                            <input type="text" class="form-control" id="menuName" name="menu_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" class="form-control" id="menuPrice" name="menu_price" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="menuDescription" name="menu_description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="editMenuCategory" name="menu_category" required>
                                <option value="" disabled>Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Add New Category</option>
                            </select>
                            <div id="editNewCategoryGroup" class="mt-2 d-none">
                                <input type="text" class="form-control" id="editNewCategory" placeholder="Enter new category name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="menu_image" accept="image/*" onchange="previewMenuImage(this, 'menuImagePreview')">
                            <div id="menuImagePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="menuAvailableEdit" name="menu_available">
                            <label class="form-check-label" for="menuAvailableEdit">Available</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Menu Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Opening Hours Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Opening Hours</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="hoursForm">
                        <input type="hidden" name="update_hours" value="1">
                        
                        <div class="row">
                            <?php 
                            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                            foreach ($days as $day): 
                                $hours = $opening_hours[$day] ?? ['open' => '', 'close' => ''];
                            ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><?php echo ucfirst($day); ?></label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="time" class="form-control" name="<?php echo $day; ?>_open" 
                                               value="<?php echo $hours['open']; ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="time" class="form-control" name="<?php echo $day; ?>_close" 
                                               value="<?php echo $hours['close']; ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Hours</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editMenu(item) {
    console.log("Edit menu item:", item); // For debugging
    
    document.getElementById('menuId').value = item.menu_id;
    document.getElementById('menuName').value = item.name;
    document.getElementById('menuPrice').value = item.price;
    document.getElementById('menuDescription').value = item.description || '';
    
    // Handle category selection
    const categorySelect = document.getElementById('editMenuCategory');
    const newCategoryGroup = document.getElementById('editNewCategoryGroup');
    const newCategoryInput = document.getElementById('editNewCategory');
    
    // Try to find the category in the dropdown
    let categoryFound = false;
    for (let i = 0; i < categorySelect.options.length; i++) {
        if (categorySelect.options[i].value === item.category) {
            categorySelect.selectedIndex = i;
            categoryFound = true;
            break;
        }
    }
    
    // If category not found, select "Add New" and populate the input
    if (!categoryFound && item.category) {
        // Find the "Add New Category" option
        for (let i = 0; i < categorySelect.options.length; i++) {
            if (categorySelect.options[i].value === 'new') {
                categorySelect.selectedIndex = i;
                break;
            }
        }
        
        // Show and populate the new category input
        newCategoryGroup.classList.remove('d-none');
        newCategoryInput.value = item.category;
        newCategoryInput.name = 'menu_category';
        categorySelect.name = '';
    }
    
    document.getElementById('menuAvailableEdit').checked = item.is_available == 1;
    
    // Show image preview if exists
    const previewDiv = document.getElementById('menuImagePreview');
    previewDiv.innerHTML = '';
    if (item.image) {
        const img = document.createElement('img');
        img.src = '../uploads/menus/' + item.image;
        img.alt = item.name;
        img.className = 'img-thumbnail';
        img.style.maxHeight = '200px';
        previewDiv.appendChild(img);
    }
}

// Image preview functionality
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Clear existing content
            preview.innerHTML = '';
            
            // Create and add new image preview
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-thumbnail';
            img.style.maxHeight = '200px';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Menu image preview functionality
function previewMenuImage(input, previewId) {
    const preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            // Clear existing content
            preview.innerHTML = '';
            
            // Create and add new image preview
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'img-thumbnail';
            img.style.maxHeight = '200px';
            preview.appendChild(img);
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Handle the category dropdowns
document.addEventListener('DOMContentLoaded', function() {
    // Add Menu category dropdown
    const addMenuCategory = document.getElementById('addMenuCategory');
    const addNewCategoryGroup = document.getElementById('addNewCategoryGroup');
    const addNewCategory = document.getElementById('addNewCategory');
    
    if (addMenuCategory) {
        addMenuCategory.addEventListener('change', function() {
            if (this.value === 'new') {
                addNewCategoryGroup.classList.remove('d-none');
                addNewCategory.setAttribute('required', 'required');
                addNewCategory.name = 'menu_category';
                this.name = ''; // Remove the name attribute from the select
            } else {
                addNewCategoryGroup.classList.add('d-none');
                addNewCategory.removeAttribute('required');
                addNewCategory.name = '';
                this.name = 'menu_category'; // Restore the name attribute to the select
            }
        });
    }
    
    // Edit Menu category dropdown
    const editMenuCategory = document.getElementById('editMenuCategory');
    const editNewCategoryGroup = document.getElementById('editNewCategoryGroup');
    const editNewCategory = document.getElementById('editNewCategory');
    
    if (editMenuCategory) {
        editMenuCategory.addEventListener('change', function() {
            if (this.value === 'new') {
                editNewCategoryGroup.classList.remove('d-none');
                editNewCategory.setAttribute('required', 'required');
                editNewCategory.name = 'menu_category';
                this.name = ''; // Remove the name attribute from the select
            } else {
                editNewCategoryGroup.classList.add('d-none');
                editNewCategory.removeAttribute('required');
                editNewCategory.name = '';
                this.name = 'menu_category'; // Restore the name attribute to the select
            }
        });
    }
    
    // Setup Edit Menu buttons
    const editButtons = document.querySelectorAll('.edit-menu-btn');
    editButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const menuItemData = this.getAttribute('data-menu-item');
            if (menuItemData) {
                try {
                    const menuItem = JSON.parse(menuItemData);
                    editMenu(menuItem);
                } catch (e) {
                    console.error('Error parsing menu item data:', e);
                }
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
<?php 
// Flush the output buffer
ob_end_flush(); 
?>
