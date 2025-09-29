<?php
/**
 * Paystack Webhook Handler
 * This script handles Paystack webhook notifications for payment events
 */

// Don't show errors in production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Log errors instead
ini_set('log_errors', 1);
ini_set('error_log', 'paystack_webhook_errors.log');

// Include required files
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';
require_once 'paystack_payment.php';

// Retrieve the request's body
$input = file_get_contents('php://input');
$event = json_decode($input, true);

// Log the incoming webhook for debugging
error_log('Paystack Webhook Received: ' . json_encode($event));

// Verify that this is a Paystack event
if (!$event || !isset($event['event'])) {
    http_response_code(400);
    exit();
}

// Process based on event type
try {
    switch ($event['event']) {
        case 'charge.success':
            // Process successful payment
            processSuccessfulPayment($event);
            break;
            
        case 'subscription.create':
        case 'subscription.disable':
        case 'subscription.enable':
        case 'invoice.update':
        case 'invoice.payment_failed':
            // Log other events for future implementation
            error_log('Unhandled Paystack event: ' . $event['event']);
            break;
            
        default:
            // Ignore other events
            error_log('Unknown Paystack event: ' . $event['event']);
    }
    
    // Return a 200 OK response to Paystack
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // Log any errors
    error_log('Paystack Webhook Error: ' . $e->getMessage());
    
    // Return a server error response
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An internal error occurred']);
}

/**
 * Process a successful payment event
 *
 * @param array $event The webhook event data
 */
function processSuccessfulPayment($event) {
    global $conn;
    
    // Extract the reference from the event
    $reference = $event['data']['reference'];
    $transaction_id = $event['data']['id'];
    
    // Check if the reference exists in our database
    $stmt = $conn->prepare("
        SELECT * FROM paystack_transactions 
        WHERE reference = ?
    ");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Transaction not found in our database
        error_log("Payment received for unknown reference: $reference");
        return;
    }
    
    $transaction = $result->fetch_assoc();
    $reseller_id = $transaction['reseller_id'];
    
    // Update the transaction status
    $status = 'success';
    $payment_date = date('Y-m-d H:i:s');
    $response_json = json_encode($event);
    
    $stmt = $conn->prepare("
        UPDATE paystack_transactions 
        SET status = ?, payment_date = ?, response_data = ? 
        WHERE reference = ?
    ");
    
    $stmt->bind_param("ssss", $status, $payment_date, $response_json, $reference);
    $stmt->execute();
    
    // Process subscription update if it's a subscription payment
    if ($transaction['transaction_type'] === 'subscription') {
        updateSubscription($reseller_id, $transaction['amount'], $reference);
        
        // Send notification to the user
        sendPaymentNotification($reseller_id, $transaction['amount'], $reference);
    }
}

/**
 * Send a payment success notification to the reseller
 *
 * @param int $reseller_id The reseller ID
 * @param float $amount The payment amount
 * @param string $reference The payment reference
 */
function sendPaymentNotification($reseller_id, $amount, $reference) {
    global $conn;
    
    try {
        // Get reseller details
        $stmt = $conn->prepare("SELECT email, full_name FROM resellers WHERE id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return; // Reseller not found
        }
        
        $reseller = $result->fetch_assoc();
        
        // Create a notification in the database
        $title = "Payment Received";
        $message = "Your payment of KSH " . number_format($amount, 2) . " (ref: $reference) was successful. Your subscription has been updated.";
        $recipient_id = $reseller_id;
        $recipient_type = 'reseller';
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (recipient_id, recipient_type, title, message)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isss", $recipient_id, $recipient_type, $title, $message);
        $stmt->execute();
        
        // You could add email notification here if needed
        
    } catch (Exception $e) {
        error_log("Error sending payment notification: " . $e->getMessage());
    }
}








