-- Create the database
CREATE DATABASE IF NOT EXISTS restaurant_review;
USE restaurant_review;

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'owner', 'admin') DEFAULT 'user',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    profile_image VARCHAR(255),
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Restaurants table
CREATE TABLE restaurants (
    restaurant_id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    cuisine_type VARCHAR(50),
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(255),
    price_range ENUM('$', '$$', '$$$', '$$$$'),
    opening_hours JSON,
    image VARCHAR(255),
    has_parking BOOLEAN DEFAULT FALSE,
    is_wheelchair_accessible BOOLEAN DEFAULT FALSE,
    has_wifi BOOLEAN DEFAULT FALSE,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Reviews table
CREATE TABLE reviews (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    restaurant_id INT,
    cleanliness_rating DECIMAL(2,1),
    taste_rating DECIMAL(2,1),
    service_rating DECIMAL(2,1),
    price_rating DECIMAL(2,1),
    parking_rating DECIMAL(2,1),
    overall_rating DECIMAL(2,1),
    comment TEXT,
    images JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Menus table
CREATE TABLE menus (
    menu_id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT,
    name VARCHAR(100),
    description TEXT,
    category VARCHAR(50),
    price DECIMAL(10,2),
    image VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Reservations table
CREATE TABLE reservations (
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    restaurant_id INT,
    reservation_date DATE,
    reservation_time TIME,
    party_size INT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    special_requests TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Waitlist table
CREATE TABLE waitlist (
    waitlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    restaurant_id INT,
    preferred_date DATE,
    preferred_time TIME,
    party_size INT,
    status ENUM('waiting', 'notified', 'expired') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Promotions table
CREATE TABLE promotions (
    promotion_id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    start_date DATE,
    end_date DATE,
    min_spend DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2),
    usage_limit INT,
    times_used INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Languages table
CREATE TABLE languages (
    language_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(5) UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Translations table
CREATE TABLE translations (
    translation_id INT PRIMARY KEY AUTO_INCREMENT,
    language_id INT,
    key_name VARCHAR(100) NOT NULL,
    translation_text TEXT NOT NULL,
    FOREIGN KEY (language_id) REFERENCES languages(language_id) ON DELETE CASCADE
);

-- Currency rates table
CREATE TABLE currency_rates (
    currency_id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(3) UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    rate DECIMAL(10,6) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blocked slots table
CREATE TABLE blocked_slots (
    block_id INT PRIMARY KEY AUTO_INCREMENT,
    restaurant_id INT,
    block_date DATE NOT NULL,
    block_time_start TIME NOT NULL,
    block_time_end TIME NOT NULL,
    reason VARCHAR(255), -- e.g. 'Maintenance', 'Private Event'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_restaurant_location ON restaurants(latitude, longitude);
CREATE INDEX idx_restaurant_cuisine ON restaurants(cuisine_type);
CREATE INDEX idx_review_ratings ON reviews(overall_rating);
CREATE INDEX idx_reservation_date ON reservations(reservation_date, reservation_time);
CREATE INDEX idx_promotion_code ON promotions(code);

-- Insert sample users (12345678)
INSERT INTO users (username, email, password, role, first_name, last_name, phone, points) VALUES
('admin', 'admin@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'admin', 'Admin', 'User', '+1234567890', 0),
('owner1', 'owner1@restaurant.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'owner', 'John', 'Smith', '+1234567891', 0),
('owner2', 'owner2@restaurant.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'owner', 'Jane', 'Doe', '+1234567892', 0),
('user1', 'user1@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'user', 'Michael', 'Johnson', '+1234567893', 150),
('user2', 'user2@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'user', 'Emily', 'Brown', '+1234567894', 75),
('user3', 'user3@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'user', 'David', 'Wilson', '+1234567895', 200),
('user4', 'user4@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'user', 'Sarah', 'Taylor', '+1234567896', 100),
('user5', 'user5@example.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'user', 'James', 'Anderson', '+1234567897', 50),
('owner3', 'owner3@restaurant.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'owner', 'Robert', 'Martin', '+1234567898', 0),
('owner4', 'owner4@restaurant.com', '$2y$10$tjGfG2ZJaGSfvSl/TiNyruuq3nSThYIyH6SERgZqFN0qkK1T2/ZKq', 'owner', 'Lisa', 'Garcia', '+1234567899', 0);

-- Insert sample restaurants
INSERT INTO restaurants (owner_id, name, description, cuisine_type, address, latitude, longitude, phone, email, website, price_range, opening_hours, is_featured) VALUES
(2, 'The Golden Spoon', 'Fine dining experience with modern cuisine', 'Contemporary', '123 Main St, City Center', 40.7128, -74.0060, '+1234567001', 'contact@goldenspoon.com', 'https://goldenspoon.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}', true),
(2, 'Pasta Paradise', 'Authentic Italian cuisine', 'Italian', '456 Oak Ave, Downtown', 40.7129, -74.0061, '+1234567002', 'info@pastaparadise.com', 'https://pastaparadise.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}', true),
(3, 'Sushi Master', 'Premium Japanese dining', 'Japanese', '789 Pine St, Eastside', 40.7130, -74.0062, '+1234567003', 'hello@sushimaster.com', 'https://sushimaster.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}', true),
(3, 'Taco Fiesta', 'Vibrant Mexican street food', 'Mexican', '321 Elm St, Westside', 40.7131, -74.0063, '+1234567004', 'hola@tacofiesta.com', 'https://tacofiesta.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}', true),
(9, 'Burger Bliss', 'Gourmet burgers and shakes', 'American', '654 Maple Dr, Northside', 40.7132, -74.0064, '+1234567005', 'eat@burgerbliss.com', 'https://burgerbliss.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}', true),
(9, 'Spice Route', 'Authentic Indian cuisine', 'Indian', '987 Cedar Ln, Southside', 40.7133, -74.0065, '+1234567006', 'namaste@spiceroute.com', 'https://spiceroute.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}', true),
(10, 'Mediterranean Delight', 'Fresh Mediterranean dishes', 'Mediterranean', '147 Beach Rd, Seaside', 40.7134, -74.0066, '+1234567007', 'hello@meddelight.com', 'https://meddelight.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}', true),
(10, 'Dim Sum Dynasty', 'Traditional Chinese dim sum', 'Chinese', '258 River St, Riverside', 40.7135, -74.0067, '+1234567008', 'info@dimsumdynasty.com', 'https://dimsumdynasty.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}', true),
(2, 'Veggie Haven', 'Creative vegetarian cuisine', 'Vegetarian', '369 Garden Ave, Parkside', 40.7136, -74.0068, '+1234567009', 'green@veggiehaven.com', 'https://veggiehaven.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}', true),
(3, 'Steak House', 'Premium aged steaks', 'Steakhouse', '741 Grill St, Uptown', 40.7137, -74.0069, '+1234567010', 'meat@steakhouse.com', 'https://steakhouse.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}', true);

-- Insert sample reviews
INSERT INTO reviews (user_id, restaurant_id, cleanliness_rating, taste_rating, service_rating, price_rating, parking_rating, overall_rating, comment) VALUES
(4, 1, 4.5, 5.0, 4.5, 4.0, 4.0, 4.5, 'Excellent fine dining experience! The service was impeccable.'),
(5, 1, 4.0, 4.5, 4.0, 3.5, 4.0, 4.0, 'Great food but a bit pricey. Worth it for special occasions.'),
(6, 2, 4.5, 5.0, 4.5, 4.5, 3.5, 4.5, 'Best Italian food in the city! Authentic flavors.'),
(7, 2, 4.0, 4.0, 4.5, 4.0, 3.0, 4.0, 'Lovely atmosphere and great pasta dishes.'),
(8, 3, 5.0, 5.0, 5.0, 4.0, 4.5, 4.8, 'Outstanding sushi! Very fresh and well-presented.'),
(4, 3, 4.5, 4.5, 4.0, 3.5, 4.0, 4.2, 'High-quality Japanese cuisine, slightly expensive.'),
(5, 4, 4.0, 4.5, 4.0, 5.0, 3.5, 4.2, 'Authentic Mexican flavors at great prices!'),
(6, 5, 4.5, 5.0, 4.0, 4.5, 4.0, 4.5, 'Best burgers in town! Great milkshakes too.'),
(7, 6, 4.5, 5.0, 4.5, 4.0, 3.5, 4.4, 'Delicious Indian food with perfect spice levels.'),
(8, 7, 4.0, 4.5, 4.5, 4.0, 4.0, 4.2, 'Fresh and healthy Mediterranean options.');

-- Insert sample menus
INSERT INTO menus (restaurant_id, name, description, category, price, image, is_available) VALUES
(1, 'Truffle Risotto', 'Creamy risotto with black truffle and parmesan', 'Main Course', 32.00, 'risotto.jpg', true),
(1, 'Beef Wellington', 'Classic beef wellington with mushroom duxelles', 'Main Course', 45.00, 'wellington.jpg', true),
(2, 'Spaghetti Carbonara', 'Traditional carbonara with guanciale', 'Pasta', 18.00, 'carbonara.jpg', true),
(2, 'Margherita Pizza', 'Classic pizza with fresh basil and mozzarella', 'Pizza', 16.00, 'margherita.jpg', true),
(3, 'Sushi Deluxe Platter', 'Chef\'s selection of premium sushi', 'Sushi', 35.00, 'sushi_platter.jpg', true),
(3, 'Ramen', 'Tonkotsu ramen with chashu pork', 'Noodles', 16.00, 'ramen.jpg', true),
(4, 'Street Tacos', 'Three authentic Mexican street tacos', 'Tacos', 12.00, 'tacos.jpg', true),
(5, 'Gourmet Burger', 'Wagyu beef burger with truffle mayo', 'Burgers', 18.00, 'burger.jpg', true),
(6, 'Butter Chicken', 'Creamy tomato-based curry with tender chicken', 'Curry', 16.00, 'butter_chicken.jpg', true),
(7, 'Mediterranean Platter', 'Selection of hummus, falafel, and pita', 'Appetizers', 20.00, 'med_platter.jpg', true);

-- Insert sample reservations
INSERT INTO reservations (user_id, restaurant_id, reservation_date, reservation_time, party_size, status, special_requests) VALUES
(4, 1, '2024-03-20', '19:00:00', 2, 'confirmed', 'Window table preferred'),
(5, 2, '2024-03-21', '18:30:00', 4, 'confirmed', 'Birthday celebration'),
(6, 3, '2024-03-22', '20:00:00', 3, 'pending', 'No special requests'),
(7, 4, '2024-03-23', '19:30:00', 2, 'confirmed', 'Vegetarian options needed'),
(8, 5, '2024-03-24', '18:00:00', 6, 'confirmed', 'High chair needed'),
(4, 6, '2024-03-25', '19:00:00', 2, 'pending', 'Spicy food preferred'),
(5, 7, '2024-03-26', '20:30:00', 4, 'confirmed', 'Outdoor seating'),
(6, 8, '2024-03-27', '18:30:00', 5, 'confirmed', 'Gluten-free options'),
(7, 9, '2024-03-28', '19:00:00', 2, 'pending', 'Anniversary celebration'),
(8, 10, '2024-03-29', '20:00:00', 3, 'confirmed', 'No nuts in any dishes');

-- Insert sample waitlist entries
INSERT INTO waitlist (user_id, restaurant_id, preferred_date, preferred_time, party_size, status) VALUES
(4, 1, '2024-03-20', '20:00:00', 2, 'waiting'),
(5, 2, '2024-03-21', '19:00:00', 4, 'waiting'),
(6, 3, '2024-03-22', '19:30:00', 3, 'notified'),
(7, 4, '2024-03-23', '20:00:00', 2, 'waiting'),
(8, 5, '2024-03-24', '19:00:00', 6, 'expired'),
(4, 6, '2024-03-25', '18:30:00', 2, 'waiting'),
(5, 7, '2024-03-26', '19:00:00', 4, 'notified'),
(6, 8, '2024-03-27', '20:00:00', 5, 'waiting'),
(7, 9, '2024-03-28', '19:30:00', 2, 'waiting'),
(8, 10, '2024-03-29', '18:00:00', 3, 'notified');

-- Insert sample promotions
INSERT INTO promotions (restaurant_id, code, description, discount_type, discount_value, start_date, end_date, min_spend, max_discount, usage_limit, times_used) VALUES
(1, 'WELCOME20', 'Welcome discount 20% off', 'percentage', 20.00, '2024-03-01', '2024-04-01', 50.00, 100.00, 100, 45),
(2, 'PASTA10', '10% off on all pasta dishes', 'percentage', 10.00, '2024-03-15', '2024-04-15', 30.00, 50.00, 200, 85),
(3, 'SUSHI25', '$25 off on orders above $100', 'fixed', 25.00, '2024-03-10', '2024-04-10', 100.00, 25.00, 150, 62),
(4, 'TACO15', '15% off on all tacos', 'percentage', 15.00, '2024-03-05', '2024-04-05', 20.00, 30.00, 300, 156),
(5, 'BURGER5', '$5 off on any burger', 'fixed', 5.00, '2024-03-20', '2024-04-20', 15.00, 5.00, 400, 189),
(6, 'SPICY20', '20% off on spicy dishes', 'percentage', 20.00, '2024-03-12', '2024-04-12', 40.00, 60.00, 250, 98),
(7, 'MED15', '15% off on platters', 'percentage', 15.00, '2024-03-08', '2024-04-08', 35.00, 45.00, 200, 87),
(8, 'DIM10', '$10 off on dim sum', 'fixed', 10.00, '2024-03-25', '2024-04-25', 30.00, 10.00, 350, 145),
(9, 'VEG25', '25% off on vegetarian meals', 'percentage', 25.00, '2024-03-18', '2024-04-18', 25.00, 40.00, 180, 76),
(10, 'STEAK50', '$50 off on premium steaks', 'fixed', 50.00, '2024-03-22', '2024-04-22', 150.00, 50.00, 100, 34);

-- Insert sample languages
INSERT INTO languages (code, name, is_active) VALUES
('en', 'English', true),
('es', 'Spanish', true),
('fr', 'French', true),
('de', 'German', true),
('it', 'Italian', true),
('zh', 'Chinese', true),
('ja', 'Japanese', true),
('ko', 'Korean', true),
('ar', 'Arabic', true),
('hi', 'Hindi', true);

-- Insert sample currency rates
INSERT INTO currency_rates (code, name, rate, last_updated) VALUES
('USD', 'US Dollar', 1.000000, CURRENT_TIMESTAMP),
('EUR', 'Euro', 0.850000, CURRENT_TIMESTAMP),
('GBP', 'British Pound', 0.730000, CURRENT_TIMESTAMP),
('JPY', 'Japanese Yen', 110.500000, CURRENT_TIMESTAMP),
('CAD', 'Canadian Dollar', 1.250000, CURRENT_TIMESTAMP),
('AUD', 'Australian Dollar', 1.350000, CURRENT_TIMESTAMP),
('CNY', 'Chinese Yuan', 6.450000, CURRENT_TIMESTAMP),
('INR', 'Indian Rupee', 74.500000, CURRENT_TIMESTAMP),
('MXN', 'Mexican Peso', 20.150000, CURRENT_TIMESTAMP),
('SGD', 'Singapore Dollar', 1.350000, CURRENT_TIMESTAMP);

-- Insert additional restaurant for testing
INSERT INTO restaurants (owner_id, name, description, cuisine_type, address, latitude, longitude, phone, email, website, price_range, opening_hours) VALUES
-- Restaurants 1-10
(2, 'The Golden Fork', 'Fine dining with a modern twist', 'Contemporary', '124 Main St, City Center', 40.7138, -74.0070, '+1234567011', 'contact@goldenfork.com', 'https://goldenfork.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}'),
(3, 'Pasta Palace', 'Authentic Italian pasta dishes', 'Italian', '457 Oak Ave, Downtown', 40.7139, -74.0071, '+1234567012', 'info@pastapalace.com', 'https://pastapalace.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}'),
(9, 'Sushi Sensation', 'Premium Japanese sushi', 'Japanese', '790 Pine St, Eastside', 40.7140, -74.0072, '+1234567013', 'hello@sushisensation.com', 'https://sushisensation.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}'),
(10, 'Taco Town', 'Vibrant Mexican tacos', 'Mexican', '322 Elm St, Westside', 40.7141, -74.0073, '+1234567014', 'hola@tacotown.com', 'https://tacotown.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}'),
(2, 'Burger Bonanza', 'Gourmet burgers and fries', 'American', '655 Maple Dr, Northside', 40.7142, -74.0074, '+1234567015', 'eat@burgerbonanza.com', 'https://burgerbonanza.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}'),
(3, 'Spice Kingdom', 'Authentic Indian curries', 'Indian', '988 Cedar Ln, Southside', 40.7143, -74.0075, '+1234567016', 'namaste@spicekingdom.com', 'https://spicekingdom.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}'),
(9, 'Mediterranean Oasis', 'Fresh Mediterranean salads', 'Mediterranean', '148 Beach Rd, Seaside', 40.7144, -74.0076, '+1234567017', 'hello@mediterraneanoasis.com', 'https://mediterraneanoasis.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}'),
(10, 'Dim Sum Delight', 'Traditional Chinese dim sum', 'Chinese', '259 River St, Riverside', 40.7145, -74.0077, '+1234567018', 'info@dimsumdelight.com', 'https://dimsumdelight.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}'),
(2, 'Veggie Village', 'Creative vegetarian dishes', 'Vegetarian', '370 Garden Ave, Parkside', 40.7146, -74.0078, '+1234567019', 'green@veggievillage.com', 'https://veggievillage.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}'),
(3, 'Steak & Co.', 'Premium aged steaks', 'Steakhouse', '742 Grill St, Uptown', 40.7147, -74.0079, '+1234567020', 'meat@steakandco.com', 'https://steakandco.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}'),

-- Restaurants 11-20
(9, 'Golden Bites', 'Modern fusion cuisine', 'Contemporary', '125 Main St, City Center', 40.7148, -74.0080, '+1234567021', 'contact@goldenbites.com', 'https://goldenbites.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}'),
(10, 'Pasta Express', 'Quick Italian meals', 'Italian', '458 Oak Ave, Downtown', 40.7149, -74.0081, '+1234567022', 'info@pastaexpress.com', 'https://pastaexpress.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}'),
(2, 'Sushi Supreme', 'Top-quality Japanese sushi', 'Japanese', '791 Pine St, Eastside', 40.7150, -74.0082, '+1234567023', 'hello@sushisupreme.com', 'https://sushisupreme.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}'),
(3, 'Taco Fiesta Express', 'Fast Mexican tacos', 'Mexican', '323 Elm St, Westside', 40.7151, -74.0083, '+1234567024', 'hola@tacofiestaexpress.com', 'https://tacofiestaexpress.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}'),
(9, 'Burger Kingpin', 'Classic burgers and fries', 'American', '656 Maple Dr, Northside', 40.7152, -74.0084, '+1234567025', 'eat@burgerkingpin.com', 'https://burgerkingpin.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}'),
(10, 'Spice Junction', 'Flavorful Indian dishes', 'Indian', '989 Cedar Ln, Southside', 40.7153, -74.0085, '+1234567026', 'namaste@spicejunction.com', 'https://spicejunction.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}'),
(2, 'Mediterranean Feast', 'Delicious Mediterranean platters', 'Mediterranean', '149 Beach Rd, Seaside', 40.7154, -74.0086, '+1234567027', 'hello@mediterraneanfeast.com', 'https://mediterraneanfeast.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}'),
(3, 'Dim Sum Dynasty II', 'Authentic Chinese dim sum', 'Chinese', '260 River St, Riverside', 40.7155, -74.0087, '+1234567028', 'info@dimsumdynastyii.com', 'https://dimsumdynastyii.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}'),
(9, 'Veggie Paradise', 'Healthy vegetarian options', 'Vegetarian', '371 Garden Ave, Parkside', 40.7156, -74.0088, '+1234567029', 'green@veggieparadise.com', 'https://veggieparadise.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}'),
(10, 'Steak Master', 'Perfectly grilled steaks', 'Steakhouse', '743 Grill St, Uptown', 40.7157, -74.0089, '+1234567030', 'meat@steakmaster.com', 'https://steakmaster.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}'),

-- Restaurants 21-30
(2, 'Golden Plate', 'Elegant dining experience', 'Contemporary', '126 Main St, City Center', 40.7158, -74.0090, '+1234567031', 'contact@goldenplate.com', 'https://goldenplate.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}'),
(3, 'Pasta Perfection', 'Homemade Italian pasta', 'Italian', '459 Oak Ave, Downtown', 40.7159, -74.0091, '+1234567032', 'info@pastaperfection.com', 'https://pastaperfection.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}'),
(9, 'Sushi Heaven', 'Exquisite Japanese sushi', 'Japanese', '792 Pine St, Eastside', 40.7160, -74.0092, '+1234567033', 'hello@sushiheaven.com', 'https://sushiheaven.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}'),
(10, 'Taco Loco', 'Spicy Mexican tacos', 'Mexican', '324 Elm St, Westside', 40.7161, -74.0093, '+1234567034', 'hola@tacoloco.com', 'https://tacoloco.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}'),
(2, 'Burger Haven', 'Juicy burgers and shakes', 'American', '657 Maple Dr, Northside', 40.7162, -74.0094, '+1234567035', 'eat@burgerhaven.com', 'https://burgerhaven.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}'),
(3, 'Spice Fusion', 'Innovative Indian flavors', 'Indian', '990 Cedar Ln, Southside', 40.7163, -74.0095, '+1234567036', 'namaste@spicefusion.com', 'https://spicefusion.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}'),
(9, 'Mediterranean Magic', 'Mouthwatering Mediterranean dishes', 'Mediterranean', '150 Beach Rd, Seaside', 40.7164, -74.0096, '+1234567037', 'hello@mediterraneanmagic.com', 'https://mediterraneanmagic.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}'),
(10, 'Dim Sum House', 'Classic Chinese dim sum', 'Chinese', '261 River St, Riverside', 40.7165, -74.0097, '+1234567038', 'info@dimsumhouse.com', 'https://dimsumhouse.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}'),
(2, 'Veggie Delight', 'Wholesome vegetarian meals', 'Vegetarian', '372 Garden Ave, Parkside', 40.7166, -74.0098, '+1234567039', 'green@veggiedelight.com', 'https://veggiedelight.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}'),
(3, 'Steak Royale', 'Luxurious steakhouse dining', 'Steakhouse', '744 Grill St, Uptown', 40.7167, -74.0099, '+1234567040', 'meat@steakroyale.com', 'https://steakroyale.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}'),

-- Restaurants 31-40
(9, 'Golden Cuisine', 'Upscale dining experience', 'Contemporary', '127 Main St, City Center', 40.7168, -74.0100, '+1234567041', 'contact@goldencuisine.com', 'https://goldencuisine.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}'),
(10, 'Pasta World', 'Global Italian flavors', 'Italian', '460 Oak Ave, Downtown', 40.7169, -74.0101, '+1234567042', 'info@pastaworld.com', 'https://pastaworld.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}'),
(2, 'Sushi Dreams', 'Dreamy Japanese sushi', 'Japanese', '793 Pine St, Eastside', 40.7170, -74.0102, '+1234567043', 'hello@sushidreams.com', 'https://sushidreams.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}'),
(3, 'Taco Fiesta Grande', 'Grand Mexican fiesta', 'Mexican', '325 Elm St, Westside', 40.7171, -74.0103, '+1234567044', 'hola@tacofiestagrande.com', 'https://tacofiestagrande.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}'),
(9, 'Burger Empire', 'King of burgers', 'American', '658 Maple Dr, Northside', 40.7172, -74.0104, '+1234567045', 'eat@burgerempire.com', 'https://burgerempire.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}'),
(10, 'Spice Harmony', 'Harmonious Indian spices', 'Indian', '991 Cedar Ln, Southside', 40.7173, -74.0105, '+1234567046', 'namaste@spiceharmony.com', 'https://spiceharmony.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}'),
(2, 'Mediterranean Coast', 'Coastal Mediterranean flavors', 'Mediterranean', '151 Beach Rd, Seaside', 40.7174, -74.0106, '+1234567047', 'hello@mediterraneancoast.com', 'https://mediterraneancoast.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}'),
(3, 'Dim Sum Palace', 'Royal Chinese dim sum', 'Chinese', '262 River St, Riverside', 40.7175, -74.0107, '+1234567048', 'info@dimsumpalace.com', 'https://dimsumpalace.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}'),
(9, 'Veggie Kingdom', 'Kingdom of vegetarian delights', 'Vegetarian', '373 Garden Ave, Parkside', 40.7176, -74.0108, '+1234567049', 'green@veggiekingdom.com', 'https://veggiekingdom.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}'),
(10, 'Steak Dynasty', 'Dynastic steakhouse experience', 'Steakhouse', '745 Grill St, Uptown', 40.7177, -74.0109, '+1234567050', 'meat@steakdynasty.com', 'https://steakdynasty.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}'),

-- Restaurants 41-50
(2, 'Golden Table', 'Table of golden flavors', 'Contemporary', '128 Main St, City Center', 40.7178, -74.0110, '+1234567051', 'contact@goldentable.com', 'https://goldentable.com', '$$$', '{"monday":{"open":"11:00","close":"22:00"}}'),
(3, 'Pasta Passion', 'Passionate Italian cooking', 'Italian', '461 Oak Ave, Downtown', 40.7179, -74.0111, '+1234567052', 'info@pastapassion.com', 'https://pastapassion.com', '$$', '{"monday":{"open":"11:30","close":"23:00"}}'),
(9, 'Sushi Zen', 'Zen-like Japanese sushi', 'Japanese', '794 Pine St, Eastside', 40.7180, -74.0112, '+1234567053', 'hello@sushizen.com', 'https://sushizen.com', '$$$', '{"monday":{"open":"12:00","close":"22:30"}}'),
(10, 'Taco Fiesta Supreme', 'Supreme Mexican tacos', 'Mexican', '326 Elm St, Westside', 40.7181, -74.0113, '+1234567054', 'hola@tacofiestasupreme.com', 'https://tacofiestasupreme.com', '$', '{"monday":{"open":"10:00","close":"21:00"}}'),
(2, 'Burger Legend', 'Legendary burger experience', 'American', '659 Maple Dr, Northside', 40.7182, -74.0114, '+1234567055', 'eat@burgerlegend.com', 'https://burgerlegend.com', '$$', '{"monday":{"open":"11:00","close":"23:00"}}'),
(3, 'Spice Symphony', 'Symphony of Indian spices', 'Indian', '992 Cedar Ln, Southside', 40.7183, -74.0115, '+1234567056', 'namaste@spicesymphony.com', 'https://spicesymphony.com', '$$', '{"monday":{"open":"11:30","close":"22:30"}}'),
(9, 'Mediterranean Breeze', 'Breezy Mediterranean dishes', 'Mediterranean', '152 Beach Rd, Seaside', 40.7184, -74.0116, '+1234567057', 'hello@mediterraneanbreeze.com', 'https://mediterraneanbreeze.com', '$$', '{"monday":{"open":"11:00","close":"21:30"}}'),
(10, 'Dim Sum Royale', 'Royale Chinese dim sum', 'Chinese', '263 River St, Riverside', 40.7185, -74.0117, '+1234567058', 'info@dimsumroyale.com', 'https://dimsumroyale.com', '$$', '{"monday":{"open":"09:00","close":"20:00"}}'),
(2, 'Veggie Bliss', 'Blissful vegetarian cuisine', 'Vegetarian', '374 Garden Ave, Parkside', 40.7186, -74.0118, '+1234567059', 'green@veggiebliss.com', 'https://veggiebliss.com', '$$', '{"monday":{"open":"10:30","close":"21:30"}}'),
(3, 'Steak Royale II', 'Second helping of luxury steaks', 'Steakhouse', '746 Grill St, Uptown', 40.7187, -74.0119, '+1234567060', 'meat@steakroyaleii.com', 'https://steakroyaleii.com', '$$$$', '{"monday":{"open":"16:00","close":"23:00"}}');