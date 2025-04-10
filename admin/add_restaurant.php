<?php
// Include header
include 'includes/header.php';

// Get all restaurant owners (users with owner role)
$owners = [];
$sql = "SELECT user_id, username, first_name, last_name FROM users WHERE role = 'owner' ORDER BY username";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $owners[] = $row;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $name = trim($_POST['name']);
    $owner_id = !empty($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
    $description = trim($_POST['description']);
    $cuisine_type = trim($_POST['cuisine_type']);
    $address = trim($_POST['address']);
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $price_range = $_POST['price_range'];
    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $is_wheelchair_accessible = isset($_POST['is_wheelchair_accessible']) ? 1 : 0;
    $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Opening hours (JSON)
    $opening_hours = [];
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    foreach ($days as $day) {
        if (isset($_POST[$day . '_closed']) && $_POST[$day . '_closed'] == 1) {
            $opening_hours[$day] = 'closed';
        } else {
            $opening_hours[$day] = [
                'open' => $_POST[$day . '_open'] ?? '09:00',
                'close' => $_POST[$day . '_close'] ?? '17:00'
            ];
        }
    }
    $opening_hours_json = json_encode($opening_hours);
    
    // Validate required fields
    $errors = [];
    if (empty($name)) {
        $errors[] = "Restaurant name is required.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "../uploads/restaurants/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = 'uploads/restaurants/' . $new_filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image format. Allowed formats: " . implode(', ', $allowed);
        }
    }
    
    // If no errors, insert restaurant
    if (empty($errors)) {
        $sql = "INSERT INTO restaurants (owner_id, name, description, cuisine_type, address, latitude, longitude, 
                                        phone, email, price_range, opening_hours, image, has_parking, 
                                        is_wheelchair_accessible, has_wifi, is_featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssddsssssiii", 
            $owner_id, $name, $description, $cuisine_type, $address, $latitude, $longitude, 
            $phone, $email, $price_range, $opening_hours_json, $image_path, $has_parking, 
            $is_wheelchair_accessible, $has_wifi, $is_featured
        );
        
        if ($stmt->execute()) {
            $restaurant_id = $stmt->insert_id;
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    Restaurant added successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
            // Clear form data
            $_POST = [];
        } else {
            $errors[] = "Failed to add restaurant: " . $conn->error;
        }
        $stmt->close();
    }
    
    // Display errors
    if (!empty($errors)) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">';
        foreach ($errors as $error) {
            echo '<li>' . $error . '</li>';
        }
        echo '</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Add New Restaurant</h1>
    <a href="restaurants.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Restaurants
    </a>
</div>

