<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent "headers already sent" errors
ob_start();

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

// Initialize variables for the form
$edit_mode = false;
$menu_item = [
    'menu_id' => '',
    'name' => '',
    'description' => '',
    'category' => '',
    'price' => '',
    'image' => '',
    'is_available' => 1
];

// Check if we're in edit mode
if (isset($_GET['edit']) && !empty($_GET['menu_id'])) {
    $edit_mode = true;
    $menu_id = intval($_GET['menu_id']);
    
    // Get the menu item data
    $sql = "SELECT * FROM menus WHERE menu_id = ? AND restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $menu_id, $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $menu_item = $result->fetch_assoc();
    } else {
        // Menu item not found or doesn't belong to this restaurant
        $_SESSION['error_message'] = "Menu item not found.";
        header("Location: menu.php");
        exit;
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu']) || isset($_POST['update_menu'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $category = $_POST['category'];
        if ($category === 'new' && !empty($_POST['new_category'])) {
            $category = $_POST['new_category'];
        }
        $price = $_POST['price'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        // If updating, get the current image
        $current_image = '';
        if (isset($_POST['update_menu'])) {
            $menu_id = intval($_POST['menu_id']);
            
            // Get current menu item to check for existing image
            $sql = "SELECT image FROM menus WHERE menu_id = ? AND restaurant_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $menu_id, $restaurant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_menu = $result->fetch_assoc();
            $stmt->close();
            
            $current_image = $current_menu['image'] ?? '';
        }
        
        // Handle menu image upload
        $image = $current_image; // Keep existing image by default for updates
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                // Get file extension
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                
                // Generate filename based on menu name and restaurant ID
                $base_filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\s]/', '', $name)));
                $random_suffix = mt_rand(1000, 9999); // 4-digit random number
                $file_name = 'menu_' . $restaurant_id . '_' . $base_filename . '_' . $random_suffix . '.' . $file_extension;
                
                $upload_dir = '../uploads/menus/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete previous image if it exists (for updates)
                    if (!empty($current_image)) {
                        $previous_image_path = $upload_dir . $current_image;
                        if (file_exists($previous_image_path)) {
                            unlink($previous_image_path);
                        }
                    }
                    
                    $image = $file_name;
                }
            }
        }
        
        if (isset($_POST['add_menu'])) {
            // Insert new menu item
            $sql = "INSERT INTO menus (restaurant_id, name, description, category, price, image, is_available) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssdssi", $restaurant_id, $name, $description, $category, $price, $image, $is_available);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = "Menu item added successfully!";
        } else {
            // Update existing menu item
            $sql = "UPDATE menus SET 
                    name = ?, 
                    description = ?, 
                    category = ?, 
                    price = ?, 
                    image = ?,
                    is_available = ? 
                    WHERE menu_id = ? AND restaurant_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdsiii", $name, $description, $category, $price, $image, $is_available, $menu_id, $restaurant_id);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['success_message'] = "Menu item updated successfully!";
        }
        
        header("Location: menu.php");
        exit;
    }
    
    if (isset($_POST['delete_menu'])) {
        // Capture menu name for the success message
        $menu_id = intval($_POST['menu_id']);
        
        // Get menu item info to delete the file and use the name in the success message
        $sql = "SELECT name, image FROM menus WHERE menu_id = ? AND restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $menu_id, $restaurant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $menu_item = $result->fetch_assoc();
        $stmt->close();
        
        if (!$menu_item) {
            $_SESSION['error_message'] = "Menu item not found.";
            header("Location: menu.php");
            exit;
        }
        
        $menu_name = $menu_item['name'];
        
        // Delete the image file if it exists
        if (!empty($menu_item['image'])) {
            $image_path = "../uploads/menus/" . $menu_item['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // Delete the menu item
        $sql = "DELETE FROM menus WHERE menu_id = ? AND restaurant_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $menu_id, $restaurant_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Menu item \"" . htmlspecialchars($menu_name) . "\" deleted successfully!";
            } else {
                $_SESSION['error_message'] = "No menu item was deleted. It may have been removed already.";
            }
        } else {
            $_SESSION['error_message'] = "Error deleting menu item: " . $stmt->error;
        }
        
        $stmt->close();
        
        header("Location: menu.php");
        exit;
    }
}

// Get current page from URL parameter
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
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

