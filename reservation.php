<?php
// Don't start a session here since it's already started in config.php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=reservation.php" . (isset($_GET['id']) ? "?id=" . $_GET['id'] : ""));
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if restaurant ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$restaurant_id = intval($_GET['id']);

// Get restaurant details
$db = getDB();
$stmt = $db->prepare("SELECT * FROM restaurants WHERE restaurant_id = ?");
$stmt->execute([$restaurant_id]);
$restaurant = $stmt->fetch();

if (!$restaurant) {
    header("Location: index.php?error=restaurant_not_found");
    exit;
}

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic reservation details
    $reservation_date = htmlspecialchars(trim($_POST['reservation_date'] ?? ''));
    $reservation_time = htmlspecialchars(trim($_POST['reservation_time'] ?? ''));
    $party_size = intval($_POST['party_size'] ?? 0);
    $special_requests = htmlspecialchars(trim($_POST['special_requests'] ?? ''));
    
    // Validate inputs
    if (empty($reservation_date) || empty($reservation_time) || $party_size < 1) {
        $error_message = "Please fill all required fields.";
    } else {
        // Check if the time slot is blocked
        $stmt = $db->prepare("SELECT * FROM blocked_slots 
                              WHERE restaurant_id = ? 
                              AND block_date = ? 
                              AND ? >= block_time_start 
                              AND ? < block_time_end");
        $stmt->execute([$restaurant_id, $reservation_date, $reservation_time, $reservation_time]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            $error_message = "Sorry, this time slot is no longer available. Please select another time.";
        } else {
            // Set all reservations to pending status by default, requiring owner approval
            $deposit_status = 'not_required';
            $deposit_amount = 0;
            $reservation_status = 'pending';
            $deposit_payment_slip = "";
            
            // Create reservation record
            if ($restaurant['deposit_required']) {
                // If deposit is required, set status to pending
                $deposit_status = 'pending';
                $deposit_amount = $restaurant['deposit_amount'];
                $reservation_status = 'pending';
                
                // Check if a payment slip was uploaded
                $deposit_payment_slip = "";
                if (isset($_FILES['deposit_payment_slip']) && $_FILES['deposit_payment_slip']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = "uploads/payment_slips/";
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($_FILES['deposit_payment_slip']['name'], PATHINFO_EXTENSION);
                    $new_filename = uniqid('payment_') . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Check file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                    if (!in_array($_FILES['deposit_payment_slip']['type'], $allowed_types)) {
                        $error_message = "Only JPG, JPEG, and PNG files are allowed.";
                    } else if ($_FILES['deposit_payment_slip']['size'] > 5000000) { // 5MB max
                        $error_message = "File is too large. Maximum size is 5MB.";
                    } else if (move_uploaded_file($_FILES['deposit_payment_slip']['tmp_name'], $upload_path)) {
                        $deposit_payment_slip = $upload_path;
                    } else {
                        $error_message = "Failed to upload payment slip.";
                    }
                } else {
                    $error_message = "Please upload your deposit payment slip.";
                }
            } else {
                // If no deposit required, set status to not_required and confirm reservation
                $deposit_status = 'not_required';
                $deposit_amount = 0;
                $reservation_status = 'pending';
                $deposit_payment_slip = "";
            }
            
            // If no errors, proceed with creating the reservation
            if (!isset($error_message)) {
                $sql = "INSERT INTO reservations (
                            user_id, restaurant_id, reservation_date, reservation_time, 
                            party_size, status, special_requests, 
                            deposit_status, deposit_amount, deposit_payment_slip, deposit_payment_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $user_id, $restaurant_id, $reservation_date, $reservation_time, 
                    $party_size, $reservation_status, $special_requests, 
                    $deposit_status, $deposit_amount, $deposit_payment_slip
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $reservation_id = $db->lastInsertId();
                    
                    // Redirect to confirmation page
                    header("Location: reservation_confirmation.php?id=" . $reservation_id);
                    exit;
                } else {
                    $error_message = "Error creating reservation: " . $db->errorInfo()[2];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Reservation - <?php echo htmlspecialchars($restaurant['name']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Flatpickr for date/time -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="mb-3">Make a Reservation</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="restaurant.php?id=<?php echo $restaurant_id; ?>"><?php echo htmlspecialchars($restaurant['name']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reservation</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Reservation Details</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="reservationForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="reservation_date" class="form-label">Date*</label>
                                    <input type="text" class="form-control" id="reservation_date" name="reservation_date" required>
                                    <div id="date-feedback" class="invalid-feedback"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="reservation_time" class="form-label">Time*</label>
                                    <select class="form-select" id="reservation_time" name="reservation_time" required disabled>
                                        <option value="">Select a date first</option>
                                    </select>
                                    <div id="time-feedback" class="invalid-feedback"></div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="party_size" class="form-label">Number of Guests*</label>
                                <select class="form-select" id="party_size" name="party_size" required>
                                    <?php for ($i = 1; $i <= 20; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php echo $i === 1 ? 'person' : 'people'; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="special_requests" class="form-label">Special Requests</label>
                                <textarea class="form-control" id="special_requests" name="special_requests" rows="3" placeholder="Any special requests or dietary requirements..."></textarea>
                            </div>
                            
                            <?php if ($restaurant['deposit_required']): ?>
                                <div class="alert alert-info mb-3">
                                    <h5><i class="fas fa-info-circle me-2"></i>Deposit Required</h5>
                                    <p>This restaurant requires a deposit of $<?php echo number_format($restaurant['deposit_amount'], 2); ?> to secure your reservation.</p>
                                    
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Payment Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-1"><strong>Bank:</strong> <?php echo htmlspecialchars($restaurant['deposit_bank_name']); ?></p>
                                            <p class="mb-1"><strong>Account Name:</strong> <?php echo htmlspecialchars($restaurant['deposit_account_name']); ?></p>
                                            <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($restaurant['deposit_account_number']); ?></p>
                                            <?php if (!empty($restaurant['deposit_payment_instructions'])): ?>
                                                <p class="mt-2"><strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($restaurant['deposit_payment_instructions'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">Deposit Process</h6>
                                        </div>
                                        <div class="card-body">
                                            <ol class="mb-0">
                                                <li class="mb-2">Make a payment of $<?php echo number_format($restaurant['deposit_amount'], 2); ?> to the account listed above.</li>
                                                <li class="mb-2">Take a screenshot or photo of your payment confirmation.</li>
                                                <li class="mb-2">Upload the payment proof below.</li>
                                                <li class="mb-2">The restaurant will verify your payment.</li>
                                                <li>Once verified, your reservation will be confirmed.</li>
                                            </ol>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deposit_payment_slip" class="form-label">Upload Payment Slip*</label>
                                        <input type="file" class="form-control" id="deposit_payment_slip" name="deposit_payment_slip" accept="image/jpeg,image/png,image/jpg" required>
                                        <div class="form-text">
                                            Please upload a screenshot or photo of your deposit payment slip. Accepted formats: JPG, JPEG, PNG (Max: 5MB)
                                        </div>
                                        <div id="slip-preview-container" class="mt-3 text-center d-none">
                                            <div class="card">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">Preview</h6>
                                                    <button type="button" class="btn-close" id="clear-preview" aria-label="Close"></button>
                                                </div>
                                                <div class="card-body">
                                                    <img id="slip-preview" src="#" alt="Payment Slip Preview" class="img-fluid rounded" style="max-height: 300px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary" id="submitButton">
                                    <i class="fas fa-calendar-check me-1"></i> Complete Reservation
                                </button>
                                <a href="restaurant.php?id=<?php echo $restaurant_id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><?php echo htmlspecialchars($restaurant['name']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($restaurant['image'])): ?>
                            <img src="<?php echo htmlspecialchars($restaurant['image']); ?>" alt="<?php echo htmlspecialchars($restaurant['name']); ?>" class="img-fluid rounded mb-3">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-primary"></i><?php echo htmlspecialchars($restaurant['address']); ?></p>
                            <p class="mb-1"><i class="fas fa-phone me-2 text-primary"></i><?php echo htmlspecialchars($restaurant['phone']); ?></p>
                            <p class="mb-1"><i class="fas fa-utensils me-2 text-primary"></i><?php echo htmlspecialchars($restaurant['cuisine_type']); ?></p>
                            <p class="mb-1"><i class="fas fa-tag me-2 text-primary"></i>Price Range: <?php echo htmlspecialchars($restaurant['price_range']); ?></p>
                        </div>
                        
                        <div class="mt-3">
                            <h5>Your Information</h5>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">Reservation Policy</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Please arrive on time</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>If you're running late, please call the restaurant</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Cancellations should be made at least 24 hours in advance</li>
                            <?php if ($restaurant['deposit_required']): ?>
                                <li class="mb-2"><i class="fas fa-exclamation-circle text-warning me-2"></i>Deposit is required to confirm reservation</li>
                                <li class="mb-2"><i class="fas fa-exclamation-circle text-warning me-2"></i>Deposit will be deducted from your final bill</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const reservationForm = document.getElementById('reservationForm');
            const submitButton = document.getElementById('submitButton');
            const dateFeedback = document.getElementById('date-feedback');
            const timeFeedback = document.getElementById('time-feedback');
            const dateInput = document.getElementById('reservation_date');
            const timeSelect = document.getElementById('reservation_time');
            
            // Initialize date picker
            flatpickr("#reservation_date", {
                minDate: "today",
                dateFormat: "Y-m-d",
                disableMobile: "true",
                onChange: function(selectedDates, dateStr) {
                    // When date changes, fetch available time slots
                    if (dateStr) {
                        // Reset validation states
                        dateInput.classList.remove('is-invalid');
                        dateFeedback.textContent = '';
                        
                        timeSelect.disabled = true;
                        timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
                        
                        // Fetch available time slots
                        fetch(`get_timeslots.php?restaurant_id=<?php echo $restaurant_id; ?>&date=${dateStr}`)
                            .then(response => response.json())
                            .then(data => {
                                timeSelect.innerHTML = '';
                                
                                if (data.error) {
                                    // Handle error response
                                    timeSelect.innerHTML = `<option value="">${data.error}</option>`;
                                    console.error('Error loading time slots:', data.error);
                                } else if (data.length === 0) {
                                    // Handle no time slots
                                    timeSelect.innerHTML = '<option value="">No available time slots</option>';
                                } else {
                                    // First create optgroup for available slots
                                    const availableGroup = document.createElement('optgroup');
                                    availableGroup.label = 'Available Times';
                                    
                                    // Then create optgroup for unavailable slots
                                    const unavailableGroup = document.createElement('optgroup');
                                    unavailableGroup.label = 'Unavailable Times';
                                    
                                    // Track if we have any available times
                                    let hasAvailableTimes = false;
                                    
                                    // Add time slots
                                    data.forEach(slot => {
                                        const option = document.createElement('option');
                                        option.value = slot.available ? slot.time : '';
                                        option.textContent = slot.time;
                                        
                                        if (slot.available) {
                                            availableGroup.appendChild(option);
                                            hasAvailableTimes = true;
                                        } else {
                                            option.disabled = true;
                                            option.textContent = `${slot.time} - ${slot.reason}`;
                                            option.classList.add('text-danger');
                                            unavailableGroup.appendChild(option);
                                        }
                                    });
                                    
                                    // Add groups to select element
                                    if (hasAvailableTimes) {
                                        timeSelect.appendChild(availableGroup);
                                    } else {
                                        // If no available times, add a disabled option
                                        const noOption = document.createElement('option');
                                        noOption.value = '';
                                        noOption.textContent = 'No available times for this date';
                                        timeSelect.appendChild(noOption);
                                    }
                                    
                                    // Add unavailable times if there are any
                                    if (unavailableGroup.children.length > 0) {
                                        timeSelect.appendChild(unavailableGroup);
                                    }
                                }
                                
                                timeSelect.disabled = false;
                            })
                            .catch(error => {
                                console.error('Fetch error:', error);
                                timeSelect.innerHTML = '<option value="">Error loading time slots. Please try again.</option>';
                                timeSelect.disabled = false;
                            });
                    }
                }
            });
            
            // Form validation before submission
            reservationForm.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Check if date is selected
                if (!dateInput.value) {
                    dateInput.classList.add('is-invalid');
                    dateFeedback.textContent = 'Please select a date';
                    isValid = false;
                }
                
                // Check if time is selected
                if (!timeSelect.value) {
                    timeSelect.classList.add('is-invalid');
                    timeFeedback.textContent = 'Please select a time';
                    isValid = false;
                }
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
            
            // Payment slip preview functionality
            const fileInput = document.getElementById('deposit_payment_slip');
            const previewContainer = document.getElementById('slip-preview-container');
            const previewImage = document.getElementById('slip-preview');
            const clearPreviewBtn = document.getElementById('clear-preview');
            
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        
                        // Check file type
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                        if (!validTypes.includes(file.type)) {
                            alert('Please select a valid image file (JPG, JPEG, or PNG).');
                            this.value = '';
                            previewContainer.classList.add('d-none');
                            return;
                        }
                        
                        // Check file size (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File is too large. Maximum size is 5MB.');
                            this.value = '';
                            previewContainer.classList.add('d-none');
                            return;
                        }
                        
                        // Create a FileReader to read and display the image
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImage.src = e.target.result;
                            previewContainer.classList.remove('d-none');
                        };
                        reader.readAsDataURL(file);
                    } else {
                        previewContainer.classList.add('d-none');
                    }
                });
                
                // Clear preview when close button is clicked
                if (clearPreviewBtn) {
                    clearPreviewBtn.addEventListener('click', function() {
                        fileInput.value = '';
                        previewContainer.classList.add('d-none');
                    });
                }
            }
        });
    </script>
</body>
</html> 