<?php
// Include header
include 'includes/header.php';

// Process actions (confirm, cancel, complete, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $reservation_id = (int)$_GET['id'];
    
    if ($action === 'confirm') {
        // Confirm reservation
        $sql = "UPDATE reservations SET status = 'confirmed' WHERE reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Reservation confirmed successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to confirm reservation.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    } elseif ($action === 'cancel') {
        // Cancel reservation
        $sql = "UPDATE reservations SET status = 'cancelled' WHERE reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Reservation cancelled successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to cancel reservation.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    } elseif ($action === 'complete') {
        // Complete reservation
        $sql = "UPDATE reservations SET status = 'completed' WHERE reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Reservation marked as completed successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to mark reservation as completed.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        // Delete reservation
        $sql = "DELETE FROM reservations WHERE reservation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $reservation_id);
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Reservation deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Failed to delete reservation.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        }
        $stmt->close();
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$query = "SELECT 
            r.*, 
            u.username, 
            u.first_name, 
            u.last_name, 
            u.email,
            res.name as restaurant_name
          FROM 
            reservations r
          LEFT JOIN 
            users u ON r.user_id = u.user_id
          LEFT JOIN 
            restaurants res ON r.restaurant_id = res.restaurant_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (
                u.username LIKE ? OR 
                u.first_name LIKE ? OR 
                u.last_name LIKE ? OR 
                u.email LIKE ? OR
                res.name LIKE ? OR
                r.special_requests LIKE ?
                )";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssssss";
}

if ($restaurant_id > 0) {
    $query .= " AND r.restaurant_id = ?";
    $params[] = $restaurant_id;
    $types .= "i";
}

