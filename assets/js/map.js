document.addEventListener('DOMContentLoaded', function() {
    // Initialize Leaflet map
    const map = L.map('map').setView([40.7130, -74.0060], 13); // Centered around sample restaurants

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ' OpenStreetMap contributors'
    }).addTo(map);

    // Function to add restaurant markers
    function addRestaurantMarkers(restaurants) {
        restaurants.forEach(restaurant => {
            const marker = L.marker([restaurant.latitude, restaurant.longitude])
                .addTo(map)
                .bindPopup(`
                    <div class="marker-popup">
                        <h5>${restaurant.name}</h5>
                        <p><strong>Cuisine:</strong> ${restaurant.cuisine_type}</p>
                        <p><strong>Price Range:</strong> ${restaurant.price_range}</p>
                        <p><strong>Address:</strong> ${restaurant.address}</p>
                        <p><strong>Phone:</strong> ${restaurant.phone}</p>
                        ${restaurant.image ? `<img src="${restaurant.image}" alt="${restaurant.name}" class="marker-image">` : ''}
                        <div class="marker-actions">
                            <a href="restaurant.php?id=${restaurant.restaurant_id}" class="btn btn-primary btn-sm">View Details</a>
                            <a href="reservation.php?id=${restaurant.restaurant_id}" class="btn btn-success btn-sm">Reserve</a>
                        </div>
                    </div>
                `);
        });
    }

    // Fetch restaurants and add markers
    fetch('get_restaurants.php')
        .then(response => response.json())
        .then(data => {
            // Check if data is an error object
            if (data.error) {
                console.error('Error:', data.error);
                return;
            }

            addRestaurantMarkers(data);

            // Add a layer control for restaurant types
            const cuisineTypes = Array.from(new Set(data.map(r => r.cuisine_type)));
            const overlays = {};
            cuisineTypes.forEach(type => {
                overlays[type] = L.layerGroup(data
                    .filter(r => r.cuisine_type === type)
                    .map(r => L.marker([r.latitude, r.longitude])));
            });
            
            L.control.layers(null, overlays, {
                collapsed: false
            }).addTo(map);
        })
        .catch(error => console.error('Error fetching restaurants:', error));
});
