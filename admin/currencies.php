<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/currency.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

// Use the global currency symbols
$currency_symbols = $GLOBALS['CURRENCY_SYMBOLS'];

// Handle fetch currencies from API
if (isset($_POST['fetch_currencies'])) {
    try {
        // Fetch exchange rates from Frankfurter API
        $api_url = "https://api.frankfurter.app/latest?from=USD";
        
        // Set context options to handle potential SSL/TLS issues
        $context = stream_context_create([
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
            "http" => [
                "timeout" => 10 // 10 second timeout
            ]
        ]);
        
        $response = file_get_contents($api_url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from currency API");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['rates']) || !is_array($data['rates'])) {
            throw new Exception("Invalid response from currency API");
        }
        
        // Fetch currency names from currencies endpoint
        $currencies_url = "https://api.frankfurter.app/currencies";
        $currencies_response = file_get_contents($currencies_url, false, $context);
        $currencies_data = json_decode($currencies_response, true);
        
        // Add or update each currency rate in the database
        $updated = 0;
        $added = 0;
        
        foreach ($data['rates'] as $code => $rate) {
            $name = isset($currencies_data[$code]) ? $currencies_data[$code] : $code;
            
            // Validate the rate - prevent out of range errors
            if ($rate > 9999) {
                // For extremely high rates (like IDR, VND), adjust to fit in the database
                // Store at a reduced scale and adjust during conversion
                $adjusted_rate = $rate / 1000; // Store in thousands
                $name .= ' (Rate in thousands)'; 
            } else {
                $adjusted_rate = $rate;
            }
            
            // Check if currency exists
            $check_sql = "SELECT * FROM currency_rates WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing currency
                $update_sql = "UPDATE currency_rates SET rate = ?, name = ?, last_updated = CURRENT_TIMESTAMP WHERE code = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("dss", $adjusted_rate, $name, $code);
                $update_stmt->execute();
                $updated++;
                $update_stmt->close();
            } else {
                // Add new currency
                $insert_sql = "INSERT INTO currency_rates (code, name, rate) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssd", $code, $name, $adjusted_rate);
                $insert_stmt->execute();
                $added++;
                $insert_stmt->close();
            }
            
            $check_stmt->close();
        }
        
        // Add USD if not present (as base currency)
        $check_sql = "SELECT * FROM currency_rates WHERE code = 'USD'";
        $result = $conn->query($check_sql);
        if ($result->num_rows == 0) {
            $insert_sql = "INSERT INTO currency_rates (code, name, rate) VALUES ('USD', 'US Dollar', 1.0)";
            $conn->query($insert_sql);
            $added++;
        }
        
        $success_message = "Exchange rates updated successfully! Added $added new currencies and updated $updated existing currencies.";
    } catch (Exception $e) {
        $error_message = "Error fetching currency data: " . $e->getMessage();
    }
}

