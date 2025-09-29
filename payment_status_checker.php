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

// Initialize log file
$log_file = 'payment_check.log';
function log_check($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_check("======= PAYMENT STATUS CHECK INITIATED =======");

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get checkout request ID from POST data
    $checkoutRequestID = isset($_POST['checkout_request_id']) ? $_POST['checkout_request_id'] : '';
    
    log_check("Checking payment status for checkout request ID: $checkoutRequestID");
    
    if (empty($checkoutRequestID)) {
        log_check("Error: No checkout request ID provided");
        echo json_encode(['success' => false, 'message' => 'No checkout request ID provided']);
        exit;
    }
    
    // First, check if payment is already marked as completed in the database
    $stmt = $conn->prepare("SELECT status, package_id, mpesa_receipt FROM mpesa_transactions WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        log_check("Error: No transaction found with checkout request ID: $checkoutRequestID");
        echo json_encode(['success' => false, 'message' => 'No transaction found with the provided checkout request ID']);
        exit;
    }
    
    $transaction = $result->fetch_assoc();
    log_check("Transaction found. Status: " . $transaction['status']);
    
    // If payment is already completed, return success
    if ($transaction['status'] === 'completed') {
        log_check("Payment already completed!");
        
        // Check if a voucher has been generated for this transaction
        $voucher_code = null;
        
        // Check if vouchers table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
        if ($tableCheck->num_rows > 0) {
            $voucherStmt = $conn->prepare("SELECT code FROM vouchers WHERE package_id = ? AND created_at > (NOW() - INTERVAL 1 DAY) ORDER BY created_at DESC LIMIT 1");
            $voucherStmt->bind_param("i", $transaction['package_id']);
            $voucherStmt->execute();
            $voucherResult = $voucherStmt->get_result();
            
            if ($voucherResult->num_rows > 0) {
                $voucherRow = $voucherResult->fetch_assoc();
                $voucher_code = $voucherRow['code'];
                log_check("Found voucher code: $voucher_code");
            } else {
                log_check("No voucher found for this transaction, generating one now");
                $voucher_code = generateVoucherCode();
                
                // Save the voucher
                try {
                    $stmt = $conn->prepare("INSERT INTO vouchers (code, package_id, status, created_at) VALUES (?, ?, 'active', NOW())");
                    $stmt->bind_param("si", $voucher_code, $transaction['package_id']);
                    $stmt->execute();
                    log_check("Generated and saved new voucher: $voucher_code");
                } catch (Exception $e) {
                    log_check("Error saving voucher: " . $e->getMessage());
                }
            }
        } else {
            log_check("Vouchers table does not exist, using mock voucher code");
            $voucher_code = "WIFI" . rand(1000, 9999);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment completed successfully',
            'receipt' => $transaction['mpesa_receipt'],
            'voucher_code' => $voucher_code
        ]);
        exit;
    }
    
    // If payment is not completed in our database, check with M-Pesa API
    log_check("Payment not marked as completed in database, checking with M-Pesa API");
    
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
        log_check("Error generating access token: " . $result);
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
    log_check("M-Pesa API query response: " . $response);
    
    if (curl_errno($curl)) {
        log_check("cURL Error: " . curl_error($curl));
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
            log_check("M-Pesa API confirms payment was successful!");
            
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
            
            log_check("Updated transaction status to completed in database");
            
            // Generate voucher code
            $voucher_code = generateVoucherCode();
            
            // Check if vouchers table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
            if ($tableCheck->num_rows > 0) {
                // Save the voucher
                try {
                    $stmt = $conn->prepare("INSERT INTO vouchers (code, package_id, status, created_at) VALUES (?, ?, 'active', NOW())");
                    $stmt->bind_param("si", $voucher_code, $transaction['package_id']);
                    $stmt->execute();
                    log_check("Generated and saved new voucher: $voucher_code");
                } catch (Exception $e) {
                    log_check("Error saving voucher: " . $e->getMessage());
                }
            } else {
                log_check("Vouchers table does not exist, using mock voucher code only");
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Payment completed successfully',
                'receipt' => $mpesaReceiptNumber,
                'voucher_code' => $voucher_code
            ]);
            exit;
        } else {
            // Payment failed or is still pending
            log_check("M-Pesa API returned non-zero result code: " . $result->ResultCode);
            
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
                
                log_check("Updated transaction with result code and description");
            }
            
            echo json_encode([
                'success' => false, 
                'message' => $result->ResultDesc ?: 'Payment not yet confirmed. Please check your phone and complete the payment.'
            ]);
            exit;
        }
    } else {
        // Error in the API request
        log_check("Error in M-Pesa API request: " . (isset($result->errorMessage) ? $result->errorMessage : 'Unknown error'));
        echo json_encode(['success' => false, 'message' => 'Error checking payment status. Please try again later.']);
        exit;
    }
} else {
    // Not a POST request
    log_check("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
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