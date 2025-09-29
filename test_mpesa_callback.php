<?php
/**
 * Simple M-Pesa Callback Testing Tool
 * This file helps test your M-Pesa callback handler without needing actual M-Pesa transactions
 */

// Start session for maintaining form data across submissions
session_start();

// Basic logging
function log_message($message) {
    $log_file = 'test_callback.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_message("Test script accessed from " . $_SERVER['REMOTE_ADDR']);

// Process form submission
$result_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_callback'])) {
    $callback_url = $_POST['callback_url'] ?? '';
    $checkout_id = $_POST['checkout_id'] ?? '';
    $merchant_id = $_POST['merchant_id'] ?? '';
    $result_code = $_POST['result_code'] ?? '0';
    $phone = $_POST['phone'] ?? '';
    $amount = $_POST['amount'] ?? '10';
    
    // Save for future form
    $_SESSION['callback_form'] = [
        'callback_url' => $callback_url,
        'checkout_id' => $checkout_id,
        'merchant_id' => $merchant_id,
        'result_code' => $result_code,
        'phone' => $phone,
        'amount' => $amount
    ];
    
    // Create callback data structure
    $callbackData = [];
    $callbackData['Body']['stkCallback']['MerchantRequestID'] = $merchant_id;
    $callbackData['Body']['stkCallback']['CheckoutRequestID'] = $checkout_id;
    $callbackData['Body']['stkCallback']['ResultCode'] = (int)$result_code;
    
    if ($result_code == '0') {
        $callbackData['Body']['stkCallback']['ResultDesc'] = 'The service request is processed successfully.';
        // Add metadata for successful transactions
        $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'] = [
            ['Name' => 'Amount', 'Value' => (float)$amount],
            ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST'.mt_rand(1000000, 9999999)],
            ['Name' => 'TransactionDate', 'Value' => date('YmdHis')],
            ['Name' => 'PhoneNumber', 'Value' => $phone]
        ];
    } else {
        $callbackData['Body']['stkCallback']['ResultDesc'] = 'Transaction failed or canceled by user';
    }
    
    // Send test callback
    $json_data = json_encode($callbackData);
    log_message("Sending test callback to $callback_url: $json_data");
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $callback_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        ]);
        
        $server_output = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        log_message("Response from callback: HTTP $http_status, Body: $server_output");
        $result_message = "Callback sent successfully! Response: HTTP $http_status, $server_output";
    } catch (Exception $e) {
        log_message("Error sending callback: " . $e->getMessage());
        $result_message = "Error sending callback: " . $e->getMessage();
    }
}

// Get form values from session or set defaults
$form_data = $_SESSION['callback_form'] ?? [
    'callback_url' => 'https://your-ngrok-id.ngrok-free.app/Wifi%20Billiling%20system/Admin/mpesa_callback.php',
    'checkout_id' => 'ws_CO_' . date('YmdHis') . mt_rand(1000, 9999),
    'merchant_id' => 'ws_MR_' . date('YmdHis') . mt_rand(1000, 9999),
    'result_code' => '0', 
    'phone' => '254700123456',
    'amount' => '10'
];

// Basic HTML style
$style = '
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #2874A6; }
    .container { max-width: 800px; margin: 0 auto; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input, select { width: 100%; padding: 8px; box-sizing: border-box; }
    button { background-color: #2874A6; color: white; padding: 10px 15px; border: none; cursor: pointer; }
    .result { margin-top: 20px; padding: 10px; background-color: #f0f0f0; border-left: 5px solid #2874A6; }
    .instructions { margin-top: 20px; background-color: #f9f9f9; padding: 15px; border: 1px solid #ddd; }
';
?>
<!DOCTYPE html>
<html>
<head>
    <title>M-Pesa Callback Test Tool</title>
    <style><?php echo $style; ?></style>
</head>
<body>
    <div class="container">
        <h1>M-Pesa Callback Test Tool</h1>
        <p>Use this simple tool to test your M-Pesa callback handler without making actual M-Pesa transactions.</p>
        
        <?php if ($result_message): ?>
            <div class="result">
                <strong>Result:</strong> <?php echo htmlspecialchars($result_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="callback_url">Callback URL:</label>
                <input type="text" id="callback_url" name="callback_url" value="<?php echo htmlspecialchars($form_data['callback_url']); ?>" required>
                <small>Your ngrok URL + /mpesa_callback.php</small>
            </div>
            
            <div class="form-group">
                <label for="checkout_id">Checkout Request ID:</label>
                <input type="text" id="checkout_id" name="checkout_id" value="<?php echo htmlspecialchars($form_data['checkout_id']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="merchant_id">Merchant Request ID:</label>
                <input type="text" id="merchant_id" name="merchant_id" value="<?php echo htmlspecialchars($form_data['merchant_id']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="result_code">Result Code:</label>
                <select id="result_code" name="result_code">
                    <option value="0" <?php echo $form_data['result_code'] == '0' ? 'selected' : ''; ?>>0 - Success</option>
                    <option value="1" <?php echo $form_data['result_code'] == '1' ? 'selected' : ''; ?>>1 - Insufficient Funds</option>
                    <option value="1032" <?php echo $form_data['result_code'] == '1032' ? 'selected' : ''; ?>>1032 - Cancelled by User</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number:</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="amount">Amount:</label>
                <input type="text" id="amount" name="amount" value="<?php echo htmlspecialchars($form_data['amount']); ?>" required>
            </div>
            
            <button type="submit" name="send_callback">Send Test Callback</button>
        </form>
        
        <div class="instructions">
            <h3>Instructions for testing with ngrok:</h3>
            <ol>
                <li>Start your ngrok tunnel with: <code>ngrok http 80</code></li>
                <li>Copy your ngrok URL (e.g. <code>https://abcd1234.ngrok-free.app</code>)</li>
                <li>Enter your full callback URL in the form above (e.g. <code>https://abcd1234.ngrok-free.app/Wifi%20Billiling%20system/Admin/mpesa_callback.php</code>)</li>
                <li>Fill in the rest of the form and click "Send Test Callback"</li>
                <li>Check your server logs in <code>mpesa_callback.log</code> to see if the callback was received</li>
            </ol>
        </div>
    </div>
</body>
</html>
