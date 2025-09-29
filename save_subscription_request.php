<?php
/**
 * Save Subscription Request
 * This script handles AJAX requests to record subscription requests in the database
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and notifications
require_once 'connection_dp.php';
require_once 'notifications.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => 'An error occurred while processing your request.'
];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'You must be logged in to request a subscription.';
    echo json_encode($response);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Calculate the requested amount based on dashboard data
        require_once 'dashboard_data.php';
        
        // Get the subscription info from the dashboard data
        $subscription = getSubscriptionInfo($conn, $user_id);
        
        // Calculate the amount for the subscription request
        // This should match the calculation from the modal display
        $revenueShare = max(500, $dashboard_data['monthly_payment'] * 0.03);
        $smsCharges = $dashboard_data['weekly_revenue'] * 0.9;
        $totalAmount = $revenueShare + $smsCharges;
        
        // Check if a request already exists and is pending
        $checkQuery = "SELECT id FROM subscription_requests 
                       WHERE reseller_id = ? AND status = 'pending'";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $response['message'] = 'You already have a pending subscription request. Please wait for admin approval.';
            echo json_encode($response);
            exit;
        }
        
        // Current date and time
        $requested_at = date('Y-m-d H:i:s');
        
        // Insert the subscription request
        $insertQuery = "INSERT INTO subscription_requests 
                        (reseller_id, amount, requested_at, status) 
                        VALUES (?, ?, ?, 'pending')";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ids", $user_id, $totalAmount, $requested_at);
        
        if ($stmt->execute()) {
            // Get the inserted request ID
            $request_id = $stmt->insert_id;
            
            // Create a notification for the admin
            $adminNotificationQuery = "INSERT INTO notifications 
                                      (recipient_id, recipient_type, title, message, is_read) 
                                      VALUES (1, 'admin', 'New Subscription Request', 
                                             'A new subscription request has been submitted by reseller ID: {$user_id}', 0)";
            $conn->query($adminNotificationQuery);
            
            // Set success response
            $response['success'] = true;
            $response['message'] = 'Your subscription request has been submitted successfully. Admin will review it shortly.';
            $response['request_id'] = $request_id;
        } else {
            $response['message'] = 'Failed to submit subscription request. Please try again.';
        }
    } catch (Exception $e) {
        error_log("Error in save_subscription_request.php: " . $e->getMessage());
        $response['message'] = 'An error occurred while processing your request: ' . $e->getMessage();
    }
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit; 