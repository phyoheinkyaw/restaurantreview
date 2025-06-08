<?php
/**
 * Currency handling utility functions
 */

// Global currency symbols array - centralized definition
$GLOBALS['CURRENCY_SYMBOLS'] = [
    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥', 
    'AUD' => 'A$', 'CAD' => 'C$', 'CHF' => 'Fr', 'CNY' => '¥', 
    'HKD' => 'HK$', 'NZD' => 'NZ$', 'SEK' => 'kr', 'KRW' => '₩',
    'SGD' => 'S$', 'NOK' => 'kr', 'MXN' => 'Mex$', 'INR' => '₹', 
    'RUB' => '₽', 'ZAR' => 'R', 'TRY' => '₺', 'BRL' => 'R$', 
    'TWD' => 'NT$', 'DKK' => 'kr', 'PLN' => 'zł', 'THB' => '฿',
    'IDR' => 'Rp', 'HUF' => 'Ft', 'CZK' => 'Kč', 'ILS' => '₪',
    'CLP' => 'CLP$', 'PHP' => '₱', 'AED' => 'د.إ', 'COP' => 'COL$',
    'SAR' => '﷼', 'MYR' => 'RM', 'RON' => 'lei', 'BGN' => 'лв',
    'VND' => '₫', 'UAH' => '₴', 'EGP' => 'E£', 'PKR' => '₨',
    'PEN' => 'S/', 'NGN' => '₦', 'ARS' => '$', 'QAR' => '﷼',
    'JOD' => 'JD', 'BHD' => '.د.ب', 'KWD' => 'د.ك', 'OMR' => '﷼',
    'LKR' => '₨', 'ISK' => 'kr', 'XCD' => 'EC$', 'JMD' => 'J$',
    'BBD' => 'Bds$', 'TTD' => 'TT$', 'BSD' => 'B$', 'MAD' => 'د.م.',
    'TND' => 'د.ت', 'DZD' => 'د.ج', 'IRR' => '﷼', 'YER' => '﷼',
    'SYP' => '£', 'IQD' => 'ع.د', 'FJD' => 'FJ$', 'BAM' => 'KM',
    'VES' => 'Bs.', 'UYU' => '$U', 'BOB' => 'Bs.', 'PYG' => '₲',
    'GYD' => 'G$', 'SRD' => 'Sr$', 'HTG' => 'G', 'GTQ' => 'Q',
    'HNL' => 'L', 'NIO' => 'C$', 'CRC' => '₡', 'BZD' => 'BZ$',
    'ZMW' => 'ZK', 'ETB' => 'Br', 'KES' => 'KSh', 'GHS' => 'GH₵',
    'UGX' => 'USh', 'TZS' => 'TSh', 'RWF' => 'R₣', 'BIF' => 'FBu',
    'SZL' => 'E', 'MUR' => '₨', 'MGA' => 'Ar', 'GMD' => 'D',
    'CVE' => 'Esc', 'LAK' => '₭', 'MNT' => '₮',
    'MMK' => 'K', 'KHR' => '៛', 'BDT' => '৳', 'NPR' => '₨'
];

