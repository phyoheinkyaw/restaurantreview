document.addEventListener('DOMContentLoaded', function() {
    // Load featured restaurants
    loadFeaturedRestaurants();
    
    // Load popular cuisines
    loadPopularCuisines();
    
    // Ensure hero image is loaded
    const heroImage = document.querySelector('.hero-section img');
    if (heroImage) {
        heroImage.onerror = function() {
            this.src = 'assets/images/placeholder-restaurant.jpg';
        };
    }
    
    // Ensure testimonial images are loaded
    document.querySelectorAll('.testimonial-avatar img').forEach(img => {
        img.onerror = function() {
            this.src = 'assets/images/user-placeholder.jpg';
        };
    });
    
    // Add smooth scrolling for in-page links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 70,
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Load popular cuisines based on reservation data
function loadPopularCuisines() {
    const cuisinesContainer = document.getElementById('popularCuisines');
    if (!cuisinesContainer) return;
    
    fetch('get_popular_cuisines.php')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error:', data.message);
                // Fall back to default cuisines
                setDefaultCuisines(cuisinesContainer);
                return;
            }
            
            // Clear loading indicator
            cuisinesContainer.innerHTML = '';
            
            // If no cuisines returned, use defaults
            if (data.cuisines.length === 0) {
                setDefaultCuisines(cuisinesContainer);
                return;
            }
            
            // Add the popular cuisines
            data.cuisines.forEach(cuisine => {
                const cuisineLink = document.createElement('a');
                cuisineLink.href = `search.php?cuisine=${encodeURIComponent(cuisine.cuisine_type)}`;
                cuisineLink.className = 'badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2';
                
                // If it has reservations, show a small indicator
                if (cuisine.reservation_count > 0) {
                    cuisineLink.innerHTML = `${cuisine.cuisine_type}`;
                } else {
                    cuisineLink.textContent = cuisine.cuisine_type;
                }
                
                cuisinesContainer.appendChild(cuisineLink);
            });
            
            // Add "Newly Added" and "Outdoor Seating" options at the end
            // const additionalFilters = [
            //     { href: 'search.php?feature=outdoor', text: 'Outdoor Seating' },
            //     { href: 'search.php?sort=newest', text: 'Newly Added' }
            // ];
            
            // additionalFilters.forEach(filter => {
            //     const filterLink = document.createElement('a');
            //     filterLink.href = filter.href;
            //     filterLink.className = 'badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2';
            //     filterLink.textContent = filter.text;
            //     cuisinesContainer.appendChild(filterLink);
            // });
        })
        .catch(error => {
            console.error('Error fetching popular cuisines:', error);
            setDefaultCuisines(cuisinesContainer);
        });
}

// Set default cuisines when API fails
function setDefaultCuisines(container) {
    container.innerHTML = `
        <a href="search.php?cuisine=italian" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Italian</a>
        <a href="search.php?cuisine=japanese" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Japanese</a>
        <a href="search.php?cuisine=mexican" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Mexican</a>
        <a href="search.php?cuisine=indian" class="badge rounded-pill bg-light text-dark me-2 mb-2 px-3 py-2">Indian</a>
    `;
}

// Load featured restaurants
function loadFeaturedRestaurants() {
    const featuredContainer = document.getElementById('featured-restaurants');
    if (!featuredContainer) return;
    
    fetch('get_featured_restaurants.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error:', data.error);
                featuredContainer.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading featured restaurants: ${data.error}
                        </div>
                    </div>
                `;
                return;
            }
            
            // Clear loading indicator
            featuredContainer.innerHTML = '';
            
            // If no featured restaurants, handle gracefully
            if (data.length === 0) {
                featuredContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <p class="text-muted">No featured restaurants available at the moment.</p>
                    </div>
                `;
                return;
            }
            
            // Generate cards for each featured restaurant
            data.forEach(restaurant => {
                const restaurantCard = createRestaurantCard(restaurant);
                featuredContainer.appendChild(restaurantCard);
            });
        })
        .catch(error => {
            console.error('Error fetching featured restaurants:', error);
            featuredContainer.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading featured restaurants. Please try again later.
                    </div>
                </div>
            `;
        });
}

// Create a restaurant card element
function createRestaurantCard(restaurant) {
    const colElement = document.createElement('div');
    colElement.className = 'col-md-6 col-lg-4 mb-4';
    
    // Format rating with stars
    const rating = parseFloat(restaurant.avg_rating) || 0;
    const ratingStars = generateRatingStars(rating);
    
    // Get price range
    const priceClass = getPriceRangeClass(restaurant.price_range);
    
    colElement.innerHTML = `
        <div class="restaurant-card card h-100">
            <div class="position-relative">
                <img src="${restaurant.image || 'assets/images/placeholder-restaurant.jpg'}" 
                     class="card-img-top" alt="${restaurant.name}">
                <span class="badge ${priceClass} position-absolute top-0 end-0 m-2">
                    ${restaurant.price_range}
                </span>
                <div class="restaurant-rating">
                    <i class="fas fa-star text-warning me-1"></i> ${rating.toFixed(1)}
                </div>
            </div>
            <div class="card-body">
                <h5 class="card-title">${restaurant.name}</h5>
                <p class="card-text text-muted mb-1">
                    <i class="fas fa-utensils me-2"></i> ${restaurant.cuisine_type}
                </p>
                <p class="card-text text-muted mb-2">
                    <i class="fas fa-map-marker-alt me-2"></i> ${restaurant.address}
                </p>
                <div class="mb-3">
                    ${ratingStars} <small class="text-muted">(${restaurant.review_count || 0} reviews)</small>
                </div>
                <div class="d-flex mt-auto">
                    <a href="restaurant.php?id=${restaurant.restaurant_id}" class="btn btn-sm btn-outline-primary me-2">View Details</a>
                    <a href="reservation.php?id=${restaurant.restaurant_id}" class="btn btn-sm btn-success">Reserve</a>
                </div>
            </div>
        </div>
    `;
    
    return colElement;
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

// Get appropriate badge class based on price range
function getPriceRangeClass(priceRange) {
    switch(priceRange) {
        case '$':
            return 'bg-success';
        case '$$':
            return 'bg-info';
        case '$$$':
            return 'bg-primary';
        case '$$$$':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
} 