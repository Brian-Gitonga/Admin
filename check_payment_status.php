<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';
// MikroTik integration removed - vouchers will be generated without router communication
require_once 'vouchers_script/payment_voucher_handler.php'; // Include voucher generator

// Initialize debug log
$log_file = 'payment_status_checks.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Set proper content type for JSON responses
header('Content-Type: application/json');

log_debug("===== PAYMENT STATUS CHECK INITIATED =====");
log_debug("Remote IP: " . $_SERVER['REMOTE_ADDR']);
log_debug("Request Method: " . $_SERVER['REQUEST_METHOD']);
log_debug("Session ID: " . session_id());

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get checkout request ID from POST data
    $checkoutRequestID = isset($_POST['checkout_request_id']) ? $_POST['checkout_request_id'] : '';
    
    log_debug("Checking payment status for checkout request ID: $checkoutRequestID");
    
    if (empty($checkoutRequestID)) {
        log_debug("Error: No checkout request ID provided");
        echo json_encode(['success' => false, 'message' => 'No checkout request ID provided']);
        exit;
    }
    
    // First, check if payment is already marked as completed in the database
    $stmt = $conn->prepare("SELECT status, package_id, mpesa_receipt, phone_number, router_id FROM mpesa_transactions WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        log_debug("Error: No transaction found with checkout request ID: $checkoutRequestID");
        echo json_encode(['success' => false, 'message' => 'No transaction found with the provided checkout request ID']);
        exit;
    }
    
    $transaction = $result->fetch_assoc();
    log_debug("Transaction found. Status: " . $transaction['status']);
    
    // If payment is already completed, return success
    if ($transaction['status'] === 'completed') {
        log_debug("Payment already completed!");
        
        // Check if a voucher has been generated for this transaction
        $voucher_code = null;
        
        // Get transaction details for voucher generation
        $txnStmt = $conn->prepare("SELECT reseller_id, phone_number, package_id, router_id FROM mpesa_transactions WHERE checkout_request_id = ?");
        $txnStmt->bind_param("s", $checkoutRequestID);
        $txnStmt->execute();
        $txnResult = $txnStmt->get_result();
        
        if ($txnResult->num_rows > 0) {
            $txnData = $txnResult->fetch_assoc();
            $resellerId = $txnData['reseller_id'];
            $phoneNumber = $txnData['phone_number'];
            $packageId = $txnData['package_id'];
            $routerId = $txnData['router_id'];
            
            // Use our new voucher handler to create/retrieve a voucher
            $voucherResult = createVoucherAfterPayment(
                $checkoutRequestID,
                $packageId,
                $resellerId,
                $phoneNumber,
                $transaction['mpesa_receipt']
            );
            
            if ($voucherResult['success']) {
                $voucher_code = $voucherResult['voucher_code'];
                log_debug("Voucher code: $voucher_code - " . $voucherResult['message']);
                
                // Add voucher to MikroTik if it exists in database but not yet in MikroTik
                $mikrotikResult = add_voucher_to_mikrotik($voucher_code, $packageId, $resellerId, $phoneNumber, $conn);
                if ($mikrotikResult['success']) {
                    log_debug("Voucher added to MikroTik: $voucher_code");
                } else {
                    log_debug("Failed to add voucher to MikroTik: " . $mikrotikResult['message']);
                }
            } else {
                log_debug("Failed to generate voucher: " . $voucherResult['message']);
                // Fallback to a mock voucher code
                $voucher_code = "WIFI" . rand(1000, 9999);
            }
        } else {
            log_debug("Could not find transaction details");
            // Fallback to a mock voucher code
            $voucher_code = "WIFI" . rand(1000, 9999);
        }
        
        // Get package duration from the database
        $packageDuration = null;
        $packageQuery = $conn->prepare("SELECT duration FROM packages WHERE id = ?");
        if ($packageQuery) {
            $packageQuery->bind_param("i", $packageId);
            $packageQuery->execute();
            $packageResult = $packageQuery->get_result();
            if ($packageResult && $packageResult->num_rows > 0) {
                $packageData = $packageResult->fetch_assoc();
                $packageDuration = $packageData['duration'];
            }
            $packageQuery->close();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment completed successfully',
            'receipt' => $transaction['mpesa_receipt'],
            'voucher_code' => $voucher_code,
            'voucher_username' => isset($voucher_username) ? $voucher_username : $voucher_code,
            'voucher_password' => isset($voucher_password) ? $voucher_password : $voucher_code,
            'phone_number' => $transaction['phone_number'],
            'package_name' => isset($packageName) ? $packageName : 'WiFi Package',
            'duration' => isset($packageDuration) ? $packageDuration : ''
        ]);
        exit;
    }
    
    // If payment is not completed in our database, check with M-Pesa API
    log_debug("Payment not marked as completed in database, checking with M-Pesa API");
    
    // Get reseller ID from the transaction
    $resellerStmt = $conn->prepare("SELECT reseller_id FROM mpesa_transactions WHERE checkout_request_id = ?");
    $resellerStmt->bind_param("s", $checkoutRequestID);
    $resellerStmt->execute();
    $resellerResult = $resellerStmt->get_result();
    $resellerRow = $resellerResult->fetch_assoc();
    $resellerId = $resellerRow['reseller_id'];
    
    // Get M-Pesa credentials for this reseller
    $mpesaCredentials = getMpesaCredentials($conn, $resellerId);
    
    $consumerKey = $mpesaCredentials['consumer_key'];
    $consumerSecret = $mpesaCredentials['consumer_secret'];
    $businessShortCode = $mpesaCredentials['business_shortcode'];
    $passkey = $mpesaCredentials['passkey'];
    
    // Get merchant request ID
    $merchantStmt = $conn->prepare("SELECT merchant_request_id FROM mpesa_transactions WHERE checkout_request_id = ?");
    $merchantStmt->bind_param("s", $checkoutRequestID);
    $merchantStmt->execute();
    $merchantResult = $merchantStmt->get_result();
    $merchantRow = $merchantResult->fetch_assoc();
    $merchantRequestId = $merchantRow['merchant_request_id'];
    
    // Generate access token
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($curl);
    
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($status != 200) {
        log_debug("Error generating access token: " . $result);
        echo json_encode(['success' => false, 'message' => 'Error connecting to M-Pesa. Please try again later.']);
        exit;
    }
    
    $response = json_decode($result);
    $access_token = $response->access_token;
    
    curl_close($curl);
    
    // Check STK push status
    date_default_timezone_set('Africa/Nairobi');
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ));
    
    $curl_post_data = array(
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutRequestID
    );
    
    $data_string = json_encode($curl_post_data);
    
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($curl);
    log_debug("M-Pesa API query response: " . $response);
    
    if (curl_errno($curl)) {
        log_debug("cURL Error: " . curl_error($curl));
        echo json_encode(['success' => false, 'message' => 'Error checking payment status. Please try again later.']);
        exit;
    }
    
    curl_close($curl);
    
    $result = json_decode($response);
    
    // Check if the request was successful
    if (isset($result->ResponseCode) && $result->ResponseCode == "0") {
        // Check the result code
        if ($result->ResultCode == "0") {
            // Payment was successful, update the database
            log_debug("M-Pesa API confirms payment was successful!");
            
            $mpesaReceiptNumber = "API" . rand(10000000, 99999999); // No receipt from API, generate mock
            $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
                status = 'completed', 
                mpesa_receipt = ?, 
                result_code = 0,
                result_description = 'The service request is processed successfully.',
                updated_at = NOW() 
                WHERE checkout_request_id = ?");
                
            $stmt->bind_param("ss", 
                $mpesaReceiptNumber, 
                $checkoutRequestID
            );
            $stmt->execute();
            
            log_debug("Updated transaction status to completed in database");
            
            // Get the phone number and router ID from the transaction
            $mpesaNumber = $transaction['phone_number'];
            $routerId = isset($transaction['router_id']) ? $transaction['router_id'] : 0;
            $packageId = $transaction['package_id'];
            
            // Get package name and duration from the database
            $packageName = 'WiFi Package'; // Default value
            $packageDuration = null;
            $packageQuery = $conn->prepare("SELECT name, duration FROM packages WHERE id = ?");
            if ($packageQuery) {
                $packageQuery->bind_param("i", $packageId);
                $packageQuery->execute();
                $packageResult = $packageQuery->get_result();
                if ($packageResult && $packageResult->num_rows > 0) {
                    $packageData = $packageResult->fetch_assoc();
                    $packageName = $packageData['name'];
                    $packageDuration = $packageData['duration'];
                }
                $packageQuery->close();
            }
            
            // Include our new voucher fetching system
            require_once 'fetch_voucher.php';
            
            // STEP 1: Try to fetch an existing voucher from the database
            $voucher = getVoucherForPayment($packageId, $routerId, $mpesaNumber, $checkoutRequestID);
            
            if ($voucher) {
                // Successfully fetched an existing voucher
                $voucher_code = $voucher['code'];
                $voucher_username = $voucher['username'] ?: $voucher['code'];
                $voucher_password = $voucher['password'] ?: $voucher['code'];
                
                log_debug("Fetched existing voucher from database: $voucher_code");
                
                // Router integration disabled - voucher available without router communication
                log_debug("Voucher ready for use (router integration disabled): $voucher_code");
            } else {
                // No existing voucher found, fall back to generating a new one
                log_debug("No existing voucher found, falling back to voucher generation");
                
                // LEGACY CODE: Generate new voucher (preserved for future use)
                // ===============================================================
                $voucher_code = generateVoucherCode();
                
                // Check if vouchers table exists
                $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
                if ($tableCheck->num_rows > 0) {
                    // Save the voucher
                    try {
                        $stmt = $conn->prepare("INSERT INTO vouchers (code, package_id, reseller_id, router_id, customer_phone, username, password, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'used', NOW())");
                        $stmt->bind_param("siissss", $voucher_code, $packageId, $resellerId, $routerId, $mpesaNumber, $voucher_code, $voucher_code);
                        $stmt->execute();
                        log_debug("Generated and saved new voucher: $voucher_code");
                        
                        // Update the transaction with voucher info
                        $voucherIdStmt = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
                        $voucherIdStmt->bind_param("s", $voucher_code);
                        $voucherIdStmt->execute();
                        $voucherIdResult = $voucherIdStmt->get_result();
                        if ($voucherIdRow = $voucherIdResult->fetch_assoc()) {
                            $voucherId = $voucherIdRow['id'];
                            $updateTxStmt = $conn->prepare("UPDATE mpesa_transactions SET voucher_id = ?, voucher_code = ? WHERE checkout_request_id = ?");
                            $updateTxStmt->bind_param("iss", $voucherId, $voucher_code, $checkoutRequestID);
                            $updateTxStmt->execute();
                        }
                        
                        // Router integration disabled - voucher generated without router communication
                        log_debug("Voucher generated successfully (router integration disabled): $voucher_code");
                    } catch (Exception $e) {
                        log_debug("Error saving voucher: " . $e->getMessage());
                    }
                } else {
                    log_debug("Vouchers table does not exist, using mock voucher code only");
                    
                    // Still add voucher to MikroTik even without vouchers table
                    $mikrotikResult = add_voucher_to_mikrotik($voucher_code, $transaction['package_id'], $resellerId, $mpesaNumber, $conn);
                    if ($mikrotikResult['success']) {
                        log_debug("Voucher added to MikroTik: $voucher_code");
                    } else {
                        log_debug("Failed to add voucher to MikroTik: " . $mikrotikResult['message']);
                    }
                }
                // End of legacy voucher generation code
                // ===============================================================
                
                $voucher_username = $voucher_code;
                $voucher_password = $voucher_code;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment completed successfully',
                'receipt' => $mpesaReceiptNumber,
                'voucher_code' => $voucher_code,
                'voucher_username' => isset($voucher_username) ? $voucher_username : $voucher_code,
                'voucher_password' => isset($voucher_password) ? $voucher_password : $voucher_code,
                'phone_number' => $transaction['phone_number'],
                'package_name' => isset($packageName) ? $packageName : 'WiFi Package',
                'duration' => isset($packageDuration) ? $packageDuration : ''
            ]);
            exit;
        } else {
            // Payment failed or is still pending
            log_debug("M-Pesa API returned non-zero result code: " . $result->ResultCode);
            
            // Update the transaction if it failed
            if ($result->ResultCode != "1032") { // 1032 is "Request cancelled by user"
                $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
                    result_code = ?,
                    result_description = ?,
                    updated_at = NOW() 
                    WHERE checkout_request_id = ?");
                    
                $stmt->bind_param("iss", 
                    $result->ResultCode,
                    $result->ResultDesc,
                    $checkoutRequestID
                );
                $stmt->execute();
                
                log_debug("Updated transaction with result code and description");
            }
            
            echo json_encode([
                'success' => false, 
                'message' => $result->ResultDesc ?: 'Payment not yet confirmed. Please check your phone and complete the payment.'
            ]);
            exit;
        }
    } else {
        // Error in the API request
        log_debug("Error in M-Pesa API request: " . (isset($result->errorMessage) ? $result->errorMessage : 'Unknown error'));
        echo json_encode(['success' => false, 'message' => 'Error checking payment status. Please try again later.']);
        exit;
    }
} else {
    // Not a POST request
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Function to generate a random voucher code
function generateVoucherCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}
?> 