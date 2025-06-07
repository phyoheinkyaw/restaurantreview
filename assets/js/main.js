document.addEventListener('DOMContentLoaded', function() {
    // Initialize any common functionality here
    
    // Handle alerts using Alertify.js
    function showAlert(message, type = 'success') {
        // Map Bootstrap alert types to Alertify notification types
        const alertifyType = type === 'danger' ? 'error' : type;
        
        // Configure alertify defaults if not already configured
        alertify.set('notifier','position', 'top-right');
        alertify.set('notifier','delay', 5);
        
        // Show the notification
        alertify.notify(message, alertifyType, 5);
    }

    // Export the function for use in other scripts
    window.showAlert = showAlert;
});
