<?php
// Include header
include 'includes/header.php';

// Process date filter
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Restaurant filter
$restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

// Get all restaurants for filter dropdown
$restaurant_sql = "SELECT restaurant_id, name FROM restaurants ORDER BY name";
$restaurant_result = $conn->query($restaurant_sql);
$restaurants = $restaurant_result->fetch_all(MYSQLI_ASSOC);

// Get reservations for the selected month
$startDate = date('Y-m-01', strtotime("$selectedYear-$selectedMonth-01"));
$endDate = date('Y-m-t', strtotime("$selectedYear-$selectedMonth-01"));

$query = "SELECT 
            r.*, 
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
          WHERE 
            r.reservation_date BETWEEN ? AND ?";

$params = [$startDate, $endDate];
$types = "ss";

if ($restaurant_id > 0) {
    $query .= " AND r.restaurant_id = ?";
    $params[] = $restaurant_id;
    $types .= "i";
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Format reservations for FullCalendar
$events = [];
while ($reservation = $result->fetch_assoc()) {
    // Set event color based on status
    $color = '#6c757d'; // Default gray
    switch ($reservation['status']) {
        case 'pending':
            $color = '#ffc107'; // Warning yellow
            break;
        case 'confirmed':
            $color = '#28a745'; // Success green
            break;
        case 'cancelled':
            $color = '#dc3545'; // Danger red
            break;
        case 'completed':
            $color = '#17a2b8'; // Info blue
            break;
    }
    
    // Format event details
    try {
        $start_date = $reservation['reservation_date'] . 'T' . $reservation['reservation_time'];
        $end_date = date('Y-m-d H:i:s', strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time'] . ' +2 hours'));
        
        $events[] = [
            'id' => $reservation['reservation_id'],
            'title' => $reservation['restaurant_name'] . ' - ' . $reservation['party_size'] . ' guests',
            'start' => $start_date,
            'end' => $end_date,
            'color' => $color,
            'extendedProps' => [
                'customer' => $reservation['first_name'] . ' ' . $reservation['last_name'],
                'email' => $reservation['email'],
                'restaurant' => $reservation['restaurant_name'],
                'party_size' => $reservation['party_size'],
                'status' => $reservation['status'],
                'special_requests' => $reservation['special_requests'],
                'reservation_id' => $reservation['reservation_id']
            ]
        ];
    } catch (Exception $e) {
        // Log the error or handle it as needed
        error_log('Error formatting reservation: ' . $e->getMessage());
    }
}

// Format the initial date for FullCalendar
$formattedMonth = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
$initialDate = "{$selectedYear}-{$formattedMonth}-01";

// Encode events as JSON with error handling
$eventsJson = !empty($events) ? json_encode($events) : '[]';
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON encoding error: ' . json_last_error_msg());
    $eventsJson = '[]';
}
?>

<!-- Page Header -->
<div class="row align-items-center mb-4">
    <div class="col-md-6">
        <h1 class="h3">Reservation Calendar</h1>
        <p class="text-muted">View and manage reservations in a calendar view</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <button id="prevMonth" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="currentMonth" class="btn btn-outline-primary">
                Today
            </button>
            <button id="nextMonth" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <input type="hidden" name="month" id="month_input" value="<?php echo $selectedMonth; ?>">
            <input type="hidden" name="year" id="year_input" value="<?php echo $selectedYear; ?>">
            
            <div class="col-md-4">
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
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="reservation_calendar.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Calendar -->
<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Reservation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="reservation-detail">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Customer</label>
                        <div class="col-sm-8">
                            <p class="mb-1 event-customer"></p>
                            <p class="mb-0 text-muted event-email"></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Restaurant</label>
                        <div class="col-sm-8">
                            <p class="mb-0 event-restaurant"></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Date & Time</label>
                        <div class="col-sm-8">
                            <p class="mb-0 event-datetime"></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Party Size</label>
                        <div class="col-sm-8">
                            <p class="mb-0 event-party-size"></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Status</label>
                        <div class="col-sm-8">
                            <span class="badge event-status"></span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Special Requests</label>
                        <div class="col-sm-8">
                            <p class="mb-0 event-special-requests"></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div id="event-action-buttons">
                    <!-- Action buttons will be added here dynamically via JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Calendar initialization starting');
        console.log('Initial date:', '<?php echo $initialDate; ?>');
        
        // Initialize FullCalendar
        const calendarEl = document.getElementById('calendar');
        
        if (!calendarEl) {
            console.error('Calendar element not found!');
            return;
        }
        
        try {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '<?php echo $initialDate; ?>',
                headerToolbar: {
                    left: 'dayGridMonth,timeGridWeek,timeGridDay',
                    center: 'title',
                    right: 'today'
                },
                navLinks: true,
                editable: false,
                dayMaxEvents: true,
                events: <?php echo $eventsJson; ?>,
                eventClick: function(info) {
                    const event = info.event;
                    const props = event.extendedProps;
                    
                    // Update modal content
                    document.querySelector('.event-customer').textContent = props.customer;
                    document.querySelector('.event-email').textContent = props.email;
                    document.querySelector('.event-restaurant').textContent = props.restaurant;
                    document.querySelector('.event-datetime').textContent = new Date(event.start).toLocaleString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    document.querySelector('.event-party-size').textContent = props.party_size + ' people';
                    
                    // Set status badge
                    const statusEl = document.querySelector('.event-status');
                    statusEl.textContent = props.status.charAt(0).toUpperCase() + props.status.slice(1);
                    
                    // Set status badge color
                    statusEl.className = 'badge'; // Reset classes
                    switch (props.status) {
                        case 'pending':
                            statusEl.classList.add('bg-warning');
                            break;
                        case 'confirmed':
                            statusEl.classList.add('bg-success');
                            break;
                        case 'cancelled':
                            statusEl.classList.add('bg-danger');
                            break;
                        case 'completed':
                            statusEl.classList.add('bg-info');
                            break;
                        default:
                            statusEl.classList.add('bg-secondary');
                    }
                    
                    // Set special requests
                    const specialRequestsEl = document.querySelector('.event-special-requests');
                    if (props.special_requests && props.special_requests.trim() !== '') {
                        specialRequestsEl.textContent = props.special_requests;
                    } else {
                        specialRequestsEl.innerHTML = '<span class="text-muted">None</span>';
                    }
                    
                    // Clear previous action buttons
                    const actionButtonsContainer = document.getElementById('event-action-buttons');
                    actionButtonsContainer.innerHTML = '';
                    
                    // Add action buttons based on reservation status
                    if (props.status === 'pending') {
                        // Confirm button
                        const confirmBtn = document.createElement('a');
                        confirmBtn.href = `reservations.php?action=confirm&id=${props.reservation_id}`;
                        confirmBtn.className = 'btn btn-success';
                        confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm';
                        confirmBtn.setAttribute('data-confirm', 'Are you sure you want to confirm this reservation?');
                        actionButtonsContainer.appendChild(confirmBtn);
                    }
                    
                    if (props.status === 'pending' || props.status === 'confirmed') {
                        // Cancel button
                        const cancelBtn = document.createElement('a');
                        cancelBtn.href = `reservations.php?action=cancel&id=${props.reservation_id}`;
                        cancelBtn.className = 'btn btn-danger ms-2';
                        cancelBtn.innerHTML = '<i class="fas fa-ban me-1"></i> Cancel';
                        cancelBtn.setAttribute('data-confirm', 'Are you sure you want to cancel this reservation?');
                        actionButtonsContainer.appendChild(cancelBtn);
                    }
                    
                    if (props.status === 'confirmed') {
                        // Complete button
                        const completeBtn = document.createElement('a');
                        completeBtn.href = `reservations.php?action=complete&id=${props.reservation_id}`;
                        completeBtn.className = 'btn btn-info';
                        completeBtn.innerHTML = '<i class="fas fa-check-double me-1"></i> Complete';
                        completeBtn.setAttribute('data-confirm', 'Are you sure you want to mark this reservation as completed?');
                        actionButtonsContainer.appendChild(completeBtn);
                    }
                    
                    // View details button
                    const viewBtn = document.createElement('a');
                    viewBtn.href = `reservations.php?search=${props.customer}`;
                    viewBtn.className = 'btn btn-primary ms-2';
                    viewBtn.innerHTML = '<i class="fas fa-search me-1"></i> View in List';
                    actionButtonsContainer.appendChild(viewBtn);
                    
                    // Open the modal
                    const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
                    eventModal.show();
                }
            });
            
            console.log('Calendar object created, rendering...');
            calendar.render();
            console.log('Calendar rendered successfully');
            
            // Previous month button
            document.getElementById('prevMonth').addEventListener('click', function() {
                const date = new Date(<?php echo $selectedYear; ?>, <?php echo $selectedMonth - 1; ?>, 1);
                date.setMonth(date.getMonth() - 1);
                
                document.getElementById('month_input').value = date.getMonth() + 1;
                document.getElementById('year_input').value = date.getFullYear();
                document.querySelector('form').submit();
            });
            
            // Next month button
            document.getElementById('nextMonth').addEventListener('click', function() {
                const date = new Date(<?php echo $selectedYear; ?>, <?php echo $selectedMonth - 1; ?>, 1);
                date.setMonth(date.getMonth() + 1);
                
                document.getElementById('month_input').value = date.getMonth() + 1;
                document.getElementById('year_input').value = date.getFullYear();
                document.querySelector('form').submit();
            });
            
            // Current month (today) button
            document.getElementById('currentMonth').addEventListener('click', function() {
                const today = new Date();
                
                document.getElementById('month_input').value = today.getMonth() + 1;
                document.getElementById('year_input').value = today.getFullYear();
                document.querySelector('form').submit();
            });
            
            // Restaurant filter change
            document.getElementById('restaurant_id').addEventListener('change', function() {
                document.querySelector('form').submit();
            });
        } catch (error) {
            console.error('Error initializing FullCalendar:', error);
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 