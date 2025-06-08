<div class="admin-sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-brand">
            <i class="fas fa-utensils text-primary me-2"></i>
            <span>Restaurant Review</span>
        </a>
    </div>
    
    <div class="sidebar-content">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="restaurants.php" class="nav-link">
                    <i class="fas fa-store"></i>
                    <span>Restaurants</span>
                    <span class="badge bg-primary rounded-pill ms-auto">12</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#reviewsSubmenu" data-bs-toggle="collapse" class="nav-link">
                    <i class="fas fa-star"></i>
                    <span>Reviews</span>
                    <i class="fas fa-chevron-down ms-auto small"></i>
                </a>
                <ul class="collapse nav flex-column ms-4" id="reviewsSubmenu">
                    <li class="nav-item">
                        <a href="reviews.php" class="nav-link">
                            <i class="fas fa-list-ul"></i>
                            <span>All Reviews</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_reviews.php" class="nav-link">
                            <i class="fas fa-th-large"></i>
                            <span>Detailed View</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a href="#reservationsSubmenu" data-bs-toggle="collapse" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Reservations</span>
                    <i class="fas fa-chevron-down ms-auto small"></i>
                </a>
                <ul class="collapse nav flex-column ms-4" id="reservationsSubmenu">
                    <li class="nav-item">
                        <a href="reservations.php" class="nav-link">
                            <i class="fas fa-list-ul"></i>
                            <span>All Reservations</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reservations.php?status=pending" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>Pending</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reservations.php?status=confirmed" class="nav-link">
                            <i class="fas fa-check"></i>
                            <span>Confirmed</span>
                        </a>
                    </li>
                    <!-- <li class="nav-item">
                        <a href="deposit_verifications.php" class="nav-link">
                            <i class="fas fa-money-check-alt"></i>
                            <span>Deposit Verifications</span>
                            <?php
                            // Count pending deposits
                            $pending_deposits_query = "SELECT COUNT(*) as count FROM reservations WHERE deposit_status = 'pending'";
                            $pending_result = $conn->query($pending_deposits_query);
                            if ($pending_result && $pending_count = $pending_result->fetch_assoc()['count']) {
                                if ($pending_count > 0) {
                                    echo '<span class="badge bg-warning rounded-pill ms-auto">' . $pending_count . '</span>';
                                }
                            }
                            ?>
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a href="reservation_calendar.php" class="nav-link">
                            <i class="fas fa-calendar-week"></i>
                            <span>Calendar View</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="nav-header">
                <span>Administration</span>
            </li>
            <li class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Contact Messages</span>
                    <?php
                    // Count unread messages
                    $unread_count_query = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
                    $unread_result = $conn->query($unread_count_query);
                    if ($unread_result && $unread_count = $unread_result->fetch_assoc()['count']) {
                        echo '<span class="badge bg-danger rounded-pill ms-auto">' . $unread_count . '</span>';
                    }
                    ?>
                </a>
            </li>
            <li class="nav-item">
                <a href="currencies.php" class="nav-link">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Currency Management</span>
                </a>
            </li>
            <!-- <li class="nav-item">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logs.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    <span>Logs</span>
                </a>
            </li> -->
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <div class="px-3 py-2">
            <a href="../index.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-globe me-2"></i>
                <span>View Website</span>
            </a>
        </div>
    </div>
</div>

<!-- Mobile overlay -->
<div class="admin-content-overlay"></div> 