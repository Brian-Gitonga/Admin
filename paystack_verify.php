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
$log_file = 'paystack_verify.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("======= PAYSTACK VERIFICATION STARTED =======");
log_debug("GET data: " . print_r($_GET, true));
log_debug("SESSION data: " . print_r($_SESSION, true));

// Check if we have a reference
$reference = isset($_GET['reference']) ? $_GET['reference'] : '';
if (empty($reference)) {
    $reference = isset($_SESSION['paystack_reference']) ? $_SESSION['paystack_reference'] : '';
}

if (empty($reference)) {
    log_debug("No reference found in GET or SESSION");
    // Redirect back to portal with error
    $_SESSION['payment_error'] = "Payment verification failed: No reference provided";
    header("Location: portal.php");
    exit;
}

log_debug("Verifying transaction with reference: " . $reference);

// Get the transaction from database
try {
    $query = "SELECT * FROM payment_transactions WHERE reference = ? AND payment_gateway = 'paystack' LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        log_debug("Error preparing query: " . $conn->error);
        throw new Exception("Database error");
    }
    
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        log_debug("Transaction not found in database: " . $reference);
        throw new Exception("Transaction not found");
    }
    
    $transaction = $result->fetch_assoc();
    log_debug("Transaction found: " . json_encode($transaction));
    
    // Get Paystack credentials for this reseller
    $mpesaCredentials = getMpesaCredentials($conn, $transaction['reseller_id']);
    
    if ($mpesaCredentials['payment_gateway'] !== 'paystack') {
        log_debug("This reseller is not configured for Paystack");
        throw new Exception("Payment gateway configuration error");
    }
    
    $secretKey = $mpesaCredentials['secret_key'];
    
    // Verify with Paystack API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . urlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $secretKey,
            "Cache-Control: no-cache",
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    log_debug("Paystack verification response: " . $response);
    
    if ($err) {
        log_debug("cURL Error: " . $err);
        throw new Exception("Connection error: " . $err);
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] !== true) {
        log_debug("Payment verification failed: " . $result['message']);
        throw new Exception("Payment verification failed: " . $result['message']);
    }
    
    // Check if payment is successful
    if ($result['data']['status'] !== 'success') {
        log_debug("Payment not successful. Status: " . $result['data']['status']);
        throw new Exception("Payment not successful. Status: " . $result['data']['status']);
    }
    
    // Update transaction status
    $updateQuery = "UPDATE payment_transactions SET status = 'completed' WHERE reference = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("s", $reference);
    $updateStmt->execute();
    log_debug("Updated transaction status to completed");
    
    // Generate voucher if needed
    // TODO: Implement voucher generation logic based on package details
    
    // Set success message in session
    $_SESSION['payment_success'] = true;
    $_SESSION['payment_message'] = "Payment completed successfully! Your package has been activated.";
    
    // Redirect back to portal with success
    header("Location: portal.php?payment_success=true");
    exit;
    
} catch (Exception $e) {
    log_debug("Error: " . $e->getMessage());
    $_SESSION['payment_error'] = "Payment verification failed: " . $e->getMessage();
    header("Location: portal.php?payment_error=true");
    exit;
}
?>