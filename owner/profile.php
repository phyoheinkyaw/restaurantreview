<?php
// Remove PHP debug error reporting for production
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Get owner info (already in $owner from header.php)
$user_id = $owner['user_id'];

// Fetch activity summary
// Number of restaurants
$sql = "SELECT COUNT(*) as cnt FROM restaurants WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$restaurants_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
// Total reservations
$sql = "SELECT COUNT(*) as cnt FROM reservations WHERE restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
// Total reviews
$sql = "SELECT COUNT(*) as cnt FROM reviews WHERE restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE owner_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reviews_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Handle profile update
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    // Profile image upload
    $profile_image = $owner['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'Invalid file type. Only jpg, jpeg, png, gif, webp allowed.';
            } else {
                $filename = 'owner_' . $user_id . '_' . time() . '.' . $ext;
                $upload_dir = realpath(__DIR__ . '/../uploads/profile');
                if ($upload_dir === false) {
                    $upload_dir = __DIR__ . '/../uploads/profile/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                } else {
                    $upload_dir .= '/';
                }
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
                    // Delete old profile image if it exists and is not empty
                    if (!empty($owner['profile_image'])) {
                        $old_path = __DIR__ . '/../uploads/profile/' . $owner['profile_image'];
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    $profile_image = $filename;
                } else {
                    $error = 'Failed to upload profile image. Check directory permissions and PHP file size limits.';
                }
            }
        } else {
            $phpFileUploadErrors = array(
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.'
            );
            $error = 'Profile image upload error: ' . ($phpFileUploadErrors[$_FILES['profile_image']['error']] ?? 'Unknown error.');
        }
    }
    if (!$error) {
        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, profile_image=? WHERE user_id=?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = 'Prepare failed: ' . $conn->error;
        } else {
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $profile_image, $user_id);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully!';
                $owner['first_name'] = $first_name;
                $owner['last_name'] = $last_name;
                $owner['email'] = $email;
                $owner['phone'] = $phone;
                $owner['profile_image'] = $profile_image;
            } else {
                $error = 'Failed to update profile: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}
// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        // Verify current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();
        if (!password_verify($current_password, $hashed_password)) {
            $error = 'Current password is incorrect.';
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_hashed, $user_id);
            if ($stmt->execute()) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password.';
            }
            $stmt->close();
        }
    }
}
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-12">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error:</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-8 offset-lg-2 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light"><h5 class="mb-0">Edit Profile</h5></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <div class="row align-items-center">
                            <!-- Photo, Upload, and Activity Summary (left) -->
                            <div class="col-md-4 text-center mb-3 mb-md-0">
                                <img src="<?php echo !empty($owner['profile_image']) ? '../uploads/profile/' . htmlspecialchars($owner['profile_image']) : 'https://ui-avatars.com/api/?name=' . strtoupper(substr($owner['first_name'], 0, 1)); ?>" class="rounded-circle mb-2" width="120" height="120" id="profilePreview">
                                <input type="file" class="form-control mt-2" name="profile_image" id="profile_image" accept="image/*" onchange="previewImage(event)">
                                <hr>
                                <h6>Activity Summary</h6>
                                <div class="mb-2"><i class="fas fa-store me-2"></i> Restaurants: <strong><?php echo $restaurants_count; ?></strong></div>
                                <div class="mb-2"><i class="fas fa-calendar-check me-2"></i> Reservations: <strong><?php echo $reservations_count; ?></strong></div>
                                <div class="mb-2"><i class="fas fa-star me-2"></i> Reviews Received: <strong><?php echo $reviews_count; ?></strong></div>
                            </div>
                            <!-- Text fields (right) -->
                            <div class="col-md-8">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($owner['first_name']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($owner['last_name']); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="emailshow" class="form-control" value="<?php echo htmlspecialchars($owner['email']); ?>" disabled>
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($owner['email']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($owner['phone']); ?>">
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">Save Changes</button>
                                    <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                </div>
                                <input type="hidden" name="update_profile" value="1">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Change Password Form: centered below profile form -->
        <div class="col-lg-8 offset-lg-2 mb-4">
            <div class="card mb-4">
                <div class="card-header bg-light"><h5 class="mb-0">Change Password</h5></div>
                <div class="card-body">
                    <form method="post" id="passwordForm" autocomplete="off">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" required aria-describedby="passwordHelp">
                            <small id="passwordHelp" class="form-text text-muted">
                                Password must be at least 8 characters, and include uppercase, lowercase, a number, and a special character.
                            </small>
                            <div id="passwordRequirements" class="mt-1">
                                <span id="pw-length" class="text-danger">&#10007; At least 8 characters</span><br>
                                <span id="pw-upper" class="text-danger">&#10007; Uppercase letter</span><br>
                                <span id="pw-lower" class="text-danger">&#10007; Lowercase letter</span><br>
                                <span id="pw-digit" class="text-danger">&#10007; Number</span><br>
                                <span id="pw-special" class="text-danger">&#10007; Special character</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <div id="pw-match" class="mt-1 text-danger">&#10007; Passwords do not match</div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="passwordSubmit" disabled>Change Password</button>
                        <button type="reset" class="btn btn-outline-secondary ms-2">Reset</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('profilePreview');
        output.src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}
// --- Live Password Validation ---
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const pwLength = document.getElementById('pw-length');
const pwUpper = document.getElementById('pw-upper');
const pwLower = document.getElementById('pw-lower');
const pwDigit = document.getElementById('pw-digit');
const pwSpecial = document.getElementById('pw-special');
const pwMatch = document.getElementById('pw-match');
const pwSubmit = document.getElementById('passwordSubmit');
function checkPasswordRequirements() {
    const pw = newPassword.value;
    let valid = true;
    // Length
    if (pw.length >= 8) {
        pwLength.classList.remove('text-danger');
        pwLength.classList.add('text-success');
        pwLength.innerHTML = '&#10003; At least 8 characters';
    } else {
        pwLength.classList.remove('text-success');
        pwLength.classList.add('text-danger');
        pwLength.innerHTML = '&#10007; At least 8 characters';
        valid = false;
    }
    // Uppercase
    if (/[A-Z]/.test(pw)) {
        pwUpper.classList.remove('text-danger');
        pwUpper.classList.add('text-success');
        pwUpper.innerHTML = '&#10003; Uppercase letter';
    } else {
        pwUpper.classList.remove('text-success');
        pwUpper.classList.add('text-danger');
        pwUpper.innerHTML = '&#10007; Uppercase letter';
        valid = false;
    }
    // Lowercase
    if (/[a-z]/.test(pw)) {
        pwLower.classList.remove('text-danger');
        pwLower.classList.add('text-success');
        pwLower.innerHTML = '&#10003; Lowercase letter';
    } else {
        pwLower.classList.remove('text-success');
        pwLower.classList.add('text-danger');
        pwLower.innerHTML = '&#10007; Lowercase letter';
        valid = false;
    }
    // Digit
    if (/[0-9]/.test(pw)) {
        pwDigit.classList.remove('text-danger');
        pwDigit.classList.add('text-success');
        pwDigit.innerHTML = '&#10003; Number';
    } else {
        pwDigit.classList.remove('text-success');
        pwDigit.classList.add('text-danger');
        pwDigit.innerHTML = '&#10007; Number';
        valid = false;
    }
    // Special character
    if (/[!@#$%^&*(),.?\":{}|<>]/.test(pw)) {
        pwSpecial.classList.remove('text-danger');
        pwSpecial.classList.add('text-success');
        pwSpecial.innerHTML = '&#10003; Special character';
    } else {
        pwSpecial.classList.remove('text-success');
        pwSpecial.classList.add('text-danger');
        pwSpecial.innerHTML = '&#10007; Special character';
        valid = false;
    }
    // Match
    if (pw && confirmPassword.value && pw === confirmPassword.value) {
        pwMatch.classList.remove('text-danger');
        pwMatch.classList.add('text-success');
        pwMatch.innerHTML = '&#10003; Passwords match';
    } else {
        pwMatch.classList.remove('text-success');
        pwMatch.classList.add('text-danger');
        pwMatch.innerHTML = '&#10007; Passwords do not match';
        valid = false;
    }
    pwSubmit.disabled = !valid;
}
if (newPassword && confirmPassword) {
    newPassword.addEventListener('input', checkPasswordRequirements);
    confirmPassword.addEventListener('input', checkPasswordRequirements);
}
</script>
<?php require_once 'includes/footer.php'; ?>
