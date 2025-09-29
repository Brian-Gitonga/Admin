<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms api/textsms_api.php'; // Include the TextSMS API class

// Get parameters from POST request
$phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : '';
$terms_agree = isset($_POST['terms_agree']) && $_POST['terms_agree'] === '1';
$mac_address = isset($_POST['mac_address']) ? $_POST['mac_address'] : '';
$ip_address = isset($_POST['ip_address']) ? $_POST['ip_address'] : $_SERVER['REMOTE_ADDR'];
$reseller_id = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 0;
$package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;

// Get hotspot settings
$hotspotSettings = [];
if ($reseller_id > 0) {
    $query = "SELECT * FROM hotspot_settings WHERE reseller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $hotspotSettings = $result->fetch_assoc();
    } else {
        // Default settings if none found
        $hotspotSettings = [
            'free_trial_limit' => 1,
            'free_trial_package' => $package_id
        ];
    }
} else {
    // Default settings if no reseller_id
    $hotspotSettings = [
        'free_trial_limit' => 1,
        'free_trial_package' => $package_id
    ];
}

// Check if phone number is provided
if (empty($phone_number)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required for free trial']);
    exit;
}

// Validate terms agreement
if (!$terms_agree) {
    echo json_encode(['success' => false, 'message' => 'You must agree to the terms and conditions']);
    exit;
}

// Check if the user has already used the free trial by phone number
$query = "SELECT usage_count FROM free_trial_usage WHERE reseller_id = ? AND phone_number = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("is", $reseller_id, $phone_number);
$stmt->execute();
$result = $stmt->get_result();

$max_uses = $hotspotSettings['free_trial_limit'];
$current_uses = 0;

if ($result->num_rows > 0) {
    $usage = $result->fetch_assoc();
    $current_uses = $usage['usage_count'];
    
    // Check if user has reached the limit
    if ($current_uses >= $max_uses) {
        echo json_encode([
            'success' => false, 
            'message' => 'You have already used your free trial ' . $current_uses . ' time(s) with this phone number. Maximum allowed is ' . $max_uses . ' time(s).'
        ]);
        exit;
    }
}

try {
    // Use the package_id from the form if provided, otherwise use the one from settings
    $package_id = $package_id > 0 ? $package_id : $hotspotSettings['free_trial_package'];
    
    // First, get package details to make sure it exists
    $query = "SELECT * FROM packages WHERE id = ? AND reseller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $package_id, $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Selected free trial package not found");
    }
    
    $package = $result->fetch_assoc();
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Get an unused voucher for this package and reseller
    $query = "SELECT id, code, username, password FROM vouchers 
              WHERE package_id = ? AND reseller_id = ? AND status = 'active' 
              ORDER BY id ASC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $package_id, $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No available vouchers found for this free trial package");
    }
    
    $voucher = $result->fetch_assoc();
    $voucher_id = $voucher['id'];
    $voucher_code = $voucher['code'];
    $voucher_username = $voucher['username'] ?? $voucher_code;
    $voucher_password = $voucher['password'] ?? $voucher_code;
    
    // Update the voucher to mark it as used
    $query = "UPDATE vouchers SET 
              status = 'used', 
              customer_phone = ?, 
              used_at = NOW() 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $phone_number, $voucher_id);
    $stmt->execute();
    
    // Record the free trial usage - now tracking primarily by phone number
    if ($current_uses > 0) {
        // Update existing record
        $query = "UPDATE free_trial_usage SET 
            usage_count = usage_count + 1, 
            mac_address = ?, 
            voucher_code = ?,
            last_usage_at = NOW() 
            WHERE reseller_id = ? AND phone_number = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssis", $mac_address, $voucher_code, $reseller_id, $phone_number);
    } else {
        // Create new record
        $query = "INSERT INTO free_trial_usage 
            (reseller_id, mac_address, ip_address, phone_number, voucher_code, usage_count) 
            VALUES (?, ?, ?, ?, ?, 1)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issss", $reseller_id, $mac_address, $ip_address, $phone_number, $voucher_code);
    }
    
    $stmt->execute();
    
    // Set up the SMS data
    $smsSent = false;
    
    // Prepare the SMS message
    $message = "Your have sucessfully bought {$package['name']} plan. Your username is: {$voucher_username}, Password: {$voucher_password} and voucher is: {$voucher_code}";
    
    // Format phone number for Umeskia - needs to be in format 07XXXXXXXX (with leading 0)
    $formattedPhone = formatPhoneNumberForUmeskia($phone_number);
    
    // Umeskia API credentials
    $apiKey = 'eadad3b302940dd8c2f58e1289c3701f';
    $appId = 'UMSC617032';
    $senderId = 'UMS_TX';
    
    // Send SMS using Umeskia API
    $smsResult = send_sms_umeskia($formattedPhone, $message, $apiKey, $appId, $senderId);
    
    // Log the API response
    error_log("Umeskia SMS API Response for $formattedPhone: " . print_r($smsResult, true));
    
    if (!$smsResult['error']) {
        // Try to decode the JSON response
        $responseData = json_decode($smsResult['response'], true);
        
        // Check if the API returned success
        if ($responseData && isset($responseData['status']) && $responseData['status'] === true) {
            $smsSent = true;
            error_log("SMS sent successfully to $formattedPhone via Umeskia");
        } else {
            $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
            error_log("Umeskia SMS API returned error: " . $errorMsg);
        }
    } else {
        error_log("Umeskia SMS API call failed. Error: " . $smsResult['error']);
    }
    
    $conn->commit();
    
    // Return success with a simpler message, hiding voucher details
    echo json_encode([
        'success' => true, 
        'message' => 'Success! We have sent a message to ' . $phone_number,
        'sms_sent' => true, // Always set to true since we want to hide the details regardless
        // The following fields are only for backend reference and should be hidden in UI
        '_voucher' => $voucher_code,
        '_username' => $voucher_username,
        '_password' => $voucher_password,
        '_package' => $package['name'],
        '_duration' => $package['duration']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    error_log("Error processing free trial: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request. Please try again later.']);
}

/**
 * Format a phone number to international format (254XXXXXXX)
 * 
 * @param string $phoneNumber Phone number to format
 * @return string Formatted phone number
 */
function formatPhoneNumber($phoneNumber) {
    // Strip any non-numeric characters
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // If it starts with 0, replace with 254 (Kenya country code)
    if (substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '254' . substr($phoneNumber, 1);
    }
    
    // If it doesn't have country code and is Kenyan number (starts with 7 or 1), add 254
    if (strlen($phoneNumber) === 9 && (substr($phoneNumber, 0, 1) === '7' || substr($phoneNumber, 0, 1) === '1')) {
        $phoneNumber = '254' . $phoneNumber;
    }
    
    return $phoneNumber;
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