<!-- Add Restaurant Form -->
<div class="card">
    <div class="card-body">
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <!-- Basic Information -->
            <div class="col-12">
                <h5 class="border-bottom pb-2 mb-3">Basic Information</h5>
            </div>
            
            <div class="col-md-6">
                <label for="name" class="form-label">Restaurant Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="owner_id" class="form-label">Restaurant Owner</label>
                <select class="form-select" id="owner_id" name="owner_id">
                    <option value="">-- Select Owner --</option>
                    <?php foreach ($owners as $owner): ?>
                        <?php $display_name = $owner['first_name'] && $owner['last_name'] ? 
                                            $owner['first_name'] . ' ' . $owner['last_name'] . ' (' . $owner['username'] . ')' : 
                                            $owner['username']; ?>
                        <option value="<?php echo $owner['user_id']; ?>" <?php echo (isset($_POST['owner_id']) && $_POST['owner_id'] == $owner['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">If no owner is selected, the restaurant will be managed by admin.</div>
            </div>
            
            <div class="col-md-12">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="col-md-6">
                <label for="cuisine_type" class="form-label">Cuisine Type</label>
                <input type="text" class="form-control" id="cuisine_type" name="cuisine_type" value="<?php echo htmlspecialchars($_POST['cuisine_type'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="price_range" class="form-label">Price Range</label>
                <select class="form-select" id="price_range" name="price_range">
                    <option value="$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] == '$') ? 'selected' : ''; ?>>$ (Inexpensive)</option>
                    <option value="$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] == '$$') ? 'selected' : ''; ?>>$$ (Moderate)</option>
                    <option value="$$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] == '$$$') ? 'selected' : ''; ?>>$$$ (Expensive)</option>
                    <option value="$$$$" <?php echo (isset($_POST['price_range']) && $_POST['price_range'] == '$$$$') ? 'selected' : ''; ?>>$$$$ (Very Expensive)</option>
                </select>
            </div>
            
            <!-- Contact Information -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2 mb-3">Contact Information</h5>
            </div>
            
            <div class="col-md-12">
                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="address" name="address" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="latitude" class="form-label">Latitude</label>
                <input type="text" class="form-control" id="latitude" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="longitude" class="form-label">Longitude</label>
                <input type="text" class="form-control" id="longitude" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                <div class="form-text">You can use a service like Google Maps to find coordinates.</div>
            </div>
            
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <!-- Opening Hours -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2 mb-3">Opening Hours</h5>
            </div>
            
            <?php
            $days = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday',
                'sunday' => 'Sunday'
            ];
            
            foreach ($days as $day_key => $day_name):
            ?>
            <div class="col-md-12 mb-2">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <label class="form-label"><?php echo $day_name; ?></label>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input day-closed" type="checkbox" id="<?php echo $day_key; ?>_closed" name="<?php echo $day_key; ?>_closed" value="1" 
                                <?php echo (isset($_POST[$day_key . '_closed']) && $_POST[$day_key . '_closed'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="<?php echo $day_key; ?>_closed">
                                Closed
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 day-hours">
                        <label class="form-label">Open</label>
                        <input type="time" class="form-control" name="<?php echo $day_key; ?>_open" value="<?php echo htmlspecialchars($_POST[$day_key . '_open'] ?? '09:00'); ?>">
                    </div>
                    <div class="col-md-4 day-hours">
                        <label class="form-label">Close</label>
                        <input type="time" class="form-control" name="<?php echo $day_key; ?>_close" value="<?php echo htmlspecialchars($_POST[$day_key . '_close'] ?? '21:00'); ?>">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Features and Image -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2 mb-3">Features and Image</h5>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="image" class="form-label">Restaurant Image</label>
                    <input class="form-control" type="file" id="image" name="image">
                    <div class="form-text">Recommended size: 800x600 pixels. Max file size: 2MB.</div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" 
                            <?php echo (isset($_POST['is_featured']) && $_POST['is_featured'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_featured">
                            Feature this restaurant (will appear on homepage)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">Amenities</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="has_parking" name="has_parking" value="1" 
                                <?php echo (isset($_POST['has_parking']) && $_POST['has_parking'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="has_parking">
                                <i class="fas fa-parking me-2 text-primary"></i> Parking Available
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="is_wheelchair_accessible" name="is_wheelchair_accessible" value="1" 
                                <?php echo (isset($_POST['is_wheelchair_accessible']) && $_POST['is_wheelchair_accessible'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_wheelchair_accessible">
                                <i class="fas fa-wheelchair me-2 text-primary"></i> Wheelchair Accessible
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="has_wifi" name="has_wifi" value="1" 
                                <?php echo (isset($_POST['has_wifi']) && $_POST['has_wifi'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="has_wifi">
                                <i class="fas fa-wifi me-2 text-primary"></i> Free WiFi
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add Restaurant
                </button>
                <a href="restaurants.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle time inputs based on "Closed" checkbox
    $('.day-closed').change(function() {
        const dayHours = $(this).closest('.row').find('.day-hours');
        if ($(this).is(':checked')) {
            dayHours.hide();
        } else {
            dayHours.show();
        }
    }).trigger('change');
    
    // Initialize any address autocomplete or map functionality here
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 