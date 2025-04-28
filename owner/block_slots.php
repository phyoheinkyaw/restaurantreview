<?php
// Handle delete block BEFORE any output or includes
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    require_once 'includes/db_connect.php';
    session_start();
    if (isset($_SESSION['current_restaurant_id'])) {
        $restaurant_id = $_SESSION['current_restaurant_id'];
        $block_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM blocked_slots WHERE block_id = ? AND restaurant_id = ?");
        $stmt->bind_param("ii", $block_id, $restaurant_id);
        $stmt->execute();
        $stmt->close();
        // Set a flag for success
        $_SESSION['block_delete_success'] = true;
    }
    header('Location: block_slots.php');
    exit;
}
?>
<?php
require_once 'includes/header.php';
// block_slots.php - Owner UI for blocking slots
require_once 'includes/db_connect.php';
if (!isset($_SESSION['current_restaurant_id'])) {
    header('Location: restaurants.php'); exit;
}
$restaurant_id = $_SESSION['current_restaurant_id'];

// Get current restaurant info for header display
$sql = "SELECT * FROM restaurants WHERE restaurant_id = ? AND owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $restaurant_id, $owner['user_id']);
$stmt->execute();
$restaurant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$restaurant) {
    header("Location: restaurants.php");
    exit;
}

