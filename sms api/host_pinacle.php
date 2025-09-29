<?php

/**
 * SMS API Integration with Hostpinnacle
 * 
 * This file provides functions to send SMS messages using Hostpinnacle SMS provider
 */

/**
 * Send SMS message via Hostpinnacle API
 * 
 * @param string $phone_number Phone number to send SMS to (format: 07xxxxxxxx)
 * @param string $message The message to send
 * @param string $userid Your Hostpinnacle user ID
 * @param string $password Your Hostpinnacle password
 * @param string $sender_id Your approved sender ID
 * @return array Response from the API and any error information
 */
function send_sms($phone_number, $message, $userid = "qtro", $password = "xxxxx", $sender_id = "SENDER") {
    // Format phone number to international format (254xxxxxxxxx)
    if (substr($phone_number, 0, 1) === '0') {
        $phone_number = '254' . substr($phone_number, 1);
    }
    
    // URL encode the message
    $encoded_message = urlencode($message);
    
    $curl = curl_init();
    
    $api_url = "https://smsportal.hostpinnacle.co.ke/SMSApi/send";
    $params = "?userid={$userid}" .
             "&password={$password}" .
             "&mobile={$phone_number}" .
             "&msg={$encoded_message}" .
             "&senderid={$sender_id}" .
             "&msgType=text" .
             "&duplicatecheck=true" .
             "&output=json" .
             "&sendMethod=quick";
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $api_url . $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache"
        ),
    ));
    
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
$default_message = 'Hello! This is a test message from your website SMS system.';
$default_userid = 'qtro';
$default_password = 'xxxxx';
$default_sender = 'SENDER';

// Check if form is submitted
$result = null;
$is_submitted = isset($_POST['submit']);

if ($is_submitted) {
    // Get values from form
    $test_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : $default_phone;
    $test_message = isset($_POST['message']) ? $_POST['message'] : $default_message;
    $test_userid = isset($_POST['userid']) ? $_POST['userid'] : $default_userid;
    $test_password = isset($_POST['password']) ? $_POST['password'] : $default_password;
    $test_sender = isset($_POST['sender_id']) ? $_POST['sender_id'] : $default_sender;
    
    // Send SMS with provided parameters
    $result = send_sms($test_number, $test_message, $test_userid, $test_password, $test_sender);
}

// HTML for the form and results
?>
<!DOCTYPE html>
<html>
<head>
    <title>SMS API Test Tool</title>
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
        }
        button:hover {
            background-color: #45a049;
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
    </style>
</head>
<body>
    <h1>SMS API Test Tool</h1>
    <p>Use this form to test the Hostpinnacle SMS API integration.</p>
    
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
            <label for="userid">User ID:</label>
            <input type="text" id="userid" name="userid" value="<?php echo $default_userid; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="text" id="password" name="password" value="<?php echo $default_password; ?>" required>
        </div>
        
        <div class="form-group">
            <label for="sender_id">Sender ID:</label>
            <input type="text" id="sender_id" name="sender_id" value="<?php echo $default_sender; ?>" required>
        </div>
        
        <button type="submit" name="submit">Send Test SMS</button>
    </form>
    
    <?php if ($is_submitted): ?>
    <div class="response">
        <h2>SMS Test Results:</h2>
        <p><strong>Sending to:</strong> <?php echo htmlspecialchars($_POST['phone_number']); ?></p>
        <p><strong>Message:</strong> <?php echo htmlspecialchars($_POST['message']); ?></p>
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($_POST['userid']); ?></p>
        <p><strong>Sender ID:</strong> <?php echo htmlspecialchars($_POST['sender_id']); ?></p>
        
        <?php if ($result['error']): ?>
            <p class="error"><strong>Error:</strong> <?php echo htmlspecialchars($result['error']); ?></p>
        <?php else: ?>
            <p class="success"><strong>API Response:</strong></p>
            <pre><?php print_r(json_decode($result['response'], true)); ?></pre>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
