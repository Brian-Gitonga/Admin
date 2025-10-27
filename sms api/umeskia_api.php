<?php

/**
 * SMS API Integration with Umeskia Softwares
 * 
 * This file provides functions to send SMS messages using Umeskia SMS provider
 * Documentation: https://comms.umeskiasoftwares.com/doccumetation#introduction
 */

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
function send_sms($phone_number, $message, $api_key, $app_id, $sender_id = "UMS_TX") {
    // Format phone number - ensure it begins with 0 for Kenyan numbers
    if (substr($phone_number, 0, 3) === '254') {
        $phone_number = '0' . substr($phone_number, 3);
    }
    
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
 * Check SMS balance via Umeskia API
 * 
 * @param string $api_key Your Umeskia API key
 * @param string $app_id Your Umeskia App ID
 * @return array Response from the API and any error information
 */
function check_sms_balance($api_key, $app_id) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://comms.umeskiasoftwares.com/api/v1/check-sms-balance?api_key={$api_key}&app_id={$app_id}",
        CURLOPT_RETURNTRANSFER => true,
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    return [
        'response' => $response,
        'error' => $err
    ];
}

// Default values
$default_phone = '0750059353';
$default_message = 'Hello! This is a test message from your website using Umeskia SMS API.';
$default_api_key = '7c973941a96b28fd910e19db909e7fda';
$default_app_id = 'UMSC631939';
$default_sender = 'UMS_SMS'; // Default sender ID for transactional SMS

// Handle form submission
$result = null;
$balance_result = null;
$is_submitted = isset($_POST['submit']);
$check_balance = isset($_POST['check_balance']);

if ($is_submitted) {
    // Get values from form
    $test_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : $default_phone;
    $test_message = isset($_POST['message']) ? $_POST['message'] : $default_message;
    $test_api_key = isset($_POST['api_key']) ? $_POST['api_key'] : $default_api_key;
    $test_app_id = isset($_POST['app_id']) ? $_POST['app_id'] : $default_app_id;
    $test_sender = isset($_POST['sender_id']) ? $_POST['sender_id'] : $default_sender;
    
    // Send SMS with provided parameters
    $result = send_sms($test_number, $test_message, $test_api_key, $test_app_id, $test_sender);
}

if ($check_balance) {
    // Get API credentials
    $test_api_key = isset($_POST['api_key']) ? $_POST['api_key'] : $default_api_key;
    $test_app_id = isset($_POST['app_id']) ? $_POST['app_id'] : $default_app_id;
    
    // Check SMS balance
    $balance_result = check_sms_balance($test_api_key, $test_app_id);
}

// HTML for the form and results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Umeskia SMS API Test Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        .button-secondary {
            background-color: #008CBA;
        }
        .button-secondary:hover {
            background-color: #007095;
        }
        .response {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 15px;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
        }
        .tab.active {
            background-color: #fff;
            border-bottom: 1px solid #fff;
        }
        .tab-content {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 0 4px 4px 4px;
        }
    </style>
</head>
<body>
    <h1>Umeskia SMS API Test Tool</h1>
    <p>Use this form to test the Umeskia SMS API integration.</p>
    
    <div class="tabs">
        <div class="tab active" onclick="openTab(event, 'sendSMS')">Send SMS</div>
        <div class="tab" onclick="openTab(event, 'checkBalance')">Check Balance</div>
    </div>
    
    <div id="sendSMS" class="tab-content" style="display: block;">
        <form method="post" action="">
            <div class="form-group">
                <label for="phone_number">Phone Number (format: 07xxxxxxxx):</label>
                <input type="text" id="phone_number" name="phone_number" value="<?php echo $default_phone; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="4" required><?php echo $default_message; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="api_key">API Key:</label>
                <input type="text" id="api_key" name="api_key" value="<?php echo $default_api_key; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="app_id">App ID:</label>
                <input type="text" id="app_id" name="app_id" value="<?php echo $default_app_id; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="sender_id">Sender ID:</label>
                <input type="text" id="sender_id" name="sender_id" value="<?php echo $default_sender; ?>" required>
                <small>Default: UMS_TX (for transactional) or UMS_SMS (for promotional)</small>
            </div>
            
            <button type="submit" name="submit">Send Test SMS</button>
        </form>
        
        <?php if ($is_submitted): ?>
        <div class="response">
            <h2>SMS Test Results:</h2>
            <p><strong>Sending to:</strong> <?php echo htmlspecialchars($_POST['phone_number']); ?></p>
            <p><strong>Message:</strong> <?php echo htmlspecialchars($_POST['message']); ?></p>
            
            <?php if ($result['error']): ?>
                <p class="error"><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></p>
            <?php else: ?>
                <p class="success"><strong>API Response:</strong></p>
                <pre><?php print_r(json_decode($result['response'], true)); ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="checkBalance" class="tab-content" style="display: none;">
        <form method="post" action="">
            <div class="form-group">
                <label for="api_key_balance">API Key:</label>
                <input type="text" id="api_key_balance" name="api_key" value="<?php echo $default_api_key; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="app_id_balance">App ID:</label>
                <input type="text" id="app_id_balance" name="app_id" value="<?php echo $default_app_id; ?>" required>
            </div>
            
            <button type="submit" name="check_balance" class="button-secondary">Check SMS Balance</button>
        </form>
        
        <?php if ($check_balance): ?>
        <div class="response">
            <h2>SMS Balance Check Results:</h2>
            
            <?php if ($balance_result['error']): ?>
                <p class="error"><strong>Error:</strong> <?php echo htmlspecialchars($balance_result['error']); ?></p>
            <?php else: ?>
                <p class="success"><strong>API Response:</strong></p>
                <pre><?php print_r(json_decode($balance_result['response'], true)); ?></pre>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Hide all tab content
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            
            // Remove "active" class from all tabs
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            
            // Show the current tab and add "active" class
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html> 