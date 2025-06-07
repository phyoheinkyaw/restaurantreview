document.addEventListener('DOMContentLoaded', function() {
    // Handle reservation cancellation
    document.querySelectorAll('.cancel-reservation').forEach(button => {
        button.addEventListener('click', async function() {
            const reservationId = this.dataset.reservationId;
            
            alertify.confirm('Cancel Reservation', 'Are you sure you want to cancel this reservation?', 
                async function() {
                    try {
                        const response = await fetch('api/cancel_reservation.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ reservation_id: reservationId })
                        });

                        const result = await response.json();

                        if (result.success) {
                            // Update the status in the UI
                            const row = button.closest('tr');
                            const statusCell = row.querySelector('.badge');
                            statusCell.classList.remove('bg-warning');
                            statusCell.classList.add('bg-danger');
                            statusCell.textContent = 'Cancelled';
                            
                            // Remove the cancel button
                            const btnGroup = row.querySelector('.btn-group');
                            btnGroup.innerHTML = `
                                <button type="button" 
                                        class="btn btn-secondary btn-sm" 
                                        onclick="window.location.href='restaurant.php?id=${row.querySelector('.write-review').dataset.restaurantId}'">
                                    View Restaurant
                                </button>
                            `;
                            
                            // Show success message
                            window.showAlert('Reservation cancelled successfully', 'success');
                        } else {
                            window.showAlert('Error cancelling reservation: ' + result.error, 'danger');
                        }
                    } catch (error) {
                        window.showAlert('Error cancelling reservation: ' + error.message, 'danger');
                    }
                },
                function() {
                    // User clicked Cancel
                }
            );
        });
    });

    // Handle review writing
    document.querySelectorAll('.write-review').forEach(button => {
        button.addEventListener('click', function() {
            const restaurantId = this.dataset.restaurantId;
            window.location.href = 'restaurant.php?id=' + restaurantId;
        });
    });
});
