<?php
/**
 * Test Voucher Workflows - Simulate both paid and free trial voucher delivery
 * This script helps test the complete voucher SMS delivery workflows
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Test parameters
$test_reseller_id = 1;
$test_phone = '254700123456';
$test_package_id = 1;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher Workflow Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .code { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .log { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; max-height: 200px; overflow-y: auto; font-size: 12px; }
        button { background-color: #007cba; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .test-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .test-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">
    <h1>🧪 Voucher Workflow Test</h1>
    
    <div class="section">
        <h2>1. Prerequisites Check</h2>
        
        <?php
        // Check database connections
        $prereq_ok = true;
        
        if ($conn && !$conn->connect_error) {
            echo "<span class='success'>✅ Main Database: Connected</span><br>";
        } else {
            echo "<span class='error'>❌ Main Database: Failed</span><br>";
            $prereq_ok = false;
        }

        if ($portal_conn && !$portal_conn->connect_error) {
            echo "<span class='success'>✅ Portal Database: Connected</span><br>";
        } else {
            echo "<span class='error'>❌ Portal Database: Failed</span><br>";
            $prereq_ok = false;
        }
        
        // Check SMS settings
        $smsSettings = getSmsSettings($conn, $test_reseller_id);
        if ($smsSettings && $smsSettings['enable_sms']) {
            echo "<span class='success'>✅ SMS Settings: Configured and enabled</span><br>";
            echo "<span class='info'>Provider: " . $smsSettings['sms_provider'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ SMS Settings: Not configured or disabled</span><br>";
            $prereq_ok = false;
        }
        
        // Check for test package
        $packageQuery = "SELECT * FROM packages WHERE id = ? AND reseller_id = ?";
        $packageStmt = $conn->prepare($packageQuery);
        $packageStmt->bind_param("ii", $test_package_id, $test_reseller_id);
        $packageStmt->execute();
        $packageResult = $packageStmt->get_result();
        
        if ($packageResult->num_rows > 0) {
            $package = $packageResult->fetch_assoc();
            echo "<span class='success'>✅ Test Package: Found - " . $package['name'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Test Package: Not found (ID: $test_package_id)</span><br>";
            $prereq_ok = false;
        }
        
        // Check for test vouchers
        $voucherQuery = "SELECT COUNT(*) as count FROM vouchers WHERE package_id = ? AND reseller_id = ? AND status = 'active'";
        $voucherStmt = $conn->prepare($voucherQuery);
        $voucherStmt->bind_param("ii", $test_package_id, $test_reseller_id);
        $voucherStmt->execute();
        $voucherResult = $voucherStmt->get_result();
        $voucherCount = $voucherResult->fetch_assoc()['count'];
        
        if ($voucherCount > 0) {
            echo "<span class='success'>✅ Test Vouchers: $voucherCount available</span><br>";
        } else {
            echo "<span class='error'>❌ Test Vouchers: None available</span><br>";
            $prereq_ok = false;
        }
        
        if (!$prereq_ok) {
            echo "<div class='test-error'><strong>⚠️ Prerequisites not met. Please fix the issues above before testing.</strong></div>";
        }
        ?>
    </div>

    <?php if ($prereq_ok): ?>
    
    <div class="section">
        <h2>2. Test Paid Package SMS Workflow</h2>
        
        <p>This test simulates the SMS sending that happens after a successful Paystack payment.</p>
        
        <button onclick="testPaidPackageSMS()">🧪 Test Paid Package SMS</button>
        
        <div id="paid-test-result"></div>
        
        <script>
        function testPaidPackageSMS() {
            document.getElementById('paid-test-result').innerHTML = '<div class="info">Testing paid package SMS workflow...</div>';
            
            fetch('test_voucher_workflows.php?action=test_paid_sms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: '<?php echo $test_phone; ?>',
                    reseller_id: <?php echo $test_reseller_id; ?>,
                    package_id: <?php echo $test_package_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultClass = data.success ? 'test-success' : 'test-error';
                const resultIcon = data.success ? '✅' : '❌';
                
                let html = `<div class="${resultClass}">
                    <strong>${resultIcon} Paid Package SMS Test Result</strong><br>
                    <strong>Status:</strong> ${data.success ? 'SUCCESS' : 'FAILED'}<br>
                    <strong>Message:</strong> ${data.message}<br>
                `;
                
                if (data.details) {
                    html += `<strong>Details:</strong><br><div class="code">${JSON.stringify(data.details, null, 2)}</div>`;
                }
                
                if (data.logs) {
                    html += `<strong>Logs:</strong><br><div class="log">${data.logs}</div>`;
                }
                
                html += '</div>';
                document.getElementById('paid-test-result').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('paid-test-result').innerHTML = 
                    `<div class="test-error"><strong>❌ Test Error:</strong> ${error.message}</div>`;
            });
        }
        </script>
    </div>

    <div class="section">
        <h2>3. Test Free Trial SMS Workflow</h2>
        
        <p>This test simulates the SMS sending that happens when a customer requests a free trial.</p>
        
        <button onclick="testFreeTrialSMS()">🧪 Test Free Trial SMS</button>
        
        <div id="free-test-result"></div>
        
        <script>
        function testFreeTrialSMS() {
            document.getElementById('free-test-result').innerHTML = '<div class="info">Testing free trial SMS workflow...</div>';
            
            fetch('test_voucher_workflows.php?action=test_free_trial_sms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    phone: '<?php echo $test_phone; ?>',
                    reseller_id: <?php echo $test_reseller_id; ?>,
                    package_id: <?php echo $test_package_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                const resultClass = data.success ? 'test-success' : 'test-error';
                const resultIcon = data.success ? '✅' : '❌';
                
                let html = `<div class="${resultClass}">
                    <strong>${resultIcon} Free Trial SMS Test Result</strong><br>
                    <strong>Status:</strong> ${data.success ? 'SUCCESS' : 'FAILED'}<br>
                    <strong>Message:</strong> ${data.message}<br>
                `;
                
                if (data.details) {
                    html += `<strong>Details:</strong><br><div class="code">${JSON.stringify(data.details, null, 2)}</div>`;
                }
                
                if (data.logs) {
                    html += `<strong>Logs:</strong><br><div class="log">${data.logs}</div>`;
                }
                
                html += '</div>';
                document.getElementById('free-test-result').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('free-test-result').innerHTML = 
                    `<div class="test-error"><strong>❌ Test Error:</strong> ${error.message}</div>`;
            });
        }
        </script>
    </div>

    <?php endif; ?>

    <div class="section">
        <h2>4. Log Monitoring</h2>
        
        <p>Monitor the logs to see detailed SMS sending attempts:</p>
        
        <button onclick="refreshLogs()">🔄 Refresh Logs</button>
        
        <div id="log-display">
            <?php
            // Show recent logs
            $logFiles = ['paystack_verify.log', 'error_log', 'php_errors.log'];
            
            foreach ($logFiles as $logFile) {
                if (file_exists($logFile)) {
                    echo "<h4>$logFile (Last 10 lines):</h4>";
                    $lines = file($logFile);
                    $recentLines = array_slice($lines, -10);
                    
                    echo "<div class='log'>";
                    foreach ($recentLines as $line) {
                        if (strpos($line, 'SMS') !== false || strpos($line, 'voucher') !== false) {
                            echo "<strong>" . htmlspecialchars($line) . "</strong><br>";
                        } else {
                            echo htmlspecialchars($line) . "<br>";
                        }
                    }
                    echo "</div>";
                }
            }
            ?>
        </div>
        
        <script>
        function refreshLogs() {
            fetch('test_voucher_workflows.php?action=get_logs')
            .then(response => response.text())
            .then(data => {
                document.getElementById('log-display').innerHTML = data;
            });
        }
        </script>
    </div>

    <div class="section">
        <h2>5. Instructions</h2>
        
        <h3>🧪 How to Use This Test:</h3>
        <ol>
            <li><strong>Check Prerequisites:</strong> Ensure all prerequisites are met above</li>
            <li><strong>Test Paid Package SMS:</strong> Click the button to simulate post-payment SMS sending</li>
            <li><strong>Test Free Trial SMS:</strong> Click the button to simulate free trial SMS sending</li>
            <li><strong>Monitor Logs:</strong> Check the logs for detailed debugging information</li>
            <li><strong>Check Your Phone:</strong> See if you receive the test SMS messages</li>
        </ol>
        
        <h3>🔍 What to Look For:</h3>
        <ul>
            <li><strong>Success Messages:</strong> Both tests should show success if SMS is working</li>
            <li><strong>Error Details:</strong> Any failures will show specific error messages</li>
            <li><strong>Log Entries:</strong> Detailed logs show exactly where the process fails</li>
            <li><strong>SMS Delivery:</strong> Check if you actually receive the SMS on your phone</li>
        </ul>
    </div>

    <p><em>Test interface loaded at: <?php echo date('Y-m-d H:i:s'); ?></em></p>
</div>

</body>
</html>

<?php
// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'test_paid_sms':
            // Test paid package SMS workflow
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Include paystack_verify.php functions
            include_once 'paystack_verify.php';
            
            if (function_exists('sendVoucherSMS')) {
                $result = sendVoucherSMS(
                    $input['phone'],
                    'TESTPAID' . time(),
                    'testuser',
                    'testpass',
                    'Test Paid Package',
                    $input['reseller_id']
                );
                
                echo json_encode([
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'details' => $result,
                    'logs' => 'Check paystack_verify.log for detailed logs'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'sendVoucherSMS function not found'
                ]);
            }
            break;
            
        case 'test_free_trial_sms':
            // Test free trial SMS workflow
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Include process_free_trial.php functions
            include_once 'process_free_trial.php';
            
            if (function_exists('sendFreeTrialVoucherSMS')) {
                $result = sendFreeTrialVoucherSMS(
                    $input['phone'],
                    'TESTFREE' . time(),
                    'freeuser',
                    'freepass',
                    'Test Free Trial Package',
                    $input['reseller_id']
                );
                
                echo json_encode([
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'details' => $result,
                    'logs' => 'Check error_log for detailed logs'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'sendFreeTrialVoucherSMS function not found'
                ]);
            }
            break;
            
        case 'get_logs':
            // Return recent logs
            $logFiles = ['paystack_verify.log', 'error_log', 'php_errors.log'];
            
            foreach ($logFiles as $logFile) {
                if (file_exists($logFile)) {
                    echo "<h4>$logFile (Last 10 lines):</h4>";
                    $lines = file($logFile);
                    $recentLines = array_slice($lines, -10);
                    
                    echo "<div class='log'>";
                    foreach ($recentLines as $line) {
                        if (strpos($line, 'SMS') !== false || strpos($line, 'voucher') !== false) {
                            echo "<strong>" . htmlspecialchars($line) . "</strong><br>";
                        } else {
                            echo htmlspecialchars($line) . "<br>";
                        }
                    }
                    echo "</div>";
                }
            }
            break;
    }
    exit;
}
?>
