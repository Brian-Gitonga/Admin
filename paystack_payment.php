<?php
/**
 * Paystack Payment Processing Script
 * This file handles initiation, verification, and processing of Paystack payments
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and necessary functions
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';

/**
 * Initialize a Paystack payment transaction
 * 
 * @param array $data Payment data (amount, email, reference, etc.)
 * @param int $reseller_id Reseller ID
 * @return array Result of the initialization
 */
function initializePaystackPayment($data, $reseller_id) {
    // Get Paystack credentials for the reseller
    $credentials = getMpesaCredentials($conn, $reseller_id);
    
    // Check if Paystack is the selected payment gateway
    if ($credentials['payment_gateway'] !== 'paystack') {
        return [
            'success' => false,
            'message' => 'Paystack is not configured as the payment gateway for this account'
        ];
    }
    
    // Check for required credentials
    if (empty($credentials['secret_key']) || empty($credentials['public_key'])) {
        return [
            'success' => false,
            'message' => 'Paystack API keys are not properly configured'
        ];
    }
    
    // Ensure required data is present
    if (!isset($data['amount']) || !isset($data['email']) || !isset($data['reference'])) {
        return [
            'success' => false,
            'message' => 'Required payment data missing'
        ];
    }
    
    // Initialize the Paystack transaction
    $url = "https://api.paystack.co/transaction/initialize";
    
    $fields = [
        'email' => $data['email'],
        'amount' => $data['amount'], // Amount should be in kobo (smallest currency unit)
        'reference' => $data['reference'],
        'callback_url' => $data['callback_url'] ?? null,
        'metadata' => [
            'reseller_id' => $reseller_id,
            'transaction_type' => $data['transaction_type'] ?? 'subscription'
        ]
    ];
    
    $headers = [
        'Authorization: Bearer ' . $credentials['secret_key'],
        'Cache-Control: no-cache',
        'Content-Type: application/json'
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for development environments
    
    // Execute the request
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log('Paystack cURL Error: ' . $err);
        return [
            'success' => false,
            'message' => 'Error connecting to Paystack API: ' . $err
        ];
    }
    
    // Process the response
    $result = json_decode($response, true);
    
    // Store the transaction in the database
    storePaystackTransaction($reseller_id, $data, $result);
    
    if ($result['status']) {
        return [
            'success' => true,
            'message' => 'Payment initialization successful',
            'authorization_url' => $result['data']['authorization_url'],
            'reference' => $result['data']['reference'],
            'access_code' => $result['data']['access_code'] ?? null
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Payment initialization failed: ' . ($result['message'] ?? 'Unknown error')
        ];
    }
}

/**
 * Verify a Paystack payment transaction
 * 
 * @param string $reference Transaction reference
 * @param int $reseller_id Reseller ID
 * @return array Verification result
 */
function verifyPaystackPayment($reference, $reseller_id) {
    global $conn;
    
    // Get Paystack credentials for the reseller
    $credentials = getMpesaCredentials($conn, $reseller_id);
    
    // Verify the transaction
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
    
    $headers = [
        'Authorization: Bearer ' . $credentials['secret_key'],
        'Cache-Control: no-cache'
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for development environments
    
    // Execute the request
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        error_log('Paystack Verification cURL Error: ' . $err);
        return [
            'success' => false,
            'message' => 'Error verifying payment: ' . $err
        ];
    }
    
    // Process the response
    $result = json_decode($response, true);
    
    if ($result['status'] && $result['data']['status'] === 'success') {
        // Update the transaction status in database
        updatePaystackTransaction($reference, $result);
        
        return [
            'success' => true,
            'message' => 'Payment verification successful',
            'data' => $result['data']
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Payment verification failed: ' . ($result['data']['gateway_response'] ?? $result['message'] ?? 'Unknown error')
        ];
    }
}

