<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'restaurant_review');
define('DB_PORT', '3308');

// Application configuration
define('SITE_NAME', 'Restaurant Review');
define('SITE_URL', 'http://localhost/restaurant-review');
define('ADMIN_EMAIL', 'admin@example.com');

// API Keys
define('MAPS_API_KEY', 'your_maps_api_key');

// Default language and currency
define('DEFAULT_LANG', 'en');
define('DEFAULT_CURRENCY', 'USD');

// Points configuration
define('POINTS_PER_RESERVATION', 10);
define('POINTS_TO_DISCOUNT_RATIO', 20); // 20 points = $1

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); 