<?php
/**
 * Send M-Pesa Payment SMS
 * A standalone endpoint for sending SMS messages for M-Pesa payment vouchers using TextSMS API
 */

// Include required files
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Initialize debug log
$log_file = 'mpesa_sms_sending.log';
function log_sms($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_sms("===== M-PESA SMS SENDING INITIATED =====");

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_sms("Error: Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the required parameters
$phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
$voucher_code = isset($_POST['voucher_code']) ? $_POST['voucher_code'] : '';
$username = isset($_POST['username']) ? $_POST['username'] : $voucher_code;
$password = isset($_POST['password']) ? $_POST['password'] : $voucher_code;
$package_name = isset($_POST['package_name']) ? $_POST['package_name'] : 'WiFi Package';
$duration = isset($_POST['duration']) ? $_POST['duration'] : '';

log_sms("SMS request - Phone: $phone_number, Voucher: $voucher_code, Package: $package_name");

// Validate required parameters
if (empty($phone_number) || empty($voucher_code)) {
    log_sms("Error: Missing required parameters");
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Format phone number for TextSMS (254XXXXXXXXX format)
$formattedPhone = formatPhoneForSMS($phone_number);
log_sms("Formatted phone number: $formattedPhone");

// Prepare the message
$message = "Thank you for your payment! Your WiFi access details: Username: $username, Password: $password, Voucher: $voucher_code for $package_name";
log_sms("Message prepared: $message");

// Get SMS settings (assuming reseller ID 1 for now, can be made dynamic)
$resellerId = 1;
$smsSettings = getSmsSettings($portal_conn, $resellerId);

if (!$smsSettings || !$smsSettings['enable_sms']) {
    log_sms("Error: SMS not enabled or settings not found");
    echo json_encode(['success' => false, 'message' => 'SMS service not configured']);
    exit;
}

log_sms("SMS settings found - Provider: " . $smsSettings['sms_provider']);

// Send SMS based on provider
switch ($smsSettings['sms_provider']) {
    case 'textsms':
        $smsResult = sendMpesaTextSMS($formattedPhone, $message, $smsSettings);
        break;
    case 'africas-talking':
        $smsResult = sendMpesaAfricaTalkingSMS($formattedPhone, $message, $smsSettings);
        break;
    default:
        log_sms("Error: Unsupported SMS provider: " . $smsSettings['sms_provider']);
        echo json_encode(['success' => false, 'message' => 'Unsupported SMS provider']);
        exit;
}

// Log the SMS result
log_sms("SMS sending result: " . json_encode($smsResult));

// Return the result
if ($smsResult['success']) {
    log_sms("SMS sent successfully to $phone_number");
    echo json_encode([
        'success' => true,
        'message' => 'Success! We have sent a message to ' . $phone_number,
        'phone' => $formattedPhone,
        'provider' => $smsSettings['sms_provider']
    ]);
} else {
    log_sms("SMS sending failed: " . $smsResult['message']);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send SMS: ' . $smsResult['message'],
        'error' => $smsResult['message']
    ]);
}

/**
 * Format phone number for SMS (254XXXXXXXXX format)
 */
function formatPhoneForSMS($phoneNumber) {
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
 * Send SMS via TextSMS API (working implementation)
 */
function sendMpesaTextSMS($phoneNumber, $message, $settings) {
    log_sms("Sending TextSMS to $phoneNumber");
    log_sms("API Key: " . (!empty($settings['textsms_api_key']) ? 'Set' : 'Not set'));
    log_sms("Partner ID: " . (!empty($settings['textsms_partner_id']) ? 'Set' : 'Not set'));

    $url = "https://sms.textsms.co.ke/api/services/sendsms/?" .
           "apikey=" . urlencode($settings['textsms_api_key']) .
           "&partnerID=" . urlencode($settings['textsms_partner_id']) .
           "&message=" . urlencode($message) .
           "&shortcode=" . urlencode($settings['textsms_sender_id']) .
           "&mobile=" . urlencode($phoneNumber);

    log_sms("TextSMS URL: " . $url);

    $response = @file_get_contents($url);

    if ($response === false) {
        log_sms("TextSMS connection failed");
        return ['success' => false, 'message' => 'Failed to connect to TextSMS API'];
    }

    log_sms("TextSMS raw response: " . $response);

    // TextSMS returns various response formats, check for success indicators
    if (strpos($response, 'success') !== false || strpos($response, 'Success') !== false || is_numeric($response)) {
        log_sms("TextSMS success detected");
        return ['success' => true, 'message' => 'SMS sent successfully via TextSMS', 'response' => $response];
    } else {
        log_sms("TextSMS error detected");
        return ['success' => false, 'message' => 'TextSMS API error', 'response' => $response];
    }
}

/**
 * Send SMS via Africa's Talking API (working implementation)
 */
function sendMpesaAfricaTalkingSMS($phoneNumber, $message, $settings) {
    log_sms("Sending Africa's Talking SMS to $phoneNumber");

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

    log_sms("Africa's Talking response: " . $response);
    log_sms("Africa's Talking HTTP code: " . $httpCode);

    if ($response === false || $httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to connect to Africa\'s Talking API'];
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