/**
 * Store a Paystack transaction in the database
 * 
 * @param int $reseller_id Reseller ID
 * @param array $request_data Original request data
 * @param array $response_data API response data
 * @return bool Success status
 */
function storePaystackTransaction($reseller_id, $request_data, $response_data) {
    global $conn;
    
    try {
        // Check if paystack_transactions table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'paystack_transactions'");
        if ($table_check->num_rows === 0) {
            // Create the table
            $create_query = "CREATE TABLE IF NOT EXISTS `paystack_transactions` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `reseller_id` int(11) NOT NULL,
                `reference` varchar(100) NOT NULL,
                `amount` decimal(10,2) NOT NULL,
                `email` varchar(100) NOT NULL,
                `transaction_type` varchar(50) NOT NULL DEFAULT 'subscription',
                `status` varchar(20) NOT NULL DEFAULT 'pending',
                `authorization_url` varchar(255) DEFAULT NULL,
                `access_code` varchar(100) DEFAULT NULL,
                `payment_date` datetime DEFAULT NULL,
                `response_data` text DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `reference` (`reference`),
                KEY `reseller_id` (`reseller_id`),
                CONSTRAINT `paystack_transactions_ibfk_1` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            $conn->query($create_query);
        }
        
        // Prepare the data for insertion
        $reference = $response_data['data']['reference'] ?? $request_data['reference'];
        $amount = ($request_data['amount'] / 100); // Convert from kobo to naira/shillings
        $email = $request_data['email'];
        $transaction_type = $request_data['transaction_type'] ?? 'subscription';
        $status = ($response_data['status']) ? 'pending' : 'failed';
        $authorization_url = $response_data['data']['authorization_url'] ?? null;
        $access_code = $response_data['data']['access_code'] ?? null;
        $response_json = json_encode($response_data);
        
        // Insert the transaction
        $stmt = $conn->prepare("
            INSERT INTO paystack_transactions 
            (reseller_id, reference, amount, email, transaction_type, status, authorization_url, access_code, response_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            status = VALUES(status), 
            authorization_url = VALUES(authorization_url), 
            access_code = VALUES(access_code), 
            response_data = VALUES(response_data)
        ");
        
        $stmt->bind_param(
            "isdssssss", 
            $reseller_id, 
            $reference, 
            $amount, 
            $email, 
            $transaction_type, 
            $status, 
            $authorization_url, 
            $access_code, 
            $response_json
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error storing Paystack transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a Paystack transaction in the database after verification
 * 
 * @param string $reference Transaction reference
 * @param array $verification_data Verification response data
 * @return bool Success status
 */
function updatePaystackTransaction($reference, $verification_data) {
    global $conn;
    
    try {
        $status = ($verification_data['status'] && $verification_data['data']['status'] === 'success') ? 'success' : 'failed';
        $payment_date = date('Y-m-d H:i:s');
        $response_json = json_encode($verification_data);
        
        $stmt = $conn->prepare("
            UPDATE paystack_transactions 
            SET status = ?, payment_date = ?, response_data = ? 
            WHERE reference = ?
        ");
        
        $stmt->bind_param("ssss", $status, $payment_date, $response_json, $reference);
        $result = $stmt->execute();
        $stmt->close();
        
        // If payment was successful, process the subscription update
        if ($status === 'success') {
            // Get transaction details
            $stmt = $conn->prepare("SELECT reseller_id, amount, transaction_type FROM paystack_transactions WHERE reference = ?");
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $reseller_id = $row['reseller_id'];
                $amount = $row['amount'];
                $transaction_type = $row['transaction_type'];
                
                // Update subscription if this is a subscription payment
                if ($transaction_type === 'subscription') {
                    updateSubscription($reseller_id, $amount, $reference);
                }
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating Paystack transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Update a subscription after successful payment
 * 
 * @param int $reseller_id Reseller ID
 * @param float $amount Payment amount
 * @param string $reference Payment reference
 * @return bool Success status
 */
function updateSubscription($reseller_id, $amount, $reference) {
    global $conn;
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get the latest subscription for this reseller
        $stmt = $conn->prepare("
            SELECT rs.*, sp.duration_days 
            FROM reseller_subscriptions rs
            JOIN subscription_plans sp ON rs.plan_id = sp.id
            WHERE rs.reseller_id = ?
            ORDER BY rs.id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Existing subscription found
            $subscription = $result->fetch_assoc();
            $plan_id = $subscription['plan_id'];
            $duration_days = $subscription['duration_days'];
            
            // Calculate new dates
            $current_date = new DateTime();
            $current_date_str = $current_date->format('Y-m-d H:i:s');
            
            if ($subscription['status'] === 'expired') {
                // Create new expiry date from current date
                $expiry_date = clone $current_date;
                $expiry_date->add(new DateInterval('P' . $duration_days . 'D'));
            } else {
                // Extend current expiry date
                $expiry_date = new DateTime($subscription['expiry_date']);
                $expiry_date->add(new DateInterval('P' . $duration_days . 'D'));
            }
            
            $expiry_date_str = $expiry_date->format('Y-m-d H:i:s');
            
            if ($subscription['status'] === 'expired') {
                // Create a new subscription record
                $stmt = $conn->prepare("
                    INSERT INTO reseller_subscriptions 
                    (reseller_id, plan_id, start_date, expiry_date, status, last_payment_date, amount_paid, payment_method, transaction_id)
                    VALUES (?, ?, ?, ?, 'active', ?, ?, 'paystack', ?)
                ");
                $stmt->bind_param("iisssds", $reseller_id, $plan_id, $current_date_str, $expiry_date_str, $current_date_str, $amount, $reference);
                $stmt->execute();
            } else {
                // Update existing subscription
                $stmt = $conn->prepare("
                    UPDATE reseller_subscriptions 
                    SET expiry_date = ?, status = 'active', last_payment_date = ?, amount_paid = ?, payment_method = 'paystack', transaction_id = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("ssdsi", $expiry_date_str, $current_date_str, $amount, $reference, $subscription['id']);
                $stmt->execute();
            }
            
            // Update reseller status
            $stmt = $conn->prepare("UPDATE resellers SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $reseller_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            return true;
        } else {
            // No subscription found, select a default plan
            $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $plan = $result->fetch_assoc();
                $plan_id = $plan['id'];
                $duration_days = $plan['duration_days'];
                
                // Calculate dates
                $current_date = new DateTime();
                $current_date_str = $current_date->format('Y-m-d H:i:s');
                
                $expiry_date = clone $current_date;
                $expiry_date->add(new DateInterval('P' . $duration_days . 'D'));
                $expiry_date_str = $expiry_date->format('Y-m-d H:i:s');
                
                // Create a new subscription
                $stmt = $conn->prepare("
                    INSERT INTO reseller_subscriptions 
                    (reseller_id, plan_id, start_date, expiry_date, status, last_payment_date, amount_paid, payment_method, transaction_id)
                    VALUES (?, ?, ?, ?, 'active', ?, ?, 'paystack', ?)
                ");
                $stmt->bind_param("iisssds", $reseller_id, $plan_id, $current_date_str, $expiry_date_str, $current_date_str, $amount, $reference);
                $stmt->execute();
                
                // Update reseller status
                $stmt = $conn->prepare("UPDATE resellers SET status = 'active' WHERE id = ?");
                $stmt->bind_param("i", $reseller_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();
                return true;
            } else {
                // No plans found
                $conn->rollback();
                error_log("No subscription plans found for reseller $reseller_id");
                return false;
            }
        }
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Error updating subscription: " . $e->getMessage());
        return false;
    }
}

// Function to generate a unique reference for Paystack transactions
function generatePaystackReference($reseller_id, $type = 'sub') {
    return $type . '_' . time() . '_' . $reseller_id . '_' . mt_rand(1000, 9999);
}









