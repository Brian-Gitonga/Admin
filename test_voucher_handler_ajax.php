<?php
/**
 * Test AJAX Handler - Simulates the exact process from check_payment_status.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once 'portal_connection.php';
require_once 'vouchers_script/payment_voucher_handler.php';

// Set proper content type for JSON responses
header('Content-Type: application/json');

// Initialize debug log
$log_file = 'voucher_test.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("=== VOUCHER TEST HANDLER STARTED ===");

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get checkout request ID from POST data
    $checkoutRequestID = isset($_POST['checkout_request_id']) ? $_POST['checkout_request_id'] : '';
    
    log_debug("Testing voucher fetch for checkout request ID: $checkoutRequestID");
    
    if (empty($checkoutRequestID)) {
        log_debug("Error: No checkout request ID provided");
        echo json_encode(['success' => false, 'message' => 'No checkout request ID provided']);
        exit;
    }
    
    // Check if payment is already marked as completed in the database
    $stmt = $conn->prepare("SELECT status, package_id, mpesa_receipt, phone_number FROM mpesa_transactions WHERE checkout_request_id = ?");
    $stmt->bind_param("s", $checkoutRequestID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        log_debug("Error: No transaction found with checkout request ID: $checkoutRequestID");
        echo json_encode(['success' => false, 'message' => 'No transaction found with the provided checkout request ID']);
        exit;
    }
    
    $transaction = $result->fetch_assoc();
    log_debug("Transaction found. Status: " . $transaction['status']);
    
    // If payment is completed, fetch voucher
    if ($transaction['status'] === 'completed') {
        log_debug("Payment is completed, fetching voucher...");
        
        // Get transaction details for voucher generation
        $txnStmt = $conn->prepare("SELECT reseller_id, phone_number, package_id FROM mpesa_transactions WHERE checkout_request_id = ?");
        $txnStmt->bind_param("s", $checkoutRequestID);
        $txnStmt->execute();
        $txnResult = $txnStmt->get_result();
        
        if ($txnResult->num_rows > 0) {
            $txnData = $txnResult->fetch_assoc();
            $resellerId = $txnData['reseller_id'];
            $phoneNumber = $txnData['phone_number'];
            $packageId = $txnData['package_id'];
            
            log_debug("Transaction details: Package ID: $packageId, Reseller ID: $resellerId, Phone: $phoneNumber");
            
            // Use the voucher handler to fetch a voucher
            $voucherResult = createVoucherAfterPayment(
                $checkoutRequestID,
                $packageId,
                $resellerId,
                $phoneNumber,
                $transaction['mpesa_receipt']
            );
            
            log_debug("Voucher handler result: " . json_encode($voucherResult));
            
            if ($voucherResult['success']) {
                $voucher_code = $voucherResult['voucher_code'];
                $voucher_username = isset($voucherResult['voucher_username']) ? $voucherResult['voucher_username'] : $voucher_code;
                $voucher_password = isset($voucherResult['voucher_password']) ? $voucherResult['voucher_password'] : $voucher_code;
                log_debug("Voucher assigned: $voucher_code (username: $voucher_username)");
            } else {
                log_debug("Failed to fetch voucher: " . $voucherResult['message']);
                echo json_encode([
                    'success' => false,
                    'message' => $voucherResult['message']
                ]);
                exit;
            }
        } else {
            log_debug("Could not find transaction details");
            echo json_encode([
                'success' => false,
                'message' => 'Could not find transaction details'
            ]);
            exit;
        }
        
        // Get package name and duration from the database
        $packageName = 'WiFi Package'; // Default value
        $packageDuration = null;
        $packageQuery = $conn->prepare("SELECT name, duration FROM packages WHERE id = ?");
        if ($packageQuery) {
            $packageQuery->bind_param("i", $packageId);
            $packageQuery->execute();
            $packageResult = $packageQuery->get_result();
            if ($packageResult && $packageResult->num_rows > 0) {
                $packageData = $packageResult->fetch_assoc();
                $packageName = $packageData['name'];
                $packageDuration = $packageData['duration'];
            }
            $packageQuery->close();
        }
        
        log_debug("Package details: Name: $packageName, Duration: " . ($packageDuration ?: 'N/A'));
        
        // Return success response
        $response = [
            'success' => true,
            'message' => 'Payment completed successfully',
            'receipt' => $transaction['mpesa_receipt'],
            'voucher_code' => $voucher_code,
            'voucher_username' => isset($voucher_username) ? $voucher_username : $voucher_code,
            'voucher_password' => isset($voucher_password) ? $voucher_password : $voucher_code,
            'phone_number' => $transaction['phone_number'],
            'package_name' => $packageName,
            'duration' => $packageDuration ?: ''
        ];
        
        log_debug("Sending success response: " . json_encode($response));
        echo json_encode($response);
        exit;
        
    } else {
        log_debug("Payment not completed yet. Status: " . $transaction['status']);
        echo json_encode([
            'success' => false,
            'message' => 'Payment not yet completed. Current status: ' . $transaction['status']
        ]);
        exit;
    }
    
} else {
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>
