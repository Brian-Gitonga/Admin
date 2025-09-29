<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the portal database connection
require_once 'portal_connection.php';

// Include M-Pesa settings operations (contains payment settings)
require_once 'mpesa_settings_operations.php';

// Initialize debug log
$log_file = 'paystack_debug.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("======= NEW PAYSTACK PAYMENT PROCESS STARTED =======");
log_debug("POST data: " . print_r($_POST, true));

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $resellerId = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 0;
    $packageName = isset($_POST['package_name']) ? $_POST['package_name'] : '';
    $packagePrice = isset($_POST['package_price']) ? $_POST['package_price'] : '';
    $paystackEmail = isset($_POST['paystack_email']) ? $_POST['paystack_email'] : '';
    $phoneNumber = isset($_POST['mpesa_number']) ? $_POST['mpesa_number'] : '';
    $packageId = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
    $routerId = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
    
    log_debug("Form data received: " . json_encode([
        'resellerId' => $resellerId,
        'packageName' => $packageName,
        'packagePrice' => $packagePrice,
        'phoneNumber' => $phoneNumber,
        'paystackEmail' => $paystackEmail,
        'packageId' => $packageId,
        'routerId' => $routerId
    ]));
    
    // Validate inputs
    if (empty($resellerId) || empty($packageName) || empty($packagePrice) || empty($phoneNumber) || empty($packageId)) {
        log_debug("Missing required fields");
        $_SESSION['payment_error'] = 'Missing required fields';
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Format phone number (remove spaces, ensure it starts with 254)
    $phoneNumber = preg_replace('/\s+/', '', $phoneNumber);
    
    // If number starts with 0, replace with 254
    if (substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '254' . substr($phoneNumber, 1);
    } elseif (substr($phoneNumber, 0, 3) !== '254') {
        $phoneNumber = '254' . $phoneNumber;
    }
    
    log_debug("Formatted phone number: " . $phoneNumber);
    
    // If email is empty, generate one from phone number
    if (empty($paystackEmail)) {
        $sanitizedPhone = preg_replace('/\D/', '', $phoneNumber);
        $paystackEmail = $sanitizedPhone . '@customer.qtro.co.ke';
        log_debug("Generated email from phone: " . $paystackEmail);
    }
    
    // Validate email
    if (!filter_var($paystackEmail, FILTER_VALIDATE_EMAIL)) {
        log_debug("Invalid email format: " . $paystackEmail);
        $_SESSION['payment_error'] = 'Invalid email format';
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    // Validate amount
    $packagePrice = intval($packagePrice);
    if ($packagePrice <= 0) {
        log_debug("ERROR: Invalid amount detected: " . $packagePrice . ". Amount must be a positive integer.");
        $_SESSION['payment_error'] = 'Invalid amount (must be positive)';
        echo json_encode(['success' => false, 'message' => 'Invalid amount (must be positive)']);
        exit;
    }

    // Convert to kobo (100 kobo = 1 Naira) if needed for Nigerian payments
    // For Kenya, we'll keep it as is
    $amount = $packagePrice;
    
    log_debug("Validated amount: " . $amount);
    
    // Paystack API integration
    // Get reseller-specific Paystack credentials
    $mpesaCredentials = getMpesaCredentials($conn, $resellerId);
    
    if ($mpesaCredentials['payment_gateway'] !== 'paystack') {
        log_debug("Error: This reseller is not configured for Paystack payments");
        echo json_encode(['success' => false, 'message' => 'This payment gateway is not available']);
        exit;
    }
    
    $secretKey = $mpesaCredentials['secret_key'];
    $publicKey = $mpesaCredentials['public_key'];
    
    if (empty($secretKey) || empty($publicKey)) {
        log_debug("Error: Missing Paystack API keys");
        echo json_encode(['success' => false, 'message' => 'Payment gateway configuration error']);
        exit;
    }
    
    log_debug("Using Paystack keys: Secret=" . substr($secretKey, 0, 5) . "..., Public=" . substr($publicKey, 0, 5) . "...");
    
    // Generate a unique reference
    $reference = 'QTRO_' . uniqid() . '_' . time();
    
    // Prepare transaction data
    $transaction_data = [
        'reference' => $reference,
        'amount' => $amount * 100, // Convert to the smallest currency unit
        'email' => $paystackEmail,
        'currency' => 'KES',
        'callback_url' => isset($_SERVER['HTTP_HOST']) ? 
            'http://' . $_SERVER['HTTP_HOST'] . '/paystack_verify.php?reference=' . $reference : 
            'https://domain.com/paystack_verify.php?reference=' . $reference,
        'metadata' => [
            'package_id' => $packageId,
            'package_name' => $packageName,
            'reseller_id' => $resellerId,
            'router_id' => $routerId,
            'phone_number' => $phoneNumber
        ]
    ];
    
    log_debug("Transaction data: " . json_encode($transaction_data));
    
    // Insert transaction into database
    try {
            // Check if payment_transactions table exists, create if not
            $tableCheckQuery = "SHOW TABLES LIKE 'payment_transactions'";
            $tableResult = $conn->query($tableCheckQuery);
            
            if ($tableResult->num_rows == 0) {
                log_debug("Creating payment_transactions table");
                
                $createTableQuery = "CREATE TABLE IF NOT EXISTS payment_transactions (
                    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    reference VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone_number VARCHAR(20) NOT NULL,
                    package_id INT(11) NOT NULL,
                    package_name VARCHAR(255) NOT NULL,
                    reseller_id INT(11) NOT NULL,
                    router_id INT(11) DEFAULT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    payment_gateway VARCHAR(50) NOT NULL DEFAULT 'paystack',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            if ($conn->query($createTableQuery) === TRUE) {
                log_debug("payment_transactions table created successfully");
            } else {
                log_debug("Error creating payment_transactions table: " . $conn->error);
            }
        }
        
        // Insert transaction record
        $insertQuery = "INSERT INTO payment_transactions 
                        (reference, amount, email, phone_number, package_id, package_name, reseller_id, router_id, status, payment_gateway) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paystack')";
                        
        $stmt = $conn->prepare($insertQuery);
        if ($stmt) {
            $stmt->bind_param("sdssiisi", 
                $reference,
                $amount,
                $paystackEmail,
                $phoneNumber,
                $packageId,
                $packageName,
                $resellerId,
                $routerId
            );
            $stmt->execute();
            log_debug("Transaction details saved to database with ID: " . $conn->insert_id);
        } else {
            log_debug("Failed to prepare transaction statement: " . $conn->error);
        }
    } catch (Exception $e) {
        log_debug("Database error when saving transaction: " . $e->getMessage());
    }
    
    // Initiate Paystack payment
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($transaction_data),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $secretKey,
            "Content-Type: application/json",
            "Cache-Control: no-cache",
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    log_debug("Paystack API response: " . $response);
    
    if ($err) {
        log_debug("cURL Error: " . $err);
        echo json_encode(['success' => false, 'message' => 'Connection error: ' . $err]);
        exit;
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] == true) {
        // Store transaction reference in session
        $_SESSION['paystack_reference'] = $reference;
        $_SESSION['payment_initiated'] = true;
        $_SESSION['payment_email'] = $paystackEmail;
        $_SESSION['payment_amount'] = $amount;
        $_SESSION['payment_timestamp'] = time();
        
        log_debug("Payment initialization successful. Redirecting to: " . $result['data']['authorization_url']);
        
        // Return the authorization URL for the frontend to handle redirection
        echo json_encode([
            'success' => true,
            'message' => 'Payment initialized',
            'authorization_url' => $result['data']['authorization_url'],
            'reference' => $reference,
            'access_code' => $result['data']['access_code']
        ]);
    } else {
        log_debug("Payment initialization failed: " . $result['message']);
        echo json_encode(['success' => false, 'message' => 'Payment initialization failed: ' . $result['message']]);
    }
    
    exit;
} else {
    // Not a POST request
    log_debug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>
