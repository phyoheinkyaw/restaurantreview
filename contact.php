<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get user data if logged in
$user_name = '';
$user_email = '';
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user_name = $user['first_name'] . ' ' . $user['last_name'];
            $user_email = $user['email'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Sanitize inputs
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);
        
        // Validate inputs
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        if (empty($subject)) {
            $errors[] = "Subject is required";
        }
        
        if (empty($message)) {
            $errors[] = "Message is required";
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
                VALUES (:name, :email, :subject, :message, false, NOW())
            ");
            
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':subject', $subject, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->execute();
            
            $success = true;
            $alert_message = "Thank you for your message! We'll get back to you soon.";
            
            // Clear form values after successful submission by redirecting
            header("Location: contact.php?success=1");
            exit;
        } else {
            $success = false;
            $alert_message = implode("<br>", $errors);
        }
    } catch (PDOException $e) {
        error_log("Error saving contact message: " . $e->getMessage());
        $success = false;
        $alert_message = "Sorry, there was an error sending your message. Please try again later.";
    }
}

// Get success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
    $alert_message = "Thank you for your message! We'll get back to you soon.";
}

// Get the page title
$page_title = "Contact Us";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Restaurant Review</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- AlertifyJS CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="row">
            <!-- Contact Form Section -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 class="card-title mb-4">Get in Touch</h2>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> mb-4">
                                <?php echo $alert_message; ?>
                            </div>
                        <?php endif; ?>

                        <form id="contactForm" action="" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo !empty($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($user_name); ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter your name
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo !empty($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user_email); ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a valid email address
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a subject
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                    <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                    <div class="invalid-feedback">
                                        Please enter your message
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button id="submitBtn" type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Office Location Section -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="card-title mb-4">Our Office</h2>
                        
                        <div id="map" style="height: 300px; width: 100%;" class="rounded mb-4"></div>
                        
                        <div class="mt-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                            <i class="fas fa-map-marker-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <h5>Address</h5>
                                            <p class="text-muted mb-0">
                                                123 Food Street<br>
                                                Restaurant City, RC 12345<br>
                                                Restaurant Country
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                            <i class="fas fa-phone text-primary"></i>
                                        </div>
                                        <div>
                                            <h5>Contact</h5>
                                            <p class="text-muted mb-0">
                                                <strong>Phone:</strong> +1 (555) 123-4567<br>
                                                <strong>Email:</strong> info@restaurantreview.com
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                            <i class="fas fa-clock text-primary"></i>
                                        </div>
                                        <div>
                                            <h5>Office Hours</h5>
                                            <p class="text-muted mb-0">
                                                Monday - Friday: 9:00 AM - 6:00 PM<br>
                                                Saturday: 10:00 AM - 2:00 PM<br>
                                                Sunday: Closed
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                                            <i class="fas fa-share-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <h5>Follow Us</h5>
                                            <div class="social-links mt-2">
                                                <a href="#" class="me-2"><i class="fab fa-facebook-f"></i></a>
                                                <a href="#" class="me-2"><i class="fab fa-twitter"></i></a>
                                                <a href="#" class="me-2"><i class="fab fa-instagram"></i></a>
                                                <a href="#" class="me-2"><i class="fab fa-linkedin-in"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- AlertifyJS JS -->
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
    <!-- Form validation script -->
    <script>
        // Initialize form validation
        (function () {
            'use strict';
            
            const forms = document.querySelectorAll('.needs-validation');
            const submitBtn = document.getElementById('submitBtn');
            const submitSpinner = document.getElementById('submitSpinner');
            const contactForm = document.getElementById('contactForm');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    } else {
                        // Disable button and show spinner to prevent multiple submissions
                        submitBtn.disabled = true;
                        submitSpinner.classList.remove('d-none');
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });

            // If the form was successfully submitted and redirected back, clear form fields
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            contactForm.reset();
            contactForm.classList.remove('was-validated');
            
            // Auto-fill user data if logged in
            <?php if (!empty($user_name)): ?>
            document.getElementById('name').value = "<?php echo htmlspecialchars($user_name); ?>";
            <?php endif; ?>
            
            <?php if (!empty($user_email)): ?>
            document.getElementById('email').value = "<?php echo htmlspecialchars($user_email); ?>";
            <?php endif; ?>
            <?php endif; ?>
        })();

        // Initialize map
        document.addEventListener('DOMContentLoaded', function() {
            const map = L.map('map').setView([40.7128, -74.0060], 13); // Default to New York
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add marker for our office
            L.marker([40.7128, -74.0060]).addTo(map)
                .bindPopup('<h5>Restaurant Review Office</h5><p>123 Food Street, Restaurant City</p>')
                .openPopup();
        });
        
        <?php if (isset($success) && $success): ?>
        // Show success notification
        alertify.success('<?php echo $alert_message; ?>');
        <?php elseif (isset($success) && !$success): ?>
        // Show error notification
        alertify.error('<?php echo $alert_message; ?>');
        <?php endif; ?>
    </script>
</body>
</html>
