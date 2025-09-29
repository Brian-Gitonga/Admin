<?php
/**
 * Paystack Payment Initialization Handler
 * This script initializes a payment transaction with Paystack API
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// Include database connection
require_once 'connection_dp.php';

// Get Paystack API credentials
$secret_key = 'sk_live_3e881579ac151896d523fa7c1e47f2c2df264400'; // Live key
$public_key = 'pk_live_21eb33f8487ac36f06c777662780e4fcfb42e32e'; // Live public key

// Get JSON data from the request
$inputData = file_get_contents('php://input');
error_log("Paystack initialize raw input: " . $inputData);

$data = json_decode($inputData, true);
error_log("Paystack initialize decoded data: " . print_r($data, true));

// Check for required fields
if (!isset($data['amount']) || !isset($data['email'])) {
    error_log("Paystack initialize error: Missing required fields");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Get user and payment information
$user_id = $_SESSION['user_id'];
$amount = intval($data['amount']); // Amount in kobo/cents
$email = $data['email'];
$payment_type = $data['payment_type'] ?? 'subscription';

// Validate the amount (minimum 100 for 1 NGN/KSH)
if ($amount < 100) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Amount must be at least 1.00']);
    exit();
}

// Generate a unique reference
$reference = 'SUB_' . time() . '_' . $user_id . '_' . mt_rand(1000, 9999);

// Save the payment information in the database
try {
    // Check if the payment_transactions table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'payment_transactions'");
    if ($check_table->num_rows == 0) {
        // Create the payment_transactions table if it doesn't exist
        $create_table_sql = "CREATE TABLE payment_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(100) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            email VARCHAR(100) NOT NULL,
            payment_type VARCHAR(50) NOT NULL DEFAULT 'subscription',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            response_code VARCHAR(10),
            response_message TEXT,
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_processor VARCHAR(50) NOT NULL DEFAULT 'paystack',
            metadata TEXT,
            FOREIGN KEY (user_id) REFERENCES resellers(id) ON DELETE CASCADE
        )";
        
        $conn->query($create_table_sql);
    }
    
    // Save transaction to database
    $stmt = $conn->prepare("INSERT INTO payment_transactions (reference, user_id, amount, email, payment_type) VALUES (?, ?, ?, ?, ?)");
    $amount_decimal = $amount / 100; // Convert back to decimal
    $stmt->bind_param("sidss", $reference, $user_id, $amount_decimal, $email, $payment_type);
    $stmt->execute();
    
    // Initialize the Paystack transaction
    $url = "https://api.paystack.co/transaction/initialize";
    
    $fields = [
        'email' => $email,
        'amount' => $amount,
        'reference' => $reference,
        'callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/SAAS/Wifi%20Billiling%20system/Admin/paystack_verify.php',
        'metadata' => [
            'user_id' => $user_id,
            'payment_type' => $payment_type
        ]
    ];
    
    $fields_string = http_build_query($fields);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secret_key,
        "Cache-Control: no-cache"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for development
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    error_log("Paystack API HTTP Code: " . $httpCode);
    error_log("Paystack API Raw Response: " . $response);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the error
        error_log("Paystack cURL Error: " . $error);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment initialization failed: ' . $error]);
        exit();
    }
    
    curl_close($ch);
    
    // Decode the response
    $response_data = json_decode($response, true);
    error_log("Paystack API Decoded Response: " . print_r($response_data, true));
    
    // Check if the transaction was successfully initialized
    if ($response_data['status']) {
        // Return the authorization URL to the frontend
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Payment initialized successfully',
            'reference' => $reference,
            'authorization_url' => $response_data['data']['authorization_url']
        ]);
        exit();
    } else {
        // Return the error message from Paystack
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Payment initialization failed: ' . $response_data['message']
        ]);
        exit();
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Paystack Initialization Error: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    exit();
}
