/**
 * Simplified Restaurant Owner Dashboard JavaScript
 */

console.log('Simple owner.js loaded');

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded in simple owner.js');
    
    // Initialize Bootstrap dropdowns
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    dropdownElementList.forEach(function(dropdownToggleEl) {
        new bootstrap.Dropdown(dropdownToggleEl);
    });
    
    // Toggle sidebar on mobile
    var sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            console.log('Sidebar toggle clicked');
            document.querySelector('.admin-sidebar').classList.toggle('show');
        });
    }
    
    // Test button
    var testButton = document.getElementById('testButton');
    if (testButton) {
        testButton.addEventListener('click', function() {
            alert('Button clicked from simple_owner.js!');
        });
    }
});