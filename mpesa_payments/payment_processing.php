<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize debug log
$log_file = 'mpesa_debug.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("======= NEW PAYMENT PROCESS STARTED =======");
log_debug("POST data: " . print_r($_POST, true));

// Database connection parameters
$host = "localhost";
$db = "wifi"; 
$user = "root"; 
$pass = "";

// Create the connection
$mysqli = new mysqli($host, $user, $pass, $db);

// Check the connection
if ($mysqli->connect_error) {
    log_debug("Database connection failed: " . $mysqli->connect_error);
    $_SESSION['payment_error'] = 'Database connection failed: ' . $mysqli->connect_error;
    header('Location: checkout.php');
    exit;
}

log_debug("Database connection successful");

// Define credentials
$consumerKey = 'bAoiO0bYMLsAHDgzGSGVMnpSAxSUuCMEfWkrrAOK1MZJNAcA';
$consumerSecret = '2idZFLPp26Du8JdF9SB3nLpKrOJO67qDIkvICkkVl7OhADTQCb0Oga5wNgzu1xQx';

function generateAccessToken($consumerKey, $consumerSecret) {
    global $log_file;
    log_debug("Generating access token...");
    
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $access_token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $access_token_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($curl);
    log_debug("Access token response: " . $response);

    if (curl_errno($curl)) {
        $error = 'Curl error: ' . curl_error($curl);
        log_debug($error);
        $_SESSION['payment_error'] = $error;
        header('Location: checkout.php');
        exit;
    }

    curl_close($curl);
    $result = json_decode($response);

    if (isset($result->access_token)) {
        log_debug("Access token generated successfully");
        return $result->access_token;
    } else {
        $error = 'Failed to generate access token: ' . ($result->errorMessage ?? 'Unknown error');
        log_debug($error);
        $_SESSION['payment_error'] = $error;
        header('Location: checkout.php');
        exit;
    }
}

// Get access token
log_debug("Requesting access token");
$access_token = generateAccessToken($consumerKey, $consumerSecret);
log_debug("Access token: " . substr($access_token, 0, 10) . '...');

// Define transaction details
$BusinessShortCode = '174379';
$Passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
date_default_timezone_set('Africa/Nairobi'); // Ensure correct timezone
$Timestamp = date('YmdHis');
$Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

log_debug("Transaction details prepared: " . json_encode([
    'BusinessShortCode' => $BusinessShortCode,
    'Timestamp' => $Timestamp
]));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    log_debug("Processing POST request");
    
    if (isset($_POST['mpesa-number1'])) {
        $phone_number = $_POST['mpesa-number1'];
        log_debug("Phone number received: " . $phone_number);
        
        // Format phone number to ensure it starts with 254
        if (substr($phone_number, 0, 1) == '0') {
            $phone_number = '254' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 3) != '254') {
            $phone_number = '254' . $phone_number;
        }
        log_debug("Formatted phone number: " . $phone_number);
        
        // Get amount from form (use default if not provided)
        $amount = isset($_POST['amount_kes']) ? intval($_POST['amount_kes']) : 30;
        log_debug("Amount in KES: " . $amount);
        
        // Validate amount
        if ($amount <= 0) {
            log_debug("ERROR: Invalid amount detected: " . $amount . ". Amount must be a positive integer.");
            echo "Error initiating payment - invalid amount (must be positive)";
            exit;
        }

        // M-Pesa sandbox minimum is sometimes 10 KES
        if ($amount < 10) {
            log_debug("WARNING: Amount less than 10 KES. Setting to minimum 10 KES for sandbox testing.");
            $amount = 10;
        }

        log_debug("Validated amount: " . $amount);
        
        $product_name = isset($_POST['product_name']) ? $_POST['product_name'] : 'Digital Product';
        log_debug("Product name: " . $product_name);
        
        // Payment details
        $PartyA = $phone_number;
        $PartyB = $BusinessShortCode;
        $Amount = $amount;
        
        // Generate dynamic callback URL based on server hostname
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $CallBackURL = $protocol . $host . "/marketplace/callback.php";
        log_debug("Callback URL: " . $CallBackURL);
        
        $AccountReference = 'Order' . time(); // Unique reference
        $TransactionDesc = 'Payment for ' . $product_name;

        // Set up the STK Push transaction
        log_debug("Setting up STK Push with amount: " . $amount . " and phone number: " . $phone_number);

        $timestamp = date('YmdHis');
        $businessShortCode = '174379'; // Replace with actual shortcode
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'; // Replace with actual passkey
        $callbackUrl = 'https://example.com/callback'; // Replace with actual callback URL
        $accountReference = 'Brian Testing';
        $transactionDesc = 'Qtro Testing';

        $password = base64_encode($businessShortCode . $passkey . $timestamp);

        $stk_push_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        // Prepare post data for STK Push
        $post_data = array(
            'BusinessShortCode' => $businessShortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone_number,
            'PartyB' => $businessShortCode,
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc
        );

        // Debug the exact data being sent to M-Pesa API
        log_debug("STK Push payload: " . json_encode($post_data, JSON_PRETTY_PRINT));
        log_debug("Amount value type: " . gettype($post_data['Amount']) . ", Value: " . $post_data['Amount']);

        // Make the STK Push request
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $stk_push_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $access_token));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        log_debug("Sending STK Push request");
        $response = curl_exec($curl);
        log_debug("STK Push response: " . $response);

        $curl_info = curl_getinfo($curl);
        log_debug("HTTP status code: " . $curl_info['http_code']);
        log_debug("Request took " . $curl_info['total_time'] . " seconds");

        if ($response === false) {
            $error = 'Curl failed: ' . curl_error($curl);
            log_debug($error);
            $_SESSION['payment_error'] = $error;
            header('Location: checkout.php');
            exit;
        }

        curl_close($curl);
        $response_data = json_decode($response);

        // Log all response data for debugging
        log_debug("Response data: " . print_r($response_data, true));

        if (isset($response_data->ResponseCode) && $response_data->ResponseCode == '0') {
            log_debug("STK Push successful! CheckoutRequestID: " . $response_data->CheckoutRequestID);
            
            // STK Push was successful - store checkout ID in session
            $_SESSION['mpesa_checkout_id'] = $response_data->CheckoutRequestID;
            $_SESSION['payment_message'] = 'Please check your phone and enter M-Pesa PIN to complete payment.';
            
            // Close database connection
            $mysqli->close();
            
            // Redirect to dashboard
            log_debug("Redirecting to dashboard");
            header('Location: userdashboard.php');
            exit;
        } else {
            // STK Push failed
            $error_message = isset($response_data->errorMessage) ? $response_data->errorMessage : 'Unknown error';
            log_debug("STK Push failed: " . $error_message);
            $_SESSION['payment_error'] = 'Error initiating payment: ' . $error_message;
            
            header('Location: checkout.php');
            exit;
        }
    } else {
        log_debug("Missing phone number in POST data");
        $_SESSION['payment_error'] = 'Missing phone number';
        header('Location: checkout.php');
        exit;
    }
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['payment_error'] = 'Invalid request method';
    header('Location: checkout.php');
    exit;
}
?>
