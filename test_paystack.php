<?php
/**
 * Paystack API Test Script
 * A simple standalone script to test Paystack API connectivity
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Paystack API credentials from Paystack_API.md
$secret_key = 'sk_live_3e881579ac151896d523fa7c1e47f2c2df264400';
$public_key = 'pk_live_21eb33f8487ac36f06c777662780e4fcfb42e32e';

// Function to test the Paystack API
function testPaystackAPI($secret_key) {
    echo "<h2>Testing Paystack API Connection</h2>";
    
    // Try to fetch transaction list (simple endpoint to test connectivity)
    $url = "https://api.paystack.co/transaction";
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secret_key,
        "Cache-Control: no-cache"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>Connection Error:</strong> " . htmlspecialchars($error);
        echo "</div>";
        return false;
    }
    
    curl_close($ch);
    
    // Output the response
    echo "<div style='padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    echo "<strong>HTTP Status Code:</strong> " . htmlspecialchars($httpCode) . "<br>";
    
    // Pretty format the response
    $formatted_response = json_encode(json_decode($response), JSON_PRETTY_PRINT);
    
    echo "<strong>Response:</strong> <pre>" . htmlspecialchars(substr($formatted_response, 0, 500)) . "...</pre>";
    echo "</div>";
    
    // Determine if the connection was successful
    $response_data = json_decode($response, true);
    if ($httpCode == 200 && isset($response_data['status']) && $response_data['status']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "<strong>Connection Successful!</strong> The Paystack API is responding correctly.";
        echo "</div>";
        return true;
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>Connection Failed:</strong> The API returned an error or unexpected response. Check your API key.";
        if (isset($response_data['message'])) {
            echo "<br>Error Message: " . htmlspecialchars($response_data['message']);
        }
        echo "</div>";
        return false;
    }
}

// Function to test creating a transaction
function testCreateTransaction($secret_key) {
    echo "<h2>Testing Transaction Initialization</h2>";
    
    // Create a test transaction
    $url = "https://api.paystack.co/transaction/initialize";
    
    // Test data for the transaction
    $fields = [
        'email' => 'test@example.com',
        'amount' => 50000, // 500 NGN in kobo
        'reference' => 'TEST_' . time(),
        'callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/SAAS/Wifi%20Billiling%20system/Admin/test_paystack_callback.php',
    ];
    
    // Encode fields as JSON
    $fields_json = json_encode($fields);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $secret_key,
        "Cache-Control: no-cache",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>Transaction Error:</strong> " . htmlspecialchars($error);
        echo "</div>";
        return false;
    }
    
    curl_close($ch);
    
    // Output the response
    echo "<div style='padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
    echo "<strong>HTTP Status Code:</strong> " . htmlspecialchars($httpCode) . "<br>";
    
    // Pretty format the response
    $formatted_response = json_encode(json_decode($response), JSON_PRETTY_PRINT);
    
    echo "<strong>Response:</strong> <pre>" . htmlspecialchars($formatted_response) . "</pre>";
    echo "</div>";
    
    // Determine if the transaction was successful
    $response_data = json_decode($response, true);
    if ($httpCode == 200 && isset($response_data['status']) && $response_data['status']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "<strong>Transaction Initialization Successful!</strong><br>";
        echo "Authorization URL: <a href='" . htmlspecialchars($response_data['data']['authorization_url']) . "' target='_blank'>" . 
             htmlspecialchars($response_data['data']['authorization_url']) . "</a><br>";
        echo "<button onclick=\"window.open('" . htmlspecialchars($response_data['data']['authorization_url']) . "', '_blank')\">Open Payment Page</button>";
        echo "</div>";
        return true;
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "<strong>Transaction Initialization Failed:</strong>";
        if (isset($response_data['message'])) {
            echo "<br>Error Message: " . htmlspecialchars($response_data['message']);
        }
        echo "</div>";
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystack API Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2 {
            color: #333;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            overflow-x: auto;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .info-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Paystack API Test</h1>
    
    <div class="info-section">
        <h3>API Credentials</h3>
        <p><strong>Secret Key:</strong> <?php echo substr($secret_key, 0, 8) . '...'; ?></p>
        <p><strong>Public Key:</strong> <?php echo substr($public_key, 0, 8) . '...'; ?></p>
    </div>
    
    <div class="info-section">
        <h3>Test Actions</h3>
        <form method="post">
            <button type="submit" name="action" value="test_connection">Test API Connection</button>
            <button type="submit" name="action" value="test_transaction">Test Transaction Initialization</button>
        </form>
    </div>
    
    <?php
    // Process the form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'test_connection':
                testPaystackAPI($secret_key);
                break;
            case 'test_transaction':
                testCreateTransaction($secret_key);
                break;
        }
    }
    ?>
    
    <div class="info-section">
        <h3>Debug Information</h3>
        <p><strong>Server Name:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_NAME']); ?></p>
        <p><strong>HTTP Host:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?></p>
        <p><strong>Request URI:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></p>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        <p><strong>cURL Enabled:</strong> <?php echo function_exists('curl_init') ? 'Yes' : 'No'; ?></p>
        <p><strong>OpenSSL Enabled:</strong> <?php echo extension_loaded('openssl') ? 'Yes' : 'No'; ?></p>
    </div>
</body>
</html>

