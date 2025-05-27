document.addEventListener('DOMContentLoaded', function() {
    // Initialize jQuery for dropdowns
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        $ = jQuery;
    }

    // Toggle sidebar
    const sidebarToggle = document.querySelector('.sidebar-toggle') || document.getElementById('sidebarToggle');
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
            
            // For Bootstrap sidebar toggle
            document.body.classList.toggle('sb-sidenav-toggled');
            localStorage.setItem('sb|sidebar-toggle', document.body.classList.contains('sb-sidenav-toggled'));
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
    
    // Initialize data tables if available and not already initialized
    initializeDataTables();
    
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
    
    // Initialize Bootstrap 5 tooltips and popovers
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (typeof bootstrap.Tooltip !== 'undefined') {
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Initialize Bootstrap 5 dropdowns manually if needed
        const dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        if (typeof bootstrap.Dropdown !== 'undefined') {
            dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
        }
        
        // Initialize Bootstrap 5 popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        if (typeof bootstrap.Popover !== 'undefined') {
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }
    }
    
    // Handle unread message marking
    const markAsReadButtons = document.querySelectorAll('.mark-as-read');
    markAsReadButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const messageId = this.getAttribute('data-id');
            const row = this.closest('tr');
            
            // Send AJAX request to mark message as read
            fetch('mark_message_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message_id=' + messageId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    row.classList.remove('fw-bold');
                    this.innerHTML = '<i class="fas fa-check"></i> Read';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-success');
                    this.disabled = true;
                    
                    // Update unread count if available
                    const badge = document.querySelector('.unread-count');
                    if (badge) {
                        const count = parseInt(badge.textContent) - 1;
                        if (count <= 0) {
                            badge.style.display = 'none';
                        } else {
                            badge.textContent = count;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
});

// Function to safely initialize DataTables
function initializeDataTables() {
    if (typeof $.fn === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        return;
    }

    // Initialize data tables with a check to prevent double initialization
    const dataTablesSelector = '.data-table table, .datatable';
    const tables = $(dataTablesSelector);
    
    tables.each(function() {
        const tableId = $(this).attr('id');
        
        // Check if this table is already a DataTable
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
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
    });
}

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
    const reviewsDistChart = document.getElementById('reviewsDistChart');
    if (reviewsDistChart) {
        new Chart(reviewsDistChart, {
            type: 'doughnut',
            data: {
                labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                datasets: [{
                    data: [350, 280, 150, 60, 40],
                    backgroundColor: ['#2dc2a3', '#6cc070', '#ea9e0b', '#f27c4d', '#e74c3c']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });
    }
    
    // Restaurants by Cuisine Chart
    const cuisineChart = document.getElementById('cuisineChart');
    if (cuisineChart) {
        new Chart(cuisineChart, {
            type: 'polarArea',
            data: {
                labels: ['Italian', 'Japanese', 'Mexican', 'Indian', 'American', 'Other'],
                datasets: [{
                    data: [40, 35, 30, 25, 45, 20],
                    backgroundColor: [
                        'rgba(45, 194, 163, 0.7)',
                        'rgba(108, 192, 112, 0.7)',
                        'rgba(234, 158, 11, 0.7)',
                        'rgba(242, 124, 77, 0.7)',
                        'rgba(231, 76, 60, 0.7)',
                        'rgba(149, 165, 166, 0.7)'
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