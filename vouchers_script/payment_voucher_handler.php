<?php
// This script handles voucher generation after payment validation

// Database connection will be provided by the calling script
// No need to include connection here as it's already available

/**
 * Fetch an existing active voucher after successful payment
 *
 * @param string $checkoutRequestId - The M-Pesa checkout request ID
 * @param int $packageId - The ID of the package purchased
 * @param int $resellerId - The ID of the reseller
 * @param string $customerPhone - The customer's phone number
 * @param string $mpesaReceipt - The M-Pesa receipt number
 * @return array - Result with success status and voucher code
 */
function createVoucherAfterPayment($checkoutRequestId, $packageId, $resellerId, $customerPhone, $mpesaReceipt = null) {
    global $conn;

    // Log function entry
    error_log("=== VOUCHER HANDLER STARTED ===");
    error_log("Fetching voucher for checkout request ID: $checkoutRequestId, package: $packageId, reseller: $resellerId");

    // Check if database connection exists
    if (!$conn) {
        error_log("ERROR: Database connection not available in voucher handler");
        return [
            'success' => false,
            'message' => 'Database connection error'
        ];
    }

    // Check if vouchers table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        error_log("ERROR: Vouchers table does not exist");
        return [
            'success' => false,
            'message' => 'Vouchers table not found. Please contact support.'
        ];
    }

    // First, check if voucher_code column exists in mpesa_transactions table
    $columnCheck = $conn->query("SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_code'");
    $hasVoucherCodeColumn = $columnCheck && $columnCheck->num_rows > 0;

    if ($hasVoucherCodeColumn) {
        // Check if we already assigned a voucher to this specific transaction
        $transactionCheckSql = "SELECT voucher_code FROM mpesa_transactions
                               WHERE checkout_request_id = ? AND voucher_code IS NOT NULL";
        $transactionStmt = $conn->prepare($transactionCheckSql);

        if (!$transactionStmt) {
            error_log("ERROR: Failed to prepare transaction check query: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Database query preparation failed'
            ];
        }

        $transactionStmt->bind_param("s", $checkoutRequestId);
        $transactionStmt->execute();
        $transactionResult = $transactionStmt->get_result();

        if ($transactionResult->num_rows > 0) {
            $transactionRow = $transactionResult->fetch_assoc();
            error_log("Found existing voucher for this transaction: " . $transactionRow['voucher_code']);

            return [
                'success' => true,
                'voucher_code' => $transactionRow['voucher_code'],
                'message' => 'Voucher already assigned to this transaction'
            ];
        }
    } else {
        error_log("INFO: voucher_code column does not exist in mpesa_transactions table");
    }

    // Fetch an available voucher that matches the package and is still active
    $fetchSql = "SELECT id, code, username, password FROM vouchers
                WHERE package_id = ? AND reseller_id = ? AND status = 'active'
                ORDER BY created_at ASC LIMIT 1";

    error_log("Fetching voucher with SQL: $fetchSql");
    error_log("Parameters: packageId=$packageId, resellerId=$resellerId");

    $fetchStmt = $conn->prepare($fetchSql);
    if (!$fetchStmt) {
        error_log("ERROR: Failed to prepare voucher fetch query: " . $conn->error);
        return [
            'success' => false,
            'message' => 'Database query preparation failed for voucher fetch'
        ];
    }

    $fetchStmt->bind_param("ii", $packageId, $resellerId);
    if (!$fetchStmt->execute()) {
        error_log("ERROR: Failed to execute voucher fetch query: " . $fetchStmt->error);
        return [
            'success' => false,
            'message' => 'Database query execution failed'
        ];
    }

    $result = $fetchStmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $voucherId = $row['id'];
        $voucherCode = $row['code'];

        error_log("Found available voucher: ID=$voucherId, Code=$voucherCode");

        // Mark this voucher as used and assign it to the customer
        $updateSql = "UPDATE vouchers SET status = 'used', customer_phone = ?, used_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            error_log("ERROR: Failed to prepare voucher update query: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Database update preparation failed'
            ];
        }

        $updateStmt->bind_param("si", $customerPhone, $voucherId);

        if ($updateStmt->execute()) {
            error_log("Successfully assigned voucher $voucherCode to customer $customerPhone");

            // Update the M-Pesa transaction with voucher details
            $updateTxnSql = "UPDATE mpesa_transactions SET voucher_id = ?, voucher_code = ? WHERE checkout_request_id = ?";
            $updateTxnStmt = $conn->prepare($updateTxnSql);
            $updateTxnStmt->bind_param("iss", $voucherId, $voucherCode, $checkoutRequestId);
            $updateTxnStmt->execute();

            return [
                'success' => true,
                'voucher_code' => $voucherCode,
                'voucher_username' => $row['username'],
                'voucher_password' => $row['password'],
                'message' => 'Active voucher fetched and assigned successfully'
            ];
        } else {
            error_log("Failed to update voucher status: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Failed to assign voucher to customer'
            ];
        }
    }

    // No available vouchers found
    error_log("No available vouchers found for package $packageId, reseller $resellerId");

    return [
        'success' => false,
        'message' => 'No available vouchers found for this package. Please contact support.'
    ];
}

/**
 * Mark a voucher as paid with M-Pesa
 *
 * @param string $voucherCode - The voucher code
 * @param string $mpesaReceipt - The M-Pesa receipt number
 * @return bool - Whether the update was successful
 */
function markVoucherAsPaid($voucherCode, $mpesaReceipt) {
    global $conn;
    
    $sql = "UPDATE vouchers SET mpesa_receipt = ? WHERE code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $mpesaReceipt, $voucherCode);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        error_log("Marked voucher $voucherCode as paid with receipt $mpesaReceipt");
        return true;
    } else {
        error_log("Failed to mark voucher $voucherCode as paid: " . $conn->error);
        return false;
    }
}
?> 