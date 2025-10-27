<?php
// M-Pesa Callback Handler - Optimized for Fast Processing
// This file receives payment notifications from Safaricom M-Pesa API

// Disable session - not needed for API callbacks (improves performance)
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable error display (log only) for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include the portal database connection
require_once 'portal_connection.php';

// Initialize lightweight logging
$log_file = 'mpesa_callback.log';
function log_callback($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Log essential information only
log_callback("=== M-PESA CALLBACK START ===");
log_callback("IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Get the callback data
$callbackJSONData = file_get_contents('php://input');
log_callback("Data received: " . (empty($callbackJSONData) ? 'EMPTY' : strlen($callbackJSONData) . ' bytes'));

// Quick validation - respond immediately if empty
if (empty($callbackJSONData)) {
    log_callback("Empty body - responding success");
    header("Content-Type: application/json");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
    exit;
}

// Parse JSON data
$callbackData = json_decode($callbackJSONData);

// Handle JSON parsing errors
if (json_last_error() !== JSON_ERROR_NONE) {
    log_callback("JSON parse error: " . json_last_error_msg());
    // Respond with success to prevent M-Pesa retries
    header("Content-Type: application/json");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
    exit;
}

// Process valid callback data
if ($callbackData && isset($callbackData->Body) && isset($callbackData->Body->stkCallback)) {
    $stkCallback = $callbackData->Body->stkCallback;
    $checkoutRequestID = $stkCallback->CheckoutRequestID ?? '';
    $resultCode = $stkCallback->ResultCode ?? -1;
    $resultDesc = $stkCallback->ResultDesc ?? 'Unknown';

    log_callback("Processing: CheckoutID=$checkoutRequestID | Result=$resultCode");

    // Verify transaction exists in database (billing_system.mpesa_transactions)
    $checkStmt = $conn->prepare("SELECT id, status FROM mpesa_transactions WHERE checkout_request_id = ?");
    if (!$checkStmt) {
        log_callback("❌ DB Error preparing SELECT: " . $conn->error);
        header("Content-Type: application/json");
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
        exit;
    }

    $checkStmt->bind_param("s", $checkoutRequestID);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        log_callback("❌ Transaction NOT FOUND in database: $checkoutRequestID");
        log_callback("⚠️ This means the transaction was not saved during payment initiation!");
        // Respond success to prevent M-Pesa retries
        header("Content-Type: application/json");
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
        exit;
    }

    // Transaction found - log current status
    $txnRow = $checkResult->fetch_assoc();
    log_callback("✅ Transaction FOUND: ID={$txnRow['id']} | Current Status={$txnRow['status']}");

    // Process based on result code
    $success = ($resultCode == 0);

    if ($success) {
        // Extract payment details
        $amount = null;
        $mpesaReceiptNumber = null;
        $transactionDate = null;
        $phoneNumber = null;

        if (isset($stkCallback->CallbackMetadata->Item)) {
            foreach ($stkCallback->CallbackMetadata->Item as $item) {
                switch ($item->Name) {
                    case "Amount": $amount = $item->Value; break;
                    case "MpesaReceiptNumber": $mpesaReceiptNumber = $item->Value; break;
                    case "TransactionDate": $transactionDate = $item->Value; break;
                    case "PhoneNumber": $phoneNumber = $item->Value; break;
                }
            }
        }

        log_callback("💰 Payment SUCCESS: Receipt=$mpesaReceiptNumber | Amount=$amount | Phone=$phoneNumber");

        // Update transaction status to 'completed' in billing_system.mpesa_transactions
        log_callback("⏳ Updating status from '{$txnRow['status']}' to 'completed'...");

        $stmt = $conn->prepare("UPDATE mpesa_transactions SET
            status = 'completed',
            mpesa_receipt = ?,
            transaction_date = ?,
            result_code = ?,
            result_description = ?,
            updated_at = NOW()
            WHERE checkout_request_id = ?");

        if ($stmt) {
            $stmt->bind_param("ssiss",
                $mpesaReceiptNumber,
                $transactionDate,
                $resultCode,
                $resultDesc,
                $checkoutRequestID
            );

            $updateStartTime = microtime(true);
            if ($stmt->execute()) {
                $updateEndTime = microtime(true);
                $updateDuration = round(($updateEndTime - $updateStartTime) * 1000, 2);

                if ($stmt->affected_rows > 0) {
                    log_callback("✅ STATUS UPDATED TO 'completed' in {$updateDuration}ms | Rows affected: {$stmt->affected_rows}");
                    log_callback("🎉 Transaction $checkoutRequestID is now COMPLETED and ready for voucher assignment");
                    
                    // AUTO-PROCESS VOUCHER: Assign voucher and send SMS immediately
                    log_callback("🔄 Starting automatic voucher processing...");
                    
                    try {
                        require_once 'auto_process_vouchers.php';
                        $voucherResult = processSpecificTransaction($checkoutRequestID);
                        
                        if ($voucherResult['success']) {
                            log_callback("✅ VOUCHER PROCESSED: Code={$voucherResult['voucher']['code']} | SMS=" . ($voucherResult['sms_result']['success'] ? 'SENT' : 'FAILED'));
                        } else {
                            log_callback("❌ VOUCHER PROCESSING FAILED: {$voucherResult['message']}");
                        }
                    } catch (Exception $e) {
                        log_callback("❌ EXCEPTION during voucher processing: " . $e->getMessage());
                    }
                } else {
                    log_callback("⚠️ UPDATE executed but 0 rows affected (possibly already completed)");
                }
            } else {
                log_callback("❌ UPDATE failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            log_callback("❌ Failed to prepare UPDATE statement: " . $conn->error);
        }
    } else {
        // Payment failed
        log_callback("❌ Payment FAILED: Code=$resultCode | Desc=$resultDesc");
        log_callback("⏳ Updating status from '{$txnRow['status']}' to 'failed'...");

        // Update transaction status to 'failed' in billing_system.mpesa_transactions
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET
            status = 'failed',
            result_code = ?,
            result_description = ?,
            updated_at = NOW()
            WHERE checkout_request_id = ?");

        if ($stmt) {
            $stmt->bind_param("iss", $resultCode, $resultDesc, $checkoutRequestID);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                log_callback("✅ STATUS UPDATED TO 'failed' | Rows affected: {$stmt->affected_rows}");
            } else {
                log_callback("⚠️ Failed to update status to 'failed'");
            }
            $stmt->close();
        }
    }
} else {
    log_callback("Invalid callback structure");
}

// Send success response to M-Pesa (prevents retries)
log_callback("=== CALLBACK COMPLETE ===");
header("Content-Type: application/json");
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
exit;