// Handle block form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_date'], $_POST['block_time_start'], $_POST['block_time_end'])) {
    $block_date = $_POST['block_date'];
    $block_time_start = $_POST['block_time_start'];
    $block_time_end = $_POST['block_time_end'];
    $reason = trim($_POST['reason'] ?? '');
    $weekday = strtolower(date('l', strtotime($block_date)));
    $hours = json_decode($restaurant['opening_hours'], true);
    $open = $hours[$weekday]['open'] ?? null;
    $close = $hours[$weekday]['close'] ?? null;
    $error = null;
    if (!$open || !$close || $block_time_start < $open || $block_time_end > $close || $block_time_start >= $block_time_end) {
        $error = 'Selected block time is outside opening hours or invalid.';
    } else {
        // Check for overlap with existing blocks
        $stmt = $conn->prepare("SELECT * FROM blocked_slots WHERE restaurant_id = ? AND block_date = ? AND block_time_start < ? AND block_time_end > ?");
        $stmt->bind_param("isss", $restaurant_id, $block_date, $block_time_end, $block_time_start);
        $stmt->execute();
        $overlap = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if ($overlap) {
            $error = 'This time overlaps with an existing block.';
        }
    }
    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO blocked_slots (restaurant_id, block_date, block_time_start, block_time_end, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $restaurant_id, $block_date, $block_time_start, $block_time_end, $reason);
        $stmt->execute();
        $stmt->close();
        $success = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Block Time Slots</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
    <style>
        .flatpickr-calendar {
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.12);
            background: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #222;
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.inRange {
            background: #0d6efd;
            color: #fff;
            border-radius: 6px;
        }
        .flatpickr-day.today {
            border-color: #0d6efd;
            background: #e3f0ff;
            color: #0d6efd;
        }
        .flatpickr-day.disabled, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay {
            color: #bbb !important;
            background: #f0f0f0;
            cursor: not-allowed;
        }
        .flatpickr-months .flatpickr-month, .flatpickr-weekdays {
            color: #222;
        }
        .flatpickr-current-month input.cur-year {
            color: #0d6efd;
        }
        .flatpickr-time input {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 4px 8px;
            background: #fff;
            color: #222;
        }
        .form-label {
            font-weight: 600;
            color: #222;
        }
        .form-control {
            color: #222;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
            color: #222;
        }
        .container.mt-5 {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.09);
            padding: 2.5rem 2rem 2rem 2rem;
            margin-top: 2.5rem !important;
            color: #222;
        }
        h2, h4 {
            color: #222;
            font-weight: 700;
        }
        .btn-primary {
            background: #0d6efd;
            border: none;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .table {
            background: #f9fafb;
            border-radius: 10px;
            overflow: hidden;
            color: #222;
        }
        .table th {
            background: #e3f0ff;
            color: #222;
            font-weight: 700;
        }
        .block-time-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        .block-time-row .col-md-2 {
            flex: 1;
            min-width: 120px;
        }
        .block-time-row .form-label {
            color: #222;
            margin-bottom: 0.4rem;
        }
        .block-time-row .form-control {
            background: #fff;
            border-radius: 8px;
            color: #222;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Block Time Slots For <?php echo htmlspecialchars($restaurant['name']); ?></h2>
    <form method="post" class="row g-3 mb-4">
        <div class="col-md-3 mb-2">
            <label class="form-label">Date</label>
            <input type="text" name="block_date" class="form-control" required>
        </div>
        <div class="block-time-row">
            <div class="col-md-2">
                <label class="form-label">Start Time</label>
                <input type="text" name="block_time_start" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">End Time</label>
                <input type="text" name="block_time_end" class="form-control" required>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" maxlength="100" placeholder="e.g. Maintenance, Private Event">
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Block Slot</button>
        </div>
    </form>
    <h4>Upcoming Blocked Slots</h4>
    <table class="table table-bordered">
        <thead>
        <tr>
            <th>Date</th>
            <th>Start</th>
            <th>End</th>
            <th>Reason</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php 
        // Get all future blocks for this restaurant
        $stmt = $conn->prepare("SELECT * FROM blocked_slots WHERE restaurant_id = ? AND (block_date > CURDATE() OR (block_date = CURDATE() AND block_time_end >= CURTIME())) ORDER BY block_date, block_time_start");
        $stmt->bind_param("i", $restaurant_id);
        $stmt->execute();
        $blocks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($blocks as $block): ?>
            <tr>
                <td><?= htmlspecialchars($block['block_date']) ?></td>
                <td><?= htmlspecialchars(substr($block['block_time_start'], 0, 5)) ?></td>
                <td><?= htmlspecialchars(substr($block['block_time_end'], 0, 5)) ?></td>
                <td><?= htmlspecialchars($block['reason']) ?></td>
                <td>
                    <a href="?delete=<?= $block['block_id'] ?>" class="btn btn-danger btn-sm btn-delete-block">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($blocks)): ?>
            <tr><td colspan="5" class="text-center">No upcoming blocks.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <a href="reservations.php" class="btn btn-secondary">Back to Reservations</a>
</div>
</body>
</html>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.querySelector('input[name="block_date"]');
    const startInput = document.querySelector('input[name="block_time_start"]');
    const endInput = document.querySelector('input[name="block_time_end"]');
    let openingHours = <?php echo json_encode($restaurant['opening_hours']); ?>;

    // Helper: get enabled dates from opening hours
    function getEnabledDates() {
        let hours;
        try { hours = JSON.parse(openingHours); } catch (e) { hours = {}; }
        // Flatpickr expects an array of functions or dates
        return [function(date) {
            const today = new Date();
            today.setHours(0,0,0,0);
            if (date < today) return false; // Disable previous dates
            const weekday = date.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
            return hours[weekday] && hours[weekday]['open'] && hours[weekday]['close'];
        }];
    }

    // Initialize Flatpickr for date input
    flatpickr(dateInput, {
        dateFormat: "Y-m-d",
        disableMobile: true,
        enable: getEnabledDates(),
        onChange: function(selectedDates, dateStr, instance) {
            if (!dateStr) return;
            setTimeOptions(dateStr);
        }
    });

    // Replace time inputs with Flatpickr time pickers
    flatpickr(startInput, { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true });
    flatpickr(endInput, { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true });

    function setTimeOptions(date) {
        let weekday = new Date(date).toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        let open = null, close = null;
        try {
            let hours = JSON.parse(openingHours);
            if (hours[weekday]) {
                open = hours[weekday]['open'];
                close = hours[weekday]['close'];
            }
        } catch (e) {}
        startInput._flatpickr.set({ minTime: open || '00:00', maxTime: close || '23:59' });
        endInput._flatpickr.set({ minTime: open || '00:00', maxTime: close || '23:59' });
        startInput.value = '';
        endInput.value = '';
        // Fetch blocked times for this date
        fetch('get_blocked_times.php?date=' + date)
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data.blocks)) return;
            let blocks = data.blocks.map(b => [b.block_time_start, b.block_time_end]);
            function isBlocked(t) {
                return blocks.some(([s, e]) => t >= s && t < e);
            }
            startInput.addEventListener('input', function() {
                if (isBlocked(this.value)) {
                    this.setCustomValidity('Start time is blocked.');
                } else {
                    this.setCustomValidity('');
                }
            });
            endInput.addEventListener('input', function() {
                if (isBlocked(this.value)) {
                    this.setCustomValidity('End time is blocked.');
                } else {
                    this.setCustomValidity('');
                }
            });
        });
    }
    // Alertify confirm for delete buttons
    document.querySelectorAll('.btn-delete-block').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var href = this.getAttribute('href');
            alertify.confirm('Delete Blocked Slot', 'Are you sure you want to delete this blocked slot?',
                function() { window.location.href = href; },
                function() { /* Cancelled */ }
            ).set('labels', {ok:'Delete', cancel:'Cancel'}).set('movable', false);
        });
    });
    // Show backend PHP alerts with Alertify
    <?php if (!empty($success)): ?>
        alertify.success('Blocked slot added successfully!');
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        alertify.error('<?= addslashes($error) ?>');
    <?php endif; ?>
    <?php if (isset($_SESSION['block_delete_success'])): ?>
        alertify.success('Blocked slot deleted successfully!');
        <?php unset($_SESSION['block_delete_success']); ?>
    <?php endif; ?>
});
</script>
<?php include 'includes/footer.php'; ?>
