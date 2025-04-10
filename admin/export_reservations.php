<?php
// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin or owner
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'owner')) {
    header('Location: ../login.php');
    exit;
}

// Create database connection
$db = new Database();
$conn = $db->getConnection();

// Get export parameters
$format = isset($_POST['format']) ? $_POST['format'] : 'csv';
$date_range = isset($_POST['date_range']) ? $_POST['date_range'] : 'all';
$status = isset($_POST['status']) ? $_POST['status'] : 'all';
$date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
$date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';

// Build query with filters
$query = "SELECT 
            r.reservation_id,
            r.user_id,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            res.name as restaurant_name,
            r.reservation_date,
            r.reservation_time,
            r.party_size,
            r.special_requests,
            r.status,
            r.created_at
          FROM 
            reservations r
          LEFT JOIN 
            users u ON r.user_id = u.user_id
          LEFT JOIN 
            restaurants res ON r.restaurant_id = res.restaurant_id
          WHERE 1=1";

$params = [];
$types = "";

// Apply status filter
if ($status !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Apply date filters based on date range
switch ($date_range) {
    case 'today':
        $query .= " AND DATE(r.reservation_date) = CURDATE()";
        break;
    case 'yesterday':
        $query .= " AND DATE(r.reservation_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $query .= " AND YEARWEEK(r.reservation_date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $query .= " AND YEARWEEK(r.reservation_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
        break;
    case 'this_month':
        $query .= " AND MONTH(r.reservation_date) = MONTH(CURDATE()) AND YEAR(r.reservation_date) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $query .= " AND MONTH(r.reservation_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(r.reservation_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'custom':
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
        break;
}

// Add ordering
$query .= " ORDER BY r.reservation_date DESC, r.reservation_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Set up headers for file download
$timestamp = date('Y-m-d_H-i-s');
$filename = "reservations_export_{$timestamp}";

// Export data in the requested format
switch ($format) {
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add header row
        fputcsv($output, [
            'ID', 
            'Username', 
            'First Name', 
            'Last Name', 
            'Email', 
            'Restaurant', 
            'Date', 
            'Time', 
            'Party Size', 
            'Special Requests', 
            'Status', 
            'Created'
        ]);
        
        // Add data rows
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['reservation_id'],
                $row['username'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['restaurant_name'],
                date('Y-m-d', strtotime($row['reservation_date'])),
                date('H:i:s', strtotime($row['reservation_time'])),
                $row['party_size'],
                $row['special_requests'],
                $row['status'],
                $row['created_at']
            ]);
        }
        
        fclose($output);
        break;
        
    case 'excel':
        // Use CSV format but with Excel headers
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Username</th>";
        echo "<th>First Name</th>";
        echo "<th>Last Name</th>";
        echo "<th>Email</th>";
        echo "<th>Restaurant</th>";
        echo "<th>Date</th>";
        echo "<th>Time</th>";
        echo "<th>Party Size</th>";
        echo "<th>Special Requests</th>";
        echo "<th>Status</th>";
        echo "<th>Created</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['reservation_id'] . "</td>";
            echo "<td>" . $row['username'] . "</td>";
            echo "<td>" . $row['first_name'] . "</td>";
            echo "<td>" . $row['last_name'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['restaurant_name'] . "</td>";
            echo "<td>" . date('Y-m-d', strtotime($row['reservation_date'])) . "</td>";
            echo "<td>" . date('H:i:s', strtotime($row['reservation_time'])) . "</td>";
            echo "<td>" . $row['party_size'] . "</td>";
            echo "<td>" . $row['special_requests'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        break;
        
    case 'pdf':
        // Basic PDF export using HTML to PDF conversion
        // Note: For a production environment, you'd want to use a proper PDF library like TCPDF or FPDF
        
        // Buffer the output
        ob_start();
        
        echo "<style>
            body { font-family: Arial, sans-serif; }
            h1 { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            tr:nth-child(even) { background-color: #f9f9f9; }
        </style>";
        
        echo "<h1>Reservations Export</h1>";
        echo "<p>Date Generated: " . date('F j, Y, g:i a') . "</p>";
        
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Customer</th>";
        echo "<th>Restaurant</th>";
        echo "<th>Date & Time</th>";
        echo "<th>Party Size</th>";
        echo "<th>Status</th>";
        echo "</tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['reservation_id'] . "</td>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "<br><small>" . $row['email'] . "</small></td>";
            echo "<td>" . $row['restaurant_name'] . "</td>";
            echo "<td>" . date('Y-m-d', strtotime($row['reservation_date'])) . "<br>" . date('H:i:s', strtotime($row['reservation_time'])) . "</td>";
            echo "<td>" . $row['party_size'] . "</td>";
            echo "<td>" . ucfirst($row['status']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        $html = ob_get_clean();
        
        // Send HTML to browser with PDF headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        
        // For a true PDF generation, you'd need a library like TCPDF:
        // This is a placeholder - in a real implementation replace with actual PDF generation
        echo $html;
        
        // Note: You should add proper PDF generation code here
        // Example with TCPDF (if installed):
        /*
        require_once('tcpdf/tcpdf.php');
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Restaurant Reservation System');
        $pdf->SetAuthor('Admin');
        $pdf->SetTitle('Reservations Export');
        $pdf->SetHeaderData('', 0, 'Reservations Export', date('F j, Y, g:i a'));
        $pdf->setHeaderFont(Array('helvetica', '', 10));
        $pdf->setFooterFont(Array('helvetica', '', 8));
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filename . '.pdf', 'D');
        */
        break;
        
    default:
        // Default to CSV if an invalid format is specified
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add header row
        fputcsv($output, [
            'ID', 
            'Username', 
            'First Name', 
            'Last Name', 
            'Email', 
            'Restaurant', 
            'Date', 
            'Time', 
            'Party Size', 
            'Special Requests', 
            'Status', 
            'Created'
        ]);
        
        // Add data rows
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['reservation_id'],
                $row['username'],
                $row['first_name'],
                $row['last_name'],
                $row['email'],
                $row['restaurant_name'],
                date('Y-m-d', strtotime($row['reservation_date'])),
                date('H:i:s', strtotime($row['reservation_time'])),
                $row['party_size'],
                $row['special_requests'],
                $row['status'],
                $row['created_at']
            ]);
        }
        
        fclose($output);
}

// Close the database connection
$stmt->close();
$conn->close();
exit;
?> 