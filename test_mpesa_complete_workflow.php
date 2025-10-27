<?php
/**
 * Test Complete M-Pesa Workflow - End-to-End Testing
 */

require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';
require_once 'sms_settings_operations.php';

echo "<h1>🧪 M-Pesa Complete Workflow Test</h1>";

// Test parameters
$test_phone = '254700123456';
$test_amount = 10;
$test_package_id = 1;
$test_reseller_id = 1;
$test_checkout_id = 'ws_CO_TEST_' . time() . rand(100000, 999999);

echo "<h2>Step 1: Pre-Test Checks</h2>";

// Check M-Pesa settings
$mpesaSettings = getMpesaSettings($portal_conn, $test_reseller_id);
if ($mpesaSettings) {
    echo "<p>✅ M-Pesa settings found</p>";
    echo "<p><strong>Callback URL:</strong> " . $mpesaSettings['callback_url'] . "</p>";
    
    if (strpos($mpesaSettings['callback_url'], 'ngrok') !== false) {
        echo "<p>✅ Using ngrok URL</p>";
    } else {
        echo "<p>⚠️ Not using ngrok URL - may cause callback issues</p>";
    }
} else {
    echo "<p>❌ M-Pesa settings not found</p>";
}

// Check SMS settings
$smsSettings = getSmsSettings($portal_conn, $test_reseller_id);
if ($smsSettings && $smsSettings['enable_sms']) {
    echo "<p>✅ SMS is enabled</p>";
    echo "<p>Provider: " . $smsSettings['sms_provider'] . "</p>";
    echo "<p>API Key: " . (empty($smsSettings['textsms_api_key']) ? '❌ Not set' : '✅ Set') . "</p>";
} else {
    echo "<p>❌ SMS not configured</p>";
}

// Check vouchers table
$vouchersTableCheck = $portal_conn->query("SHOW TABLES LIKE 'vouchers'");
if ($vouchersTableCheck && $vouchersTableCheck->num_rows > 0) {
    echo "<p>✅ Vouchers table exists</p>";
    
    $voucherCount = $portal_conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'");
    if ($voucherCount) {
        $count = $voucherCount->fetch_assoc()['count'];
        echo "<p>📊 Active vouchers: $count</p>";
    }
} else {
    echo "<p>⚠️ Vouchers table does not exist - will use generated vouchers</p>";
}

echo "<h2>Step 2: Create Test M-Pesa Transaction</h2>";

// Create test transaction
$insertQuery = "INSERT INTO mpesa_transactions (
    checkout_request_id, merchant_request_id, amount, phone_number, 
    package_id, reseller_id, status, created_at
) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $portal_conn->prepare($insertQuery);
if ($stmt) {
    $merchant_id = 'ws_MR_TEST_' . time();
    $stmt->bind_param("ssdiii", 
        $test_checkout_id,
        $merchant_id,
        $test_amount,
        $test_phone,
        $test_package_id,
        $test_reseller_id
    );
    
    if ($stmt->execute()) {
        echo "<p>✅ Test M-Pesa transaction created</p>";
        echo "<p><strong>Checkout Request ID:</strong> $test_checkout_id</p>";
        echo "<p><strong>Phone:</strong> $test_phone</p>";
        echo "<p><strong>Amount:</strong> KES $test_amount</p>";
    } else {
        echo "<p>❌ Failed to create test transaction: " . $stmt->error . "</p>";
        exit;
    }
} else {
    echo "<p>❌ Failed to prepare transaction insert: " . $portal_conn->error . "</p>";
    exit;
}

echo "<h2>Step 3: Test Payment Status Check</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Simulating 'I have completed payment' button click...</h4>";
echo "<button onclick='testPaymentStatus()' style='background: #007cba; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>🧪 Test Payment Status Check</button>";
echo "<div id='test-result' style='margin-top: 15px;'></div>";
echo "</div>";

echo "<h2>Step 4: Test SMS Sending Directly</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Test SMS sending with sample voucher data...</h4>";
echo "<button onclick='testSMSSending()' style='background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>📱 Test SMS Sending</button>";
echo "<div id='sms-test-result' style='margin-top: 15px;'></div>";
echo "</div>";

echo "<h2>Step 5: Recent Logs</h2>";

