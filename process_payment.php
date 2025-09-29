<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the portal database connection
require_once 'portal_connection.php';

// Include M-Pesa settings operations
require_once 'mpesa_settings_operations.php';

// Initialize debug log
$log_file = 'mpesa_debug.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("======= NEW PAYMENT PROCESS STARTED =======");
log_debug("POST data: " . print_r($_POST, true));

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $resellerId = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 0;
    $packageName = isset($_POST['package_name']) ? $_POST['package_name'] : '';
    $packagePrice = isset($_POST['package_price']) ? $_POST['package_price'] : '';
    $mpesaNumber = isset($_POST['mpesa_number']) ? $_POST['mpesa_number'] : '';
    $packageId = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $routerId = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
    
    log_debug("Form data received: " . json_encode([
        'resellerId' => $resellerId,
        'packageName' => $packageName,
        'packagePrice' => $packagePrice,
        'mpesaNumber' => $mpesaNumber,
        'packageId' => $packageId,
        'routerId' => $routerId
    ]));
    
    // Validate inputs
    if (empty($resellerId) || empty($packageName) || empty($packagePrice) || empty($mpesaNumber) || empty($packageId)) {
        log_debug("Missing required fields");
        $_SESSION['payment_error'] = 'Missing required fields';
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Format phone number (remove spaces, ensure it starts with 254)
    $mpesaNumber = preg_replace('/\s+/', '', $mpesaNumber);
    
    // If number starts with 0, replace with 254
    if (substr($mpesaNumber, 0, 1) === '0') {
        $mpesaNumber = '254' . substr($mpesaNumber, 1);
    } elseif (substr($mpesaNumber, 0, 3) !== '254') {
        $mpesaNumber = '254' . $mpesaNumber;
    }
    
    log_debug("Formatted phone number: " . $mpesaNumber);
    
    // Validate amount
    $packagePrice = intval($packagePrice);
    if ($packagePrice <= 0) {
        log_debug("ERROR: Invalid amount detected: " . $packagePrice . ". Amount must be a positive integer.");
        $_SESSION['payment_error'] = 'Invalid amount (must be positive)';
        echo json_encode(['success' => false, 'message' => 'Invalid amount (must be positive)']);
        exit;
    }

    // M-Pesa sandbox minimum is sometimes 10 KES
    if ($packagePrice < 10) {
        log_debug("WARNING: Amount less than 10 KES. Setting to minimum 10 KES for sandbox testing.");
        $packagePrice = 10;
    }
    
    log_debug("Validated amount: " . $packagePrice);
    
    // M-Pesa API integration
    // Get reseller-specific M-Pesa credentials
    $mpesaCredentials = getMpesaCredentials($conn, $resellerId);
    
    $consumerKey = $mpesaCredentials['consumer_key'];
    $consumerSecret = $mpesaCredentials['consumer_secret'];
    
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
            return false;
        }

        curl_close($curl);
        $result = json_decode($response);

        if (isset($result->access_token)) {
            log_debug("Access token generated successfully: " . substr($result->access_token, 0, 10) . '...');
            return $result->access_token;
        } else {
            $error = 'Failed to generate access token: ' . (isset($result->errorMessage) ? $result->errorMessage : 'Unknown error');
            log_debug($error);
            $_SESSION['payment_error'] = $error;
            return false;
        }
    }

    $access_token = generateAccessToken($consumerKey, $consumerSecret);
    if (!$access_token) {
        echo json_encode(['success' => false, 'message' => 'Failed to generate access token']);
        exit;
    }

    $BusinessShortCode = $mpesaCredentials['business_shortcode'];
    $Passkey = $mpesaCredentials['passkey'];
    date_default_timezone_set('Africa/Nairobi');
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $Passkey . $Timestamp);

    // Use the callback URL from the settings if available, otherwise use the default
    $CallBackURL = !empty($mpesaCredentials['callback_url']) ? $mpesaCredentials['callback_url'] : 'https://mydomain.com/path';
    log_debug("Using callback URL: " . $CallBackURL);

    // Enhanced logging when using ngrok
    if (strpos($CallBackURL, 'ngrok') !== false) {
        log_debug("NGROK detected in callback URL - ensure this matches your active ngrok tunnel");
    }

    // Check for common callback URL issues
    if (strpos($CallBackURL, 'localhost') !== false || strpos($CallBackURL, '127.0.0.1') !== false) {
        log_debug("WARNING: Callback URL contains localhost which won't work with M-Pesa");
    }

    $AccountReference = 'Qtro Technologies';
    $TransactionDesc = 'Payment for ' . $packageName;

    $stk_push_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    $post_data = array(
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $packagePrice,
        'PartyA' => $mpesaNumber,
        'PartyB' => $BusinessShortCode,
        'PhoneNumber' => $mpesaNumber,
        'CallBackURL' => $CallBackURL,
        'AccountReference' => $AccountReference,
        'TransactionDesc' => $TransactionDesc
    );

    log_debug("STK Push payload: " . json_encode($post_data, JSON_PRETTY_PRINT));

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
        $error_message = 'Curl failed: ' . curl_error($curl);
        log_debug($error_message);
        $_SESSION['payment_error'] = $error_message;
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    curl_close($curl);
    $response_data = json_decode($response);
    log_debug("Response data: " . print_r($response_data, true));

    if (isset($response_data->ResponseCode) && $response_data->ResponseCode == '0') {
        log_debug("STK Push successful! CheckoutRequestID: " . $response_data->CheckoutRequestID);
        log_debug("MerchantRequestID: " . $response_data->MerchantRequestID);
        
        // Store transaction details in the database with all available info
        try {
            // If table doesn't exist yet, this will fail but we'll continue processing
            $query = "INSERT INTO mpesa_transactions 
                     (checkout_request_id, merchant_request_id, amount, phone_number, package_id, package_name, reseller_id, router_id, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                     
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("ssdsisii", 
                    $response_data->CheckoutRequestID,
                    $response_data->MerchantRequestID, 
                    $packagePrice, 
                    $mpesaNumber, 
                    $packageId, 
                    $packageName, 
                    $resellerId,
                    $routerId
                );
                $stmt->execute();
                log_debug("Transaction details saved to database with ID: " . $conn->insert_id);
            } else {
                log_debug("Failed to prepare transaction statement: " . $conn->error);
            }
        } catch (Exception $e) {
            log_debug("Database error when saving transaction: " . $e->getMessage());
            // Continue even if DB error occurs, to not disrupt user flow
        }

        // Store checkout ID in session
        $_SESSION['mpesa_checkout_id'] = $response_data->CheckoutRequestID;
        $_SESSION['mpesa_merchant_request_id'] = $response_data->MerchantRequestID;
        $_SESSION['payment_initiated'] = true;
        $_SESSION['payment_phone'] = $mpesaNumber;
        $_SESSION['payment_amount'] = $packagePrice;
        $_SESSION['payment_timestamp'] = time();
        
        log_debug("Sending successful response to client");
        echo json_encode([
            'success' => true, 
            'message' => 'Payment request sent. Please check your phone to complete the transaction.',
            'checkout_request_id' => $response_data->CheckoutRequestID,
            'merchant_request_id' => $response_data->MerchantRequestID,
            'instructions' => 'Please enter your M-Pesa PIN when prompted on your phone. After payment, you will need to come back to this page and notify the system that you have completed payment.'
        ]);
    } else {
        $error_message = isset($response_data->errorMessage) ? $response_data->errorMessage : 'Unknown error';
        log_debug("STK Push failed: " . $error_message);
        $_SESSION['payment_error'] = 'Error initiating payment: ' . $error_message;
        
        echo json_encode(['success' => false, 'message' => 'Error initiating payment: ' . $error_message]);
    }
    exit;
} else {
    // Not a POST request
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?> 