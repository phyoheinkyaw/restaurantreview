<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include header and database connection
require_once 'includes/header.php';

// Get all restaurants owned by this owner
$sql = "SELECT r.*, 
               (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.restaurant_id) as review_count,
               (SELECT COUNT(*) FROM reservations WHERE restaurant_id = r.restaurant_id AND status != 'cancelled') as reservation_count
        FROM restaurants r
        WHERE r.owner_id = ?
        ORDER BY r.name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner['user_id']);
$stmt->execute();
$restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle restaurant deletion
if (isset($_POST['delete_restaurant'])) {
    $restaurant_id = $_POST['restaurant_id'];
    
    // Delete associated reviews and reservations first
    $sql = "DELETE FROM reviews WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();

    $sql = "DELETE FROM reservations WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete the restaurant
    $sql = "DELETE FROM restaurants WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: restaurants.php");
    exit;
}

// Handle restaurant status update
if (isset($_POST['update_status'])) {
    $restaurant_id = $_POST['restaurant_id'];
    $status = $_POST['status'];
    
    $sql = "UPDATE restaurants SET is_active = ? WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $status, $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: restaurants.php");
    exit;
}

// Handle restaurant feature update
if (isset($_POST['update_featured'])) {
    $restaurant_id = $_POST['restaurant_id'];
    $featured = $_POST['featured'];
    
    $sql = "UPDATE restaurants SET is_featured = ? WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $featured, $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: restaurants.php");
    exit;
}
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">My Restaurants</h5>
                    <a href="add_restaurant.php" class="btn btn-primary">Add New Restaurant</a>
                </div>
                <div class="card-body">
                    <?php if (empty($restaurants)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                            <p class="text-muted">You haven't added any restaurants yet.</p>
                            <p><a href="add_restaurant.php" class="btn btn-outline-primary">Add Your First Restaurant</a></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Cuisine</th>
                                        <th>Location</th>
                                        <th>Reviews</th>
                                        <th>Reservations</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($restaurants as $restaurant): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($restaurant['image'])): ?>
                                                        <img src="../uploads/restaurants/<?php echo htmlspecialchars($restaurant['image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($restaurant['name']); ?>" 
                                                             class="rounded-circle me-2" 
                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($restaurant['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($restaurant['cuisine_type']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($restaurant['address'], 0, 50) . (strlen($restaurant['address']) > 50 ? '...' : '')); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $restaurant['review_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $restaurant['reservation_count']; ?></span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo isset($restaurant['is_active']) ? ($restaurant['is_active'] ? 0 : 1) : 0; ?>">
                                                    <button type="submit" name="update_status" 
                                                            class="btn btn-sm <?php echo isset($restaurant['is_active']) && $restaurant['is_active'] ? 'btn-success' : 'btn-danger'; ?>" 
                                                            title="<?php echo isset($restaurant['is_active']) && $restaurant['is_active'] ? 'Active' : 'Inactive'; ?>">
                                                        <i class="fas <?php echo isset($restaurant['is_active']) && $restaurant['is_active'] ? 'fa-check' : 'fa-times'; ?>"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="edit_restaurant.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this restaurant?');">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                        <button type="submit" name="delete_restaurant" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                        <input type="hidden" name="featured" value="<?php echo $restaurant['is_featured'] ? 0 : 1; ?>">
                                                        <button type="submit" name="update_featured" 
                                                                class="btn btn-sm <?php echo $restaurant['is_featured'] ? 'btn-warning' : 'btn-outline-warning'; ?>" 
                                                                title="<?php echo $restaurant['is_featured'] ? 'Featured' : 'Not Featured'; ?>">
                                                            <i class="fas <?php echo $restaurant['is_featured'] ? 'fa-star' : 'fa-star-o'; ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
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

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Custom JS -->
<script>
$(document).ready(function() {
    $('.table').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search restaurants..."
        }
    });
});
</script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
<!-- AlertifyJS -->
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

<?php include 'includes/footer.php'; ?>