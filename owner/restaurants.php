<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'includes/db_connect.php';

// Get owner information (this will also check if user is logged in)
$sql = "SELECT * FROM users WHERE user_id = ? AND role = 'owner'";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../login.php");
    exit;
}

$owner = $result->fetch_assoc();
$stmt->close();

// Helper function to check for pending reservations
function hasPendingReservations($conn, $restaurant_id) {
    $sql = "SELECT COUNT(*) as count FROM reservations 
            WHERE restaurant_id = ? 
            AND status IN ('pending', 'confirmed')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        return false;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] > 0;
}

// Handle restaurant status update
if (isset($_GET['update_status'])) {
    $restaurant_id = $_GET['restaurant_id'];
    $status = $_GET['status'];
    
    // Check for pending reservations
    if (hasPendingReservations($conn, $restaurant_id)) {
        $_SESSION['status_message'] = 'Cannot change status: This restaurant has pending reservations.';
        header("Location: restaurants.php");
        exit;
    }
    
    // Get restaurant name
    $sql_name = "SELECT name FROM restaurants WHERE restaurant_id = ?";
    $stmt_name = $conn->prepare($sql_name);
    
    if (!$stmt_name) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt_name->bind_param("i", $restaurant_id);
    $stmt_name->execute();
    $result = $stmt_name->get_result();
    
    if (!$result) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $restaurant = $result->fetch_assoc();
    $stmt_name->close();
    
    $sql = "UPDATE restaurants SET is_active = ? WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt->bind_param("ii", $status, $restaurant_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['status_message'] = $status ? 
            "Restaurant '{$restaurant['name']}' activated successfully!" : 
            "Restaurant '{$restaurant['name']}' deactivated successfully!";
    } else {
        $_SESSION['status_message'] = 'Failed to update restaurant status.';
    }
    
    $stmt->close();
    header("Location: restaurants.php");
    exit;
}

// Handle restaurant deletion
if (isset($_GET['delete'])) {
    $restaurant_id = $_GET['restaurant_id'];
    
    // Check for pending reservations
    if (hasPendingReservations($conn, $restaurant_id)) {
        $_SESSION['status_message'] = 'Cannot delete: This restaurant has pending reservations.';
        header("Location: restaurants.php");
        exit;
    }
    
    // Get restaurant name for success message
    $sql_name = "SELECT name FROM restaurants WHERE restaurant_id = ?";
    $stmt_name = $conn->prepare($sql_name);
    
    if (!$stmt_name) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt_name->bind_param("i", $restaurant_id);
    $stmt_name->execute();
    $result = $stmt_name->get_result();
    
    if (!$result) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $restaurant = $result->fetch_assoc();
    $stmt_name->close();
    
    // Delete associated reviews first
    $sql = "DELETE FROM reviews WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete associated reservations
    $sql = "DELETE FROM reservations WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete associated waitlist entries
    $sql = "DELETE FROM waitlist WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    $stmt->close();
    
    // Finally delete the restaurant
    $sql = "DELETE FROM restaurants WHERE restaurant_id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['status_message'] = 'Database error: ' . $conn->error;
        header("Location: restaurants.php");
        exit;
    }
    
    $stmt->bind_param("i", $restaurant_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['status_message'] = "Restaurant '{$restaurant['name']}' deleted successfully!";
    } else {
        $_SESSION['status_message'] = 'Failed to delete restaurant.';
    }
    
    $stmt->close();
    header("Location: restaurants.php");
    exit;
}

// Now include header and show the page
require_once 'includes/header.php';

// Get all restaurants owned by this owner
$sql = "SELECT r.*, 
               (SELECT COUNT(*) FROM reviews WHERE restaurant_id = r.restaurant_id) as review_count,
               (SELECT COUNT(*) FROM reservations WHERE restaurant_id = r.restaurant_id AND status != 'cancelled') as reservation_count
        FROM restaurants r
        WHERE r.owner_id = ?
        ORDER BY r.name";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $owner['user_id']);
$stmt->execute();
$restaurants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
                                                <span class="badge bg-info">
                                                    <a href="restaurant_reviews.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                                                       class="text-white text-decoration-none">
                                                        <?php echo $restaurant['review_count']; ?>
                                                    </a>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <a href="restaurant_reservations.php?id=<?php echo $restaurant['restaurant_id']; ?>" 
                                                       class="text-white text-decoration-none">
                                                        <?php echo $restaurant['reservation_count']; ?>
                                                    </a>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="GET" class="d-inline status-form">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo isset($restaurant['is_active']) ? ($restaurant['is_active'] ? 0 : 1) : 0; ?>">
                                                    <button type="submit" 
                                                            class="btn btn-sm <?php echo isset($restaurant['is_active']) && $restaurant['is_active'] ? 'btn-success' : 'btn-danger'; ?> status-btn">
                                                        <i class="fas <?php echo isset($restaurant['is_active']) && $restaurant['is_active'] ? 'fa-check' : 'fa-times'; ?>"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <form method="POST" action="change_restaurant.php" class="d-inline">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                        <input type="hidden" name="redirect" value="manage_restaurant.php">
                                                        <button type="submit" class="btn btn-sm btn-primary me-2">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </form>
                                                    <form method="GET" class="d-inline delete-form">
                                                        <input type="hidden" name="delete" value="1">
                                                        <input type="hidden" name="restaurant_id" value="<?php echo $restaurant['restaurant_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger delete-btn">
                                                            <i class="fas fa-trash"></i>
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
    // Show status message if exists
    <?php if (isset($_SESSION['status_message'])): ?>
        <?php if (strpos($_SESSION['status_message'], 'Cannot') !== false): ?>
            alertify.error("<?php echo $_SESSION['status_message']; ?>");
        <?php else: ?>
            alertify.success("<?php echo $_SESSION['status_message']; ?>");
        <?php endif; ?>
        <?php unset($_SESSION['status_message']); ?>
    <?php endif; ?>

    // Add confirmation dialog for status changes
    $('.status-btn').click(function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var restaurantName = $(this).closest('tr').find('td:first-child span').text();
        var newStatus = $(this).hasClass('btn-success') ? 'deactivate' : 'activate';
        
        var message = 'Are you sure you want to ' + newStatus + ' ' + restaurantName + '?';
        
        alertify.confirm(
            'Confirm Status',
            message,
            function() {
                form.submit();
            },
            function() {
                alertify.error('Operation cancelled');
            }
        );
    });

    // Add confirmation dialog for deletion
    $('.delete-btn').click(function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var restaurantName = $(this).closest('tr').find('td:first-child span').text();
        
        var message = 'Are you sure you want to delete ' + restaurantName + '?';
        
        alertify.confirm(
            'Confirm Delete',
            message,
            function() {
                form.submit();
            },
            function() {
                alertify.error('Operation cancelled');
            }
        );
    });

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