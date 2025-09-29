<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to save settings']);
    exit;
}

// Include database connection and functions
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';

// Get the reseller ID from the session
$reseller_id = $_SESSION['user_id'];

// Check if the database connection is established
if (!is_db_connected()) {
    error_log("Database connection error in save_mpesa_settings.php");
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// Verify if the reseller exists in the database
$checkReseller = $conn->prepare("SELECT id FROM resellers WHERE id = ?");
$checkReseller->bind_param("i", $reseller_id);
$checkReseller->execute();
$resellerResult = $checkReseller->get_result();

// If reseller doesn't exist, create a temporary record
if ($resellerResult->num_rows == 0) {
    error_log("Reseller ID $reseller_id does not exist in database. Creating temporary record.");
    
    // Default to monthly billing cycle
    $paymentInterval = filter_input(INPUT_POST, 'billing_cycle', FILTER_SANITIZE_STRING) ?: 'monthly';
    
    try {
        // Insert basic reseller record to satisfy foreign key constraint
        $createReseller = $conn->prepare("INSERT INTO resellers (id, business_name, full_name, email, phone, password, payment_interval) 
                                        VALUES (?, 'Temporary Business', 'Temporary User', 'temp_user_$reseller_id@example.com', '0700000000', 'temporary_password', ?)");
        $createReseller->bind_param("is", $reseller_id, $paymentInterval);
        $createResult = $createReseller->execute();
        
        if (!$createResult) {
            error_log("Failed to create temporary reseller record: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Could not create reseller account. Please contact support.']);
            exit;
        }
    } catch (Exception $e) {
        error_log("Exception creating reseller: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error creating account: ' . $e->getMessage()]);
        exit;
    }
}

// Process only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if the required parameters are set
if (!isset($_POST['payment_gateway']) || !isset($_POST['environment'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get form data
$paymentGateway = filter_input(INPUT_POST, 'payment_gateway', FILTER_SANITIZE_STRING);
$environment = filter_input(INPUT_POST, 'environment', FILTER_SANITIZE_STRING);

// Validate the gateway type
if (!in_array($paymentGateway, ['phone', 'paybill', 'till', 'paystack'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment gateway type']);
    exit;
}

// Validate the environment
if (!in_array($environment, ['sandbox', 'live'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid environment']);
    exit;
}

// Get the system's predefined M-Pesa API credentials
$systemCredentials = getSystemMpesaApiCredentials();

// Initialize settings array with common values
$settings = [
    'payment_gateway' => $paymentGateway,
    'environment' => $environment,
    'is_active' => true,
    'callback_url' => $systemCredentials['callback_url'], // Use system callback URL
];

// Set values based on payment gateway type
switch ($paymentGateway) {
    case 'phone':
        $settings['mpesa_phone'] = filter_input(INPUT_POST, 'mpesa_phone', FILTER_SANITIZE_STRING);
        
        // For phone payment, ALWAYS use system API credentials - force override any existing values
        $settings['paybill_number'] = '';
        $settings['paybill_shortcode'] = $systemCredentials['shortcode'];
        $settings['paybill_passkey'] = $systemCredentials['passkey'];
        $settings['paybill_consumer_key'] = $systemCredentials['consumer_key'];
        $settings['paybill_consumer_secret'] = $systemCredentials['consumer_secret'];
        
        // Empty till settings but use system credentials for API consistency
        $settings['till_number'] = '';
        $settings['till_shortcode'] = $systemCredentials['shortcode'];
        $settings['till_passkey'] = $systemCredentials['passkey'];
        $settings['till_consumer_key'] = $systemCredentials['consumer_key'];
        $settings['till_consumer_secret'] = $systemCredentials['consumer_secret'];
        
        // Empty Paystack settings
        $settings['paystack_secret_key'] = '';
        $settings['paystack_public_key'] = '';
        $settings['paystack_email'] = '';
        
        // Add explicit logging to verify system credentials are being applied
        error_log("Phone payment selected - Applying system API credentials: " . 
                 "Shortcode: " . $settings['paybill_shortcode'] . 
                 ", Consumer Key: " . substr($settings['paybill_consumer_key'], 0, 5) . "..." .
                 " for reseller: " . $reseller_id);
        
        // Update the reseller's payment interval if provided
        $billingCycle = filter_input(INPUT_POST, 'billing_cycle', FILTER_SANITIZE_STRING);
        if ($billingCycle && in_array($billingCycle, ['monthly', 'weekly'])) {
            $updateInterval = $conn->prepare("UPDATE resellers SET payment_interval = ? WHERE id = ?");
            $updateInterval->bind_param("si", $billingCycle, $reseller_id);
            $updateInterval->execute();
        }
        break;
    
    case 'paybill':
        // For paybill, allow user to specify all fields
        $settings['paybill_number'] = filter_input(INPUT_POST, 'paybill_number', FILTER_SANITIZE_STRING);
        $settings['paybill_shortcode'] = filter_input(INPUT_POST, 'paybill_shortcode', FILTER_SANITIZE_STRING);
        $settings['paybill_passkey'] = filter_input(INPUT_POST, 'paybill_passkey', FILTER_SANITIZE_STRING);
        $settings['paybill_consumer_key'] = filter_input(INPUT_POST, 'paybill_consumer_key', FILTER_SANITIZE_STRING);
        $settings['paybill_consumer_secret'] = filter_input(INPUT_POST, 'paybill_consumer_secret', FILTER_SANITIZE_STRING);
        
        // Empty phone number
        $settings['mpesa_phone'] = '';
        
        // Empty till settings
        $settings['till_number'] = '';
        $settings['till_shortcode'] = '';
        $settings['till_passkey'] = '';
        $settings['till_consumer_key'] = '';
        $settings['till_consumer_secret'] = '';
        
        // Empty Paystack settings
        $settings['paystack_secret_key'] = '';
        $settings['paystack_public_key'] = '';
        $settings['paystack_email'] = '';
        break;
    
    case 'till':
        // For till, allow user to specify all fields
        $settings['till_number'] = filter_input(INPUT_POST, 'till_number', FILTER_SANITIZE_STRING);
        $settings['till_shortcode'] = filter_input(INPUT_POST, 'till_shortcode', FILTER_SANITIZE_STRING);
        $settings['till_passkey'] = filter_input(INPUT_POST, 'till_passkey', FILTER_SANITIZE_STRING);
        $settings['till_consumer_key'] = filter_input(INPUT_POST, 'till_consumer_key', FILTER_SANITIZE_STRING);
        $settings['till_consumer_secret'] = filter_input(INPUT_POST, 'till_consumer_secret', FILTER_SANITIZE_STRING);
        
        // Empty phone number
        $settings['mpesa_phone'] = '';
        
        // Empty paybill settings
        $settings['paybill_number'] = '';
        $settings['paybill_shortcode'] = '';
        $settings['paybill_passkey'] = '';
        $settings['paybill_consumer_key'] = '';
        $settings['paybill_consumer_secret'] = '';
        
        // Empty Paystack settings
        $settings['paystack_secret_key'] = '';
        $settings['paystack_public_key'] = '';
        $settings['paystack_email'] = '';
        break;
        
    case 'paystack':
        // For Paystack, get the user-specified fields
        $settings['paystack_secret_key'] = filter_input(INPUT_POST, 'paystack_secret_key', FILTER_SANITIZE_STRING);
        $settings['paystack_public_key'] = filter_input(INPUT_POST, 'paystack_public_key', FILTER_SANITIZE_STRING);
        $settings['paystack_email'] = filter_input(INPUT_POST, 'paystack_email', FILTER_SANITIZE_EMAIL);
        
        // Empty M-Pesa phone settings
        $settings['mpesa_phone'] = '';
        
        // Empty paybill settings
        $settings['paybill_number'] = '';
        $settings['paybill_shortcode'] = '';
        $settings['paybill_passkey'] = '';
        $settings['paybill_consumer_key'] = '';
        $settings['paybill_consumer_secret'] = '';
        
        // Empty till settings
        $settings['till_number'] = '';
        $settings['till_shortcode'] = '';
        $settings['till_passkey'] = '';
        $settings['till_consumer_key'] = '';
        $settings['till_consumer_secret'] = '';
        
        error_log("Paystack payment selected for reseller: " . $reseller_id);
        break;
}

// Log the settings data for debugging (remove in production)
error_log("Saving M-Pesa settings for reseller ID: $reseller_id with gateway: $paymentGateway");

// Save settings
try {
    $result = saveMpesaSettings($conn, $reseller_id, $settings);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'M-Pesa settings saved successfully'
        ]);
    } else {
        error_log("Failed to save M-Pesa settings for reseller ID: $reseller_id");
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to save M-Pesa settings. Please check all fields and try again.'
        ]);
    }
} catch (Exception $e) {
    error_log("Exception in save_mpesa_settings.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 