// Handle add/edit currency
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_currency']) || isset($_POST['update_currency'])) {
        $currency_id = isset($_POST['currency_id']) ? (int)$_POST['currency_id'] : 0;
        
        // Check if we're updating USD
        $is_usd_update = false;
        if (isset($_POST['update_currency']) && $currency_id > 0) {
            // Check if this is USD being updated
            $check_sql = "SELECT code FROM currency_rates WHERE currency_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $currency_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $curr = $result->fetch_assoc();
            $check_stmt->close();
            
            if ($curr && $curr['code'] === 'USD') {
                $is_usd_update = true;
            }
        }
        
        if ($is_usd_update) {
            // Special handling for USD updates
            $code = 'USD';
            $name = isset($_POST['name']) ? trim($_POST['name']) : 'US Dollar';
            $rate = 1.0; // USD rate is always 1.0
            
            // Update USD name only (code and rate remain fixed)
            $sql = "UPDATE currency_rates SET name = ?, last_updated = CURRENT_TIMESTAMP WHERE currency_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $name, $currency_id);
            
            if ($stmt->execute()) {
                $success_message = "USD currency information updated successfully!";
            } else {
                $error_message = "Error updating USD currency: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Normal handling for other currencies
            $code = strtoupper(trim($_POST['code']));
            $name = trim($_POST['name']);
            $rate = floatval($_POST['rate']);
            
            // Validate fields
            if (empty($code) || strlen($code) > 3) {
                $error_message = "Currency code must be 1-3 characters.";
            } elseif (empty($name)) {
                $error_message = "Currency name is required.";
            } elseif ($rate <= 0) {
                $error_message = "Rate must be greater than zero.";
            } else {
                // Update or insert currency
                if ($currency_id > 0) {
                    // Update existing currency
                    $sql = "UPDATE currency_rates SET code = ?, name = ?, rate = ?, last_updated = CURRENT_TIMESTAMP WHERE currency_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssdi", $code, $name, $rate, $currency_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Currency updated successfully!";
                    } else {
                        $error_message = "Error updating currency: " . $conn->error;
                    }
                    $stmt->close();
                } else {
                    // Check if currency code already exists
                    $check_sql = "SELECT * FROM currency_rates WHERE code = ?";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bind_param("s", $code);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error_message = "Currency with code '$code' already exists.";
                    } else {
                        // Add new currency
                        $sql = "INSERT INTO currency_rates (code, name, rate) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssd", $code, $name, $rate);
                        
                        if ($stmt->execute()) {
                            $success_message = "Currency added successfully!";
                        } else {
                            $error_message = "Error adding currency: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    $check_stmt->close();
                }
            }
        }
    }
    
    // Handle delete currency
    if (isset($_POST['delete_currency'])) {
        $currency_id = (int)$_POST['currency_id'];
        
        // Do not allow deleting USD (base currency)
        $sql = "SELECT code FROM currency_rates WHERE currency_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $currency_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $currency = $result->fetch_assoc();
        $stmt->close();
        
        if ($currency && $currency['code'] === 'USD') {
            $error_message = "Cannot delete base currency (USD).";
        } else {
            $sql = "DELETE FROM currency_rates WHERE currency_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $currency_id);
            
            if ($stmt->execute()) {
                $success_message = "Currency deleted successfully!";
            } else {
                $error_message = "Error deleting currency: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get all currencies
$sql = "SELECT * FROM currency_rates ORDER BY code = 'USD' DESC, name ASC";
$result = $conn->query($sql);
$currencies = [];
$existing_currency_codes = []; // Track existing currency codes
while ($row = $result->fetch_assoc()) {
    $currencies[] = $row;
    $existing_currency_codes[] = $row['code'];
}

// Use the global world currencies definition
$world_currencies = $GLOBALS['WORLD_CURRENCIES'];

// Sort world currencies by name
uasort($world_currencies, function($a, $b) {
    return $a['name'] <=> $b['name'];
});

// Group currencies for the dropdown
$prioritized_currencies = [];
$common_currencies = [];
$other_currencies = [];

// Define common currencies (most frequently used worldwide)
$common_currency_codes = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'HKD', 'SGD', 'NZD', 'INR', 'MXN', 'BRL', 'ZAR'];

// Create common and other currency groups from world currencies that are not already in the database
foreach ($world_currencies as $code => $details) {
    // Skip currencies that already exist in the database
    if (in_array($code, $existing_currency_codes)) {
        continue;
    }
    
    // Common currencies group
    if (in_array($code, $common_currency_codes)) {
        $common_currencies[$code] = $details['name'];
    }
    // All other currencies
    else {
        $other_currencies[$code] = $details['name'];
    }
}

// Include header
include 'includes/header.php';
?>

<style>
/* Styles for currency dropdown */
#codeSelect {
    max-height: 300px;
    overflow-y: auto;
}

/* Ensure select2 container takes full width */
.select2-container {
    width: 100% !important;
}

/* Style the select2 dropdown to match the form */
.select2-container--bootstrap-5 .select2-selection {
    border-radius: 0.25rem;
    height: calc(1.5em + 0.75rem + 2px);
    padding: 0.375rem 0.75rem;
}

/* Format the dropdown options */
.select2-container--bootstrap-5 .select2-results__option {
    padding: 8px 12px;
}

/* Style the optgroups */
.select2-container--bootstrap-5 .select2-results__group {
    padding: 8px 12px;
    font-weight: bold;
    color: #495057;
    background-color: #f8f9fa;
}

/* Ensure symbols are properly displayed */
.currency-symbol {
    margin-left: 5px;
    opacity: 0.7;
}
</style>

<!-- Page content -->
<div class="content-wrapper">
    <div class="container-fluid">
        <!-- Breadcrumb-->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between">
                    <h4 class="mb-0">Currency Management</h4>
                </div>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Auto-update currency rates button -->
        <div class="row mb-3 mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Fetch Latest Exchange Rates</h5>
                        <p class="card-text">
                            Click the button below to fetch the latest exchange rates from Frankfurter API. 
                            This will update all existing currencies and add any new ones that are available.
                        </p>
                        <form method="POST" action="">
                            <button type="submit" name="fetch_currencies" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-1"></i> Update All Exchange Rates From API
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Currency Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0" id="formTitle">Add New Currency</h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="cancelBtn">
                            <i class="fas fa-times me-1"></i>Cancel Editing
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="currencyForm" method="POST">
                            <input type="hidden" name="currency_id" id="currency_id" value="0">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="codeSelect" class="form-label">Select Currency</label>
                                    <select class="form-select select2" id="codeSelect" aria-label="Select Currency Code">
                                        <option value="">-- Select or search for a currency --</option>
                                        
                                        <optgroup label="Common Currencies">
                                            <?php foreach($common_currencies as $code => $name): ?>
                                            <option value="<?php echo $code; ?>" data-name="<?php echo $name; ?>">
                                                <?php echo $code; ?> - <?php echo $name; ?> <?php echo isset($currency_symbols[$code]) ? "({$currency_symbols[$code]})" : ''; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        
                                        <optgroup label="All Other Currencies">
                                            <?php foreach($other_currencies as $code => $name): ?>
                                            <option value="<?php echo $code; ?>" data-name="<?php echo $name; ?>">
                                                <?php echo $code; ?> - <?php echo $name; ?> <?php echo isset($currency_symbols[$code]) ? "({$currency_symbols[$code]})" : ''; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="code" class="form-label">Currency Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-code me-1"></i></span>
                                        <input type="text" class="form-control" id="code" name="code" maxlength="3" required placeholder="e.g., USD">
                                        <span class="input-group-text" id="currencySymbol">Symbol: $</span>
                                    </div>
                                    <div class="form-text">3 letter ISO currency code</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Currency Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-font me-1"></i></span>
                                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., US Dollar">
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="rate" class="form-label">Exchange Rate</label>
                                    <div class="input-group">
                                        <span class="input-group-text">1 USD =</span>
                                        <input type="number" step="0.000001" min="0.000001" class="form-control" id="rate" name="rate" required placeholder="e.g., 0.85 for Euro">
                                        <span class="input-group-text" id="codeBadge">USD</span>
                                    </div>
                                    <div class="form-text">Rate relative to 1 USD</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary" name="add_currency" id="submitBtn">
                                    <i class="fas fa-plus-circle me-1"></i> Add Currency
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Currency List Card -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Currency Rates</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="currencySearch" placeholder="Search currencies...">
                            </div>
                            <small class="form-text text-muted">Search by code, name, or symbol</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="currenciesTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Symbol</th>
                                        <th>Name</th>
                                        <th>Rate (1 USD =)</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($currencies)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No currencies found.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($currencies as $currency): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($currency['code']); ?></td>
                                        <td><?php echo isset($currency_symbols[$currency['code']]) ? $currency_symbols[$currency['code']] : $currency['code']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($currency['name']); ?>
                                            <?php if (strpos($currency['name'], '(Rate in thousands)') !== false): ?>
                                                <span class="badge bg-info ms-1" title="This rate is stored at 1/1000 of actual value due to its high value">Adjusted</span>
                                            <?php endif; ?>
                                            <?php if ($currency['code'] === 'USD'): ?>
                                                <span class="badge bg-primary ms-1" title="Base currency used for all conversions">Base Currency</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            // Format number with 6 decimal places then trim trailing zeros
                                            $formatted_rate = number_format($currency['rate'], 6); 
                                            // Remove trailing zeros
                                            $formatted_rate = rtrim(rtrim($formatted_rate, '0'), '.');
                                            echo $formatted_rate;
                                            ?>
                                            <?php if (strpos($currency['name'], '(Rate in thousands)') !== false): ?>
                                                <span class="text-muted">× 1000</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($currency['last_updated']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary edit-currency" 
                                                    data-currency='<?php echo json_encode($currency); ?>'
                                                    <?php echo ($currency['code'] === 'USD') ? 'data-usd="true"' : ''; ?>>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($currency['code'] != 'USD'): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-currency-btn" 
                                                    data-currency-id="<?php echo $currency['currency_id']; ?>"
                                                    data-currency-code="<?php echo htmlspecialchars($currency['code']); ?>"
                                                    data-currency-name="<?php echo htmlspecialchars($currency['name']); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-danger" disabled title="Base currency (USD) cannot be deleted">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCurrencyModal" tabindex="-1" aria-labelledby="deleteCurrencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCurrencyModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="display-1 text-danger">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                </div>
                <p class="text-center fs-5">Are you sure you want to delete the currency <strong id="currencyToDelete"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    This action cannot be undone. Any prices stored in this currency will need to be manually converted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <form method="POST" id="deleteCurrencyForm">
                    <input type="hidden" name="delete_currency" value="1">
                    <input type="hidden" name="currency_id" id="deleteCurrencyId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt me-1"></i>Delete Currency
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for currency dropdown with search
    if (typeof $.fn.select2 !== 'undefined') {
        $('#codeSelect').select2({
            width: '100%',
            placeholder: 'Select or search for a currency',
            allowClear: true,
            theme: 'bootstrap-5',
            templateResult: formatCurrencyOption,
            templateSelection: formatCurrencyOption
        });
    } else {
        console.warn('Select2 library not loaded. Falling back to basic select.');
    }
    
    // Format currency options with symbols
    function formatCurrencyOption(option) {
        if (!option.id) return option.text;
        
        const code = option.id;
        const symbol = currencySymbols[code] || code;
        
        return $(`<span><strong>${code}</strong> - ${option.text.split(' - ')[1] || option.text} <span class="ms-1">(${symbol})</span></span>`);
    }

    // Edit currency functionality
    const editButtons = document.querySelectorAll('.edit-currency');
    const form = document.getElementById('currencyForm');
    const formTitle = document.getElementById('formTitle');
    const submitBtn = document.getElementById('submitBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const codeSelect = document.getElementById('codeSelect');
    const codeInput = document.getElementById('code');
    const nameInput = document.getElementById('name');
    const rateInput = document.getElementById('rate');
    const codeBadge = document.getElementById('codeBadge');
    const currencySymbol = document.getElementById('currencySymbol');
    
    // Currency symbols mapping
    const currencySymbols = <?php echo json_encode($currency_symbols); ?>;
    
    // Add event listener to form to handle submission
    form.addEventListener('submit', function(e) {
        const isUsdUpdate = codeInput.disabled && codeInput.value === 'USD';
        
        if (isUsdUpdate) {
            // When updating USD, ensure name field is filled
            if (!nameInput.value.trim()) {
                e.preventDefault();
                alert('Currency name is required.');
                nameInput.focus();
            }
        }
    });
    
    // Update symbol based on code
    codeInput.addEventListener('input', function() {
        const code = this.value.toUpperCase();
        if (code) {
            codeBadge.textContent = code;
            currencySymbol.textContent = 'Symbol: ' + (currencySymbols[code] || code);
        }
    });
    
    // Currency dropdown change handler
    codeSelect.addEventListener('change', function() {
        if (this.value) {
            const selectedOption = this.options[this.selectedIndex];
            const code = this.value;
            const name = selectedOption.dataset.name;
            
            codeInput.value = code;
            nameInput.value = name;
            codeBadge.textContent = code;
            currencySymbol.textContent = 'Symbol: ' + (currencySymbols[code] || code);
        }
    });
    
    // Handle Select2 change event
    $('#codeSelect').on('select2:select', function(e) {
        const data = e.params.data;
        const code = data.id;
        const nameParts = data.text.split(' - ');
        const name = nameParts.length > 1 ? nameParts[1].split(' (')[0] : data.text;
        
        codeInput.value = code;
        nameInput.value = name;
        codeBadge.textContent = code;
        currencySymbol.textContent = 'Symbol: ' + (currencySymbols[code] || code);
    });
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currency = JSON.parse(this.getAttribute('data-currency'));
            const isUsd = this.hasAttribute('data-usd');
            
            // Fill the form with currency data
            document.getElementById('currency_id').value = currency.currency_id;
            codeInput.value = currency.code;
            nameInput.value = currency.name;
            rateInput.value = currency.rate;
            codeBadge.textContent = currency.code;
            currencySymbol.textContent = 'Symbol: ' + (currencySymbols[currency.code] || currency.code);
            
            // Update form UI for editing mode
            formTitle.textContent = 'Edit Currency';
            submitBtn.textContent = 'Update Currency';
            submitBtn.name = 'update_currency';
            submitBtn.innerHTML = '<i class="fas fa-save me-1"></i> Update Currency';
            cancelBtn.classList.remove('d-none');
            
            // Disable code field for USD (base currency)
            if (isUsd) {
                codeInput.disabled = true;
                // For USD (base currency), also disable rate editing as it's always 1.0
                rateInput.disabled = true;
                rateInput.value = 1.0;
                // Add a note about USD being the base currency
                submitBtn.insertAdjacentHTML('afterend', 
                    '<div id="usdNote" class="mt-2 alert alert-info">' +
                    '<i class="fas fa-info-circle me-2"></i>' +
                    '<strong>Base Currency:</strong> For USD, only the name can be edited. ' +
                    'The code and rate are fixed as this is the base currency for all conversions.' +
                    '</div>');
                
                // Update the form title to be clearer
                formTitle.textContent = 'Edit USD (Base Currency)';
            } else {
                codeInput.disabled = false;
                rateInput.disabled = false;
                // Remove any existing USD note
                const usdNote = document.getElementById('usdNote');
                if (usdNote) usdNote.remove();
            }
            
            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    
    // Cancel button functionality
    cancelBtn.addEventListener('click', function() {
        resetForm();
    });
    
    function resetForm() {
        form.reset();
        document.getElementById('currency_id').value = 0;
        codeInput.disabled = false;
        rateInput.disabled = false;
        codeBadge.textContent = 'USD';
        currencySymbol.textContent = 'Symbol: $';
        formTitle.textContent = 'Add New Currency';
        submitBtn.textContent = 'Add Currency';
        submitBtn.innerHTML = '<i class="fas fa-plus-circle me-1"></i> Add Currency';
        submitBtn.name = 'add_currency';
        cancelBtn.classList.add('d-none');
        
        // Remove any USD note
        const usdNote = document.getElementById('usdNote');
        if (usdNote) usdNote.remove();
    }
    
    // Delete currency modal setup
    const deleteButtons = document.querySelectorAll('.delete-currency-btn');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteCurrencyModal'));
    const currencyToDeleteEl = document.getElementById('currencyToDelete');
    const deleteCurrencyIdEl = document.getElementById('deleteCurrencyId');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const currencyId = this.getAttribute('data-currency-id');
            const currencyCode = this.getAttribute('data-currency-code');
            const currencyName = this.getAttribute('data-currency-name');
            
            currencyToDeleteEl.textContent = `${currencyCode} (${currencyName})`;
            deleteCurrencyIdEl.value = currencyId;
            
            deleteModal.show();
        });
    });
    
    // Initialize DataTable if available
    if (typeof $.fn.DataTable !== 'undefined') {
        const table = $('#currenciesTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
        });
        
        // Use DataTable search functionality
        $('#currencySearch').on('keyup', function() {
            table.search(this.value).draw();
        });
    } else {
        // Fallback for basic search if DataTables isn't available
        $('#currencySearch').on('keyup', function() {
            const searchText = this.value.toLowerCase();
            $('#currenciesTable tbody tr').each(function() {
                const rowText = $(this).text().toLowerCase();
                $(this).toggle(rowText.indexOf(searchText) > -1);
            });
        });
    }
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 