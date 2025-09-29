<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once 'connectiondp.php';

// Get the response data from M-Pesa
$mpesa_response = file_get_contents('php://input');
$decoded_response = json_decode($mpesa_response, true);

// Log the callback for debugging
$log_file = 'mpesa_callbacks.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$timestamp] Callback received: " . $mpesa_response . PHP_EOL, FILE_APPEND);

// Check if it's a valid callback
if (isset($decoded_response['Body']) && isset($decoded_response['Body']['stkCallback'])) {
    $callback_data = $decoded_response['Body']['stkCallback'];
    $result_code = $callback_data['ResultCode'];
    $checkout_request_id = $callback_data['CheckoutRequestID'];
    
    if ($result_code == 0) {
        // Payment was successful
        // Update transaction status in database
        try {
            $stmt = $conn->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE checkout_request_id = ?");
            $stmt->bind_param("s", $checkout_request_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                file_put_contents($log_file, "[$timestamp] Transaction updated to completed for CheckoutRequestID: $checkout_request_id" . PHP_EOL, FILE_APPEND);
            } else {
                file_put_contents($log_file, "[$timestamp] No transaction found with CheckoutRequestID: $checkout_request_id" . PHP_EOL, FILE_APPEND);
            }
        } catch (Exception $e) {
            file_put_contents($log_file, "[$timestamp] Database error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    } else {
        // Payment failed
        // Update transaction status in database
        try {
            $result_desc = $callback_data['ResultDesc'] ?? 'Unknown error';
            $stmt = $conn->prepare("UPDATE transactions SET status = 'failed', notes = ?, updated_at = NOW() WHERE checkout_request_id = ?");
            $stmt->bind_param("ss", $result_desc, $checkout_request_id);
            $stmt->execute();
            
            file_put_contents($log_file, "[$timestamp] Transaction marked as failed for CheckoutRequestID: $checkout_request_id - Reason: $result_desc" . PHP_EOL, FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents($log_file, "[$timestamp] Database error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }
}

// Return a response to M-Pesa
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?> 