// Get all menu items for the current restaurant (without pagination for DataTables)
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
$result = $stmt->get_result();
$categories = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['category'])) {
        $categories[] = $row['category'];
    }
}
$stmt->close();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Menu Management - <?php echo htmlspecialchars($restaurant['name']); ?></h2>
            
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $edit_mode ? 'Edit Menu Item' : 'Add New Menu Item'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="menuForm">
                        <?php if ($edit_mode): ?>
                        <input type="hidden" name="update_menu" value="1">
                        <input type="hidden" name="menu_id" value="<?php echo $menu_item['menu_id']; ?>">
                        <?php else: ?>
                        <input type="hidden" name="add_menu" value="1">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($menu_item['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="menuCategory" name="category" required>
                                    <option value="" disabled <?php echo empty($menu_item['category']) ? 'selected' : ''; ?>>Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($menu_item['category'] === $category) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="new">+ Add New Category</option>
                                </select>
                                <div id="newCategoryGroup" class="mt-2 d-none">
                                    <input type="text" class="form-control" id="newCategory" name="new_category" placeholder="Enter new category name">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" class="form-control" name="price" value="<?php echo htmlspecialchars($menu_item['price']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image" accept="image/*" id="menuImage">
                                <div class="form-text">Upload a high-quality image (JPG, JPEG, PNG only)</div>
                                <div id="imagePreview" class="mt-2 <?php echo empty($menu_item['image']) ? 'd-none' : ''; ?>">
                                    <?php if (!empty($menu_item['image'])): ?>
                                    <img src="../uploads/menus/<?php echo htmlspecialchars($menu_item['image']); ?>" 
                                         alt="Menu Image" class="img-thumbnail" style="max-height: 200px;">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($menu_item['description']); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_available" id="menuAvailable" <?php echo $menu_item['is_available'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="menuAvailable">
                                        Available
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_mode ? 'Update Menu Item' : 'Add Menu Item'; ?>
                            </button>
                            
                            <?php if ($edit_mode): ?>
                            <a href="menu.php" class="btn btn-secondary">Cancel Update</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Menu Items</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($menu_items)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                        <p class="text-muted">You haven't added any menu items yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="menuTable">
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
                                        <?php if (!empty($item['image'])): ?>
                                        <img src="../uploads/menus/<?php echo htmlspecialchars($item['image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             class="img-thumbnail" style="max-width: 60px;">
                                        <?php else: ?>
                                        <span class="text-muted">No image</span>
                                        <?php endif; ?>
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
                                        <a href="menu.php?edit=1&menu_id=<?php echo $item['menu_id']; ?>" class="btn btn-sm btn-primary me-2">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" action="menu.php" style="display: inline;" class="delete-form">
                                            <input type="hidden" name="menu_id" value="<?php echo $item['menu_id']; ?>">
                                            <input type="hidden" name="delete_menu" value="1">
                                            <button type="submit" class="btn btn-sm btn-danger delete-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
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

<!-- Add required CSS for DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
<!-- AlertifyJS -->
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#menuTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search menu items..."
        },
        columnDefs: [
            { orderable: false, targets: [0, 5] } // Disable sorting on image and actions columns
        ]
    });

    // Handle category dropdown
    $('#menuCategory').change(function() {
        if ($(this).val() === 'new') {
            $('#newCategoryGroup').removeClass('d-none');
            $('#newCategory').prop('required', true);
        } else {
            $('#newCategoryGroup').addClass('d-none');
            $('#newCategory').prop('required', false);
        }
    });

    // Handle image preview
    $('#menuImage').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').removeClass('d-none');
                // If there's an existing image, replace it
                if ($('#imagePreview img').length) {
                    $('#imagePreview img').attr('src', e.target.result);
                } else {
                    // Create a new image element
                    const img = $('<img>')
                        .attr('src', e.target.result)
                        .attr('alt', 'Menu Image Preview')
                        .addClass('img-thumbnail')
                        .css('max-height', '200px');
                    $('#imagePreview').append(img);
                }
            };
            reader.readAsDataURL(file);
        }
    });

    // Handle delete confirmation
    $('.delete-btn').click(function(e) {
        e.preventDefault();
        const form = $(this).closest('form');
        const menuName = $(this).closest('tr').find('td:eq(1)').text().trim();
        
        console.log('Delete clicked for: ' + menuName);
        console.log('Form action: ' + form.attr('action'));
        console.log('Form method: ' + form.attr('method'));
        
        alertify.confirm(
            'Confirm Delete',
            'Are you sure you want to delete "' + menuName + '"?',
            function() {
                console.log('Delete confirmed, submitting form...');
                form.submit();
            },
            function() {
                alertify.error('Delete cancelled');
            }
        );
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<?php 
// Flush the output buffer
ob_end_flush(); 
?>
