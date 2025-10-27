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

// Include SMS settings operations
require_once 'sms_settings_operations.php';

// Check database connection
if (!$portal_conn || $portal_conn->connect_error) {
    log_debug("Database connection failed: " . ($portal_conn ? $portal_conn->connect_error : "Connection is null"));
    die("Database connection error. Please try again later.");
}

// Set global $conn for compatibility with fetch_voucher.php
$conn = $portal_conn;

// Initialize debug log
$log_file = 'paystack_verify.log';
function log_debug($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// CRITICAL: Log that this file is being accessed
log_debug("🔥 PAYSTACK_VERIFY.PHP ACCESSED - Callback received!");
log_debug("Request Method: " . $_SERVER['REQUEST_METHOD']);
log_debug("Request URI: " . $_SERVER['REQUEST_URI']);
log_debug("HTTP Host: " . $_SERVER['HTTP_HOST']);
log_debug("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set'));
log_debug("Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'Not set'));
log_debug("Remote IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Not set'));

log_debug("======= PAYSTACK VERIFICATION STARTED =======");
log_debug("Current URL: " . $_SERVER['REQUEST_URI']);
log_debug("HTTP Host: " . $_SERVER['HTTP_HOST']);
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
    $query = "SELECT * FROM payment_transactions WHERE reference = ? LIMIT 1";
    $stmt = $portal_conn->prepare($query);
    if (!$stmt) {
        log_debug("Error preparing query: " . $portal_conn->error);
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
    $mpesaCredentials = getMpesaCredentials($portal_conn, $transaction['reseller_id']);

    if (!$mpesaCredentials || empty($mpesaCredentials['secret_key'])) {
        log_debug("This reseller is not configured for Paystack or missing secret key");
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
    $updateStmt = $portal_conn->prepare($updateQuery);

    if (!$updateStmt) {
        log_debug("Error preparing update query: " . $portal_conn->error);
        throw new Exception("Database update error");
    }

    $updateStmt->bind_param("s", $reference);
    $updateResult = $updateStmt->execute();

    if (!$updateResult) {
        log_debug("Error executing update query: " . $updateStmt->error);
        throw new Exception("Failed to update transaction status");
    }

    log_debug("Updated transaction status to completed");

    // Also record in mpesa_transactions table for compatibility
    $mpesaInsertQuery = "INSERT INTO mpesa_transactions
                         (checkout_request_id, merchant_request_id, amount, phone_number,
                          result_code, result_desc, transaction_id, reseller_id, package_id,
                          package_name, router_id, status, created_at)
                         VALUES (?, ?, ?, ?, 0, 'Payment completed via Paystack', ?, ?, ?, ?, ?, 'completed', NOW())";

    $mpesaStmt = $portal_conn->prepare($mpesaInsertQuery);
    if ($mpesaStmt) {
        $mpesaStmt->bind_param("ssdssiiis",
            $reference, // Use reference as checkout_request_id
            $reference, // Use reference as merchant_request_id
            $transaction['amount'],
            $transaction['phone_number'],
            $reference, // Use reference as transaction_id
            $transaction['reseller_id'],
            $transaction['package_id'],
            $transaction['package_name'],
            $transaction['router_id']
        );
        $mpesaResult = $mpesaStmt->execute();

        if ($mpesaResult) {
            log_debug("Transaction also recorded in mpesa_transactions table");
        } else {
            log_debug("Error recording in mpesa_transactions table: " . $mpesaStmt->error);
        }
    } else {
        log_debug("Error preparing mpesa insert query: " . $portal_conn->error);
    }

    // Fetch and assign voucher to customer
    require_once 'fetch_voucher.php';

    $voucher = getVoucherForPayment(
        $transaction['package_id'],
        $transaction['router_id'],
        $transaction['phone_number'],
        $reference
    );

    if ($voucher) {
        log_debug("Voucher assigned: " . $voucher['code']);

        // Store voucher details in session for success page
        $_SESSION['voucher_code'] = $voucher['code'];
        $_SESSION['voucher_username'] = $voucher['username'] ?: $voucher['code'];
        $_SESSION['voucher_password'] = $voucher['password'] ?: $voucher['code'];
        $_SESSION['package_name'] = $transaction['package_name'];
        $_SESSION['customer_phone'] = $transaction['phone_number'];

        // Send SMS with voucher details
        log_debug("=== INITIATING VOUCHER SMS DELIVERY ===");
        log_debug("Voucher details - Code: " . $voucher['code'] . ", Username: " . ($voucher['username'] ?: $voucher['code']) . ", Password: " . ($voucher['password'] ?: $voucher['code']));

        $smsResult = sendVoucherSMS(
            $transaction['phone_number'],
            $voucher['code'],
            $voucher['username'] ?: $voucher['code'],
            $voucher['password'] ?: $voucher['code'],
            $transaction['package_name'],
            $transaction['reseller_id']
        );

        log_debug("SMS function returned: " . json_encode($smsResult));

        if ($smsResult['success']) {
            log_debug("✅ SMS sent successfully to " . $transaction['phone_number']);
            $_SESSION['sms_sent'] = true;
        } else {
            log_debug("❌ SMS sending failed: " . $smsResult['message']);
            $_SESSION['sms_sent'] = false;
            $_SESSION['sms_error'] = $smsResult['message'];
        }

        // Set success message in session
        $_SESSION['payment_success'] = true;
        $_SESSION['payment_message'] = "Payment completed successfully! Your voucher has been sent to your phone.";

        // Redirect to success page
        header("Location: payment_success.php");
        exit;

    } else {
        log_debug("No voucher available for package ID: " . $transaction['package_id']);
        $_SESSION['payment_error'] = "Payment completed but no voucher available. Please contact support.";
        header("Location: portal.php?payment_error=true");
        exit;
    }
    
} catch (Exception $e) {
    log_debug("Error: " . $e->getMessage());
    $_SESSION['payment_error'] = "Payment verification failed: " . $e->getMessage();
    header("Location: portal.php?payment_error=true");
    exit;
}

/**
 * Send voucher SMS to customer
 *
 * @param string $phoneNumber Customer's phone number
 * @param string $voucherCode Voucher code
 * @param string $username Voucher username
 * @param string $password Voucher password
 * @param string $packageName Package name
 * @param int $resellerId Reseller ID
 * @return array Result with success status and message
 */
function sendVoucherSMS($phoneNumber, $voucherCode, $username, $password, $packageName, $resellerId) {
    global $portal_conn, $conn;

    log_debug("=== VOUCHER SMS SENDING STARTED ===");
    log_debug("Phone: $phoneNumber, Voucher: $voucherCode, Package: $packageName, Reseller: $resellerId");

    try {
        // Use the same connection approach as test_sms_send.php
        $db_connection = $conn ?: $portal_conn;

        // Get SMS settings for the reseller (same as test_sms_send.php)
        $smsSettings = getSmsSettings($db_connection, $resellerId);
        log_debug("SMS Settings retrieved: " . ($smsSettings ? 'Found' : 'Not found'));

        if (!$smsSettings) {
            log_debug("No SMS settings found for reseller $resellerId");
            return ['success' => false, 'message' => 'No SMS settings found for this reseller'];
        }

        if (!$smsSettings['enable_sms']) {
            log_debug("SMS is disabled for reseller $resellerId");
            return ['success' => false, 'message' => 'SMS is not enabled for this reseller'];
        }

        log_debug("SMS Provider: " . $smsSettings['sms_provider']);

        // Format phone number using the same method as test_sms_send.php
        $formattedPhone = formatPhoneForVoucherSMS($phoneNumber);
        log_debug("Phone formatted from $phoneNumber to $formattedPhone");

        // Prepare message using template
        $template = $smsSettings['payment_template'] ?: 'Thank you for your purchase of {package}. Your login credentials: Username: {username}, Password: {password}, Voucher: {voucher}';
        log_debug("SMS Template: $template");

        $message = str_replace(
            ['{package}', '{username}', '{password}', '{voucher}'],
            [$packageName, $username, $password, $voucherCode],
            $template
        );
        log_debug("SMS Message prepared: $message");

        // Send SMS based on provider (using working implementation from test_sms_send.php)
        log_debug("Sending SMS via provider: " . $smsSettings['sms_provider']);

        switch ($smsSettings['sms_provider']) {
            case 'textsms':
                $result = sendVoucherTextSMS($formattedPhone, $message, $smsSettings);
                log_debug("TextSMS result: " . json_encode($result));
                return $result;

            case 'africas-talking':
                $result = sendVoucherAfricaTalkingSMS($formattedPhone, $message, $smsSettings);
                log_debug("Africa's Talking result: " . json_encode($result));
                return $result;

            default:
                log_debug("Unsupported SMS provider: " . $smsSettings['sms_provider']);
                return ['success' => false, 'message' => 'Unsupported SMS provider: ' . $smsSettings['sms_provider']];
        }

    } catch (Exception $e) {
        log_debug("SMS sending exception: " . $e->getMessage());
        log_debug("Exception trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'SMS sending error: ' . $e->getMessage()];
    }
}

/**
 * Format phone number for Kenya (same as test_sms_send.php)
 */
function formatPhoneForVoucherSMS($phoneNumber) {
    // Remove any spaces, dashes, or plus signs
    $phone = preg_replace('/[\s\-\+]/', '', $phoneNumber);

    // If it starts with 0, replace with 254
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }

    // If it doesn't start with 254, assume it's a local number and add 254
    if (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }

    return $phone;
}

/**
 * Send SMS via TextSMS API (working implementation from test_sms_send.php)
 */
function sendVoucherTextSMS($phoneNumber, $message, $settings) {
    log_debug("Sending TextSMS to $phoneNumber");
    log_debug("API Key: " . (!empty($settings['textsms_api_key']) ? 'Set' : 'Not set'));
    log_debug("Partner ID: " . (!empty($settings['textsms_partner_id']) ? 'Set' : 'Not set'));

    $url = "https://sms.textsms.co.ke/api/services/sendsms/?" .
           "apikey=" . urlencode($settings['textsms_api_key']) .
           "&partnerID=" . urlencode($settings['textsms_partner_id']) .
           "&message=" . urlencode($message) .
           "&shortcode=" . urlencode($settings['textsms_sender_id']) .
           "&mobile=" . urlencode($phoneNumber);

    log_debug("TextSMS URL: " . $url);

    $response = @file_get_contents($url);

    if ($response === false) {
        log_debug("TextSMS connection failed");
        return ['success' => false, 'message' => 'Failed to connect to TextSMS API', 'details' => 'Connection failed'];
    }

    log_debug("TextSMS raw response: " . $response);

    // TextSMS returns various response formats, check for success indicators
    if (strpos($response, 'success') !== false || strpos($response, 'Success') !== false || is_numeric($response)) {
        log_debug("TextSMS success detected");
        return ['success' => true, 'message' => 'SMS sent successfully via TextSMS', 'response' => $response];
    } else {
        log_debug("TextSMS error detected");
        return ['success' => false, 'message' => 'TextSMS API error', 'response' => $response];
    }
}

/**
 * Send SMS via Africa's Talking API (working implementation from test_sms_send.php)
 */
function sendVoucherAfricaTalkingSMS($phoneNumber, $message, $settings) {
    log_debug("Sending Africa's Talking SMS to $phoneNumber");

    $url = 'https://api.africastalking.com/version1/messaging';

    $data = [
        'username' => $settings['at_username'],
        'to' => $phoneNumber,
        'message' => $message,
        'from' => $settings['at_shortcode'] ?: null
    ];

    $headers = [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'apiKey: ' . $settings['at_api_key']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_debug("Africa's Talking response: " . $response);
    log_debug("Africa's Talking HTTP code: " . $httpCode);

    if ($response === false || $httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to connect to Africa\'s Talking API', 'details' => "HTTP Code: $httpCode"];
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['SMSMessageData']['Recipients'][0]['status']) &&
        $responseData['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
        return ['success' => true, 'message' => 'SMS sent successfully via Africa\'s Talking', 'response' => $responseData];
    } else {
        $error = isset($responseData['SMSMessageData']['Recipients'][0]['status']) ?
                 $responseData['SMSMessageData']['Recipients'][0]['status'] : 'Unknown error';
        return ['success' => false, 'message' => 'Africa\'s Talking API error: ' . $error, 'response' => $responseData];
    }
}

/**
 * Send SMS via HostPinnacle API
 */
function sendHostPinnacleSMS($phoneNumber, $message, $settings) {
    // HostPinnacle implementation would go here
    // For now, return a placeholder
    return ['success' => false, 'message' => 'HostPinnacle SMS not implemented yet'];
}
?>