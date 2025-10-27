<?php
/**
 * SMS Test Tool - Simple SMS Sending Test
 * Enter phone number and message to test SMS delivery
 */

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Handle form submission
$smsResult = null;
$testPhone = '';
$testMessage = '';
$testResellerId = 1; // Default reseller ID

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testPhone = $_POST['phone_number'] ?? '';
    $testMessage = $_POST['message'] ?? '';
    $testResellerId = $_POST['reseller_id'] ?? 1;
    
    if (!empty($testPhone) && !empty($testMessage)) {
        // Get SMS settings
        $smsSettings = getSmsSettings($conn, $testResellerId);
        
        if ($smsSettings && $smsSettings['enable_sms']) {
            // Format phone number
            $formattedPhone = formatPhoneForSMS($testPhone);
            
            // Send SMS based on provider
            switch ($smsSettings['sms_provider']) {
                case 'textsms':
                    $smsResult = sendTestTextSMS($formattedPhone, $testMessage, $smsSettings);
                    break;
                    
                case 'africas-talking':
                    $smsResult = sendTestAfricaTalkingSMS($formattedPhone, $testMessage, $smsSettings);
                    break;
                    
                default:
                    $smsResult = ['success' => false, 'message' => 'Unsupported SMS provider: ' . $smsSettings['sms_provider']];
            }
        } else {
            $smsResult = ['success' => false, 'message' => 'SMS is not enabled or configured for this reseller'];
        }
    } else {
        $smsResult = ['success' => false, 'message' => 'Phone number and message are required'];
    }
}

// Helper functions
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

function sendTestTextSMS($phoneNumber, $message, $settings) {
    $url = "https://sms.textsms.co.ke/api/services/sendsms/?" .
           "apikey=" . urlencode($settings['textsms_api_key']) .
           "&partnerID=" . urlencode($settings['textsms_partner_id']) .
           "&message=" . urlencode($message) .
           "&shortcode=" . urlencode($settings['textsms_sender_id']) .
           "&mobile=" . urlencode($phoneNumber);

    $response = @file_get_contents($url);
    
    if ($response === false) {
        return ['success' => false, 'message' => 'Failed to connect to TextSMS API', 'details' => 'Connection failed'];
    }

    // TextSMS returns various response formats, check for success indicators
    if (strpos($response, 'success') !== false || strpos($response, 'Success') !== false || is_numeric($response)) {
        return ['success' => true, 'message' => 'SMS sent successfully via TextSMS', 'response' => $response];
    } else {
        return ['success' => false, 'message' => 'TextSMS API error', 'response' => $response];
    }
}

function sendTestAfricaTalkingSMS($phoneNumber, $message, $settings) {
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

// Get SMS settings for display
$smsSettings = getSmsSettings($conn, $testResellerId);
?>

<!DOCTYPE html>
<html>
<head>
    <title>SMS Test Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; }
        button { background-color: #007cba; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #005a87; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .settings { background-color: #fff3cd; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .code { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin-top: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h1>📱 SMS Test Tool</h1>
    
    <?php if ($smsSettings): ?>
        <div class="settings">
            <h3>📋 Current SMS Configuration</h3>
            <strong>Provider:</strong> <?php echo $smsSettings['sms_provider'] ?: 'Not set'; ?><br>
            <strong>Status:</strong> <?php echo $smsSettings['enable_sms'] ? '✅ Enabled' : '❌ Disabled'; ?><br>
            
            <?php if ($smsSettings['sms_provider'] === 'textsms'): ?>
                <strong>TextSMS API Key:</strong> <?php echo !empty($smsSettings['textsms_api_key']) ? '✅ Set' : '❌ Not set'; ?><br>
                <strong>TextSMS Partner ID:</strong> <?php echo !empty($smsSettings['textsms_partner_id']) ? '✅ Set' : '❌ Not set'; ?><br>
                <strong>Sender ID:</strong> <?php echo $smsSettings['textsms_sender_id'] ?: 'Not set'; ?><br>
            <?php elseif ($smsSettings['sms_provider'] === 'africas-talking'): ?>
                <strong>AT Username:</strong> <?php echo !empty($smsSettings['at_username']) ? '✅ Set' : '❌ Not set'; ?><br>
                <strong>AT API Key:</strong> <?php echo !empty($smsSettings['at_api_key']) ? '✅ Set' : '❌ Not set'; ?><br>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="result error">
            <strong>⚠️ No SMS Settings Found</strong><br>
            Please configure SMS settings first in Settings → SMS Settings
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="reseller_id">Reseller ID:</label>
            <input type="number" id="reseller_id" name="reseller_id" value="<?php echo htmlspecialchars($testResellerId); ?>" min="1">
        </div>
        
        <div class="form-group">
            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($testPhone); ?>" placeholder="0700123456 or 254700123456" required>
            <small>Enter phone number in format: 0700123456 or 254700123456</small>
        </div>
        
        <div class="form-group">
            <label for="message">Message:</label>
            <textarea id="message" name="message" placeholder="Enter your test message here..." required><?php echo htmlspecialchars($testMessage); ?></textarea>
        </div>
        
        <button type="submit">📤 Send Test SMS</button>
    </form>

    <?php if ($smsResult): ?>
        <div class="result <?php echo $smsResult['success'] ? 'success' : 'error'; ?>">
            <h3><?php echo $smsResult['success'] ? '✅ SMS Test Result: SUCCESS' : '❌ SMS Test Result: FAILED'; ?></h3>
            <strong>Message:</strong> <?php echo htmlspecialchars($smsResult['message']); ?><br>
            
            <?php if (isset($smsResult['response'])): ?>
                <strong>API Response:</strong>
                <div class="code"><?php echo htmlspecialchars(is_array($smsResult['response']) ? json_encode($smsResult['response'], JSON_PRETTY_PRINT) : $smsResult['response']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($smsResult['details'])): ?>
                <strong>Details:</strong> <?php echo htmlspecialchars($smsResult['details']); ?><br>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="info" style="margin-top: 20px;">
        <h3>💡 Testing Tips</h3>
        <ul>
            <li>Use your actual phone number to receive the test SMS</li>
            <li>Ensure your SMS provider account has sufficient balance</li>
            <li>Check that your SMS provider credentials are correct</li>
            <li>Phone numbers are automatically formatted to Kenya format (254XXXXXXXXX)</li>
            <li>If SMS fails, check the API response for specific error details</li>
        </ul>
    </div>
</div>

</body>
</html>
