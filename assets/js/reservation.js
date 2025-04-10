document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const reservationForm = document.getElementById('reservationForm');
    const reservationDate = document.getElementById('reservationDate');
    const reservationTime = document.getElementById('reservationTime');
    const availabilityStatus = document.getElementById('availabilityStatus');
    const reserveButton = document.getElementById('reserveButton');
    const restaurantId = window.restaurantId;

    // Check if required elements exist
    if (!reservationForm || !reservationDate || !reservationTime) {
        console.error('Missing required form elements');
        return;
    }

    // Initialize date picker to today or later
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    if (reservationDate) {
        reservationDate.min = todayString;
        reservationDate.value = todayString;
    }

    // Update available times when date changes
    if (reservationDate) {
        reservationDate.addEventListener('change', updateAvailableTimes);
    }
    
    if (reservationTime) {
        reservationTime.addEventListener('change', checkAvailability);
    }

    // Initial times update
    updateAvailableTimes();

    // Form submission
    if (reservationForm) {
        reservationForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Check availability first
            const available = await checkAvailability();
            if (!available) return;

            // Prepare data
            const formData = new FormData(reservationForm);
            const data = {
                restaurant_id: restaurantId,
                date: formData.get('date'),
                time: formData.get('time'),
                party_size: formData.get('party_size'),
                special_requests: formData.get('special_requests')
            };

            try {
                const response = await fetch('api/make_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    window.showAlert('Reservation created successfully!', 'success');
                    // You might want to redirect to a confirmation page
                    window.location.href = 'reservation_confirmation.php?id=' + result.reservation_id;
                } else {
                    window.showAlert('Error: ' + result.error, 'danger');
                }
            } catch (error) {
                window.showAlert('Error: ' + error.message, 'danger');
            }
        });
    }

    async function updateAvailableTimes() {
        if (!reservationDate) return;

        try {
            const response = await fetch('api/get_timeslots.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    restaurant_id: restaurantId,
                    date: reservationDate.value
                })
            });

            const timeslots = await response.json();
            if (timeslots.success) {
                populateTimeSlots(timeslots.timeslots);
            }
        } catch (error) {
            console.error('Error fetching timeslots:', error);
            window.showAlert('Error fetching available times', 'danger');
        }
    }

    async function checkAvailability() {
        if (!reservationDate || !reservationTime) return false;

        const date = reservationDate.value;
        const time = reservationTime.value;
        const partySize = document.getElementById('partySize')?.value;

        if (!date || !time || !partySize) {
            if (availabilityStatus) {
                availabilityStatus.innerHTML = '<div class="alert alert-warning">Please fill in all fields</div>';
            }
            return false;
        }

        try {
            const response = await fetch('api/check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    restaurant_id: restaurantId,
                    date: date,
                    time: time,
                    party_size: partySize
                })
            });

            const result = await response.json();

            if (result.success) {
                if (result.can_reserve) {
                    if (availabilityStatus) {
                        availabilityStatus.innerHTML = `
                            <div class="alert alert-success">
                                Available seats: ${result.available_seats}<br>
                                Current guests: ${result.current_guests}<br>
                                Total capacity: ${result.capacity}
                            </div>
                        `;
                    }
                    if (reserveButton) {
                        reserveButton.disabled = false;
                    }
                    return true;
                } else {
                    if (availabilityStatus) {
                        availabilityStatus.innerHTML = `
                            <div class="alert alert-danger">
                                This time slot is fully booked<br>
                                Available seats: ${result.available_seats}<br>
                                Current guests: ${result.current_guests}<br>
                                Total capacity: ${result.capacity}
                            </div>
                        `;
                    }
                    if (reserveButton) {
                        reserveButton.disabled = true;
                    }
                    return false;
                }
            } else {
                if (availabilityStatus) {
                    availabilityStatus.innerHTML = '<div class="alert alert-danger">Error checking availability</div>';
                }
                if (reserveButton) {
                    reserveButton.disabled = true;
                }
                return false;
            }
        } catch (error) {
            if (availabilityStatus) {
                availabilityStatus.innerHTML = '<div class="alert alert-danger">Error checking availability</div>';
            }
            if (reserveButton) {
                reserveButton.disabled = true;
            }
            return false;
        }
    }

    function populateTimeSlots(timeslots) {
        if (!reservationTime) return;

        const timeSelect = reservationTime;
        timeSelect.innerHTML = '<option value="">Select time</option>';
        
        timeslots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot.time;
            option.textContent = slot.time;
            timeSelect.appendChild(option);
        });
    }
});
