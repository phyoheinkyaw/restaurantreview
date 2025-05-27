<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;
$user = getUserData($user_id);

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $phone = sanitize($_POST['phone']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Handle password change if provided
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        // Validate new password
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = 'Password must contain at least one number';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Validate password confirmation
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        }
    }
    
    // Handle profile image upload
    $profile_image = $user['profile_image']; // Default to current image
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $upload_result = handleFileUpload($_FILES['profile_image'], 'uploads/profile', $allowed_types);
        
        if ($upload_result === false) {
            $errors[] = 'Failed to upload profile image. Please try again.';
        } else {
            $profile_image = $upload_result;
        }
    }
    
    // Update user data if no errors
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Prepare SQL based on whether password is being changed
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET 
                        password = :password,
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        profile_image = :profile_image
                        WHERE user_id = :user_id";
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            } else {
                $sql = "UPDATE users SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        phone = :phone,
                        profile_image = :profile_image
                        WHERE user_id = :user_id";
                
                $stmt = $db->prepare($sql);
            }
            
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':profile_image', $profile_image, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $success = true;
                $user = getUserData($user_id); // Reload user data
            } else {
                $errors[] = 'Update failed. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $errors[] = 'An error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Restaurant Review</title>
    
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-4">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                 class="rounded-circle img-fluid mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center mx-auto mb-3" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mb-0"><?php echo htmlspecialchars($user['username']); ?></h5>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <span>Points</span>
                            <span class="badge bg-success rounded-pill"><?php echo htmlspecialchars($user['points']); ?></span>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <a href="profile.php" class="list-group-item list-group-item-action active">
                            <i class="fas fa-user me-2"></i> My Profile
                        </a>
                        <a href="reservations.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar me-2"></i> My Reservations
                        </a>
                        <a href="reviews.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-star me-2"></i> My Reviews
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Edit Profile</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>Your profile has been updated successfully!
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <!-- Basic Information -->
                            <h5 class="border-bottom pb-2 mb-3">Basic Information</h5>
                            
                            <div class="row mb-3">
                                <!-- Profile Image Upload -->
                                <div class="col-md-3 text-center">
                                    <div class="mb-3">
                                        <label for="profile_image" class="form-label d-block">Profile Image</label>
                                        <div class="profile-image-preview mb-2">
                                            <?php if (!empty($user['profile_image'])): ?>
                                                <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                     alt="Profile Preview" 
                                                     class="img-thumbnail rounded-circle" 
                                                     style="width: 100px; height: 100px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded-circle d-flex justify-content-center align-items-center mx-auto" 
                                                     style="width: 100px; height: 100px; font-size: 2rem;">
                                                    <i class="fas fa-user text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                        <div class="form-text">Max size: 2MB</div>
                                    </div>
                                </div>
                            
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name"
                                                   value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Password Change -->
                            <h5 class="border-bottom pb-2 mb-3">Change Password</h5>
                            <p class="text-muted small">Leave blank if you don't want to change your password</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-4">
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Statistics -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Account Statistics</h4>
                    </div>
                    
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="p-3 border rounded">
                                    <h2 class="text-primary"><?php echo $user['points']; ?></h2>
                                    <p class="mb-0">Total Points</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="p-3 border rounded">
                                    <?php 
                                    $db = getDB();
                                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reviews WHERE user_id = :user_id");
                                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $review_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    ?>
                                    <h2 class="text-primary"><?php echo $review_count; ?></h2>
                                    <p class="mb-0">Reviews</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded">
                                    <?php 
                                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id = :user_id");
                                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                                    $stmt->execute();
                                    $reservation_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    ?>
                                    <h2 class="text-primary"><?php echo $reservation_count; ?></h2>
                                    <p class="mb-0">Reservations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Profile image preview
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const preview = document.querySelector('.profile-image-preview');
            const file = this.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Profile Preview" 
                             class="img-thumbnail rounded-circle" 
                             style="width: 100px; height: 100px; object-fit: cover;">
                    `;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Password visibility toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const targetInput = document.getElementById(targetId);
                
                // Toggle password visibility
                const type = targetInput.getAttribute('type') === 'password' ? 'text' : 'password';
                targetInput.setAttribute('type', type);
                
                // Toggle eye icon
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
        
        // Form validation
        (function() {
            'use strict';
            
            // Fetch forms that need validation
            var forms = document.querySelectorAll('.needs-validation');
            
            // Loop over and prevent submission
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Success message handling
        <?php if ($success): ?>
        // Scroll to top to see the success message
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Use AlertifyJS if available
        if (typeof alertify !== 'undefined') {
            alertify.success('Your profile has been updated successfully!');
        }
        <?php endif; ?>
    </script>
</body>
</html> 