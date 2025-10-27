<?php
/**
 * Simulate Payment Completion - Update transaction status to completed
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

header('Content-Type: application/json');

function log_completion($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('payment_completion_simulation.log', "[$timestamp] $message\n", FILE_APPEND);
}

log_completion("=== PAYMENT COMPLETION SIMULATION STARTED ===");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkoutRequestId = isset($_POST['checkout_request_id']) ? $_POST['checkout_request_id'] : '';
    
    log_completion("Checkout Request ID: $checkoutRequestId");
    
    if (empty($checkoutRequestId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Checkout Request ID is required'
        ]);
        exit;
    }
    
    try {
        // Check if transaction exists
        $checkStmt = $conn->prepare("SELECT id, status, phone_number, amount FROM mpesa_transactions WHERE checkout_request_id = ?");
        $checkStmt->bind_param("s", $checkoutRequestId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            log_completion("ERROR: Transaction not found");
            echo json_encode([
                'success' => false,
                'message' => 'Transaction not found'
            ]);
            exit;
        }
        
        $transaction = $result->fetch_assoc();
        log_completion("Transaction found: ID={$transaction['id']}, Status={$transaction['status']}");
        
        if ($transaction['status'] === 'completed') {
            log_completion("Transaction already completed");
            echo json_encode([
                'success' => true,
                'message' => 'Transaction is already completed'
            ]);
            exit;
        }
        
        // Generate fake M-Pesa receipt
        $mpesaReceipt = 'SIM' . strtoupper(substr(md5($checkoutRequestId), 0, 8));
        
        // Update transaction to completed
        $updateStmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'completed', mpesa_receipt = ?, result_code = 0, result_description = 'The service request is processed successfully.', updated_at = NOW() WHERE checkout_request_id = ?");
        $updateStmt->bind_param("ss", $mpesaReceipt, $checkoutRequestId);
        
        if ($updateStmt->execute()) {
            log_completion("Transaction updated to completed successfully");
            log_completion("M-Pesa Receipt: $mpesaReceipt");
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment status updated to completed successfully',
                'mpesa_receipt' => $mpesaReceipt,
                'checkout_request_id' => $checkoutRequestId
            ]);
        } else {
            throw new Exception("Failed to update transaction: " . $updateStmt->error);
        }
        
    } catch (Exception $e) {
        log_completion("ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests allowed'
    ]);
}
?>