// Global world currencies array - comprehensive list with ISO 4217 codes and countries
$GLOBALS['WORLD_CURRENCIES'] = [
    // North America
    'USD' => ['name' => 'US Dollar', 'countries' => ['US', 'AS', 'BQ', 'IO', 'EC', 'SV', 'GU', 'HT', 'MH', 'FM', 'MP', 'PW', 'PA', 'PR', 'TL', 'TC', 'UM', 'VG', 'VI']],
    'CAD' => ['name' => 'Canadian Dollar', 'countries' => ['CA']],
    'MXN' => ['name' => 'Mexican Peso', 'countries' => ['MX']],
    'XCD' => ['name' => 'East Caribbean Dollar', 'countries' => ['AG', 'DM', 'GD', 'KN', 'LC', 'VC', 'AI', 'MS']],
    'JMD' => ['name' => 'Jamaican Dollar', 'countries' => ['JM']],
    'BBD' => ['name' => 'Barbadian Dollar', 'countries' => ['BB']],
    'TTD' => ['name' => 'Trinidad and Tobago Dollar', 'countries' => ['TT']],
    'BSD' => ['name' => 'Bahamian Dollar', 'countries' => ['BS']],
    'HTG' => ['name' => 'Haitian Gourde', 'countries' => ['HT']],
    'DOP' => ['name' => 'Dominican Peso', 'countries' => ['DO']],
    'CUP' => ['name' => 'Cuban Peso', 'countries' => ['CU']],
    'CUC' => ['name' => 'Cuban Convertible Peso', 'countries' => ['CU']],
    'PAB' => ['name' => 'Panamanian Balboa', 'countries' => ['PA']],
    'GTQ' => ['name' => 'Guatemalan Quetzal', 'countries' => ['GT']],
    'HNL' => ['name' => 'Honduran Lempira', 'countries' => ['HN']],
    'NIO' => ['name' => 'Nicaraguan Córdoba', 'countries' => ['NI']],
    'CRC' => ['name' => 'Costa Rican Colón', 'countries' => ['CR']],
    'BZD' => ['name' => 'Belize Dollar', 'countries' => ['BZ']],

    // Europe
    'EUR' => ['name' => 'Euro', 'countries' => ['AD', 'AT', 'BE', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'MC', 'ME', 'NL', 'PT', 'SM', 'SK', 'SI', 'ES', 'VA']],
    'GBP' => ['name' => 'British Pound', 'countries' => ['GB', 'GG', 'IM', 'JE']],
    'CHF' => ['name' => 'Swiss Franc', 'countries' => ['CH', 'LI']],
    'NOK' => ['name' => 'Norwegian Krone', 'countries' => ['NO', 'SJ', 'BV']],
    'SEK' => ['name' => 'Swedish Krona', 'countries' => ['SE']],
    'DKK' => ['name' => 'Danish Krone', 'countries' => ['DK', 'FO', 'GL']],
    'PLN' => ['name' => 'Polish Złoty', 'countries' => ['PL']],
    'CZK' => ['name' => 'Czech Koruna', 'countries' => ['CZ']],
    'HUF' => ['name' => 'Hungarian Forint', 'countries' => ['HU']],
    'RON' => ['name' => 'Romanian Leu', 'countries' => ['RO']],
    'BGN' => ['name' => 'Bulgarian Lev', 'countries' => ['BG']],
    'HRK' => ['name' => 'Croatian Kuna', 'countries' => ['HR']],
    'RSD' => ['name' => 'Serbian Dinar', 'countries' => ['RS']],
    'ISK' => ['name' => 'Icelandic Króna', 'countries' => ['IS']],
    'UAH' => ['name' => 'Ukrainian Hryvnia', 'countries' => ['UA']],
    'RUB' => ['name' => 'Russian Ruble', 'countries' => ['RU']],
    'TRY' => ['name' => 'Turkish Lira', 'countries' => ['TR']],
    'MDL' => ['name' => 'Moldovan Leu', 'countries' => ['MD']],
    'ALL' => ['name' => 'Albanian Lek', 'countries' => ['AL']],
    'MKD' => ['name' => 'Macedonian Denar', 'countries' => ['MK']],
    'BAM' => ['name' => 'Bosnia and Herzegovina Convertible Mark', 'countries' => ['BA']],

    // Asia
    'JPY' => ['name' => 'Japanese Yen', 'countries' => ['JP']],
    'CNY' => ['name' => 'Chinese Yuan', 'countries' => ['CN']],
    'HKD' => ['name' => 'Hong Kong Dollar', 'countries' => ['HK']],
    'TWD' => ['name' => 'New Taiwan Dollar', 'countries' => ['TW']],
    'KRW' => ['name' => 'South Korean Won', 'countries' => ['KR']],
    'SGD' => ['name' => 'Singapore Dollar', 'countries' => ['SG']],
    'MYR' => ['name' => 'Malaysian Ringgit', 'countries' => ['MY']],
    'THB' => ['name' => 'Thai Baht', 'countries' => ['TH']],
    'IDR' => ['name' => 'Indonesian Rupiah', 'countries' => ['ID']],
    'PHP' => ['name' => 'Philippine Peso', 'countries' => ['PH']],
    'INR' => ['name' => 'Indian Rupee', 'countries' => ['IN', 'BT']],
    'PKR' => ['name' => 'Pakistani Rupee', 'countries' => ['PK']],
    'BDT' => ['name' => 'Bangladeshi Taka', 'countries' => ['BD']],
    'LKR' => ['name' => 'Sri Lankan Rupee', 'countries' => ['LK']],
    'NPR' => ['name' => 'Nepalese Rupee', 'countries' => ['NP']],
    'VND' => ['name' => 'Vietnamese Dong', 'countries' => ['VN']],
    'KHR' => ['name' => 'Cambodian Riel', 'countries' => ['KH']],
    'MMK' => ['name' => 'Burmese Kyat', 'countries' => ['MM']],
    'LAK' => ['name' => 'Lao Kip', 'countries' => ['LA']],
    'MNT' => ['name' => 'Mongolian Tögrög', 'countries' => ['MN']],

    // Middle East
    'AED' => ['name' => 'United Arab Emirates Dirham', 'countries' => ['AE']],
    'SAR' => ['name' => 'Saudi Riyal', 'countries' => ['SA']],
    'QAR' => ['name' => 'Qatari Riyal', 'countries' => ['QA']],
    'BHD' => ['name' => 'Bahraini Dinar', 'countries' => ['BH']],
    'KWD' => ['name' => 'Kuwaiti Dinar', 'countries' => ['KW']],
    'OMR' => ['name' => 'Omani Rial', 'countries' => ['OM']],
    'ILS' => ['name' => 'Israeli New Shekel', 'countries' => ['IL', 'PS']],
    'JOD' => ['name' => 'Jordanian Dinar', 'countries' => ['JO']],
    'LBP' => ['name' => 'Lebanese Pound', 'countries' => ['LB']],
    'IRR' => ['name' => 'Iranian Rial', 'countries' => ['IR']],
    'YER' => ['name' => 'Yemeni Rial', 'countries' => ['YE']],
    'SYP' => ['name' => 'Syrian Pound', 'countries' => ['SY']],
    'IQD' => ['name' => 'Iraqi Dinar', 'countries' => ['IQ']],

    // Oceania
    'AUD' => ['name' => 'Australian Dollar', 'countries' => ['AU', 'CX', 'CC', 'HM', 'KI', 'NR', 'NF', 'TV']],
    'NZD' => ['name' => 'New Zealand Dollar', 'countries' => ['NZ', 'CK', 'NU', 'PN', 'TK']],
    'FJD' => ['name' => 'Fijian Dollar', 'countries' => ['FJ']],
    'PGK' => ['name' => 'Papua New Guinean Kina', 'countries' => ['PG']],
    'SBD' => ['name' => 'Solomon Islands Dollar', 'countries' => ['SB']],
    'VUV' => ['name' => 'Vanuatu Vatu', 'countries' => ['VU']],
    'TOP' => ['name' => 'Tongan Paʻanga', 'countries' => ['TO']],
    'WST' => ['name' => 'Samoan Tala', 'countries' => ['WS']],

    // Africa
    'ZAR' => ['name' => 'South African Rand', 'countries' => ['ZA', 'NA', 'LS', 'SZ']],
    'EGP' => ['name' => 'Egyptian Pound', 'countries' => ['EG']],
    'NGN' => ['name' => 'Nigerian Naira', 'countries' => ['NG']],
    'KES' => ['name' => 'Kenyan Shilling', 'countries' => ['KE']],
    'GHS' => ['name' => 'Ghanaian Cedi', 'countries' => ['GH']],
    'MAD' => ['name' => 'Moroccan Dirham', 'countries' => ['MA']],
    'TND' => ['name' => 'Tunisian Dinar', 'countries' => ['TN']],
    'DZD' => ['name' => 'Algerian Dinar', 'countries' => ['DZ']],
    'ZMW' => ['name' => 'Zambian Kwacha', 'countries' => ['ZM']],
    'UGX' => ['name' => 'Ugandan Shilling', 'countries' => ['UG']],
    'TZS' => ['name' => 'Tanzanian Shilling', 'countries' => ['TZ']],
    'RWF' => ['name' => 'Rwandan Franc', 'countries' => ['RW']],
    'BIF' => ['name' => 'Burundian Franc', 'countries' => ['BI']],
    'ETB' => ['name' => 'Ethiopian Birr', 'countries' => ['ET']],
    'SDG' => ['name' => 'Sudanese Pound', 'countries' => ['SD']],
    'SSP' => ['name' => 'South Sudanese Pound', 'countries' => ['SS']],
    'MGA' => ['name' => 'Malagasy Ariary', 'countries' => ['MG']],
    'MUR' => ['name' => 'Mauritian Rupee', 'countries' => ['MU']],
    'SZL' => ['name' => 'Swazi Lilangeni', 'countries' => ['SZ']],
    'GMD' => ['name' => 'Gambian Dalasi', 'countries' => ['GM']],
    'CVE' => ['name' => 'Cape Verdean Escudo', 'countries' => ['CV']],

    // South America
    'BRL' => ['name' => 'Brazilian Real', 'countries' => ['BR']],
    'ARS' => ['name' => 'Argentine Peso', 'countries' => ['AR']],
    'CLP' => ['name' => 'Chilean Peso', 'countries' => ['CL']],
    'COP' => ['name' => 'Colombian Peso', 'countries' => ['CO']],
    'PEN' => ['name' => 'Peruvian Sol', 'countries' => ['PE']],
    'VES' => ['name' => 'Venezuelan Bolívar', 'countries' => ['VE']],
    'UYU' => ['name' => 'Uruguayan Peso', 'countries' => ['UY']],
    'BOB' => ['name' => 'Bolivian Boliviano', 'countries' => ['BO']],
    'PYG' => ['name' => 'Paraguayan Guaraní', 'countries' => ['PY']],
    'GYD' => ['name' => 'Guyanese Dollar', 'countries' => ['GY']],
    'SRD' => ['name' => 'Surinamese Dollar', 'countries' => ['SR']]
];

