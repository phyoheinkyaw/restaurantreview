<footer class="footer bg-dark text-light py-5 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="mb-4">About Restaurant Review</h5>
                <p>Discover and review the best restaurants in your area. Make reservations and earn rewards with every visit.</p>
                <div class="social-links mt-3">
                    <a href="#" class="text-light me-3"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-light"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2">
                <h5 class="mb-4">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="about.php" class="text-decoration-none">About Us</a></li>
                    <li class="mb-2"><a href="contact.php" class="text-decoration-none">Contact</a></li>
                    <li class="mb-2"><a href="terms.php" class="text-decoration-none">Terms of Service</a></li>
                    <li class="mb-2"><a href="privacy.php" class="text-decoration-none">Privacy Policy</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3">
                <h5 class="mb-4">Popular Categories</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="search.php?cuisine=italian" class="text-decoration-none">Italian Restaurants</a></li>
                    <li class="mb-2"><a href="search.php?cuisine=japanese" class="text-decoration-none">Japanese Restaurants</a></li>
                    <li class="mb-2"><a href="search.php?cuisine=mexican" class="text-decoration-none">Mexican Restaurants</a></li>
                    <li class="mb-2"><a href="search.php?cuisine=indian" class="text-decoration-none">Indian Restaurants</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3">
                <h5 class="mb-4">Newsletter</h5>
                <p>Subscribe to our newsletter for updates and exclusive offers.</p>
                <form class="newsletter-form">
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Enter your email">
                        <button class="btn btn-primary" type="submit">Subscribe</button>
                    </div>
                </form>
            </div>
        </div>
        
        <hr class="bg-light my-4">
        
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> Restaurant Review. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <img src="assets/images/payment-methods.png" alt="Payment Methods" height="30">
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AlertifyJS JS -->
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
<script>
    // Configure AlertifyJS defaults
    alertify.defaults = {
        autoReset: true,
        basic: false,
        closable: true,
        closableByDimmer: true,
        frameless: false,
        maintainFocus: true,
        maximizable: true,
        modal: true,
        movable: true,
        moveBounded: false,
        overflow: true,
        padding: true,
        pinnable: true,
        pinned: true,
        preventBodyShift: false,
        resizable: true,
        startMaximized: false,
        transition: 'zoom',
        notifier: {
            delay: 5,
            position: 'top-right',
            closeButton: false
        },
        glossary: {
            title: 'Alert',
            ok: 'OK',
            cancel: 'Cancel'
        }
    };
</script>
<!-- Custom JS -->
<script src="assets/js/main.js"></script>
