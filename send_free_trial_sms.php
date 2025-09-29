<?php
/**
 * Send Free Trial SMS
 * A standalone endpoint for sending SMS messages for free trial vouchers using Umeskia API
 */

// Log the access
error_log("send_free_trial_sms.php accessed");

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get the required parameters
$phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
$voucher_code = isset($_POST['voucher_code']) ? $_POST['voucher_code'] : '';
$username = isset($_POST['username']) ? $_POST['username'] : $voucher_code;
$password = isset($_POST['password']) ? $_POST['password'] : $voucher_code;
$package_name = isset($_POST['package_name']) ? $_POST['package_name'] : 'Free Trial';
$duration = isset($_POST['duration']) ? $_POST['duration'] : '';

// Validate required parameters
if (empty($phone_number) || empty($voucher_code)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Format phone number for Umeskia - needs to be in format 07XXXXXXXX (with leading 0)
$formattedPhone = formatPhoneNumberForUmeskia($phone_number);

// Log the request details
error_log("Sending free trial SMS to $formattedPhone with voucher: $voucher_code");

// Prepare the message
$message = "Your have sucessfully bought $package_name plan. Your username is: $username, Password: $password and voucher is: $voucher_code";

// Umeskia API credentials
$apiKey = 'eadad3b302940dd8c2f58e1289c3701f';
$appId = 'UMSC617032';
$senderId = 'UMS_SMS';

// Send SMS using Umeskia API
$smsResult = send_sms_umeskia($formattedPhone, $message, $apiKey, $appId, $senderId);

// Log the API response
error_log("Umeskia SMS API Response for $formattedPhone: " . print_r($smsResult, true));

// Process the response
if (!$smsResult['error']) {
    // Try to decode the JSON response
    $responseData = json_decode($smsResult['response'], true);
    
    // Check if the API returned success
    if ($responseData && isset($responseData['status']) && $responseData['status'] === true) {
        echo json_encode([
            'success' => true, 
            'message' => 'Success! We have sent a message to ' . $phone_number,
            '_message_id' => isset($responseData['message_id']) ? $responseData['message_id'] : 'N/A',
            '_phone' => $formattedPhone
        ]);
    } else {
        $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
        
        error_log("Umeskia SMS API returned error: " . $errorMsg);
        
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred while processing your request. Please try again later.',
            '_error' => $errorMsg,
            '_response' => $responseData
        ]);
    }
} else {
    error_log("Umeskia SMS API call failed. Error: " . $smsResult['error']);
    
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while processing your request. Please try again later.',
        '_error' => $smsResult['error']
    ]);
}

/**
 * Format a phone number for Umeskia (07XXXXXXXX)
 * 
 * @param string $phoneNumber Phone number to format
 * @return string Formatted phone number
 */
function formatPhoneNumberForUmeskia($phoneNumber) {
    // Strip any non-numeric characters
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // If it starts with 254, replace with 0
    if (substr($phoneNumber, 0, 3) === '254') {
        $phoneNumber = '0' . substr($phoneNumber, 3);
    }
    
    // If it's a 9-digit number (without country code), add leading 0
    if (strlen($phoneNumber) === 9 && (substr($phoneNumber, 0, 1) === '7' || substr($phoneNumber, 0, 1) === '1')) {
        $phoneNumber = '0' . $phoneNumber;
    }
    
    return $phoneNumber;
}

/**
 * Send SMS message via Umeskia API
 * 
 * @param string $phone_number Phone number to send SMS to (format: 07xxxxxxxx)
 * @param string $message The message to send
 * @param string $api_key Your Umeskia API key
 * @param string $app_id Your Umeskia App ID
 * @param string $sender_id Your approved sender ID
 * @return array Response from the API and any error information
 */
function send_sms_umeskia($phone_number, $message, $api_key, $app_id, $sender_id = "UMS_TX") {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://comms.umeskiasoftwares.com/api/v1/sms/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            "api_key" => $api_key,
            "app_id" => $app_id,
            "sender_id" => $sender_id,
            "message" => $message,
            "phone" => $phone_number
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    return [
        'response' => $response,
        'error' => $err
    ];
} 