<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();

    try {
        $stmt = $db->prepare("
            INSERT INTO contact_messages (name, email, subject, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['subject'],
            $_POST['message']
        ]);
        
        $success = true;
        $message = "Thank you for your message! We'll get back to you soon.";
    } catch (PDOException $e) {
        error_log("Error saving contact message: " . $e->getMessage());
        $success = false;
        $message = "Sorry, there was an error sending your message. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Restaurant Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container py-5">
        <div class="row">
            <!-- Contact Form Section -->
            <div class="col-lg-6 mb-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h2 class="h3 mb-4">Get in Touch</h2>
                        
                        <?php if (isset($success)): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Office Location Section -->
            <div class="col-lg-6 mb-5">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h2 class="h3 mb-4">Our Office</h2>
                        
                        <div id="map" style="height: 400px; width: 100%;"></div>
                        
                        <div class="mt-4">
                            <h4>Address</h4>
                            <p class="text-muted">
                                123 Food Street<br>
                                Restaurant City, RC 12345<br>
                                Restaurant Country
                            </p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h4>Phone</h4>
                                    <p class="text-muted">
                                        <i class="fas fa-phone me-2"></i>+1 (555) 123-4567<br>
                                        <i class="fas fa-fax me-2"></i>+1 (555) 123-4568
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h4>Hours</h4>
                                    <p class="text-muted">
                                        Monday - Friday: 9:00 AM - 6:00 PM<br>
                                        Saturday: 10:00 AM - 2:00 PM<br>
                                        Sunday: Closed
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Initialize form validation
        (function () {
            'use strict'
            
            const forms = document.querySelectorAll('.needs-validation')
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    
                    form.classList.add('was-validated')
                }, false)
            })
        })()

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
    </script>
</body>
</html>