// Show recent payment status check logs
if (file_exists('payment_status_checks.log')) {
    echo "<h4>Payment Status Check Logs:</h4>";
    $logs = file_get_contents('payment_status_checks.log');
    $logLines = explode("\n", $logs);
    $recentLogs = array_slice($logLines, -10);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 200px; overflow-y: auto;'>";
    foreach ($recentLogs as $line) {
        if (!empty(trim($line))) {
            $line = htmlspecialchars($line);
            if (strpos($line, 'successful') !== false) {
                $line = "<span style='color: green;'>$line</span>";
            } elseif (strpos($line, 'Error') !== false) {
                $line = "<span style='color: red;'>$line</span>";
            }
            echo $line . "<br>";
        }
    }
    echo "</div>";
}

// Show M-Pesa SMS logs if they exist
if (file_exists('mpesa_sms_sending.log')) {
    echo "<h4>M-Pesa SMS Sending Logs:</h4>";
    $smsLogs = file_get_contents('mpesa_sms_sending.log');
    $smsLogLines = explode("\n", $smsLogs);
    $recentSmsLogs = array_slice($smsLogLines, -10);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 200px; overflow-y: auto;'>";
    foreach ($recentSmsLogs as $line) {
        if (!empty(trim($line))) {
            $line = htmlspecialchars($line);
            if (strpos($line, 'success') !== false) {
                $line = "<span style='color: green;'>$line</span>";
            } elseif (strpos($line, 'Error') !== false || strpos($line, 'failed') !== false) {
                $line = "<span style='color: red;'>$line</span>";
            }
            echo $line . "<br>";
        }
    }
    echo "</div>";
}

echo "<h2>Step 6: Cleanup</h2>";
echo "<p><a href='?cleanup=1' style='color: red;'>🗑️ Click here to cleanup test data</a></p>";

// Handle cleanup
if (isset($_GET['cleanup'])) {
    $deleteStmt = $portal_conn->prepare("DELETE FROM mpesa_transactions WHERE checkout_request_id = ?");
    $deleteStmt->bind_param("s", $test_checkout_id);
    $deleteStmt->execute();
    echo "<p>✅ Test data cleaned up</p>";
}

?>

<script>
function testPaymentStatus() {
    document.getElementById('test-result').innerHTML = '<p>Testing payment status check...</p>';
    
    fetch('check_payment_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'checkout_request_id=' + encodeURIComponent('<?php echo $test_checkout_id; ?>')
    })
    .then(response => response.json())
    .then(result => {
        let resultHtml = '<h5>Payment Status Check Result:</h5>';
        resultHtml += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            resultHtml += '<p style="color: green;">✅ Payment verification successful!</p>';
            if (result.sms_sent) {
                resultHtml += '<p style="color: green;">✅ SMS sent successfully!</p>';
            } else {
                resultHtml += '<p style="color: orange;">⚠️ SMS sending failed: ' + (result.sms_message || 'Unknown error') + '</p>';
            }
        } else {
            resultHtml += '<p style="color: red;">❌ Payment verification failed: ' + result.message + '</p>';
        }
        
        document.getElementById('test-result').innerHTML = resultHtml;
    })
    .catch(error => {
        document.getElementById('test-result').innerHTML = '<p style="color: red;">❌ Request failed: ' + error + '</p>';
    });
}

function testSMSSending() {
    document.getElementById('sms-test-result').innerHTML = '<p>Testing SMS sending...</p>';
    
    const smsData = new FormData();
    smsData.append('phone_number', '<?php echo $test_phone; ?>');
    smsData.append('voucher_code', 'TEST123456');
    smsData.append('username', 'TEST123456');
    smsData.append('password', 'TEST123456');
    smsData.append('package_name', 'Test WiFi Package');
    
    fetch('send_free_trial_sms.php', {
        method: 'POST',
        body: smsData
    })
    .then(response => response.json())
    .then(result => {
        let resultHtml = '<h5>SMS Sending Result:</h5>';
        resultHtml += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            resultHtml += '<p style="color: green;">✅ SMS sent successfully!</p>';
        } else {
            resultHtml += '<p style="color: red;">❌ SMS sending failed: ' + result.message + '</p>';
        }
        
        document.getElementById('sms-test-result').innerHTML = resultHtml;
    })
    .catch(error => {
        document.getElementById('sms-test-result').innerHTML = '<p style="color: red;">❌ Request failed: ' + error + '</p>';
    });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
button:hover { opacity: 0.9; }
pre { font-size: 12px; }
</style>
