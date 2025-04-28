<?php
// Add content overlay for mobile
echo '<div class="admin-content-overlay" onclick="document.querySelector(\'.admin-sidebar\').classList.remove(\'show\'); this.classList.remove(\'show\');"></div>';
?>
<div class="admin-sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-utensils me-2"></i>
            Restaurant Review
        </a>
    </div>
    
    <div class="sidebar-content">
        <div class="nav flex-column">
            <div class="nav-header">MAIN</div>
            <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <?php if ($has_restaurants): ?>
            <div class="nav-header">RESTAURANT MANAGEMENT</div>
            <a href="restaurants.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'restaurants.php' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                <span>My All Restaurants</span>
            </a>
            <a href="manage_restaurant.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_restaurant.php' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i>
                <span>Manage Restaurant</span>
            </a>
            <a href="menu.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Manage Menu</span>
            </a>
            <a href="reservations.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reservations.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Reservations</span>
                <?php if (isset($notification_count) && $notification_count > 0): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="reviews.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Customer Reviews</span>
            </a>
            <a href="block_slots.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'block_slots.php' ? 'active' : ''; ?>">
                <i class="fas fa-ban"></i>
                <span>Blocked Slots</span>
            </a>
            <?php else: ?>
            <div class="nav-header">GET STARTED</div>
            <a href="add_restaurant.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'add_restaurant.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Your Restaurant</span>
            </a>
            <?php endif; ?>
            
            <div class="nav-header">ACCOUNT</div>
            <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-outline-light w-100">
            <i class="fas fa-sign-out-alt me-2"></i>
            Logout
        </a>
    </div>
</div> 