document.addEventListener('DOMContentLoaded', function() {
    // Initialize jQuery for dropdowns
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        $ = jQuery;
    }

    // Toggle sidebar
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const adminContent = document.querySelector('.admin-content');
    const contentOverlay = document.querySelector('.admin-content-overlay');
    
    // Function to check if we're on mobile
    const isMobile = () => window.innerWidth < 768;
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (isMobile()) {
                // On mobile, we expand/collapse differently
                adminSidebar.classList.toggle('expanded');
                if (contentOverlay) {
                    contentOverlay.classList.toggle('show');
                }
            } else {
                // On desktop, we use collapsed/expanded classes
                adminSidebar.classList.toggle('collapsed');
                adminContent.classList.toggle('expanded');
            }
        });
    }
    
    // Close sidebar when clicking on overlay (mobile)
    if (contentOverlay) {
        contentOverlay.addEventListener('click', function() {
            adminSidebar.classList.remove('expanded');
            contentOverlay.classList.remove('show');
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (!isMobile() && adminSidebar.classList.contains('expanded')) {
            adminSidebar.classList.remove('expanded');
            if (contentOverlay) {
                contentOverlay.classList.remove('show');
            }
        }
    });
    
    // Initialize any charts if available
    initCharts();
    
    // Add active class to current sidebar menu item
    const currentPageUrl = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('.admin-sidebar .nav-link');
    
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPageUrl.includes(href)) {
            link.classList.add('active');
        }
    });
    
    // Initialize any data tables if available
    if (typeof $.fn !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        $('.data-table table').DataTable({
            responsive: true,
            scrollX: false,
            autoWidth: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search..."
            },
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ]
        });
    }
    
    // Handle confirmation dialogs
    const confirmActions = document.querySelectorAll('[data-confirm]');
    confirmActions.forEach(action => {
        action.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Initialize Bootstrap 5 tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Initialize Bootstrap 5 dropdowns manually if needed
    const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    if (typeof bootstrap !== 'undefined' && typeof bootstrap.Dropdown !== 'undefined') {
        dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
    }
});

// Chart initialization function
function initCharts() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') return;
    
    // Revenue Chart
    const revenueChart = document.getElementById('revenueChart');
    if (revenueChart) {
        new Chart(revenueChart, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Revenue',
                    data: [1500, 2000, 1800, 2200, 2400, 2800, 3000, 3200, 3500, 3800, 4000, 4200],
                    borderColor: '#ea9e0b',
                    backgroundColor: 'rgba(234, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Users Chart
    const usersChart = document.getElementById('usersChart');
    if (usersChart) {
        new Chart(usersChart, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'New Users',
                    data: [50, 80, 60, 120, 150, 200],
                    backgroundColor: '#2dc2a3'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
    
    // Reviews Distribution Chart
    const reviewsChart = document.getElementById('reviewsChart');
    if (reviewsChart) {
        new Chart(reviewsChart, {
            type: 'doughnut',
            data: {
                labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                datasets: [{
                    data: [45, 25, 15, 10, 5],
                    backgroundColor: [
                        '#2dc2a3',
                        '#5ad8bf',
                        '#f6b83c',
                        '#dd1840',
                        '#e93559'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
} 