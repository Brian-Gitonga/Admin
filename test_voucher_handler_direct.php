<?php
/**
 * Test Voucher Handler Direct - Direct test of the voucher handler function
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';
require_once 'vouchers_script/payment_voucher_handler.php';

header('Content-Type: application/json');

function log_test($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('voucher_handler_test.log', "[$timestamp] $message\n", FILE_APPEND);
}

log_test("=== VOUCHER HANDLER DIRECT TEST STARTED ===");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_voucher_handler'])) {
    $checkoutRequestId = $_POST['checkout_request_id'] ?? '';
    $packageId = intval($_POST['package_id'] ?? 0);
    $resellerId = intval($_POST['reseller_id'] ?? 0);
    $phoneNumber = $_POST['phone_number'] ?? '';
    $mpesaReceipt = $_POST['mpesa_receipt'] ?? '';
    
    log_test("Test parameters: checkout=$checkoutRequestId, package=$packageId, reseller=$resellerId, phone=$phoneNumber");
    
    if (empty($checkoutRequestId) || empty($packageId) || empty($resellerId) || empty($phoneNumber)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required test parameters',
            'debug_info' => [
                'checkout_request_id' => $checkoutRequestId,
                'package_id' => $packageId,
                'reseller_id' => $resellerId,
                'phone_number' => $phoneNumber
            ]
        ]);
        exit;
    }
    
    try {
        // Test the voucher handler function directly
        log_test("Calling createVoucherAfterPayment function...");
        
        $result = createVoucherAfterPayment(
            $checkoutRequestId,
            $packageId,
            $resellerId,
            $phoneNumber,
            $mpesaReceipt
        );
        
        log_test("Voucher handler result: " . json_encode($result));
        
        if ($result['success']) {
            // If successful, also update the mpesa_transactions table with voucher info
            $updateSql = "UPDATE mpesa_transactions SET voucher_code = ?, voucher_id = ? WHERE checkout_request_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            
            if ($updateStmt) {
                $voucherId = null; // We don't have voucher ID in the result, but that's OK
                $updateStmt->bind_param("sis", $result['voucher_code'], $voucherId, $checkoutRequestId);
                $updateStmt->execute();
                log_test("Updated mpesa_transactions with voucher code: " . $result['voucher_code']);
            }
            
            echo json_encode([
                'success' => true,
                'voucher_code' => $result['voucher_code'],
                'voucher_username' => $result['voucher_username'] ?? $result['voucher_code'],
                'voucher_password' => $result['voucher_password'] ?? $result['voucher_code'],
                'message' => $result['message'],
                'debug_info' => [
                    'function_called' => 'createVoucherAfterPayment',
                    'parameters_used' => [
                        'checkout_request_id' => $checkoutRequestId,
                        'package_id' => $packageId,
                        'reseller_id' => $resellerId,
                        'phone_number' => $phoneNumber,
                        'mpesa_receipt' => $mpesaReceipt
                    ]
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $result['message'],
                'debug_info' => [
                    'function_result' => $result,
                    'parameters_used' => [
                        'checkout_request_id' => $checkoutRequestId,
                        'package_id' => $packageId,
                        'reseller_id' => $resellerId,
                        'phone_number' => $phoneNumber,
                        'mpesa_receipt' => $mpesaReceipt
                    ]
                ]
            ]);
        }
        
    } catch (Exception $e) {
        log_test("EXCEPTION: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Exception occurred: ' . $e->getMessage(),
            'debug_info' => [
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method or missing test parameter'
    ]);
}
?>
