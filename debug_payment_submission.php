<?php
/**
 * Debug Payment Submission - Test what's being sent to process_payment.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>🔍 Debug Payment Submission</h1>";

// Step 1: Check if we can access process_payment.php
echo "<h2>Step 1: Test process_payment.php Access</h2>";

$testUrl = 'http://localhost/SAAS/Wifi%20Billiling%20system/Admin/process_payment.php';
echo "<p>Testing URL: <code>$testUrl</code></p>";

// Test with minimal POST data
$testData = [
    'reseller_id' => '6',
    'package_name' => 'Test Package',
    'package_price' => '100',
    'mpesa_number' => '254114669532',
    'package_id' => '15',
    'router_id' => '0',
    'payment_gateway' => 'mpesa'
];

echo "<h3>Test Data Being Sent:</h3>";
echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
print_r($testData);
echo "</pre>";

// Use cURL to test the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Response:</h3>";
echo "<p><strong>HTTP Code:</strong> $httpCode</p>";

if ($error) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> $error</p>";
} else {
    // Split headers and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    echo "<h4>Response Headers:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;'>";
    echo htmlspecialchars($headers);
    echo "</pre>";
    
    echo "<h4>Response Body:</h4>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
    echo htmlspecialchars($body);
    echo "</pre>";
    
    // Try to parse as JSON
    $jsonData = json_decode($body, true);
    if ($jsonData) {
        echo "<h4>Parsed JSON:</h4>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 4px;'>";
        print_r($jsonData);
        echo "</pre>";
    }
}

// Step 2: Check database and system status
echo "<h2>Step 2: System Status Check</h2>";

// Check database connection
if ($conn) {
    echo "<p style='color: green;'>✅ Database connected</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
}

// Check mpesa_transactions table
$tableCheck = $conn->query("SHOW TABLES LIKE 'mpesa_transactions'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ mpesa_transactions table exists</p>";
} else {
    echo "<p style='color: red;'>❌ mpesa_transactions table missing</p>";
}

// Check vouchers table
$voucherTableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($voucherTableCheck && $voucherTableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ vouchers table exists</p>";
    
    $activeVouchers = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'")->fetch_assoc()['count'];
    echo "<p>Active vouchers: <strong>$activeVouchers</strong></p>";
} else {
    echo "<p style='color: red;'>❌ vouchers table missing</p>";
}

// Check packages
$packages = $conn->query("SELECT COUNT(*) as count FROM packages")->fetch_assoc()['count'];
echo "<p>Available packages: <strong>$packages</strong></p>";

// Step 3: Test JavaScript form submission simulation
echo "<h2>Step 3: Test Form Submission (JavaScript Simulation)</h2>";

echo "<button onclick='testFormSubmission()' style='background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🧪 Test Form Submission</button>";

echo "<div id='form-test-result' style='margin-top: 20px;'></div>";

echo "<script>
function testFormSubmission() {
    const resultDiv = document.getElementById('form-test-result');
    resultDiv.innerHTML = '<p>🔄 Testing form submission...</p>';
    
    // Create FormData exactly like portal.php does
    const formData = new FormData();
    formData.append('reseller_id', '6');
    formData.append('package_name', 'Test Package');
    formData.append('package_price', '100');
    formData.append('mpesa_number', '254114669532');
    formData.append('package_id', '15');
    formData.append('router_id', '0');
    formData.append('payment_gateway', 'mpesa');
    
    console.log('Form data being sent:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    fetch('process_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        return response.text().then(text => {
            console.log('Raw response:', text);
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Parsed response:', data);
        
        if (data.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #065f46; margin-top: 0;'>✅ Payment Submission Successful!</h4>
                    <p><strong>Message:</strong> \${data.message}</p>
                    <p><strong>Checkout Request ID:</strong> \${data.checkout_request_id || 'N/A'}</p>
                    <p><strong>Merchant Request ID:</strong> \${data.merchant_request_id || 'N/A'}</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #dc2626; margin-top: 0;'>❌ Payment Submission Failed</h4>
                    <p><strong>Error:</strong> \${data.message || 'Unknown error'}</p>
                    \${data.debug_info ? '<p><strong>Debug:</strong> ' + JSON.stringify(data.debug_info) + '</p>' : ''}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Request error:', error);
        
        resultDiv.innerHTML = `
            <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                <h4 style='color: #dc2626; margin-top: 0;'>🚨 Request Failed</h4>
                <p><strong>Error:</strong> \${error.message}</p>
                <p style='font-size: 12px; color: #6b7280;'>Check browser console for detailed error information.</p>
            </div>
        `;
    });
}
</script>";

// Step 4: Check recent logs
echo "<h2>Step 4: Recent Logs</h2>";

$logFiles = ['mpesa_debug.log', 'payment_status_checks.log', 'voucher_test.log'];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        echo "<h4>$logFile (last 10 lines):</h4>";
        $lines = file($logFile);
        $lastLines = array_slice($lines, -10);
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; max-height: 200px; overflow-y: auto;'>";
        echo htmlspecialchars(implode('', $lastLines));
        echo "</pre>";
    } else {
        echo "<p>$logFile: <em>File not found</em></p>";
    }
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
code { background: #f3f4f6; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
button:hover { opacity: 0.9; }
</style>
