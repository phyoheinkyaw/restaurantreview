<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';
require_once 'currency.php';

// Get all available currencies for the header dropdown
$all_currencies = getAllCurrencies();
$current_currency = getCurrentCurrency();

// If currency is changed via GET request
if (isset($_GET['currency'])) {
    $new_currency = htmlspecialchars($_GET['currency']);
    setCurrentCurrency($new_currency);
    
    // Get the current URL without the currency parameter
    $url_parts = parse_url($_SERVER['REQUEST_URI']);
    $path = $url_parts['path'];
    
    // Preserve any other query parameters except currency
    $query = [];
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query);
        unset($query['currency']); // Remove the currency parameter
    }
    
    // Rebuild the URL
    $redirect_url = $path;
    if (!empty($query)) {
        $redirect_url .= '?' . http_build_query($query);
    }
    
    // Redirect to the same page without the currency parameter
    header("Location: $redirect_url");
    exit;
}
?> 