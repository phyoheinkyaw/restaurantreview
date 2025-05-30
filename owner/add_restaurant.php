<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Include database connection and header
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Ensure user is logged in as an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: ../login.php");
    exit;
}

// Initialize variables
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $cuisine_type = trim($_POST['cuisine_type']);
    $address = trim($_POST['address']);
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $website = trim($_POST['website'] ?? '');
    $price_range = $_POST['price_range'];
    
    // Optional features
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $is_wheelchair_accessible = isset($_POST['is_wheelchair_accessible']) ? 1 : 0;
    $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
    
    // Deposit settings
    $deposit_required = isset($_POST['deposit_required']) ? 1 : 0;
    $deposit_amount = $deposit_required ? floatval($_POST['deposit_amount']) : 0;
    $deposit_account_name = $deposit_required ? trim($_POST['deposit_account_name']) : '';
    $deposit_account_number = $deposit_required ? trim($_POST['deposit_account_number']) : '';
    $deposit_bank_name = $deposit_required ? trim($_POST['deposit_bank_name']) : '';
    $deposit_payment_instructions = $deposit_required ? trim($_POST['deposit_payment_instructions']) : '';
    
    // Validate required fields
    if (empty($name)) {
        $errors[] = "Restaurant name is required";
    }
    
    if (empty($cuisine_type)) {
        $errors[] = "Cuisine type is required";
    }
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = "Invalid website URL";
    }
    
    // Validate deposit info if required
    if ($deposit_required) {
        if (empty($deposit_account_name)) {
            $errors[] = "Account name is required when deposit is enabled";
        }
        if (empty($deposit_account_number)) {
            $errors[] = "Account number is required when deposit is enabled";
        }
        if (empty($deposit_bank_name)) {
            $errors[] = "Bank name is required when deposit is enabled";
        }
        if ($deposit_amount <= 0) {
            $errors[] = "Deposit amount must be greater than zero";
        }
    }
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_type = $_FILES['image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Only JPG, JPEG, and PNG images are allowed";
        } else {
            // Get file extension
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            
            // Generate filename based on restaurant name
            $base_filename = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9\s]/', '', $name)));
            $random_suffix = mt_rand(1000, 9999); // 4-digit random number
            $file_name = $base_filename . '_' . $random_suffix . '.' . $file_extension;
            
            $upload_dir = '../uploads/restaurants/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image = $file_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // Initialize default opening hours (empty structure)
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $opening_hours = [];
    foreach ($days as $day) {
        $opening_hours[$day] = [
            'open' => '',
            'close' => ''
        ];
    }
    $opening_hours_json = json_encode($opening_hours);
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Create SQL statement
            $sql = "INSERT INTO restaurants (
                owner_id, name, description, cuisine_type, address, 
                latitude, longitude, phone, email, website, price_range, opening_hours, 
                image, has_parking, is_wheelchair_accessible, has_wifi,
                is_featured, is_active, deposit_required, deposit_amount,
                deposit_account_name, deposit_account_number, deposit_bank_name,
                deposit_payment_instructions
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                0, 1, ?, ?,
                ?, ?, ?,
                ?
            )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issssddssssssiiiidssss",
                $_SESSION['user_id'], $name, $description, $cuisine_type, $address,
                $latitude, $longitude, $phone, $email, $website, $price_range, $opening_hours_json,
                $image, $has_parking, $is_wheelchair_accessible, $has_wifi,
                $deposit_required, $deposit_amount,
                $deposit_account_name, $deposit_account_number, $deposit_bank_name,
                $deposit_payment_instructions
            );
            
            if ($stmt->execute()) {
                $new_restaurant_id = $stmt->insert_id;
                $_SESSION['current_restaurant_id'] = $new_restaurant_id;
                $_SESSION['status_message'] = "Restaurant '{$name}' has been added successfully!";
                header("Location: manage_restaurant.php");
                exit;
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
            
            $stmt->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid p-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Add New Restaurant</h5>
                    <a href="restaurants.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Restaurants
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Restaurant Name*</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                    <div class="invalid-feedback">Please enter restaurant name</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cuisine_type" class="form-label">Cuisine Type*</label>
                                    <input type="text" class="form-control" id="cuisine_type" name="cuisine_type" value="<?php echo isset($_POST['cuisine_type']) ? htmlspecialchars($_POST['cuisine_type']) : ''; ?>" required>
                                    <div class="invalid-feedback">Please specify cuisine type</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price_range" class="form-label">Price Range*</label>
                                    <select class="form-select" id="price_range" name="price_range" required>
                                        <option value="" disabled <?php echo !isset($_POST['price_range']) ? 'selected' : ''; ?>>Select price range</option>
                                        <option value="$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] === '$') ? 'selected' : ''; ?>>$ (Inexpensive)</option>
                                        <option value="$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] === '$$') ? 'selected' : ''; ?>>$$ (Moderate)</option>
                                        <option value="$$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] === '$$$') ? 'selected' : ''; ?>>$$$ (Expensive)</option>
                                        <option value="$$$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] === '$$$$') ? 'selected' : ''; ?>>$$$$ (Very Expensive)</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a price range</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="image" class="form-label">Restaurant Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                                    <div class="form-text">Upload a high-quality image of your restaurant (JPG, JPEG, PNG only)</div>
                                    <div id="imagePreview" class="mt-2 d-none">
                                        <img src="" alt="Image Preview" class="img-thumbnail" style="max-height: 200px;">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Contact Information</h5>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address*</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                    <div class="invalid-feedback">Please provide the restaurant address</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="latitude" class="form-label">Latitude</label>
                                        <input type="number" step="any" class="form-control" id="latitude" name="latitude" value="<?php echo isset($_POST['latitude']) ? htmlspecialchars($_POST['latitude']) : ''; ?>">
                                        <div class="form-text">Decimal format (e.g., 40.7128)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="longitude" class="form-label">Longitude</label>
                                        <input type="number" step="any" class="form-control" id="longitude" name="longitude" value="<?php echo isset($_POST['longitude']) ? htmlspecialchars($_POST['longitude']) : ''; ?>">
                                        <div class="form-text">Decimal format (e.g., -74.0060)</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number*</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                    <div class="invalid-feedback">Please provide a contact phone number</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="website" class="form-label">Website</label>
                                    <input type="url" class="form-control" id="website" name="website" value="<?php echo isset($_POST['website']) ? htmlspecialchars($_POST['website']) : ''; ?>" placeholder="https://example.com">
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Features</h5>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="has_parking" name="has_parking" <?php echo isset($_POST['has_parking']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_parking">
                                        Has Parking
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_wheelchair_accessible" name="is_wheelchair_accessible" <?php echo isset($_POST['is_wheelchair_accessible']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_wheelchair_accessible">
                                        Wheelchair Accessible
                                    </label>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="has_wifi" name="has_wifi" <?php echo isset($_POST['has_wifi']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="has_wifi">
                                        Free WiFi
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Deposit Settings</h5>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="deposit_required" name="deposit_required" <?php echo isset($_POST['deposit_required']) ? 'checked' : ''; ?> onclick="toggleDepositFields()">
                                    <label class="form-check-label" for="deposit_required">
                                        Require Deposit for Reservations
                                    </label>
                                </div>
                                
                                <div id="depositFields" class="<?php echo isset($_POST['deposit_required']) ? '' : 'd-none'; ?>">
                                    <div class="mb-3">
                                        <label for="deposit_amount" class="form-label">Deposit Amount*</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" class="form-control" id="deposit_amount" name="deposit_amount" value="<?php echo isset($_POST['deposit_amount']) ? htmlspecialchars($_POST['deposit_amount']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deposit_account_name" class="form-label">Account Name*</label>
                                        <input type="text" class="form-control" id="deposit_account_name" name="deposit_account_name" value="<?php echo isset($_POST['deposit_account_name']) ? htmlspecialchars($_POST['deposit_account_name']) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deposit_account_number" class="form-label">Account Number*</label>
                                        <input type="text" class="form-control" id="deposit_account_number" name="deposit_account_number" value="<?php echo isset($_POST['deposit_account_number']) ? htmlspecialchars($_POST['deposit_account_number']) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deposit_bank_name" class="form-label">Bank Name*</label>
                                        <input type="text" class="form-control" id="deposit_bank_name" name="deposit_bank_name" value="<?php echo isset($_POST['deposit_bank_name']) ? htmlspecialchars($_POST['deposit_bank_name']) : ''; ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="deposit_payment_instructions" class="form-label">Payment Instructions</label>
                                        <textarea class="form-control" id="deposit_payment_instructions" name="deposit_payment_instructions" rows="3"><?php echo isset($_POST['deposit_payment_instructions']) ? htmlspecialchars($_POST['deposit_payment_instructions']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> Add Restaurant
                            </button>
                            <a href="restaurants.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Toggle deposit fields
function toggleDepositFields() {
    const depositRequired = document.getElementById('deposit_required').checked;
    const depositFields = document.getElementById('depositFields');
    
    if (depositRequired) {
        depositFields.classList.remove('d-none');
    } else {
        depositFields.classList.add('d-none');
    }
}

// Image preview functionality
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = preview.querySelector('img');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.classList.remove('d-none');
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        previewImg.src = '';
        preview.classList.add('d-none');
    }
}
</script>

<?php 
// Include footer
require_once 'includes/footer.php';

// Flush the output buffer
ob_end_flush();
?> 