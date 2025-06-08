/**
 * Currency conversion functionality
 */

// Currency data will be loaded from the server
let currencyData = {};
let currentCurrency = 'USD';

/**
 * Initialize the currency system
 */
function initCurrency() {
    // Listen for changes on the currency selector
    const currencySelector = document.getElementById('currencySelector');
    if (currencySelector) {
        currencySelector.addEventListener('change', function() {
            const newCurrency = this.value;
            setCurrency(newCurrency);
            
            // Reload the page with the new currency while preserving other parameters
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('currency', newCurrency);
            window.location.href = currentUrl.toString();
        });
        
        // Store current selection
        currentCurrency = currencySelector.value;
    }
    
    // Load currency data from the server
    fetch('api/get_currencies.php')
        .then(response => response.json())
        .then(data => {
            currencyData = data;
            
            // After loading data, update all price elements
            updateAllPriceElements();
        })
        .catch(error => {
            console.error('Error loading currency data:', error);
        });
}

/**
 * Set the current currency and save it
 */
function setCurrency(currencyCode) {
    // Store in localStorage for persistence
    localStorage.setItem('selectedCurrency', currencyCode);
    currentCurrency = currencyCode;
}

/**
 * Convert amount from USD to target currency
 */
function convertAmount(amountUSD, targetCurrency) {
    // Default to current currency if not specified
    const currency = targetCurrency || currentCurrency;
    
    // If no conversion needed or data not loaded
    if (currency === 'USD' || !currencyData[currency]) {
        return amountUSD;
    }
    
    // Get the currency data
    const currencyInfo = currencyData[currency];
    let rate = currencyInfo.rate;
    
    // Check if this is a rate stored in thousands (adjusted rate)
    if (currencyInfo.name && currencyInfo.name.includes('(Rate in thousands)')) {
        // Multiply by 1000 to get the actual rate
        rate = rate * 1000;
    }
    
    // Convert using the exchange rate
    return amountUSD * rate;
}

/**
 * Format amount with currency symbol
 */
function formatCurrency(amount, currencyCode) {
    const code = currencyCode || currentCurrency;
    
    // If we don't have currency data yet, use basic formatting with code
    if (!currencyData[code]) {
        return code + ' ' + amount.toFixed(2);
    }
    
    // Use the symbol from the API if available
    const symbol = currencyData[code].symbol || code;
    
    // Currency-specific formatting
    switch (code) {
        case 'JPY':
        case 'CNY':
        case 'KRW':
        case 'CLP':
        case 'IDR':
        case 'VND':
        case 'MMK':
            return symbol + '\u00A0' + Math.round(amount).toLocaleString(); // No decimals
            
        case 'BHD':
        case 'KWD':
        case 'OMR':
            return symbol + '\u00A0' + amount.toLocaleString(undefined, {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3
            }); // 3 decimal places
            
        default:
            return symbol + '\u00A0' + amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }); // Standard 2 decimal places
    }
}

/**
 * Update all price elements on the page
 */
function updateAllPriceElements() {
    // Find all elements with price data attribute
    const priceElements = document.querySelectorAll('[data-price-usd]');
    
    priceElements.forEach(element => {
        const priceUSD = parseFloat(element.getAttribute('data-price-usd'));
        if (!isNaN(priceUSD)) {
            const convertedPrice = convertAmount(priceUSD);
            element.textContent = formatCurrency(convertedPrice);
        }
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initCurrency); 