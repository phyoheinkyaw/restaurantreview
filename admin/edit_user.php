<?php
// Include header
include 'includes/header.php';

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger">User ID is required.</div>';
    echo '<a href="users.php" class="btn btn-primary">Back to Users</a>';
    include 'includes/footer.php';
    exit;
}

$user_id = (int)$_GET['id'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate required fields
    $errors = [];
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($role) || !in_array($role, ['user', 'owner', 'admin'])) {
        $errors[] = "Valid role is required.";
    }
    
    // Check if new password is provided
    $password_updated = false;
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        } else {
            $password_updated = true;
        }
    }
    
    // Check if username or email already exists (excluding current user)
    $sql = "SELECT COUNT(*) as count FROM users WHERE (username = ? OR email = ?) AND user_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $username, $email, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['count'] > 0) {
        $errors[] = "Username or email already exists.";
    }
    
    // Get current user data for profile image
    $sql = "SELECT profile_image FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_user = $result->fetch_assoc();
    $stmt->close();
    
    // Handle profile image upload
    $profile_image = $current_user['profile_image'] ?? '';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $target_dir = "../uploads/profiles/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if (!empty($profile_image) && file_exists("../" . $profile_image)) {
                    unlink("../" . $profile_image);
                }
                $profile_image = 'uploads/profiles/' . $new_filename;
            } else {
                $errors[] = "Failed to upload profile image.";
            }
        } else {
            $errors[] = "Invalid image format. Allowed formats: " . implode(', ', $allowed);
        }
    }
    
    // If no errors, update user
    if (empty($errors)) {
        if ($password_updated) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET 
                    username = ?, email = ?, password = ?, role = ?, 
                    first_name = ?, last_name = ?, phone = ?, profile_image = ?
                    WHERE user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssi", 
                $username, $email, $hashed_password, $role, 
                $first_name, $last_name, $phone, $profile_image, $user_id
            );
        } else {
            $sql = "UPDATE users SET 
                    username = ?, email = ?, role = ?, 
                    first_name = ?, last_name = ?, phone = ?, profile_image = ?
                    WHERE user_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", 
                $username, $email, $role, 
                $first_name, $last_name, $phone, $profile_image, $user_id
            );
        }
        
        if ($stmt->execute()) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    User updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
        } else {
            $errors[] = "Failed to update user: " . $conn->error;
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

// Get user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">User not found.</div>';
    echo '<a href="users.php" class="btn btn-primary">Back to Users</a>';
    include 'includes/footer.php';
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if editing own account
$is_self = ($user_id == $_SESSION['user_id']);
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Edit User</h1>
    <a href="users.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to Users
    </a>
</div>

<!-- Edit User Form -->
<div class="card">
    <div class="card-body">
        <form action="" method="POST" enctype="multipart/form-data" class="row g-3">
            <!-- Account Information -->
            <div class="col-12">
                <h5 class="border-bottom pb-2 mb-3">Account Information</h5>
            </div>
            
            <div class="col-md-6">
                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="username" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role" required <?php echo $is_self ? 'disabled' : ''; ?>>
                    <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Regular User</option>
                    <option value="owner" <?php echo ($user['role'] == 'owner') ? 'selected' : ''; ?>>Restaurant Owner</option>
                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                </select>
                <?php if ($is_self): ?>
                    <div class="form-text text-warning">You cannot change your own role.</div>
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($user['role']); ?>">
                <?php endif; ?>
            </div>
            
            <div class="col-md-6">
                <label for="created_at" class="form-label">Joined</label>
                <input type="text" class="form-control" id="created_at" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" disabled>
            </div>
            
            <!-- Password Change -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2 mb-3">Change Password</h5>
                <div class="form-text mb-3">Leave blank to keep current password.</div>
            </div>
            
            <div class="col-md-6">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password">
                <div class="form-text">Password must be at least 8 characters long.</div>
            </div>
            
            <div class="col-md-6">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
            </div>
            
            <!-- Personal Information -->
            <div class="col-12 mt-4">
                <h5 class="border-bottom pb-2 mb-3">Personal Information</h5>
            </div>
            
            <div class="col-md-6">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
            </div>
            
            <div class="col-md-6">
                <label for="profile_image" class="form-label">Profile Image</label>
                <?php if (!empty($user['profile_image'])): ?>
                    <div class="mb-2">
                        <img src="../<?php echo $user['profile_image']; ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="img-thumbnail" style="max-height: 100px;">
                    </div>
                <?php endif; ?>
                <input type="file" class="form-control" id="profile_image" name="profile_image">
                <div class="form-text">Leave empty to keep current image. Recommended size: 200x200 pixels. Max file size: 2MB.</div>
            </div>
            
            <!-- Submit Button -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i> Update User
                </button>
                <a href="users.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Password strength validation
    $('#new_password').on('input', function() {
        const password = $(this).val();
        
        if (password.length > 0) {
            let strength = 0;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            const strengthBar = $('#password-strength');
            
            switch (strength) {
                case 0:
                case 1:
                    strengthBar.removeClass().addClass('progress-bar bg-danger').css('width', '20%').text('Very Weak');
                    break;
                case 2:
                    strengthBar.removeClass().addClass('progress-bar bg-warning').css('width', '40%').text('Weak');
                    break;
                case 3:
                    strengthBar.removeClass().addClass('progress-bar bg-info').css('width', '60%').text('Medium');
                    break;
                case 4:
                    strengthBar.removeClass().addClass('progress-bar bg-primary').css('width', '80%').text('Strong');
                    break;
                case 5:
                    strengthBar.removeClass().addClass('progress-bar bg-success').css('width', '100%').text('Very Strong');
                    break;
            }
        }
    });
    
    // Password confirmation validation
    $('#confirm_password').on('input', function() {
        const password = $('#new_password').val();
        const confirmPassword = $(this).val();
        
        if (password === confirmPassword) {
            $(this).removeClass('is-invalid').addClass('is-valid');
        } else {
            $(this).removeClass('is-valid').addClass('is-invalid');
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 