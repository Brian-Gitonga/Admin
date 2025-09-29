<?php
/**
 * Generate Voucher
 * 
 * This file generates a voucher for a completed transaction that doesn't have one
 */

// Start session
session_start();

// Include necessary files
require_once '../portal_connection.php';
// MikroTik integration removed - vouchers will be generated without router communication

// Set up logging
$log_file = '../logs/voucher_generation.log';
// Create logs directory if it doesn't exist
if (!file_exists('../logs')) {
    mkdir('../logs', 0777, true);
}

function log_voucher($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Check if transaction ID is provided
if (!isset($_GET['transaction_id']) || empty($_GET['transaction_id'])) {
    $_SESSION['payment_message'] = 'No transaction ID provided';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$transaction_id = (int)$_GET['transaction_id'];
log_voucher("Generating voucher for transaction ID: $transaction_id");

// Get transaction details
$stmt = $conn->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
if ($stmt === false) {
    log_voucher("SQL Error preparing statement: " . $conn->error);
    $_SESSION['payment_message'] = 'Database error: ' . $conn->error;
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    log_voucher("Transaction not found: $transaction_id");
    $_SESSION['payment_message'] = 'Transaction not found';
    $_SESSION['payment_status'] = 'error';
    header('Location: ../transations.php');
    exit;
}

$transaction = $result->fetch_assoc();

// Only completed transactions can generate vouchers
if ($transaction['status'] !== 'completed') {
    log_voucher("Transaction is not completed: $transaction_id, Status: {$transaction['status']}");
    $_SESSION['payment_message'] = 'Only completed transactions can generate vouchers';
    $_SESSION['payment_status'] = 'warning';
    header('Location: ../transaction_details.php?id=' . $transaction_id);
    exit;
}

// Check if voucher already exists for this transaction
$voucherCheck = $conn->prepare("
    SELECT v.code FROM vouchers v 
    WHERE v.package_id = ? AND v.customer_phone = ? AND v.created_at >= ? AND v.created_at <= DATE_ADD(?, INTERVAL 5 MINUTE)
    LIMIT 1
");

if ($voucherCheck === false) {
    // Check if vouchers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        log_voucher("SQL Error preparing voucher check: " . $conn->error);
        $_SESSION['payment_message'] = 'Database error: ' . $conn->error;
        $_SESSION['payment_status'] = 'error';
        header('Location: ../transaction_details.php?id=' . $transaction_id);
        exit;
    }
} else {
    $voucherCheck->bind_param('isss', 
        $transaction['package_id'], 
        $transaction['phone_number'], 
        $transaction['created_at'],
        $transaction['created_at']
    );
    $voucherCheck->execute();
    $voucherResult = $voucherCheck->get_result();

    if ($voucherResult->num_rows > 0) {
        $voucherData = $voucherResult->fetch_assoc();
        $existingCode = $voucherData['code'];
        
        log_voucher("Voucher already exists for transaction: $transaction_id, Code: $existingCode");
        $_SESSION['payment_message'] = "Voucher already exists: $existingCode";
        $_SESSION['payment_status'] = 'info';
        header('Location: ../transaction_details.php?id=' . $transaction_id);
        exit;
    }
}

// Generate a new voucher code
$voucher_code = generateVoucherCode();
log_voucher("Generated new voucher code: $voucher_code for transaction: $transaction_id");

// Check if vouchers table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    // Store the voucher in the database
    $voucherStmt = $conn->prepare("INSERT INTO vouchers 
        (code, package_id, reseller_id, customer_phone, status, created_at) 
        VALUES (?, ?, ?, ?, 'active', NOW())");
    $voucherStmt->bind_param("siis", 
        $voucher_code, 
        $transaction['package_id'], 
        $transaction['reseller_id'], 
        $transaction['phone_number']
    );
    
    if ($voucherStmt->execute()) {
        log_voucher("Saved voucher to database: $voucher_code");
    } else {
        log_voucher("Error saving voucher to database: " . $conn->error);
    }
}

// Router integration disabled - voucher generated without router communication
log_voucher("Voucher generated successfully (router integration disabled): $voucher_code");
$_SESSION['payment_message'] = 'Voucher generated successfully: ' . $voucher_code;
$_SESSION['payment_status'] = 'success';

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