<?php
/**
 * Check Transaction Status
 * 
 * This file manually checks the status of a pending M-Pesa transaction
 */

// Start session
session_start();

// Include necessary files
require_once '../portal_connection.php';

// Logging function
function log_check($message) {
    $log_file = '../logs/transaction_checks.log';
    // Create logs directory if it doesn't exist
    if (!file_exists('../logs')) {
        mkdir('../logs', 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['payment_message'] = 'No transaction ID provided';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$transaction_id = (int)$_GET['id'];
log_check("Checking transaction ID: $transaction_id");

// Get transaction details
$stmt = $conn->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
if ($stmt === false) {
    log_check("SQL Error preparing statement: " . $conn->error);
    $_SESSION['payment_message'] = 'Database error: ' . $conn->error;
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    log_check("Transaction not found: $transaction_id");
    $_SESSION['payment_message'] = 'Transaction not found';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$transaction = $result->fetch_assoc();
$checkoutRequestID = $transaction['checkout_request_id'];

// Only check pending transactions
if ($transaction['status'] !== 'pending') {
    log_check("Transaction is not pending: $transaction_id, Status: {$transaction['status']}");
    $_SESSION['payment_message'] = 'This transaction is already ' . $transaction['status'];
    $_SESSION['payment_status'] = 'info';
    header('Location: ../transaction_details.php?id=' . $transaction_id);
    exit;
}

// Check if this is a manually recorded transaction (not through M-Pesa API)
if (strpos($checkoutRequestID, 'MANUAL') === 0) {
    log_check("This is a manual transaction, updating to completed: $transaction_id");
    
    // Update transaction to completed
    $updateStmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'completed', result_code = 0, result_description = 'Manually marked as completed', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('i', $transaction_id);
    $updateStmt->execute();
    
    // Get updated transaction
    $stmt = $conn->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    
    // Generate voucher
    require_once '../mikrotik_helper.php';
    $voucher_code = generateVoucherCode();
    
    // Store the voucher in the database if vouchers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
    if ($tableCheck->num_rows > 0) {
        $voucherStmt = $conn->prepare("INSERT INTO vouchers 
            (code, package_id, reseller_id, customer_phone, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())");
        $voucherStmt->bind_param("siis", 
            $voucher_code, 
            $transaction['package_id'], 
            $transaction['reseller_id'], 
            $transaction['phone_number']
        );
        $voucherStmt->execute();
        
        log_check("Created voucher for manual transaction: $voucher_code");
    }
    
    // Router integration disabled - voucher generated without router communication
    log_check("Voucher generated successfully (router integration disabled): $voucher_code");
    
    $_SESSION['payment_message'] = 'Transaction marked as completed and voucher generated';
    $_SESSION['payment_status'] = 'success';
    header('Location: ../transaction_details.php?id=' . $transaction_id);
    exit;
}

// Include M-Pesa settings operations
require_once '../mpesa_settings_operations.php';

// Get M-Pesa credentials for this reseller
$resellerId = $transaction['reseller_id'];
$mpesaCredentials = getMpesaCredentials($conn, $resellerId);

$consumerKey = $mpesaCredentials['consumer_key'];
$consumerSecret = $mpesaCredentials['consumer_secret'];
$businessShortCode = $mpesaCredentials['business_shortcode'];
$passkey = $mpesaCredentials['passkey'];

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
    log_check("Error generating access token: $result");
    $_SESSION['payment_message'] = 'Error connecting to M-Pesa API';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transaction_details.php?id=' . $transaction_id);
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
log_check("M-Pesa API query response: $response");

if (curl_errno($curl)) {
    log_check("cURL Error: " . curl_error($curl));
    $_SESSION['payment_message'] = 'Error checking payment status';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transaction_details.php?id=' . $transaction_id);
    exit;
}

curl_close($curl);

$result = json_decode($response);

// Prepare default response
$_SESSION['payment_status'] = 'info';
$_SESSION['payment_message'] = 'Transaction status hasn\'t changed';

// Check if the request was successful
if (isset($result->ResponseCode) && $result->ResponseCode == "0") {
    // Check the result code
    if ($result->ResultCode == "0") {
        // Payment was successful, update the database
        log_check("M-Pesa API confirms payment was successful for transaction: $transaction_id");
        
        // Extract payment details
        $mpesaReceiptNumber = isset($result->Item) && isset($result->Item->Value) ? $result->Item->Value : null;
        
        if (!$mpesaReceiptNumber) {
            // Generate a mock receipt number if none is provided
            $mpesaReceiptNumber = "API" . rand(10000000, 99999999);
        }
        
        // Update transaction
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
            status = 'completed', 
            mpesa_receipt = ?, 
            result_code = 0,
            result_description = 'The service request is processed successfully.',
            updated_at = NOW() 
            WHERE id = ?");
            
        $stmt->bind_param("si", 
            $mpesaReceiptNumber, 
            $transaction_id
        );
        $stmt->execute();
        
        log_check("Updated transaction status to completed: $transaction_id");
        
        // Generate voucher code
        require_once '../mikrotik_helper.php';
        $voucher_code = generateVoucherCode();
        
        // Check if vouchers table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
        if ($tableCheck->num_rows > 0) {
            $voucherStmt = $conn->prepare("INSERT INTO vouchers 
                (code, package_id, reseller_id, customer_phone, status, created_at) 
                VALUES (?, ?, ?, ?, 'active', NOW())");
            $voucherStmt->bind_param("siis", 
                $voucher_code, 
                $transaction['package_id'], 
                $transaction['reseller_id'], 
                $transaction['phone_number']
            );
            $voucherStmt->execute();
            
            log_check("Voucher created: $voucher_code");
        }
        
        // Add voucher to MikroTik
        $mikrotikResult = addVoucherToMikrotik(
            $voucher_code, 
            $transaction['package_id'], 
            $transaction['reseller_id'], 
            $transaction['phone_number'], 
            $conn
        );
        
        if ($mikrotikResult === true) {
            log_check("Voucher added to MikroTik: $voucher_code");
        } else {
            log_check("Failed to add voucher to MikroTik: $mikrotikResult");
        }
        
        $_SESSION['payment_message'] = 'Payment confirmed! Voucher generated successfully.';
        $_SESSION['payment_status'] = 'success';
    } else {
        // Payment failed or is still pending
        log_check("M-Pesa API returned non-zero result code: " . $result->ResultCode);
        
        // Update the transaction status
        $updateStatus = $result->ResultCode == "1032" ? 'pending' : 'failed';
        $updateDesc = isset($result->ResultDesc) ? $result->ResultDesc : 'Payment verification failed';
        
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
            status = ?, 
            result_code = ?,
            result_description = ?,
            updated_at = NOW() 
            WHERE id = ?");
            
        $stmt->bind_param("sisi", 
            $updateStatus,
            $result->ResultCode,
            $updateDesc,
            $transaction_id
        );
        $stmt->execute();
        
        log_check("Updated transaction with status $updateStatus: $transaction_id");
        
        $_SESSION['payment_message'] = $updateDesc;
        $_SESSION['payment_status'] = 'warning';
    }
} else {
    // Error in the API request
    $errorMessage = isset($result->errorMessage) ? $result->errorMessage : 'Unknown error';
    log_check("Error in M-Pesa API request: $errorMessage");
    
    $_SESSION['payment_message'] = 'Error checking payment status: ' . $errorMessage;
    $_SESSION['payment_status'] = 'error';
}

// Redirect back to transaction details
header('Location: ../transaction_details.php?id=' . $transaction_id);
exit;

/**
 * Generate a random voucher code
 * 
 * @param int $length The length of the voucher code
 * @return string Random voucher code
 */
function generateVoucherCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
} 