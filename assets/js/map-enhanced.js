document.addEventListener('DOMContentLoaded', function() {
    // Initialize Leaflet map
    const map = L.map('map').setView([40.7130, -74.0060], 13); // Centered around sample restaurants

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Custom marker icons based on rating
    function getMarkerIcon(rating) {
        let color, icon;
        
        if (rating >= 4.5) {
            color = 'success';
            icon = 'fa-award';
        } else if (rating >= 4.0) {
            color = 'primary';
            icon = 'fa-utensils';
        } else if (rating >= 3.5) {
            color = 'warning';
            icon = 'fa-utensils';
        } else {
            color = 'danger';
            icon = 'fa-utensils';
        }
        
        return L.divIcon({
            className: `restaurant-marker bg-${color}`,
            html: `<i class="fas ${icon}"></i>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -18]
        });
    }

    // Store all markers for filtering
    let allMarkers = [];
    let markerLayers = {};
    let cuisineFilters = {};
    let priceFilters = {};
    
    // Function to add restaurant markers
    function addRestaurantMarkers(restaurants) {
        // Create marker groups by cuisine and price
        const cuisineTypes = [...new Set(restaurants.map(r => r.cuisine_type))];
        const priceRanges = ['$', '$$', '$$$', '$$$$'];
        
        // Generate cuisine filter buttons
        const cuisineFiltersContainer = document.getElementById('cuisine-filters');
        if (cuisineFiltersContainer) {
            cuisineFiltersContainer.innerHTML = '';
            
            cuisineTypes.forEach(cuisine => {
                const button = document.createElement('button');
                button.className = 'btn btn-sm btn-primary';
                button.setAttribute('data-cuisine', cuisine);
                button.textContent = cuisine;
                button.addEventListener('click', () => toggleCuisineFilter(cuisine));
                cuisineFiltersContainer.appendChild(button);
                
                // Initialize filter state
                cuisineFilters[cuisine] = true;
            });
        }
        
        // Set up price filter buttons
        const priceFilterButtons = document.querySelectorAll('#price-filters button');
        priceFilterButtons.forEach(button => {
            const price = button.getAttribute('data-price');
            button.addEventListener('click', () => togglePriceFilter(price));
            
            // Initialize filter state
            priceFilters[price] = true;
            
            // Set initial styling (filled)
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-secondary');
        });
        
        // Create markers for each restaurant
        restaurants.forEach(restaurant => {
            const rating = parseFloat(restaurant.avg_rating) || 3.0;
            const marker = L.marker([restaurant.latitude, restaurant.longitude], {
                icon: getMarkerIcon(rating)
            })
            .bindPopup(createPopupContent(restaurant))
            .on('click', () => {
                updateRecentActivity(`Viewed ${restaurant.name}`);
            });
            
            // Store marker with metadata for filtering
            const markerData = {
                marker: marker,
                cuisine: restaurant.cuisine_type,
                price: restaurant.price_range,
                rating: rating,
                restaurant: restaurant
            };
            
            allMarkers.push(markerData);
            
            // Add to appropriate layer groups
            if (!markerLayers[restaurant.cuisine_type]) {
                markerLayers[restaurant.cuisine_type] = [];
            }
            markerLayers[restaurant.cuisine_type].push(marker);
            
            // Add marker to map
            marker.addTo(map);
        });
    }
    
    // Create popup content with enhanced UI
    function createPopupContent(restaurant) {
        const rating = parseFloat(restaurant.avg_rating) || 0;
        const ratingStars = generateRatingStars(rating);
        
        // Get price range badge class
        let priceClass;
        switch(restaurant.price_range) {
            case '$': priceClass = 'bg-success text-white'; break;
            case '$$': priceClass = 'bg-info text-white'; break;
            case '$$$': priceClass = 'bg-primary text-white'; break;
            case '$$$$': priceClass = 'bg-danger text-white'; break;
            default: priceClass = 'bg-secondary text-white';
        }
        
        // Format image if available
        const imageSection = restaurant.image ? 
            `<img src="${restaurant.image}" alt="${restaurant.name}" class="marker-image">` : 
            `<div class="marker-image d-flex align-items-center justify-content-center bg-light">
                <i class="fas fa-utensils fa-2x text-secondary"></i>
             </div>`;
        
        const popupHTML = `
            <div class="marker-popup">
                ${imageSection}
                <div class="marker-popup-header">
                    <h5 class="mb-1">${restaurant.name}</h5>
                    <div>${ratingStars} <small class="text-muted">(${restaurant.review_count || 0})</small></div>
                    <span class="marker-popup-badge badge ${priceClass}">${restaurant.price_range}</span>
                </div>
                <div class="marker-popup-body">
                    <div class="marker-info cuisine">
                        <i class="fas fa-utensils"></i>
                        <span>${restaurant.cuisine_type}</span>
                    </div>
                    <div class="marker-info price">
                        <i class="fas fa-tag"></i>
                        <span>${restaurant.price_range} · ${getPriceRangeText(restaurant.price_range)}</span>
                    </div>
                    <div class="marker-info address">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${restaurant.address}</span>
                    </div>
                    <div class="marker-info phone">
                        <i class="fas fa-phone"></i>
                        <span>${restaurant.phone}</span>
                    </div>
                    
                    <div class="marker-actions">
                        <a href="restaurant.php?id=${restaurant.restaurant_id}" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-info-circle me-1"></i> Details
                        </a>
                        <a href="reservation.php?id=${restaurant.restaurant_id}" class="btn btn-success btn-sm flex-grow-1">
                            <i class="fas fa-calendar-check me-1"></i> Reserve
                        </a>
                    </div>
                </div>
            </div>
        `;
        console.log('Index Page Popup HTML:', popupHTML); // Log the HTML
        return popupHTML;
    }
    
    // Get price range text description
    function getPriceRangeText(priceRange) {
        switch(priceRange) {
            case '$': return 'Inexpensive';
            case '$$': return 'Moderate';
            case '$$$': return 'Expensive';
            case '$$$$': return 'Very Expensive';
            default: return '';
        }
    }
    
    // Generate HTML for rating stars
    function generateRatingStars(rating) {
        let starsHtml = '';
        const fullStars = Math.floor(rating);
        const halfStar = rating % 1 >= 0.5;
        
        for (let i = 0; i < fullStars; i++) {
            starsHtml += '<i class="fas fa-star text-warning"></i>';
        }
        
        if (halfStar) {
            starsHtml += '<i class="fas fa-star-half-alt text-warning"></i>';
        }
        
        const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
        for (let i = 0; i < emptyStars; i++) {
            starsHtml += '<i class="far fa-star text-warning"></i>';
        }
        
        return starsHtml;
    }
    
    // Toggle cuisine filter
    function toggleCuisineFilter(cuisine) {
        cuisineFilters[cuisine] = !cuisineFilters[cuisine];
        
        // Update button appearance
        const button = document.querySelector(`button[data-cuisine="${cuisine}"]`);
        if (button) {
            if (cuisineFilters[cuisine]) {
                // When this cuisine is visible (not filtered out), button should be filled
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-primary');
            } else {
                // When this cuisine is filtered out, button should be outlined
                button.classList.remove('btn-primary');
                button.classList.add('btn-outline-primary');
            }
        }
        
        applyFilters();
    }
    
    // Toggle price filter
    function togglePriceFilter(price) {
        priceFilters[price] = !priceFilters[price];
        
        // Update button appearance
        const button = document.querySelector(`button[data-price="${price}"]`);
        if (button) {
            if (priceFilters[price]) {
                // When this price range is visible (not filtered out), button should be filled
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-secondary');
            } else {
                // When this price range is filtered out, button should be outlined
                button.classList.remove('btn-secondary');
                button.classList.add('btn-outline-secondary');
            }
        }
        
        applyFilters();
    }
    
    // Apply all filters
    function applyFilters() {
        allMarkers.forEach(markerData => {
            const cuisineVisible = cuisineFilters[markerData.cuisine];
            const priceVisible = priceFilters[markerData.price];
            
            if (cuisineVisible && priceVisible) {
                if (!map.hasLayer(markerData.marker)) {
                    markerData.marker.addTo(map);
                }
            } else {
                if (map.hasLayer(markerData.marker)) {
                    map.removeLayer(markerData.marker);
                }
            }
        });
    }
    
    // Update recent activity
    function updateRecentActivity(message) {
        const recentActivityList = document.getElementById('recent-activity-list');
        if (recentActivityList) {
            const timestamp = new Date().toLocaleTimeString();
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item small mb-2';
            activityItem.innerHTML = `
                <span class="text-muted">${timestamp}</span>
                <p class="mb-0">${message}</p>
            `;
            
            // Keep only the last 3 activities
            if (recentActivityList.children.length >= 3) {
                recentActivityList.removeChild(recentActivityList.lastChild);
            }
            
            recentActivityList.insertBefore(activityItem, recentActivityList.firstChild);
        }
    }
    
    // Locate user function
    document.getElementById('locate-me')?.addEventListener('click', function() {
        // Toggle button style when clicked - switch from filled to outline
        this.classList.remove('btn-primary');
        this.classList.add('btn-outline-primary');
        
        // Restore style after 2 seconds
        setTimeout(() => {
            this.classList.remove('btn-outline-primary');
            this.classList.add('btn-primary');
        }, 2000);
        
        if (navigator.geolocation) {
            updateRecentActivity('Finding your location...');
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const userLat = position.coords.latitude;
                    const userLng = position.coords.longitude;
                    
                    // Move map to user location
                    map.setView([userLat, userLng], 14);
                    
                    // Add user marker
                    const userMarker = L.marker([userLat, userLng], {
                        icon: L.divIcon({
                            className: 'user-location-marker',
                            html: '<i class="fas fa-user-circle"></i>',
                            iconSize: [36, 36],
                            iconAnchor: [18, 18]
                        })
                    }).addTo(map);
                    
                    userMarker.bindPopup('<strong>Your Location</strong>').openPopup();
                    
                    // Find nearby restaurants
                    const nearbyRestaurants = findNearbyRestaurants(userLat, userLng);
                    updateRecentActivity(`Found ${nearbyRestaurants.length} restaurants near you`);
                    
                    // Highlight nearby restaurants
                    highlightNearbyRestaurants(nearbyRestaurants);
                },
                function(error) {
                    console.error('Error getting location:', error);
                    updateRecentActivity('Error finding your location');
                    alertify.error('Unable to get your location. Please check your permissions.');
                }
            );
        } else {
            alertify.error('Geolocation is not supported by your browser');
        }
    });
    
    // Reset map view
    document.getElementById('reset-map')?.addEventListener('click', function() {
        // Toggle button style when clicked - switch from filled to outline
        this.classList.remove('btn-secondary');
        this.classList.add('btn-outline-secondary');
        
        // Restore style after 2 seconds
        setTimeout(() => {
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-secondary');
        }, 2000);
        
        map.setView([40.7130, -74.0060], 13);
        updateRecentActivity('Map view reset');
    });
    
    // Find restaurants near a given location
    function findNearbyRestaurants(lat, lng, maxDistance = 2) { // maxDistance in km
        return allMarkers.filter(markerData => {
            const restaurant = markerData.restaurant;
            const distance = getDistance(
                lat, lng,
                parseFloat(restaurant.latitude),
                parseFloat(restaurant.longitude)
            );
            return distance <= maxDistance;
        });
    }
    
    // Highlight nearby restaurants
    function highlightNearbyRestaurants(nearbyMarkers) {
        // Reset all markers first
        allMarkers.forEach(markerData => {
            markerData.marker.setZIndexOffset(0);
            
            // Reset icon if needed
            const newIcon = getMarkerIcon(markerData.rating);
            markerData.marker.setIcon(newIcon);
        });
        
        // Highlight nearby markers
        nearbyMarkers.forEach(markerData => {
            markerData.marker.setZIndexOffset(1000);
            
            // Custom highlight icon
            const restaurant = markerData.restaurant;
            
            // Calculate distance for label
            const userLatLng = map.getCenter();
            const distance = getDistance(
                userLatLng.lat, userLatLng.lng,
                parseFloat(restaurant.latitude),
                parseFloat(restaurant.longitude)
            ).toFixed(1);
            
            markerData.marker.setIcon(L.divIcon({
                className: 'restaurant-marker bg-success pulse',
                html: `
                    <i class="fas fa-utensils"></i>
                    <span class="distance-label">${distance} km</span>
                `,
                iconSize: [40, 40],
                iconAnchor: [20, 20],
                popupAnchor: [0, -20]
            }));
            
            // Update popup content to show "Near You" badge
            const popupContent = createPopupContent(restaurant);
            markerData.marker.setPopupContent(popupContent + `
                <div class="nearby-badge">
                    <span class="badge bg-success">
                        <i class="fas fa-map-marker-alt me-1"></i> ${distance} km from you
                    </span>
                </div>
            `);
            
            // Open popup of the closest restaurant
            if (nearbyMarkers.length > 0 && markerData === nearbyMarkers[0]) {
                markerData.marker.openPopup();
            }
        });
        
        // Update recent activity
        if (nearbyMarkers.length > 0) {
            updateRecentActivity(`Found ${nearbyMarkers.length} restaurants near your location`);
        }
    }
    
    // Calculate distance between two coordinates (Haversine formula)
    function getDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c; // Distance in km
    }

    // Fetch restaurants and add markers
    fetch('get_restaurants.php')
        .then(response => response.json())
        .then(data => {
            // Check if data is an error object
            if (data.error) {
                console.error('Error:', data.error);
                document.getElementById('map').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading restaurants: ${data.error}
                    </div>
                `;
                return;
            }

            // Add markers to the map
            addRestaurantMarkers(data);
            
            // Update recent activity
            updateRecentActivity(`Loaded ${data.length} restaurants`);
        })
        .catch(error => {
            console.error('Error fetching restaurants:', error);
            document.getElementById('map').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading restaurants. Please try again later.
                </div>
            `;
        });

    // When document is ready, add the near-me-btn class to the locate-me button
    const locateMeButton = document.getElementById('locate-me');
    if (locateMeButton) {
        locateMeButton.classList.add('near-me-btn');
    }
}); 