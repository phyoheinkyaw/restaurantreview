<?php
// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/currency.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get all currencies from database
    $currencies = getAllCurrencies();
    
    // Format response
    $response = [];
    
    foreach ($currencies as $currency) {
        $response[$currency['code']] = [
            'rate' => (float) $currency['rate'],
            'name' => $currency['name'],
            'symbol' => getCurrencySymbol($currency['code'])
        ];
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Error getting currencies: ' . $e->getMessage()
    ]);
}
?> 