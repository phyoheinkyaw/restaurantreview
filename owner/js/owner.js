/**
 * Restaurant Owner Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Owner dashboard JS loaded');
    
    // Initialize Bootstrap components
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Initialize DataTables
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.data-table').DataTable({
            responsive: true,
            autoWidth: true,
            columnDefs: [
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: -1 }
            ]
        });
    }
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Toggle sidebar
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const contentOverlay = document.querySelector('.admin-content-overlay');
    
    if (sidebarToggle && adminSidebar && contentOverlay) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Sidebar toggle clicked');
            adminSidebar.classList.toggle('show');
            contentOverlay.classList.toggle('show');
        });
        
        // Close sidebar when clicking on overlay
        contentOverlay.addEventListener('click', function() {
            adminSidebar.classList.remove('show');
            contentOverlay.classList.remove('show');
        });
    }
    
    // Prevent automatic scrolling when charts are initialized
    if (typeof Chart !== 'undefined') {
        Chart.defaults.plugins.legend.display = true;
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
    }
    
    // Handle restaurant selector
    document.querySelectorAll(".restaurant-selector .dropdown-item").forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Restaurant selector clicked');
            window.location.href = this.getAttribute("href") + "&" + window.location.search.substring(1).replace(/restaurant_id=\d+&?/, "");
        });
    });
    
    // Confirmation dialogs
    document.querySelectorAll('.confirm-action').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            var targetUrl = this.getAttribute('href');
            var message = this.dataset.confirmMessage || 'Are you sure you want to perform this action?';
            
            alertify.confirm('Confirm Action', message, function() {
                window.location.href = targetUrl;
            }, function() {
                // User clicked Cancel
            });
        });
    });
    
    // Handle status changes
    document.querySelectorAll('.status-change').forEach(function(element) {
        element.addEventListener('change', function() {
            var itemId = this.dataset.id;
            var newStatus = this.value;
            var itemType = this.dataset.type;
            
            fetch('ajax/update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + itemId + '&status=' + newStatus + '&type=' + itemType
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertify.success(data.message);
                } else {
                    alertify.error(data.message);
                }
            })
            .catch(() => {
                alertify.error('An error occurred while updating the status.');
            });
        });
    });
    
    // Image preview
    document.querySelectorAll('.image-upload').forEach(function(element) {
        element.addEventListener('change', function() {
            var preview = this.nextElementSibling;
            var file = this.files[0];
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    });
}); 