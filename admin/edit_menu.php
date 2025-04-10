<?php
// Include header
include 'includes/header.php';

// Check if menu ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">Menu item ID is required.</div>';
    echo '<a href="menus.php" class="btn btn-primary">Back to Menus</a>';
    include 'includes/footer.php';
    exit;
}

$menu_id = (int)$_GET['id'];

// Get all restaurants for dropdown
$restaurants = [];
$sql = "SELECT restaurant_id, name FROM restaurants ORDER BY name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}

// Get all menu categories for dropdown
$categories = [];
$sql = "SELECT DISTINCT category FROM menus WHERE category IS NOT NULL AND category != '' ORDER BY category";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $restaurant_id = !empty($_POST['restaurant_id']) ? (int)$_POST['restaurant_id'] : 0;
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    // Validate required fields
    $errors = [];
    if (empty($name)) {
        $errors[] = "Menu item name is required.";
    }
    if ($restaurant_id <= 0) {
        $errors[] = "Restaurant is required.";
    }
    if ($price <= 0) {
        $errors[] = "Price must be greater than zero.";
    }
    
    // Get current menu item data for image
    $sql = "SELECT image FROM menus WHERE menu_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $menu_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_menu = $result->fetch_assoc();
    $stmt->close();
    
    // Handle image upload
    $image_path = $current_menu['image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "../uploads/menus/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if (!empty($image_path) && file_exists("../" . $image_path)) {
                    unlink("../" . $image_path);
                }
                $image_path = 'uploads/menus/' . $new_filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image format. Allowed formats: " . implode(', ', $allowed);
        }
    }
    
    // If no errors, update menu item
    if (empty($errors)) {
        $sql = "UPDATE menus SET 
                restaurant_id = ?, name = ?, description = ?, category = ?, 
                price = ?, image = ?, is_available = ?
                WHERE menu_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssdsis", 
            $restaurant_id, $name, $description, $category, 
            $price, $image_path, $is_available, $menu_id
        );
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Menu item updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            $errors[] = "Failed to update menu item: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Display errors
    if (!empty($errors)) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}

// Get menu item data
$sql = "SELECT m.*, r.name as restaurant_name 
        FROM menus m
        JOIN restaurants r ON m.restaurant_id = r.restaurant_id
        WHERE m.menu_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Menu item not found.</div>';
    echo '<a href="menus.php" class="btn btn-primary">Back to Menus</a>';
    include 'includes/footer.php';
    exit;
}

$menu = $result->fetch_assoc();
$stmt->close();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Edit Menu Item</h1>
    <div>
        <a href="menus.php?restaurant_id=<?php echo $menu['restaurant_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Menu
        </a>
    </div>
</div>

<!-- Edit Menu Item Form -->
<div class="card">
    <div class="card-body">
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <!-- Basic Information -->
            <div class="col-12">
                <h5 class="border-bottom pb-2 mb-3">Menu Item Information</h5>
            </div>
            
            <div class="col-md-6">
                <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($menu['name']); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="restaurant_id" class="form-label">Restaurant <span class="text-danger">*</span></label>
                <select class="form-select" id="restaurant_id" name="restaurant_id" required>
                    <option value="">-- Select Restaurant --</option>
                    <?php foreach ($restaurants as $r): ?>
                        <option value="<?php echo $r['restaurant_id']; ?>" <?php echo ($menu['restaurant_id'] == $r['restaurant_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($menu['description'] ?? ''); ?></textarea>
                <div class="form-text">Describe the menu item, including ingredients and preparation method.</div>
            </div>
            
            <div class="col-md-6">
                <label for="category" class="form-label">Category</label>
                <input type="text" class="form-control" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($menu['category'] ?? ''); ?>">
                <datalist id="category-list">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                    <?php endforeach; ?>
                </datalist>
                <div class="form-text">E.g., Appetizers, Main Course, Desserts, Beverages, etc.</div>
            </div>
            
            <div class="col-md-6">
                <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($menu['price']); ?>">
                </div>
            </div>
            
            <div class="col-md-6">
                <label for="image" class="form-label">Item Image</label>
                <?php if (!empty($menu['image'])): ?>
                    <div class="mb-2">
                        <img src="../<?php echo $menu['image']; ?>" alt="<?php echo htmlspecialchars($menu['name']); ?>" class="img-thumbnail" style="max-height: 150px;">
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" id="image" name="image">
                <div class="form-text">Leave empty to keep current image. Recommended size: 800x600 pixels. Max file size: 2MB.</div>
            </div>
            
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="is_available" name="is_available" value="1" <?php echo $menu['is_available'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_available">
                        <i class="fas fa-check-circle me-2 text-success"></i> Item is available for ordering
                    </label>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Update Menu Item
                </button>
                <a href="menus.php?restaurant_id=<?php echo $menu['restaurant_id']; ?>" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add any JavaScript for form validation or dynamic behavior here
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 