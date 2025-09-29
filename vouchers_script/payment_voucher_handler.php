<?php
// This script handles voucher generation after payment validation

// Include the database connection
require_once 'db_connection.php';

/**
 * Create a voucher after successful payment
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
    
    // Check if a voucher already exists for this transaction
    $checkSql = "SELECT id, code FROM vouchers 
                WHERE package_id = ? AND reseller_id = ? AND customer_phone = ? 
                AND created_at > (NOW() - INTERVAL 1 DAY)
                ORDER BY created_at DESC LIMIT 1";
    
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("iis", $packageId, $resellerId, $customerPhone);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    // If voucher already exists, return it
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        error_log("Found existing voucher for checkout request ID: $checkoutRequestId - " . $row['code']);
        
        return [
            'success' => true,
            'voucher_code' => $row['code'],
            'message' => 'Existing voucher retrieved'
        ];
    }
    
    // Generate new voucher
    $voucherCode = createVoucher($conn, $packageId, $resellerId, $customerPhone);
    
    if ($voucherCode) {
        // Log the generated voucher
        error_log("Generated new voucher for checkout request ID: $checkoutRequestId - $voucherCode");
        
        // Record M-Pesa receipt if provided
        if ($mpesaReceipt) {
            $updateSql = "UPDATE vouchers SET mpesa_receipt = ? WHERE code = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ss", $mpesaReceipt, $voucherCode);
            $updateStmt->execute();
        }
        
        return [
            'success' => true,
            'voucher_code' => $voucherCode,
            'message' => 'New voucher generated successfully'
        ];
    } else {
        error_log("Failed to generate voucher for checkout request ID: $checkoutRequestId");
        
        return [
            'success' => false,
            'message' => 'Failed to generate voucher'
        ];
    }
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