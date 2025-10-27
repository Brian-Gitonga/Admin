<?php
/**
 * Fetch and Update Voucher
 * This script handles voucher retrieval based on phone number and M-Pesa transaction
 * 
 * Flow:
 * 1. Get phone number from request
 * 2. Find latest M-Pesa transaction for that phone
 * 3. Check if payment was successful (result_code = 0)
 * 4. If voucher_code exists in transaction, return it
 * 5. If voucher_code is NULL, fetch from vouchers table and update transaction
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to user
ini_set('log_errors', 1);
error_log("=== Fetch Update Voucher Script Started ===");

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'connection_dp.php';

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}

// Get phone number from POST request
$phone_number = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';

// Validate phone number
if (empty($phone_number)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your phone number.'
    ]);
    exit;
}

// Normalize phone number (remove spaces, dashes, etc.)
$phone_number = preg_replace('/[^0-9]/', '', $phone_number);

// Ensure phone number starts with 254 (Kenya country code)
if (substr($phone_number, 0, 1) === '0') {
    $phone_number = '254' . substr($phone_number, 1);
} elseif (substr($phone_number, 0, 3) !== '254') {
    $phone_number = '254' . $phone_number;
}

error_log("Processing voucher request for phone: $phone_number");

try {
    // Step 1: Find the latest M-Pesa transaction for this phone number
    $query = "SELECT * FROM mpesa_transactions 
              WHERE phone_number = ? 
              ORDER BY updated_at DESC, created_at DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if transaction exists
    if ($result->num_rows === 0) {
        error_log("No transaction found for phone: $phone_number");
        echo json_encode([
            'success' => false,
            'message' => 'No payment found for this phone number. Please make a payment first.'
        ]);
        exit;
    }
    
    $transaction = $result->fetch_assoc();
    error_log("Found transaction ID: " . $transaction['id'] . " with result_code: " . $transaction['result_code']);
    
    // Step 2: Check if payment was successful (result_code = 0 means success in M-Pesa)
    if ($transaction['result_code'] === null) {
        error_log("Payment pending for transaction ID: " . $transaction['id']);
        echo json_encode([
            'success' => false,
            'message' => 'Your payment is still being processed. Please wait a moment and try again.'
        ]);
        exit;
    }
    
    if ($transaction['result_code'] != 0) {
        error_log("Payment failed for transaction ID: " . $transaction['id'] . " with result_code: " . $transaction['result_code']);
        echo json_encode([
            'success' => false,
            'message' => 'Your payment was not successful. Result: ' . $transaction['result_description']
        ]);
        exit;
    }
    
    // Step 3: Check if voucher already exists in the transaction
    if (!empty($transaction['voucher_code'])) {
        error_log("Voucher already exists: " . $transaction['voucher_code']);
        
        // Return existing voucher
        echo json_encode([
            'success' => true,
            'voucher_code' => $transaction['voucher_code'],
            'package_name' => $transaction['package_name'],
            'amount' => $transaction['amount'],
            'transaction_date' => $transaction['transaction_date'],
            'message' => 'Voucher retrieved successfully!'
        ]);
        exit;
    }
    
    // Step 4: Voucher doesn't exist, fetch from vouchers table
    error_log("Fetching new voucher for package_id: " . $transaction['package_id'] . ", reseller_id: " . $transaction['reseller_id']);
    
    // Find an active (unused) voucher matching the package and reseller
    $voucherQuery = "SELECT * FROM vouchers 
                     WHERE package_id = ? 
                     AND reseller_id = ? 
                     AND status = 'active' 
                     ORDER BY created_at ASC 
                     LIMIT 1";
    
    $voucherStmt = $conn->prepare($voucherQuery);
    if (!$voucherStmt) {
        throw new Exception("Failed to prepare voucher query: " . $conn->error);
    }
    
    $voucherStmt->bind_param("ii", $transaction['package_id'], $transaction['reseller_id']);
    $voucherStmt->execute();
    $voucherResult = $voucherStmt->get_result();
    
    // Check if voucher is available
    if ($voucherResult->num_rows === 0) {
        error_log("No active vouchers available for package_id: " . $transaction['package_id']);
        echo json_encode([
            'success' => false,
            'message' => 'No vouchers available for your package. Please contact support.'
        ]);
        exit;
    }
    
    $voucher = $voucherResult->fetch_assoc();
    error_log("Found voucher ID: " . $voucher['id'] . ", code: " . $voucher['code']);
    
    // Step 5: Update the voucher status to 'used'
    $updateVoucherQuery = "UPDATE vouchers 
                           SET status = 'used', 
                               customer_phone = ?, 
                               used_at = NOW() 
                           WHERE id = ?";
    
    $updateVoucherStmt = $conn->prepare($updateVoucherQuery);
    if (!$updateVoucherStmt) {
        throw new Exception("Failed to prepare voucher update: " . $conn->error);
    }
    
    $updateVoucherStmt->bind_param("si", $phone_number, $voucher['id']);
    if (!$updateVoucherStmt->execute()) {
        throw new Exception("Failed to update voucher: " . $updateVoucherStmt->error);
    }
    
    error_log("Voucher marked as used");
    
    // Step 6: Update the transaction with voucher details
    $updateTransactionQuery = "UPDATE mpesa_transactions 
                               SET voucher_id = ?, 
                                   voucher_code = ?, 
                                   updated_at = NOW() 
                               WHERE id = ?";
    
    $updateTransactionStmt = $conn->prepare($updateTransactionQuery);
    if (!$updateTransactionStmt) {
        throw new Exception("Failed to prepare transaction update: " . $conn->error);
    }
    
    $updateTransactionStmt->bind_param("isi", $voucher['id'], $voucher['code'], $transaction['id']);
    if (!$updateTransactionStmt->execute()) {
        throw new Exception("Failed to update transaction: " . $updateTransactionStmt->error);
    }
    
    error_log("Transaction updated with voucher code");
    
    // Step 7: Return success with voucher details
    echo json_encode([
        'success' => true,
        'voucher_code' => $voucher['code'],
        'package_name' => $transaction['package_name'],
        'amount' => $transaction['amount'],
        'transaction_date' => $transaction['transaction_date'],
        'message' => 'Voucher generated successfully!'
    ]);
    
    error_log("=== Voucher fetch completed successfully ===");
    
} catch (Exception $e) {
    error_log("Error in fetch_update_voucher.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please try again.'
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>

