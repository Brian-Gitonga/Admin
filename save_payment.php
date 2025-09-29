<?php
/**
 * Save Payment Script
 * This file processes manually recorded M-Pesa payments and saves them to the database
 */

// Start session
session_start();

// Include database connection
require_once 'portal_connection.php';

// Set up logging
$log_file = 'manual_payments.log';
function log_payment($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Default response
$response = [
    'success' => false,
    'message' => 'An error occurred while processing your request.'
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    log_payment("Manual payment form submitted");
    
    // Get form data
    $phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';
    $mpesa_receipt = isset($_POST['mpesa_receipt']) ? trim($_POST['mpesa_receipt']) : '';
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $payment_date = isset($_POST['payment_date']) ? trim($_POST['payment_date']) : date('Y-m-d H:i:s');
    $reseller_id = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 1;
    
    // Validate inputs
    $errors = [];
    
    if (empty($phone_number)) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^0[0-9]{9}$|^254[0-9]{9}$/', $phone_number)) {
        $errors[] = 'Invalid phone number format. Use 07XXXXXXXX or 254XXXXXXXXX';
    }
    
    if (empty($mpesa_receipt)) {
        $errors[] = 'M-Pesa receipt number is required';
    }
    
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero';
    }
    
    if ($package_id <= 0) {
        $errors[] = 'Package selection is required';
    }
    
    // Check if receipt number already exists
    $checkStmt = $conn->prepare("SELECT id FROM mpesa_transactions WHERE mpesa_receipt = ?");
    $checkStmt->bind_param("s", $mpesa_receipt);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $errors[] = 'Transaction with this receipt number already exists';
    }
    
    // If no errors, proceed with saving
    if (empty($errors)) {
        try {
            // Get package name
            $packageStmt = $conn->prepare("SELECT name FROM packages WHERE id = ?");
            $packageStmt->bind_param("i", $package_id);
            $packageStmt->execute();
            $packageResult = $packageStmt->get_result();
            
            if ($packageResult->num_rows > 0) {
                $package = $packageResult->fetch_assoc();
                $package_name = $package['name'];
            } else {
                $package_name = "Package #$package_id";
            }
            
            // Generate merchant and checkout request IDs for consistency with API transactions
            $merchant_request_id = 'MANUAL' . date('YmdHis') . rand(1000, 9999);
            $checkout_request_id = 'MANUAL' . date('YmdHis') . rand(1000, 9999);
            
            // Convert payment date to MySQL format
            $payment_timestamp = strtotime($payment_date);
            $formatted_date = date('Y-m-d H:i:s', $payment_timestamp);
            
            // Insert transaction record
            $stmt = $conn->prepare("INSERT INTO mpesa_transactions 
                (checkout_request_id, merchant_request_id, amount, phone_number, 
                package_id, package_name, reseller_id, status, mpesa_receipt, 
                transaction_date, result_code, result_description, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, 0, 'Manually recorded payment', ?, ?)");
                
            $stmt->bind_param(
                "ssdsisssss", 
                $checkout_request_id, 
                $merchant_request_id, 
                $amount, 
                $phone_number, 
                $package_id, 
                $package_name, 
                $reseller_id, 
                $mpesa_receipt, 
                $formatted_date, 
                $formatted_date, 
                $formatted_date
            );
            
            $result = $stmt->execute();
            
            if ($result) {
                $transaction_id = $conn->insert_id;
                log_payment("Payment recorded successfully: ID=$transaction_id, Receipt=$mpesa_receipt, Amount=$amount, Phone=$phone_number");
                
                // Create a voucher if needed
                require_once 'mikrotik_helper.php';
                
                // Generate voucher code
                $voucher_code = generateVoucherCode();
                
                // Check if vouchers table exists
                $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
                if ($tableCheck->num_rows > 0) {
                    // Store the voucher
                    $voucherStmt = $conn->prepare("INSERT INTO vouchers 
                        (code, package_id, reseller_id, customer_phone, status, created_at) 
                        VALUES (?, ?, ?, ?, 'active', NOW())");
                    $voucherStmt->bind_param("siis", $voucher_code, $package_id, $reseller_id, $phone_number);
                    $voucherStmt->execute();
                    
                    if ($voucherStmt->affected_rows > 0) {
                        log_payment("Voucher created: $voucher_code for transaction $transaction_id");
                        
                        // Router integration disabled - voucher generated without router communication
                        log_payment("Voucher generated successfully (router integration disabled): $voucher_code");
                    }
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Payment recorded successfully!',
                    'transaction_id' => $transaction_id,
                    'voucher_code' => $voucher_code ?? null
                ];
            } else {
                log_payment("Error recording payment: " . $conn->error);
                $response['message'] = 'Error recording payment: ' . $conn->error;
            }
        } catch (Exception $e) {
            log_payment("Exception when recording payment: " . $e->getMessage());
            $response['message'] = 'Exception: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Validation errors: ' . implode(', ', $errors);
        log_payment("Validation errors: " . implode(', ', $errors));
    }
}

// Redirect back to transactions page with a message
if ($response['success']) {
    $_SESSION['payment_message'] = $response['message'];
    $_SESSION['payment_status'] = 'success';
} else {
    $_SESSION['payment_message'] = $response['message'];
    $_SESSION['payment_status'] = 'error';
}

header('Location: transations.php');
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