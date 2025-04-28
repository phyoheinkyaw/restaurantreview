<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Handle restaurant selection
if (isset($_GET['restaurant_id'])) {
    $_SESSION['current_restaurant_id'] = intval($_GET['restaurant_id']);
    header("Location: reservations.php");
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
    // Restaurant not found or not owned by this user
    header("Location: restaurants.php");
    exit;
}

// Helper: check if a reservation time is blocked
function is_time_blocked($restaurant_id, $date, $time, $conn) {
    $stmt = $conn->prepare("SELECT * FROM blocked_slots WHERE restaurant_id = ? AND block_date = ? AND ? >= block_time_start AND ? < block_time_end");
    $stmt->bind_param("isss", $restaurant_id, $date, $time, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $blocked = $result->num_rows > 0;
    $stmt->close();
    return $blocked;
}

// Get all reservations for the current restaurant
$sql = "SELECT r.*, u.username, u.email, u.phone 
        FROM reservations r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.restaurant_id = ? 
        ORDER BY r.reservation_date DESC, r.reservation_time DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $restaurant_id);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4">Reservations - <?php echo htmlspecialchars($restaurant['name']); ?></h2>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Reservation List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Party Size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                <?php
                                    $is_blocked = is_time_blocked($restaurant_id, $reservation['reservation_date'], $reservation['reservation_time'], $conn);
                                ?>
                                <tr<?= $is_blocked ? ' class="table-warning"' : '' ?>>
                                    <td><?php echo date('Y-m-d', strtotime($reservation['reservation_date'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($reservation['reservation_time'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($reservation['username']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($reservation['email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($reservation['phone']); ?></small>
                                    </td>
                                    <td><?php echo $reservation['party_size']; ?></td>
                                    <td>
                                        <?php
                                        $current = $reservation['status'];
                                        if ($is_blocked) {
                                            echo '<span class="status-badge cancelled">Blocked</span>';
                                        } else if ($current === 'pending' || $current === 'confirmed') {
                                            $status_hierarchy = [
                                                'pending' => ['confirmed', 'cancelled'],
                                                'confirmed' => ['completed'],
                                            ];
                                            echo '<select class="form-select status-select ' . $current . ' reservation-status-dropdown" data-reservation-id="' . $reservation['reservation_id'] . '">';
                                            echo '<option value="' . $current . '" selected>' . status_emoji($current) . ' ' . ucfirst($current) . '</option>';
                                            foreach ($status_hierarchy[$current] as $next) {
                                                echo '<option value="' . $next . '">' . status_emoji($next) . ' ' . ucfirst($next) . '</option>';
                                            }
                                            echo '</select>';
                                        } else {
                                            echo '<span class="status-badge ' . $current . '">' . status_emoji($current) . ' ' . ucfirst($current) . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="viewSpecialRequests(<?php echo $reservation['reservation_id']; ?>)">
                                            <i class="fas fa-info-circle me-1"></i> Details
                                        </button>
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

<!-- Add jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Add SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Add Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* Status badge styles */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    display: inline-block;
}
.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.confirmed { background: #d4edda; color: #155724; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }
.status-badge.completed { background: #cce5ff; color: #0c5460; }

/* Beautiful status dropdown styling */
.status-select {
    min-width: 160px;
    padding-left: 2.2rem;
    padding-right: 2.2rem;
    background-repeat: no-repeat;
    background-size: 1.2rem 1.2rem;
    font-weight: 500;
    border-radius: 0.375rem;
    border: 1.5px solid #d1d5db;
    transition: border-color 0.2s, background-color 0.2s, color 0.2s;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
}

.status-select.pending {
    background-color: #fffbea;
    color: #856404;
}
.status-select.confirmed {
    background-color: #e8f5e9;
    color: #155724;
}
.status-select.cancelled {
    background-color: #f8d7da;
    color: #721c24;
}
.status-select.completed {
    background-color: #cce5ff;
    color: #0c5460;
}
.status-select:focus {
    border-color: #2e7d32;
    box-shadow: 0 0 0 0.15rem rgba(46,125,50,.15);
}
.status-select:disabled {
    opacity: 0.7;
    background-color: #f8f9fa;
    color: #adb5bd;
    border: 1.5px solid #e0e0e0;
}

/* Style the option icons using Unicode emojis */
.status-option-emoji {
    font-family: inherit;
    font-size: 1em;
    margin-right: 0.35em;
}

@media (max-width: 768px) {
    .modal-dialog { max-width: 95%; margin: 1rem; }
    .modal-body { max-height: calc(100vh - 150px); }
}
</style>

<?php
function status_emoji($status) {
    switch ($status) {
        case 'pending': return '⏳';
        case 'confirmed': return '✔️';
        case 'cancelled': return '❌';
        case 'completed': return '🏁';
        default: return '';
    }
}
?>

<script>
// Helper for status emoji in JS
function status_emoji(status) {
    switch (status) {
        case 'pending': return '⏳';
        case 'confirmed': return '✔️';
        case 'cancelled': return '❌';
        case 'completed': return '🏁';
        default: return '';
    }
}

// View Details Modal (Special Requests and Reservation Details)
function viewSpecialRequests(reservationId) {
    $.ajax({
        url: 'get_reservation_details.php',
        method: 'POST',
        data: {
            reservation_id: reservationId,
            restaurant_id: <?php echo $restaurant_id; ?>
        },
        success: function(response) {
            var $resp = $('<div>' + response + '</div>');
            $resp.find('.status-badge').each(function() {
                var status = $(this).text().trim().toLowerCase();
                $(this).removeClass().addClass('status-badge ' + status);
            });
            $('#reservationDetails').html($resp.html());
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            var modal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            modal.show();
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error loading reservation details',
                text: 'Failed to load reservation details.',
                timer: 1800,
                showConfirmButton: false
            });
        }
    });
}

$(document).ready(function() {
    var previousStatus = {};
    const statusHierarchy = {
        'pending': ['confirmed', 'cancelled'],
        'confirmed': ['completed'],
        'cancelled': [],
        'completed': []
    };
    $('.reservation-status-dropdown').each(function() {
        previousStatus[$(this).data('reservation-id')] = $(this).val();
    });
    $('.reservation-status-dropdown').on('focus', function() {
        var rid = $(this).data('reservation-id');
        previousStatus[rid] = $(this).val();
    });
    $('.reservation-status-dropdown').on('change', function() {
        var $dropdown = $(this);
        var reservationId = $dropdown.data('reservation-id');
        var newStatus = $dropdown.val();
        var oldStatus = previousStatus[reservationId];
        var restaurantId = <?php echo $restaurant_id; ?>;
        if (newStatus === oldStatus) return;
        Swal.fire({
            title: 'Confirm Status Change',
            html: 'Are you sure you want to change the status to <b>' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '</b>?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'update_reservation_status.php',
                    type: 'POST',
                    data: {
                        update_status: 1,
                        reservation_id: reservationId,
                        status: newStatus,
                        restaurant_id: restaurantId
                    },
                    success: function(data) {
                        if (typeof data === 'string') {
                            try { data = JSON.parse(data); } catch (e) {}
                        }
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Status Updated',
                                text: 'Status updated to ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1),
                                timer: 1800,
                                showConfirmButton: false
                            });
                            previousStatus[reservationId] = newStatus;
                            // Update dropdown options dynamically, keeping the select element and class
                            var options = '';
                            options += '<option value="' + newStatus + '" selected>' + status_emoji(newStatus) + ' ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '</option>';
                            var nextStatuses = statusHierarchy[newStatus] || [];
                            nextStatuses.forEach(function(next) {
                                options += '<option value="' + next + '">' + status_emoji(next) + ' ' + next.charAt(0).toUpperCase() + next.slice(1) + '</option>';
                            });
                            $dropdown.html(options);
                            // Always update the dropdown class to match the new status, and always keep status-select
                            $dropdown.removeClass('pending confirmed cancelled completed').addClass('status-select').addClass(newStatus);
                            // Force browser repaint to ensure background/arrow is visible
                            $dropdown.hide().show(0);
                            // Optionally disable dropdown if no further transitions
                            if (nextStatuses.length === 0) {
                                $dropdown.prop('disabled', true);
                            } else {
                                $dropdown.prop('disabled', false);
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: data.message || 'Failed to update status.',
                                timer: 1800,
                                showConfirmButton: false
                            });
                            $dropdown.val(oldStatus);
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update Failed',
                            text: 'Failed to update status.',
                            timer: 1800,
                            showConfirmButton: false
                        });
                        $dropdown.val(oldStatus);
                    }
                });
            } else {
                $dropdown.val(oldStatus);
            }
        });
    });
    // On page load, ensure all dropdowns have correct status class
    $('.reservation-status-dropdown').each(function() {
        var status = $(this).val();
        $(this).removeClass('pending confirmed cancelled completed').addClass('status-select').addClass(status);
    });
});
</script>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-3 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="viewDetailsModalLabel">Reservation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="reservationDetails" class="text-center">
                    <!-- Details will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>
