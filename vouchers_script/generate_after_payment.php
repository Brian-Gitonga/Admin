<?php
// This script is responsible for generating a voucher after a successful payment
require_once 'db_connection.php';

/**
 * Generate a voucher after a successful payment
 * 
 * @param int $packageId - The ID of the package purchased
 * @param int $resellerId - The ID of the reseller
 * @param string $customerPhone - The customer's phone number
 * @param string $transactionId - The M-Pesa transaction ID
 * @return array - Result with success status and voucher code
 */
function generateVoucherAfterPayment($packageId, $resellerId, $customerPhone, $transactionId = null) {
    global $conn;
    
    // Log the voucher generation request
    error_log("Generating voucher for package ID: $packageId, reseller ID: $resellerId, customer: $customerPhone");
    
    // Validate inputs
    if (!$packageId || !$resellerId) {
        error_log("Missing required parameters for voucher generation");
        return [
            'success' => false,
            'message' => 'Missing required parameters'
        ];
    }
    
    // Generate the voucher
    $voucherCode = createVoucher($conn, $packageId, $resellerId, $customerPhone);
    
    if ($voucherCode) {
        // Log the success
        error_log("Successfully generated voucher: $voucherCode for phone: $customerPhone");
        
        // Log the transaction if provided
        if ($transactionId) {
            // You could log this in a separate transactions table if needed
            error_log("Payment transaction ID: $transactionId for voucher: $voucherCode");
        }
        
        return [
            'success' => true,
            'voucher_code' => $voucherCode,
            'message' => 'Voucher generated successfully'
        ];
    } else {
        error_log("Failed to generate voucher for customer: $customerPhone");
        return [
            'success' => false,
            'message' => 'Failed to generate voucher'
        ];
    }
}

/**
 * Generate multiple vouchers at once
 * 
 * @param int $packageId - The ID of the package
 * @param int $resellerId - The ID of the reseller
 * @param int $count - Number of vouchers to generate
 * @return array - Result with success status and generated voucher codes
 */
function generateMultipleVouchers($packageId, $resellerId, $count = 1) {
    global $conn;
    
    // Validate inputs
    if (!$packageId || !$resellerId || $count < 1) {
        return [
            'success' => false,
            'message' => 'Invalid parameters for bulk voucher generation'
        ];
    }
    
    // Cap the maximum number of vouchers that can be generated at once
    $count = min($count, 100);
    
    $generatedCodes = [];
    $successCount = 0;
    
    // Start a transaction for bulk insertion
    $conn->begin_transaction();
    
    try {
        // Generate the specified number of vouchers
        for ($i = 0; $i < $count; $i++) {
            $voucherCode = createVoucher($conn, $packageId, $resellerId);
            
            if ($voucherCode) {
                $generatedCodes[] = $voucherCode;
                $successCount++;
            }
        }
        
        // Commit the transaction if all operations were successful
        $conn->commit();
        
        return [
            'success' => true,
            'count' => $successCount,
            'voucher_codes' => $generatedCodes,
            'message' => "$successCount vouchers generated successfully"
        ];
    } catch (Exception $e) {
        // Rollback the transaction if any error occurred
        $conn->rollback();
        error_log("Error generating multiple vouchers: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'An error occurred while generating vouchers'
        ];
    }
}
?> 