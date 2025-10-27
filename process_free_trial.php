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
    
    // Include SMS settings operations to use the same SMS system as paid packages
    require_once 'sms_settings_operations.php';

    // Set up the SMS data
    $smsSent = false;

    // Send SMS using the same system as paid packages
    error_log("=== INITIATING FREE TRIAL SMS DELIVERY ===");
    error_log("Voucher details - Code: $voucher_code, Username: $voucher_username, Password: $voucher_password");

    try {
        $smsResult = sendFreeTrialVoucherSMS(
            $phone_number,
            $voucher_code,
            $voucher_username,
            $voucher_password,
            $package['name'],
            $reseller_id
        );

        // Log the SMS result
        error_log("Free Trial SMS function returned: " . json_encode($smsResult));

        if ($smsResult['success']) {
            $smsSent = true;
            error_log("✅ Free trial SMS sent successfully to $phone_number");
        } else {
            error_log("❌ Free trial SMS sending failed: " . $smsResult['message']);
            // Don't fail the entire process if SMS fails, just log it
            $smsSent = false;
        }
    } catch (Exception $smsException) {
        error_log("❌ Free trial SMS exception: " . $smsException->getMessage());
        error_log("Exception trace: " . $smsException->getTraceAsString());
        $smsSent = false;
        // Continue with the process even if SMS fails
    }
    
    $conn->commit();
    
    // Return success with appropriate message based on SMS status
    $message = $smsSent ?
        'Success! We have sent your voucher to ' . $phone_number :
        'Success! Your free trial voucher has been generated. Voucher: ' . $voucher_code;

    echo json_encode([
        'success' => true,
        'message' => $message,
        'sms_sent' => $smsSent,
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

/**
 * Send free trial voucher SMS using the same system as paid packages
 */
function sendFreeTrialVoucherSMS($phoneNumber, $voucherCode, $username, $password, $packageName, $resellerId) {
    global $conn, $portal_conn;

    // Use portal_conn if available, fallback to conn
    $db_conn = $portal_conn ?: $conn;

    try {
        error_log("=== FREE TRIAL SMS SENDING STARTED ===");
        error_log("Phone: $phoneNumber, Voucher: $voucherCode, Package: $packageName, Reseller: $resellerId");

        // Get SMS settings for the reseller
        $smsSettings = getSmsSettings($db_conn, $resellerId);
        error_log("SMS Settings retrieved: " . ($smsSettings ? 'Found' : 'Not found'));

        if (!$smsSettings) {
            error_log("No SMS settings found for reseller $resellerId");
            return ['success' => false, 'message' => 'No SMS settings found for this reseller'];
        }

        if (!$smsSettings['enable_sms']) {
            error_log("SMS is disabled for reseller $resellerId");
            return ['success' => false, 'message' => 'SMS is not enabled for this reseller'];
        }

        error_log("SMS Provider: " . $smsSettings['sms_provider']);

        // Format phone number for Kenya (ensure it starts with 254)
        $formattedPhone = formatPhoneNumberForSMS($phoneNumber);
        error_log("Phone formatted from $phoneNumber to $formattedPhone");

        // Prepare message using template
        $template = $smsSettings['payment_template'] ?: 'Thank you for your free trial of {package}. Your login credentials: Username: {username}, Password: {password}, Voucher: {voucher}';
        error_log("SMS Template: $template");

        $message = str_replace(
            ['{package}', '{username}', '{password}', '{voucher}'],
            [$packageName, $username, $password, $voucherCode],
            $template
        );
        error_log("SMS Message prepared: $message");

        // Send SMS based on provider
        error_log("Sending SMS via provider: " . $smsSettings['sms_provider']);

        switch ($smsSettings['sms_provider']) {
            case 'textsms':
                $result = sendTextSMSForFreeTrial($formattedPhone, $message, $smsSettings);
                error_log("TextSMS result: " . json_encode($result));
                return $result;

            case 'africas-talking':
                $result = sendAfricaTalkingSMSForFreeTrial($formattedPhone, $message, $smsSettings);
                error_log("Africa's Talking result: " . json_encode($result));
                return $result;

            case 'hostpinnacle':
                $result = sendHostPinnacleSMSForFreeTrial($formattedPhone, $message, $smsSettings);
                error_log("HostPinnacle result: " . json_encode($result));
                return $result;

            default:
                error_log("Unsupported SMS provider: " . $smsSettings['sms_provider']);
                return ['success' => false, 'message' => 'Unsupported SMS provider: ' . $smsSettings['sms_provider']];
        }

    } catch (Exception $e) {
        error_log("Free trial SMS sending exception: " . $e->getMessage());
        error_log("Exception trace: " . $e->getTraceAsString());
        return ['success' => false, 'message' => 'SMS sending error: ' . $e->getMessage()];
    }
}

/**
 * Format phone number for SMS (Kenya format)
 */
function formatPhoneNumberForSMS($phoneNumber) {
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
 * Send SMS via TextSMS API for free trial
 */
function sendTextSMSForFreeTrial($phoneNumber, $message, $settings) {
    $url = "https://sms.textsms.co.ke/api/services/sendsms/?" .
           "apikey=" . urlencode($settings['textsms_api_key']) .
           "&partnerID=" . urlencode($settings['textsms_partner_id']) .
           "&message=" . urlencode($message) .
           "&shortcode=" . urlencode($settings['textsms_sender_id']) .
           "&mobile=" . urlencode($phoneNumber);

    $response = @file_get_contents($url);

    if ($response === false) {
        return ['success' => false, 'message' => 'Failed to connect to TextSMS API'];
    }

    // TextSMS returns various response formats, check for success indicators
    if (strpos($response, 'success') !== false || strpos($response, 'Success') !== false || is_numeric($response)) {
        return ['success' => true, 'message' => 'SMS sent successfully via TextSMS', 'response' => $response];
    } else {
        return ['success' => false, 'message' => 'TextSMS API error: ' . $response];
    }
}

/**
 * Send SMS via Africa's Talking API for free trial
 */
function sendAfricaTalkingSMSForFreeTrial($phoneNumber, $message, $settings) {
    // Africa's Talking implementation
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

    if ($response === false || $httpCode !== 200) {
        return ['success' => false, 'message' => 'Failed to connect to Africa\'s Talking API'];
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['SMSMessageData']['Recipients'][0]['status']) &&
        $responseData['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
        return ['success' => true, 'message' => 'SMS sent successfully via Africa\'s Talking'];
    } else {
        $error = isset($responseData['SMSMessageData']['Recipients'][0]['status']) ?
                 $responseData['SMSMessageData']['Recipients'][0]['status'] : 'Unknown error';
        return ['success' => false, 'message' => 'Africa\'s Talking API error: ' . $error];
    }
}

/**
 * Send SMS via HostPinnacle API for free trial (placeholder)
 */
function sendHostPinnacleSMSForFreeTrial($phoneNumber, $message, $settings) {
    // HostPinnacle implementation would go here
    return ['success' => false, 'message' => 'HostPinnacle SMS provider not yet implemented'];
}