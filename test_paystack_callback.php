<?php
/**
 * Test Paystack Callback - Check if Paystack is calling our callback URL
 */

// Start session
session_start();

// Include required files
require_once 'portal_connection.php';

echo "<h1>🔍 Paystack Callback Test</h1>";

// Clear the log file for fresh testing
$logFile = 'paystack_verify.log';
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
    echo "<p>✅ Cleared paystack_verify.log for fresh testing</p>";
}

// Set test parameters
$test_reference = 'CALLBACK_TEST_' . time();
$test_phone = '254700123456';
$test_amount = 1; // KES 1 for testing
$test_package_id = 1;
$test_router_id = 1;
$test_reseller_id = 1;

echo "<h2>Step 1: Creating Test Transaction</h2>";

// Create a test transaction
$insertQuery = "INSERT INTO payment_transactions (
    reference, phone_number, amount, package_id, package_name,
    router_id, reseller_id, user_id, status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $portal_conn->prepare($insertQuery);
if ($stmt) {
    $package_name = "Test Package";
    $stmt->bind_param("ssdiiiii",
        $test_reference,
        $test_phone,
        $test_amount,
        $test_package_id,
        $package_name,
        $test_router_id,
        $test_reseller_id,
        $test_reseller_id  // user_id same as reseller_id
    );
    
    if ($stmt->execute()) {
        echo "<p>✅ Test transaction created with reference: <strong>$test_reference</strong></p>";
    } else {
        echo "<p>❌ Failed to create test transaction: " . $stmt->error . "</p>";
        exit;
    }
} else {
    echo "<p>❌ Failed to prepare transaction insert: " . $portal_conn->error . "</p>";
    exit;
}

// Set session data (normally set during payment initialization)
$_SESSION['paystack_reference'] = $test_reference;
$_SESSION['payment_initiated'] = 1;
$_SESSION['payment_email'] = $test_phone . '@customer.qtro.co.ke';
$_SESSION['payment_amount'] = $test_amount;
$_SESSION['payment_timestamp'] = time();

echo "<h2>Step 2: Callback URL Test</h2>";

// Test the callback URL directly
$callback_url = "paystack_verify.php?reference=" . urlencode($test_reference);
echo "<p><strong>Callback URL:</strong> <a href='$callback_url' target='_blank'>$callback_url</a></p>";

echo "<h2>Step 3: Instructions</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🧪 How to Test Paystack Callback:</h3>";
echo "<ol>";
echo "<li><strong>Click the callback URL above</strong> to simulate Paystack calling our verification endpoint</li>";
echo "<li><strong>Check the logs below</strong> to see if paystack_verify.php was accessed</li>";
echo "<li><strong>Look for the 🔥 PAYSTACK_VERIFY.PHP ACCESSED message</strong> in the logs</li>";
echo "<li><strong>If you see the access message</strong>, the callback mechanism is working</li>";
echo "<li><strong>If you don't see the access message</strong>, there's an issue with the callback URL or routing</li>";
echo "</ol>";
echo "</div>";

echo "<h2>Step 4: Real-Time Log Monitor</h2>";
echo "<div id='log-container' style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 400px; overflow-y: auto;'>";
echo "<p>Logs will appear here...</p>";
echo "</div>";

echo "<button onclick='refreshLogs()' style='margin: 10px 0; padding: 10px 15px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;'>🔄 Refresh Logs</button>";

echo "<h2>Step 5: Check Current Logs</h2>";
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if (!empty($logs)) {
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars($logs);
        echo "</pre>";
    } else {
        echo "<p>No logs yet. Click the callback URL above to generate logs.</p>";
    }
} else {
    echo "<p>Log file doesn't exist yet. Click the callback URL above to create it.</p>";
}

echo "<h2>Step 6: Cleanup</h2>";
echo "<p><a href='?cleanup=1' style='color: red;'>Click here to cleanup test data</a></p>";

// Handle cleanup
if (isset($_GET['cleanup'])) {
    // Delete test transaction
    $deleteTransaction = $portal_conn->prepare("DELETE FROM payment_transactions WHERE reference = ?");
    $deleteTransaction->bind_param("s", $test_reference);
    $deleteTransaction->execute();
    
    echo "<p>✅ Test data cleaned up</p>";
}

?>

<script>
function refreshLogs() {
    fetch('test_paystack_callback.php?action=get_logs')
    .then(response => response.text())
    .then(data => {
        document.getElementById('log-container').innerHTML = data;
    })
    .catch(error => {
        document.getElementById('log-container').innerHTML = '<p style="color: red;">Error loading logs: ' + error + '</p>';
    });
}

// Auto-refresh logs every 5 seconds
setInterval(refreshLogs, 5000);
</script>

<?php
// Handle AJAX request for logs
if (isset($_GET['action']) && $_GET['action'] === 'get_logs') {
    header('Content-Type: text/html');
    
    if (file_exists($logFile)) {
        $logs = file_get_contents($logFile);
        if (!empty($logs)) {
            // Highlight important messages
            $logs = htmlspecialchars($logs);
            $logs = str_replace('🔥 PAYSTACK_VERIFY.PHP ACCESSED', '<strong style="color: green;">🔥 PAYSTACK_VERIFY.PHP ACCESSED</strong>', $logs);
            $logs = str_replace('CALLBACK', '<strong style="color: blue;">CALLBACK</strong>', $logs);
            $logs = str_replace('ERROR', '<strong style="color: red;">ERROR</strong>', $logs);
            $logs = str_replace('SUCCESS', '<strong style="color: green;">SUCCESS</strong>', $logs);
            
            echo "<pre style='margin: 0;'>" . $logs . "</pre>";
        } else {
            echo "<p>No logs yet. Click the callback URL to generate logs.</p>";
        }
    } else {
        echo "<p>Log file doesn't exist yet.</p>";
    }
    exit;
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1, h2 { color: #333; }
a { color: #007cba; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
