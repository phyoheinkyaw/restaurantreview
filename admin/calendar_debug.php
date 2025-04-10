<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and has appropriate role
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'owner')) {
    header('Location: ../login.php');
    exit;
}

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Process date filter
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Format the initial date for FullCalendar
$formattedMonth = str_pad($selectedMonth, 2, '0', STR_PAD_LEFT);
$initialDate = "{$selectedYear}-{$formattedMonth}-01";

// Get current month start and end dates
$startDate = date('Y-m-01', strtotime("$selectedYear-$selectedMonth-01"));
$endDate = date('Y-m-t', strtotime("$selectedYear-$selectedMonth-01"));

// Query for reservations in this month
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

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
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
    $start_date = $reservation['reservation_date'] . 'T' . $reservation['reservation_time'];
    $end_date = date('Y-m-d H:i:s', strtotime($reservation['reservation_date'] . ' ' . $reservation['reservation_time'] . ' +2 hours'));
    
    $events[] = [
        'id' => $reservation['reservation_id'],
        'title' => $reservation['restaurant_name'] . ' - ' . $reservation['party_size'] . ' guests',
        'start' => $start_date,
        'end' => $end_date,
        'color' => $color
    ];
}

// Encode events as JSON
$eventsJson = json_encode($events);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Debug Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .data-section {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        h1 {
            color: #333;
        }
        pre {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>Calendar Debug Information</h1>
    
    <div class="card">
        <h2>Date Parameters</h2>
        <div class="data-section">
            <p><strong>Selected Month:</strong> <?php echo $selectedMonth; ?></p>
            <p><strong>Selected Year:</strong> <?php echo $selectedYear; ?></p>
            <p><strong>Formatted Month:</strong> <?php echo $formattedMonth; ?></p>
            <p><strong>Initial Date for Calendar:</strong> <?php echo $initialDate; ?></p>
            <p><strong>Start Date for Query:</strong> <?php echo $startDate; ?></p>
            <p><strong>End Date for Query:</strong> <?php echo $endDate; ?></p>
        </div>
    </div>
    
    <div class="card">
        <h2>Reservation Data</h2>
        <div class="data-section">
            <p><strong>Total Reservations Found:</strong> <?php echo count($events); ?></p>
            
            <?php if (count($events) > 0): ?>
                <h3>Reservations Table</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Color</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo $event['id']; ?></td>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo $event['start']; ?></td>
                                <td><?php echo $event['end']; ?></td>
                                <td style="background-color: <?php echo $event['color']; ?>">
                                    <?php echo $event['color']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><em>No reservations found for the selected month.</em></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <h2>JSON Data for Calendar</h2>
        <div class="data-section">
            <p>This is the JSON data that will be passed to FullCalendar:</p>
            <pre><?php echo htmlspecialchars($eventsJson); ?></pre>
            
            <p><strong>JSON Last Error:</strong> <?php echo json_last_error() === JSON_ERROR_NONE ? 'No error' : json_last_error_msg(); ?></p>
        </div>
    </div>
    
    <div class="card">
        <h2>Test Simple Calendar</h2>
        <div id="calendar" style="height: 400px;"></div>
        
        <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Calendar debugging test');
                
                const calendarEl = document.getElementById('calendar');
                if (!calendarEl) {
                    console.error('Calendar element not found');
                    return;
                }
                
                try {
                    console.log('Creating calendar with initialDate:', '<?php echo $initialDate; ?>');
                    
                    const calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        initialDate: '<?php echo $initialDate; ?>',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        },
                        events: <?php echo $eventsJson; ?>
                    });
                    
                    console.log('Rendering calendar...');
                    calendar.render();
                    console.log('Calendar rendered');
                } catch (error) {
                    console.error('Error creating calendar:', error);
                    document.getElementById('calendar').innerHTML = 
                        '<div style="color: red; padding: 20px;">Error creating calendar: ' + error.message + '</div>';
                }
            });
        </script>
    </div>
    
    <div class="card">
        <h2>Links</h2>
        <p><a href="reservation_calendar.php">Back to Reservation Calendar</a></p>
        <p><a href="reservations.php">Back to Reservations List</a></p>
    </div>
</body>
</html> 