if (!empty($status)) {
    $query .= " AND r.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND r.reservation_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND r.reservation_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM ($query) as subquery";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_reservations = $total_row['total'];
$total_pages = ceil($total_reservations / $per_page);
$stmt->close();

// Add sorting and pagination to the main query
$query .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all restaurants for filter dropdown
$restaurant_sql = "SELECT restaurant_id, name FROM restaurants ORDER BY name";
$restaurant_result = $conn->query($restaurant_sql);
$restaurants = $restaurant_result->fetch_all(MYSQLI_ASSOC);
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h3">Manage Reservations</h1>
        <p class="text-muted">View and manage all restaurant reservations</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <a class="btn btn-outline-primary" id="refreshBtn">
                <i class="fas fa-sync-alt me-2"></i> Refresh
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-download me-2"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label for="restaurant_id" class="form-label">Restaurant</label>
                <select class="form-select" id="restaurant_id" name="restaurant_id">
                    <option value="0">All Restaurants</option>
                    <?php foreach ($restaurants as $restaurant): ?>
                        <option value="<?php echo $restaurant['restaurant_id']; ?>" 
                                <?php echo $restaurant_id === (int)$restaurant['restaurant_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($restaurant['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">Date From</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">Date To</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="reservations.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Reservations Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#ID</th>
                        <th>Customer</th>
                        <th>Restaurant</th>
                        <th>Date & Time</th>
                        <th>Party Size</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($reservation = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $reservation['reservation_id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($reservation['restaurant_name']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?></div>
                                    <div class="small text-muted"><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></div>
                                </td>
                                <td><?php echo $reservation['party_size']; ?> people</td>
                                <td>
                                    <?php 
                                        $status_class = '';
                                        switch ($reservation['status']) {
                                            case 'pending':
                                                $status_class = 'bg-warning';
                                                break;
                                            case 'confirmed':
                                                $status_class = 'bg-success';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-danger';
                                                break;
                                            case 'completed':
                                                $status_class = 'bg-info';
                                                break;
                                        }
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($reservation['status']); ?></span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($reservation['created_at'])); ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?php echo $reservation['reservation_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $reservation['reservation_id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#viewReservationModal<?php echo $reservation['reservation_id']; ?>">
                                                    <i class="fas fa-eye me-2"></i> View Details
                                                </a>
                                            </li>
                                            <?php if ($reservation['status'] === 'pending'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="reservations.php?action=confirm&id=<?php echo $reservation['reservation_id']; ?>" data-confirm="Are you sure you want to confirm this reservation?">
                                                        <i class="fas fa-check me-2"></i> Confirm
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] === 'pending' || $reservation['status'] === 'confirmed'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="reservations.php?action=cancel&id=<?php echo $reservation['reservation_id']; ?>" data-confirm="Are you sure you want to cancel this reservation?">
                                                        <i class="fas fa-ban me-2"></i> Cancel
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] === 'confirmed'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="reservations.php?action=complete&id=<?php echo $reservation['reservation_id']; ?>" data-confirm="Are you sure you want to mark this reservation as completed?">
                                                        <i class="fas fa-check-double me-2"></i> Mark as Completed
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="reservations.php?action=delete&id=<?php echo $reservation['reservation_id']; ?>" data-confirm="Are you sure you want to delete this reservation? This action cannot be undone.">
                                                    <i class="fas fa-trash-alt me-2"></i> Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- View Reservation Modal -->
                            <div class="modal fade" id="viewReservationModal<?php echo $reservation['reservation_id']; ?>" tabindex="-1" aria-labelledby="viewReservationModalLabel<?php echo $reservation['reservation_id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewReservationModalLabel<?php echo $reservation['reservation_id']; ?>">
                                                Reservation Details #<?php echo $reservation['reservation_id']; ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="reservation-detail">
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Customer</label>
                                                    <div class="col-sm-8">
                                                        <p class="mb-1"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($reservation['email']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Restaurant</label>
                                                    <div class="col-sm-8">
                                                        <p class="mb-0"><?php echo htmlspecialchars($reservation['restaurant_name']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Date & Time</label>
                                                    <div class="col-sm-8">
                                                        <p class="mb-1"><?php echo date('F j, Y', strtotime($reservation['reservation_date'])); ?></p>
                                                        <p class="mb-0 text-muted"><?php echo date('g:i A', strtotime($reservation['reservation_time'])); ?></p>
                                                    </div>
                                                </div>
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Party Size</label>
                                                    <div class="col-sm-8">
                                                        <p class="mb-0"><?php echo $reservation['party_size']; ?> people</p>
                                                    </div>
                                                </div>
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Status</label>
                                                    <div class="col-sm-8">
                                                        <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($reservation['status']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="mb-3 row">
                                                    <label class="col-sm-4 col-form-label">Special Requests</label>
                                                    <div class="col-sm-8">
                                                        <?php if (!empty($reservation['special_requests'])): ?>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($reservation['special_requests'])); ?></p>
                                                        <?php else: ?>
                                                            <p class="mb-0 text-muted">None</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mb-0 row">
                                                    <label class="col-sm-4 col-form-label">Created</label>
                                                    <div class="col-sm-8">
                                                        <p class="mb-0"><?php echo date('F j, Y, g:i A', strtotime($reservation['created_at'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if ($reservation['status'] === 'pending'): ?>
                                                <a href="reservations.php?action=confirm&id=<?php echo $reservation['reservation_id']; ?>" class="btn btn-success">
                                                    <i class="fas fa-check me-2"></i> Confirm
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($reservation['status'] === 'pending' || $reservation['status'] === 'confirmed'): ?>
                                                <a href="reservations.php?action=cancel&id=<?php echo $reservation['reservation_id']; ?>" class="btn btn-danger">
                                                    <i class="fas fa-ban me-2"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No reservations found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo '?page=' . ($page - 1) . '&search=' . urlencode($search) . '&restaurant_id=' . $restaurant_id . '&status=' . urlencode($status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to); ?>" tabindex="-1" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo '?page=' . $i . '&search=' . urlencode($search) . '&restaurant_id=' . $restaurant_id . '&status=' . urlencode($status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo '?page=' . ($page + 1) . '&search=' . urlencode($search) . '&restaurant_id=' . $restaurant_id . '&status=' . urlencode($status) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Reservations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="export_reservations.php" method="POST">
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Export Format</label>
                        <select class="form-select" id="export_format" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_date_range" class="form-label">Date Range</label>
                        <select class="form-select" id="export_date_range" name="date_range">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this_week">This Week</option>
                            <option value="last_week">Last Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    
                    <div id="custom_date_range" class="row g-3 d-none">
                        <div class="col-md-6">
                            <label for="export_date_from" class="form-label">From</label>
                            <input type="date" class="form-control" id="export_date_from" name="date_from">
                        </div>
                        <div class="col-md-6">
                            <label for="export_date_to" class="form-label">To</label>
                            <input type="date" class="form-control" id="export_date_to" name="date_to">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_status" class="form-label">Status</label>
                        <select class="form-select" id="export_status" name="status">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="exportButton">Export</button>
            </div>
        </div>
    </div>
</div>

<!-- Page Script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Export date range toggle
        const exportDateRange = document.getElementById('export_date_range');
        const customDateRange = document.getElementById('custom_date_range');
        
        exportDateRange.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.classList.remove('d-none');
            } else {
                customDateRange.classList.add('d-none');
            }
        });
        
        // Export button click
        document.getElementById('exportButton').addEventListener('click', function() {
            const form = document.querySelector('#exportModal form');
            form.submit();
        });
        
        // Refresh button click
        document.getElementById('refreshBtn').addEventListener('click', function(e) {
            e.preventDefault();
            window.location.reload();
        });
        
        // Initialize date pickers if needed
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');
        
        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && this.value > dateTo.value) {
                    dateTo.value = this.value;
                }
            });
            
            dateTo.addEventListener('change', function() {
                if (dateFrom.value && this.value < dateFrom.value) {
                    dateFrom.value = this.value;
                }
            });
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 