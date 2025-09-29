<?php
/**
 * Fetch Voucher System
 * 
 * This script fetches an active voucher from the database based on package_id and router_id,
 * then updates its status to "used" to prevent duplicate use.
 * 
 * NOTE: This script is part of a transitional system:
 * - Current implementation: Fetch pre-generated vouchers from database
 * - Future implementation: Generate vouchers on-demand (see generate_voucher.php)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'vouchers_script/db_connection.php';

/**
 * Fetches an active voucher from the database based on package ID and router ID
 * 
 * @param object $conn Database connection
 * @param int $packageId The package ID
 * @param int $routerId The router ID
 * @param string $customerPhone The customer's phone number
 * @return array|null Voucher data or null if no voucher found
 */
function fetchVoucher($conn, $packageId, $routerId, $customerPhone) {
    // Log the request
    error_log("Fetching voucher for package ID: $packageId, router ID: $routerId, phone: $customerPhone");
    
    // First check if the router has any active vouchers for this package
    $query = "SELECT v.* FROM vouchers v 
              WHERE v.package_id = ? 
              AND v.router_id = ? 
              AND v.status = 'active' 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    
    // Check if prepare was successful
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("ii", $packageId, $routerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If voucher found, update its status and return it
    if ($result->num_rows > 0) {
        $voucher = $result->fetch_assoc();
        
        // Update the voucher status to 'used' and set customer phone
        $updateQuery = "UPDATE vouchers 
                        SET status = 'used', 
                            customer_phone = ?, 
                            used_at = NOW() 
                        WHERE id = ?";
        
        $updateStmt = $conn->prepare($updateQuery);
        
        if (!$updateStmt) {
            error_log("Error preparing update statement: " . $conn->error);
            return $voucher; // Return voucher anyway, but log the error
        }
        
        $updateStmt->bind_param("si", $customerPhone, $voucher['id']);
        $updateStmt->execute();
        
        // Log success
        error_log("Voucher assigned: ID {$voucher['id']}, Code: {$voucher['code']} to phone: $customerPhone");
        
        return $voucher;
    }
    
    // No voucher found for this router, try to find any voucher for this package (router-agnostic)
    error_log("No voucher found for specific router, trying any voucher for package");
    
    $fallbackQuery = "SELECT v.* FROM vouchers v 
                      WHERE v.package_id = ? 
                      AND (v.router_id IS NULL OR v.router_id = 0) 
                      AND v.status = 'active' 
                      LIMIT 1";
    
    $fallbackStmt = $conn->prepare($fallbackQuery);
    
    if (!$fallbackStmt) {
        error_log("Error preparing fallback statement: " . $conn->error);
        return null;
    }
    
    $fallbackStmt->bind_param("i", $packageId);
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    
    if ($fallbackResult->num_rows > 0) {
        $voucher = $fallbackResult->fetch_assoc();
        
        // Update voucher with router ID, status, and customer phone
        $updateFallbackQuery = "UPDATE vouchers 
                               SET status = 'used', 
                                   customer_phone = ?, 
                                   router_id = ?,
                                   used_at = NOW() 
                               WHERE id = ?";
        
        $updateFallbackStmt = $conn->prepare($updateFallbackQuery);
        
        if (!$updateFallbackStmt) {
            error_log("Error preparing fallback update statement: " . $conn->error);
            return $voucher; // Return voucher anyway, but log the error
        }
        
        $updateFallbackStmt->bind_param("sii", $customerPhone, $routerId, $voucher['id']);
        $updateFallbackStmt->execute();
        
        // Log success
        error_log("Fallback voucher assigned: ID {$voucher['id']}, Code: {$voucher['code']} to phone: $customerPhone");
        
        return $voucher;
    }
    
    // No voucher found at all
    error_log("No active vouchers found for package ID: $packageId");
    return null;
}

/**
 * Endpoint to fetch a voucher via AJAX
 * 
 * Expected POST parameters:
 * - package_id: The package ID
 * - router_id: The router ID
 * - phone_number: The customer's phone number
 * - transaction_id: Optional transaction ID for reference
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get parameters
    $packageId = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $routerId = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
    $phoneNumber = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
    $transactionId = isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '';
    
    // Validate input
    if (!$packageId || !$routerId || empty($phoneNumber)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters.'
        ]);
        exit;
    }
    
    // Fetch voucher
    $voucher = fetchVoucher($conn, $packageId, $routerId, $phoneNumber);
    
    if ($voucher) {
        // Log transaction if transaction_id provided
        if (!empty($transactionId)) {
            $logQuery = "UPDATE mpesa_transactions 
                         SET voucher_id = ?, 
                             voucher_code = ? 
                         WHERE checkout_request_id = ?";
            
            $logStmt = $conn->prepare($logQuery);
            
            if ($logStmt) {
                $logStmt->bind_param("iss", $voucher['id'], $voucher['code'], $transactionId);
                $logStmt->execute();
            }
        }
        
        // Send success response
        echo json_encode([
            'success' => true,
            'voucher' => [
                'code' => $voucher['code'],
                'username' => $voucher['username'] ?: $voucher['code'],
                'password' => $voucher['password'] ?: $voucher['code']
            ]
        ]);
    } else {
        // No voucher found
        echo json_encode([
            'success' => false,
            'message' => 'No available vouchers for this package. Please contact support.'
        ]);
    }
    exit;
}

/**
 * Function to be used directly from other PHP files
 * 
 * @param int $packageId The package ID
 * @param int $routerId The router ID
 * @param string $customerPhone The customer's phone number
 * @param string $transactionId Optional transaction ID
 * @return array|null Voucher data or null
 */
function getVoucherForPayment($packageId, $routerId, $customerPhone, $transactionId = '') {
    global $conn;
    
    // Fetch voucher
    $voucher = fetchVoucher($conn, $packageId, $routerId, $customerPhone);
    
    if ($voucher && !empty($transactionId)) {
        // Log transaction if transaction_id provided
        $logQuery = "UPDATE mpesa_transactions 
                     SET voucher_id = ?, 
                         voucher_code = ? 
                     WHERE checkout_request_id = ?";
        
        $logStmt = $conn->prepare($logQuery);
        
        if ($logStmt) {
            $logStmt->bind_param("iss", $voucher['id'], $voucher['code'], $transactionId);
            $logStmt->execute();
        }
        
        // Send SMS notification about the voucher
        error_log("Sending voucher SMS notification for transaction $transactionId to $customerPhone");
        
        // Get package name/details for SMS
        $packageName = "WiFi Package"; // Default name
        $packageDuration = "";
        
        // Try to get package details
        $packageQuery = $conn->prepare("SELECT name, description, duration FROM packages WHERE id = ?");
        if ($packageQuery) {
            $packageQuery->bind_param("i", $packageId);
            $packageQuery->execute();
            $packageResult = $packageQuery->get_result();
            
            if ($packageResult && $packageResult->num_rows > 0) {
                $packageData = $packageResult->fetch_assoc();
                $packageName = $packageData['name'];
                $packageDuration = $packageData['duration'] ?? "";
            }
            $packageQuery->close();
        }
        
        // Prepare parameters for SMS sending
        $smsData = [
            'phone_number' => $customerPhone,
            'voucher_code' => $voucher['code'],
            'username' => $voucher['username'] ?: $voucher['code'],
            'password' => $voucher['password'] ?: $voucher['code'],
            'package_name' => $packageName,
            'duration' => $packageDuration
        ];
        
        // Send SMS using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'send_free_trial_sms.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($smsData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $smsResponse = curl_exec($ch);
        $smsError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($smsError) {
            error_log("SMS sending error: $smsError");
        } else {
            error_log("SMS sending response (HTTP $httpCode): $smsResponse");
            
            // Try to parse the JSON response
            $smsResult = json_decode($smsResponse, true);
            if ($smsResult && isset($smsResult['success']) && $smsResult['success']) {
                error_log("SMS sent successfully to: $customerPhone");
            } else {
                $errorMsg = isset($smsResult['message']) ? $smsResult['message'] : 'Unknown error';
                error_log("SMS sending failed: $errorMsg");
            }
        }
    }
    
    return $voucher;
} 