// Store in session for easy access across the application
if (!isset($_SESSION['currency_symbols'])) {
    $_SESSION['currency_symbols'] = $GLOBALS['CURRENCY_SYMBOLS'];
}

/**
 * Get all available currencies from the database
 * @return array Array of currencies
 */
function getAllCurrencies() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM currency_rates ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting currencies: " . $e->getMessage());
        return [];
    }
}

/**
 * Get currency data by code
 * @param string $code Currency code (e.g., USD, EUR)
 * @return array|bool Currency data or false if not found
 */
function getCurrencyByCode($code) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM currency_rates WHERE code = :code");
        $stmt->bindParam(':code', $code, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting currency by code: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user's selected currency or default
 * @return string Currency code
 */
function getCurrentCurrency() {
    return $_SESSION['currency'] ?? DEFAULT_CURRENCY;
}

/**
 * Set user's currency preference
 * @param string $currencyCode Currency code to set
 */
function setCurrentCurrency($currencyCode) {
    $_SESSION['currency'] = $currencyCode;
}

/**
 * Convert amount from USD to target currency
 */
function convertUsdToTargetCurrency($amount_usd, $target_currency = null) {
    global $conn;
    
    // Get target currency if not provided
    if (!$target_currency) {
        $target_currency = getCurrentCurrency();
    }
    
    // If target is USD, no conversion needed
    if ($target_currency === 'USD') {
        return $amount_usd;
    }
    
    // Get exchange rate
    $stmt = $conn->prepare("SELECT code, rate, name FROM currency_rates WHERE code = ?");
    $stmt->bind_param("s", $target_currency);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $rate = $row['rate'];
        
        // Check if this is a rate stored in thousands (adjusted rate)
        if (strpos($row['name'], '(Rate in thousands)') !== false) {
            // Multiply by 1000 to get the actual rate
            $rate = $rate * 1000;
        }
        
        return $amount_usd * $rate;
    }
    
    // If currency not found, return original amount
    return $amount_usd;
}

