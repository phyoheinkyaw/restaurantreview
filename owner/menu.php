<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Handle restaurant selection
if (isset($_GET['restaurant_id'])) {
    $_SESSION['current_restaurant_id'] = intval($_GET['restaurant_id']);
    header("Location: menu.php");
    exit;
}

// Check if restaurant is selected
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
    unset($_SESSION['current_restaurant_id']);
    header("Location: restaurants.php");
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $image = $_FILES['image']['name'] ?? '';
        
        // Handle image upload
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/menus/";
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = basename($_FILES["image"]["name"]);
            }
        }
        
        $sql = "INSERT INTO menus (restaurant_id, name, description, category, price, image, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssds", $restaurant_id, $name, $description, $category, $price, $image);
        $stmt->execute();
        $stmt->close();
        
        header("Location: menu.php");
        exit;
    }
    
    if (isset($_POST['update_menu'])) {
        $menu_id = $_POST['menu_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $image = $_POST['existing_image'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // Handle new image upload
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/menus/";
            $target_file = $target_dir . basename($_FILES["image"]["name"]);
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                $image = basename($_FILES["image"]["name"]);
            }
        }
        
        $sql = "UPDATE menus SET 
                name = ?, 
                description = ?, 
                category = ?, 
                price = ?, 
                image = ?,
                is_available = ? 
                WHERE menu_id = ? AND restaurant_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssdssii", $name, $description, $category, $price, $image, $is_available, $menu_id, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: menu.php");
        exit;
    }
    
    if (isset($_POST['delete_menu'])) {
        $menu_id = $_POST['menu_id'];
        
        // Delete the menu item
        $sql = "DELETE FROM menus WHERE menu_id = ? AND restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $menu_id, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: menu.php");
        exit;
    }
}

// Get all menu items for the current restaurant
$sql = "SELECT * FROM menus WHERE restaurant_id = ? ORDER BY category, name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$menu_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all categories (for filtering)
$sql = "SELECT DISTINCT category FROM menus WHERE restaurant_id = ? ORDER BY category";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Menu Management - <?php echo htmlspecialchars($restaurant['name']); ?></h2>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Add New Menu Item</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category" required>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category['category']); ?>">
                                            <?php echo htmlspecialchars($category['category']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" class="form-control" name="price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="add_menu" class="btn btn-primary">Add Menu Item</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Menu Items</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menu_items as $item): ?>
                                <tr>
                                    <td>
                                        <img src="../uploads/menus/<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="img-thumbnail" style="max-width: 100px;">
                                    </td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $item['is_available'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $item['is_available'] ? 'Available' : 'Not Available'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-menu" 
                                                data-id="<?php echo $item['menu_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                                data-price="<?php echo $item['price']; ?>"
                                                data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                data-image="<?php echo htmlspecialchars($item['image']); ?>"
                                                data-status="<?php echo $item['is_available']; ?>">
                                            Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this menu item?')">
                                            <input type="hidden" name="menu_id" value="<?php echo $item['menu_id']; ?>">
                                            <button type="submit" name="delete_menu" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
                <form id="editMenuForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="menu_id" id="menu_id">
                    <input type="hidden" name="existing_image" id="existing_image">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" id="edit_category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['category']); ?>">
                                        <?php echo htmlspecialchars($category['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" class="form-control" name="price" id="edit_price" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div class="mt-2">
                                <img id="current_image" src="" alt="Current Image" class="img-thumbnail" style="max-width: 100px; display: none;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="is_available" id="edit_status">
                        <label class="form-check-label" for="edit_status">Available</label>
                    </div>
                    
                    <button type="submit" name="update_menu" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Edit menu item modal handler
    $('.edit-menu').click(function() {
        const menuId = $(this).data('id');
        const name = $(this).data('name');
        const category = $(this).data('category');
        const price = $(this).data('price');
        const description = $(this).data('description');
        const image = $(this).data('image');
        const status = $(this).data('status');
        
        $('#menu_id').val(menuId);
        $('#existing_image').val(image);
        $('#edit_name').val(name);
        $('#edit_category').val(category);
        $('#edit_price').val(price);
        $('#edit_description').val(description);
        $('#edit_status').prop('checked', status === '1');
        
        const currentImage = $('#current_image');
        currentImage.attr('src', '../uploads/menus/' + image);
        currentImage.show();
        
        $('#editMenuModal').modal('show');
    });
});
</script>
