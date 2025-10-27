<?php
/**
 * Umeskia SMS Sender - Clean and Simple
 * 
 * This file provides a simple function to send SMS via Umeskia API
 * Based on the working code from sms api/umeskia_api.php
 */

// Umeskia API Configuration
define('UMESKIA_API_KEY', '7c973941a96b28fd910e19db909e7fda');
define('UMESKIA_APP_ID', 'UMSC631939');
define('UMESKIA_SENDER_ID', 'UMS_SMS');
define('UMESKIA_API_URL', 'https://comms.umeskiasoftwares.com/api/v1/sms/send');

/**
 * Send SMS via Umeskia API
 * 
 * @param string $phone_number Phone number (254xxxxxxxx or 07xxxxxxxx)
 * @param string $message SMS message to send
 * @return array Result with success status and details
 */
function sendUmeskiaSms($phone_number, $message) {
    // Format phone number - Umeskia expects 07xxxxxxxx format
    if (substr($phone_number, 0, 3) === '254') {
        $phone_number = '0' . substr($phone_number, 3);
    }
    
    // Initialize cURL
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => UMESKIA_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            "api_key" => UMESKIA_API_KEY,
            "app_id" => UMESKIA_APP_ID,
            "sender_id" => UMESKIA_SENDER_ID,
            "message" => $message,
            "phone" => $phone_number
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    // Handle cURL errors
    if ($err) {
        return [
            'success' => false,
            'message' => 'cURL Error: ' . $err,
            'phone' => $phone_number
        ];
    }
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if request was successful
    if ($httpCode === 200 && $responseData) {
        // Umeskia returns different response formats, check for success indicators
        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'phone' => $phone_number,
                'message_id' => $responseData['message_id'] ?? null,
                'response' => $responseData
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Umeskia Error: ' . ($responseData['message'] ?? 'Unknown error'),
                'phone' => $phone_number,
                'response' => $responseData
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'HTTP Error: ' . $httpCode . ' - ' . $response,
            'phone' => $phone_number
        ];
    }
}

/**
 * Create a professional voucher SMS message
 * 
 * @param string $voucher_code Voucher code
 * @param string $username Voucher username
 * @param string $password Voucher password
 * @param string $package_name Package name
 * @return string Formatted SMS message
 */
function createVoucherSmsMessage($voucher_code, $username, $password, $package_name) {
    $message = "🎉 Payment Successful!\n\n";
    $message .= "Your WiFi Voucher Details:\n";
    $message .= "📱 Code: $voucher_code\n";
    $message .= "👤 Username: $username\n";
    $message .= "🔐 Password: $password\n";
    $message .= "📦 Package: $package_name\n\n";
    $message .= "Connect to WiFi and use these details to access the internet.\n\n";
    $message .= "Thank you for your payment!";
    
    return $message;
}

/**
 * Log SMS activity to file
 * 
 * @param string $message Log message
 */
function logSmsActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents('umeskia_sms.log', $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Test function - can be called directly to test SMS sending
 */
if (isset($_GET['test']) && $_GET['test'] === '1') {
    $testPhone = $_GET['phone'] ?? '0750059353';
    $testMessage = $_GET['message'] ?? 'Test SMS from WiFi Hotspot System - Umeskia integration working!';
    
    echo "<h2>Testing Umeskia SMS</h2>";
    echo "<p><strong>Phone:</strong> $testPhone</p>";
    echo "<p><strong>Message:</strong> $testMessage</p>";
    
    $result = sendUmeskiaSms($testPhone, $testMessage);
    
    echo "<h3>Result:</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'><strong>✅ SMS sent successfully!</strong></p>";
        logSmsActivity("TEST SMS SUCCESS: Sent to $testPhone");
    } else {
        echo "<p style='color: red;'><strong>❌ SMS sending failed!</strong></p>";
        logSmsActivity("TEST SMS FAILED: " . $result['message']);
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Umeskia SMS Sender</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-form { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #059669; }
    </style>
</head>
<body>
    <h1>🚀 Umeskia SMS Sender</h1>
    
    <div class="test-form">
        <h3>📱 Test SMS Sending</h3>
        <form method="get">
            <input type="hidden" name="test" value="1">
            <label>Phone Number (07xxxxxxxx or 254xxxxxxxx):</label>
            <input type="tel" name="phone" value="0750059353" required>
            
            <label>Test Message:</label>
            <textarea name="message" rows="3" required>Test SMS from WiFi Hotspot System - Umeskia integration working!</textarea>
            
            <button type="submit">📤 Send Test SMS</button>
        </form>
    </div>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h4>📋 Configuration:</h4>
        <p><strong>API URL:</strong> <?php echo UMESKIA_API_URL; ?></p>
        <p><strong>App ID:</strong> <?php echo UMESKIA_APP_ID; ?></p>
        <p><strong>Sender ID:</strong> <?php echo UMESKIA_SENDER_ID; ?></p>
        <p><strong>Status:</strong> ✅ Ready</p>
    </div>
    
    <div style="background: #ecfdf5; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h4>🎯 Usage:</h4>
        <p><strong>Include this file:</strong> <code>require_once 'umeskia_sms.php';</code></p>
        <p><strong>Send SMS:</strong> <code>$result = sendUmeskiaSms($phone, $message);</code></p>
        <p><strong>Create voucher message:</strong> <code>$message = createVoucherSmsMessage($code, $user, $pass, $package);</code></p>
    </div>
</body>
</html>
