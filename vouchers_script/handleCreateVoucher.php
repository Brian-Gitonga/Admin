<?php
/**
 * Helper functions for handling voucher creation
 */

/**
 * Handle creation of a single voucher with better error handling
 */
function handleCreateVoucherImproved($conn, $resellerId, $packageId) {
    // Special handling for demo packages
    $isDemoPackage = ($packageId == 998 || $packageId == 999);
    
    // Verify the package belongs to this reseller (skip check for demo packages)
    if (!$isDemoPackage) {
        $checkSql = "SELECT id FROM packages WHERE id = ? AND reseller_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        
        if (!$checkStmt) {
            return [
                'success' => false,
                'message' => "Database error: " . $conn->error
            ];
        }
        
        $checkStmt->bind_param("ii", $packageId, $resellerId);
        
        if (!$checkStmt->execute()) {
            return [
                'success' => false,
                'message' => "Query execution error: " . $checkStmt->error
            ];
        }
        
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => "Invalid package selected or package does not belong to you"
            ];
        }
    }
    
    // Generate the voucher
    $voucherCode = createVoucher($conn, $packageId, $resellerId);
    
    if (!$voucherCode) {
        return [
            'success' => false,
            'message' => "Failed to create voucher: " . $conn->error
        ];
    }
    
    // Add voucher to MikroTik router
    $customerPhone = "admin"; // Default customer phone for manually created vouchers
    
    if (function_exists('addVoucherToMikrotik')) {
        $mikrotikResult = addVoucherToMikrotik($voucherCode, $packageId, $resellerId, $customerPhone, $conn);
        
        if ($mikrotikResult === true) {
            error_log("Successfully added voucher to MikroTik: $voucherCode");
            return [
                'success' => true,
                'message' => "Voucher created successfully and added to router",
                'voucher_code' => $voucherCode
            ];
        } else {
            error_log("Failed to add voucher to MikroTik: $mikrotikResult");
            return [
                'success' => true,
                'message' => "Voucher created but could not be added to router: $mikrotikResult",
                'voucher_code' => $voucherCode
            ];
        }
    } else {
        // MikroTik integration not available
        return [
            'success' => true,
            'message' => "Voucher created successfully (MikroTik integration not available)",
            'voucher_code' => $voucherCode
        ];
    }
}

/**
 * Handle bulk creation of vouchers with better error handling
 */
function handleBulkCreateVouchersImproved($conn, $resellerId, $packageId, $count) {
    // Validate input parameters
    if (!is_numeric($packageId) || !is_numeric($count) || $count < 1) {
        return [
            'success' => false,
            'message' => "Invalid parameters: package ID or count is not valid"
        ];
    }
    
    // Cap the count at 100 for safety
    $count = min((int)$count, 100);
    
    // Special handling for demo packages
    $isDemoPackage = ($packageId == 998 || $packageId == 999);
    
    // Verify the package belongs to this reseller (skip for demo packages)
    if (!$isDemoPackage) {
        $checkSql = "SELECT id FROM packages WHERE id = ? AND reseller_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        
        if (!$checkStmt) {
            return [
                'success' => false,
                'message' => "Database error: " . $conn->error
            ];
        }
        
        $checkStmt->bind_param("ii", $packageId, $resellerId);
        
        if (!$checkStmt->execute()) {
            return [
                'success' => false,
                'message' => "Query execution error: " . $checkStmt->error
            ];
        }
        
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'success' => false,
                'message' => "Invalid package selected or package does not belong to you"
            ];
        }
    }
    
    // Generate the vouchers
    try {
        // Generate multiple vouchers
        $generatedCodes = [];
        $successCount = 0;
        
        // Start a transaction
        $conn->begin_transaction();
        
        for ($i = 0; $i < $count; $i++) {
            $voucherCode = createVoucher($conn, $packageId, $resellerId);
            
            if ($voucherCode) {
                $generatedCodes[] = $voucherCode;
                $successCount++;
            }
        }
        
        if ($successCount === 0) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => "Failed to create any vouchers"
            ];
        }
        
        // Commit the transaction
        $conn->commit();
        
        // Handle MikroTik integration
        $mikrotikSuccessCount = 0;
        $mikrotikFailCount = 0;
        
        if (function_exists('addVoucherToMikrotik')) {
            $customerPhone = "bulk-admin"; // Default for bulk vouchers
            
            foreach ($generatedCodes as $voucherCode) {
                $mikrotikResult = addVoucherToMikrotik($voucherCode, $packageId, $resellerId, $customerPhone, $conn);
                
                if ($mikrotikResult === true) {
                    $mikrotikSuccessCount++;
                } else {
                    $mikrotikFailCount++;
                }
            }
            
            if ($mikrotikFailCount > 0) {
                return [
                    'success' => true,
                    'message' => "$successCount vouchers created successfully. ($mikrotikSuccessCount added to router, $mikrotikFailCount failed)",
                    'count' => $successCount,
                    'voucher_codes' => $generatedCodes
                ];
            } else {
                return [
                    'success' => true,
                    'message' => "$successCount vouchers created and added to router successfully",
                    'count' => $successCount,
                    'voucher_codes' => $generatedCodes
                ];
            }
        } else {
            // MikroTik integration not available
            return [
                'success' => true,
                'message' => "$successCount vouchers created successfully (MikroTik integration not available)",
                'count' => $successCount,
                'voucher_codes' => $generatedCodes
            ];
        }
    } catch (Exception $e) {
        // Rollback on error
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        
        return [
            'success' => false,
            'message' => "Error creating vouchers: " . $e->getMessage()
        ];
    }
}
?> 