/**
 * Convert price from USD to selected currency
 * @param float $amount Amount in USD
 * @param string|null $targetCurrency Currency to convert to (or use current if null)
 * @return float Converted amount
 */
function convertPrice($amount, $targetCurrency = null) {
    $currencyCode = $targetCurrency ?? getCurrentCurrency();
    
    // If USD or invalid currency, return original amount
    if ($currencyCode === 'USD') {
        return $amount;
    }
    
    $currency = getCurrencyByCode($currencyCode);
    if (!$currency) {
        return $amount;
    }
    
    // Convert price using the exchange rate
    return $amount * $currency['rate'];
}

/**
 * Get currency symbol for a given currency code
 * @param string $code Currency code (e.g., USD, EUR)
 * @return string Currency symbol
 */
function getCurrencySymbol($code) {
    global $CURRENCY_SYMBOLS;
    
    // First check session (most up-to-date source)
    if (isset($_SESSION['currency_symbols']) && isset($_SESSION['currency_symbols'][$code])) {
        return $_SESSION['currency_symbols'][$code];
    }
    
    // Then check global array (fallback)
    if (isset($CURRENCY_SYMBOLS[$code])) {
        return $CURRENCY_SYMBOLS[$code];
    }
    
    // Default to currency code if no symbol found
    return $code;
}

/**
 * Format price with currency symbol
 * @param float $amount The amount to format
 * @param string|null $currencyCode Currency code (or use current if null)
 * @return string Formatted price
 */
function formatPrice($amount, $currencyCode = null) {
    $code = $currencyCode ?? getCurrentCurrency();
    $symbol = getCurrencySymbol($code);
    
    // Currency-specific formatting
    switch ($code) {
        case 'JPY':
        case 'CNY':
        case 'KRW':
        case 'CLP':
        case 'IDR':
        case 'VND':
        case 'MMK':
            // No decimals for these currencies
            return $symbol . "&nbsp;" . number_format(round($amount)); 
            
        case 'BHD':
        case 'KWD':
        case 'OMR':
            // 3 decimal places for these currencies, remove trailing zeros
            $formatted = number_format($amount, 3);
            // Remove trailing zeros
            if (strpos($formatted, '.') !== false) {
                $formatted = rtrim(rtrim($formatted, '0'), '.');
            }
            return $symbol . "&nbsp;" . $formatted;
            
        default:
            // Format with 2 decimal places initially
            $formatted = number_format($amount, 2);
            
            // For values with decimal part other than .00
            if (substr($formatted, -3) !== '.00') {
                // Remove trailing zero if present (e.g., 10.50 -> 10.5)
                if (substr($formatted, -1) === '0') {
                    $formatted = substr($formatted, 0, -1);
                }
            }
            
            return $symbol . "&nbsp;" . $formatted;
    }
} 