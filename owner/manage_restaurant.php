<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);


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
        
        $sql = "UPDATE menus SET 
                name = ?, 
                price = ?, 
                description = ?, 
                category = ?, 
                is_available = ? 
                WHERE menu_id = ? AND restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sdssiii", $menu_name, $menu_price, $menu_description, $menu_category, $menu_available, $menu_id, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Menu item updated successfully!";
    }
    
    // Handle restaurant settings updates
    if (isset($_POST['update_settings'])) {
        $name = $_POST['name'];
        $cuisine_type = $_POST['cuisine_type'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $website = $_POST['website'];
        $description = $_POST['description'];
        $status = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "UPDATE restaurants SET 
                name = ?, 
                cuisine_type = ?, 
                address = ?, 
                phone = ?, 
                email = ?, 
                website = ?, 
                description = ?, 
                is_active = ? 
                WHERE restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $name, $cuisine_type, $address, $phone, $email, $website, $description, $status, $restaurant_id);
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
        $stmt->bind_param("si", json_encode($hours), $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['status_message'] = "Opening hours updated successfully!";
    }
    
    header("Location: manage_restaurant.php");
    exit;
}

// Get menu items
$sql = "SELECT * FROM menus WHERE restaurant_id = ? ORDER BY category, name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$menu_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="status" 
                                   <?php echo ($restaurant['is_active'] ?? false) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Active Status</label>
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
                                        <button class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editMenuModal" 
                                                onclick="editMenu(<?php echo json_encode($item); ?>)">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
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
                            <input type="text" class="form-control" name="menu_category" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="menu_image" accept="image/*">
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
                            <input type="text" class="form-control" id="menuCategory" name="menu_category" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="menu_image" accept="image/*">
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
    document.getElementById('menuId').value = item.menu_id;
    document.getElementById('menuName').value = item.name;
    document.getElementById('menuPrice').value = item.price;
    document.getElementById('menuDescription').value = item.description;
    document.getElementById('menuCategory').value = item.category;
    document.getElementById('menuAvailableEdit').checked = item.is_available;
    
    // Show image preview if exists
    const previewDiv = document.getElementById('menuImagePreview');
    previewDiv.innerHTML = '';
    if (item.image) {
        const img = document.createElement('img');
        img.src = '../uploads/menus/' + item.image;
        img.style.maxWidth = '100px';
        previewDiv.appendChild(img);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
