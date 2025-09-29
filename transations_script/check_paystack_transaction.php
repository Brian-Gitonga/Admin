<?php
// Start session
session_start();

// Include database connection
require_once '../portal_connection.php';

// Include M-Pesa settings operations (contains payment settings)
require_once '../mpesa_settings_operations.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get reseller ID from session
$reseller_id = $_SESSION['user_id'];

// Initialize debug log
$log_file = '../paystack_check.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_debug("======= PAYSTACK TRANSACTION CHECK STARTED =======");
log_debug("GET data: " . print_r($_GET, true));

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = 'Transaction ID is required';
    header('Location: ../transations.php');
    exit();
}

$transaction_id = intval($_GET['id']);

// Get transaction details from database
$query = "SELECT * FROM payment_transactions WHERE id = ? AND reseller_id = ? AND payment_gateway = 'paystack'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $transaction_id, $reseller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Transaction not found or you do not have permission to check this transaction';
    header('Location: ../transations.php');
    exit();
}

$transaction = $result->fetch_assoc();
log_debug("Transaction found: " . json_encode($transaction));

// Get Paystack credentials for this reseller
$mpesaCredentials = getMpesaCredentials($conn, $reseller_id);

if ($mpesaCredentials['payment_gateway'] !== 'paystack') {
    log_debug("This reseller is not configured for Paystack");
    $_SESSION['error_message'] = 'Your account is not configured for Paystack payments';
    header('Location: ../transations.php');
    exit();
}

$secretKey = $mpesaCredentials['secret_key'];

if (empty($secretKey)) {
    log_debug("Missing Paystack API keys");
    $_SESSION['error_message'] = 'Paystack API keys are not configured';
    header('Location: ../transations.php');
    exit();
}

// Get transaction reference
$reference = $transaction['reference'];

if (empty($reference)) {
    log_debug("Missing transaction reference");
    $_SESSION['error_message'] = 'Transaction reference is missing';
    header('Location: ../transations.php');
    exit();
}

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
    $_SESSION['error_message'] = 'Connection error: ' . $err;
    header('Location: ../transations.php');
    exit();
}

$result = json_decode($response, true);

if ($result['status'] !== true) {
    log_debug("Payment verification failed: " . $result['message']);
    $_SESSION['error_message'] = 'Payment verification failed: ' . $result['message'];
    header('Location: ../transations.php');
    exit();
}

// Check if payment is successful
$newStatus = $transaction['status'];
if ($result['data']['status'] === 'success') {
    $newStatus = 'completed';
    log_debug("Payment is successful. Updating status to completed.");
} elseif ($result['data']['status'] === 'failed') {
    $newStatus = 'failed';
    log_debug("Payment failed. Updating status to failed.");
}

// Update transaction status if changed
if ($newStatus !== $transaction['status']) {
    $updateQuery = "UPDATE payment_transactions SET status = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("si", $newStatus, $transaction_id);
    $updateStmt->execute();
    log_debug("Updated transaction status to " . $newStatus);
    
    // If payment is successful, generate voucher if needed
    if ($newStatus === 'completed') {
        // TODO: Implement voucher generation logic based on package details
        // This would typically be similar to what you do for M-Pesa payments
        
        // For now, just log that we would generate a voucher
        log_debug("Payment completed successfully. Voucher generation would happen here.");
    }
    
    $_SESSION['success_message'] = 'Transaction status updated to ' . $newStatus;
} else {
    $_SESSION['info_message'] = 'Transaction status remains ' . $newStatus;
}

// Redirect back to transactions page
header('Location: ../transations.php');
exit();
?>








