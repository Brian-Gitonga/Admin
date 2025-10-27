<?php
/**
 * Debug Voucher SMS Delivery Issues
 * This script helps debug why vouchers are not being sent via SMS
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Voucher SMS Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .log { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Voucher SMS Delivery Debug</h1>
    
    <div class="section">
        <h2>1. Database Connection Status</h2>
        
        <?php
        if ($conn && !$conn->connect_error) {
            echo "<span class='success'>✅ Main Database: Connected</span><br>";
        } else {
            echo "<span class='error'>❌ Main Database: Failed</span><br>";
            if ($conn) echo "<span class='error'>Error: " . $conn->connect_error . "</span><br>";
        }

        if ($portal_conn && !$portal_conn->connect_error) {
            echo "<span class='success'>✅ Portal Database: Connected</span><br>";
        } else {
            echo "<span class='error'>❌ Portal Database: Failed</span><br>";
            if ($portal_conn) echo "<span class='error'>Error: " . $portal_conn->connect_error . "</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. SMS Settings Analysis</h2>
        
        <?php
        $smsSettings = getSmsSettings($conn, $test_reseller_id);
        
        if ($smsSettings) {
            echo "<span class='success'>✅ SMS Settings Found for Reseller $test_reseller_id</span><br>";
            echo "<table>";
            echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
            
            $settings_check = [
                'enable_sms' => $smsSettings['enable_sms'] ? 'Enabled' : 'Disabled',
                'sms_provider' => $smsSettings['sms_provider'] ?: 'Not Set',
                'textsms_api_key' => !empty($smsSettings['textsms_api_key']) ? 'Set' : 'Not Set',
                'textsms_partner_id' => !empty($smsSettings['textsms_partner_id']) ? 'Set' : 'Not Set',
                'textsms_sender_id' => $smsSettings['textsms_sender_id'] ?: 'Not Set',
                'payment_template' => !empty($smsSettings['payment_template']) ? 'Set' : 'Using Default'
            ];
            
            foreach ($settings_check as $key => $value) {
                $status = 'info';
                if ($key === 'enable_sms' && !$smsSettings['enable_sms']) {
                    $status = 'error';
                } elseif ($key === 'sms_provider' && empty($smsSettings[$key])) {
                    $status = 'error';
                } elseif ($smsSettings['sms_provider'] === 'textsms' && in_array($key, ['textsms_api_key', 'textsms_partner_id']) && empty($smsSettings[$key])) {
                    $status = 'error';
                }
                
                echo "<tr><td>$key</td><td>$value</td><td><span class='$status'>OK</span></td></tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='error'>❌ No SMS Settings Found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Test Paid Package SMS Function</h2>
        
        <?php
        // Include paystack_verify.php to get the SMS functions
        include_once 'paystack_verify.php';
        
        if (function_exists('sendVoucherSMS')) {
            echo "<span class='info'>Testing sendVoucherSMS function...</span><br>";
            
            $paidResult = sendVoucherSMS(
                $test_phone,
                'TESTPAID123',
                'testuser',
                'testpass',
                'Test Paid Package',
                $test_reseller_id
            );
            
            if ($paidResult['success']) {
                echo "<span class='success'>✅ Paid Package SMS Function: SUCCESS</span><br>";
                echo "<span class='info'>Message: " . $paidResult['message'] . "</span><br>";
            } else {
                echo "<span class='error'>❌ Paid Package SMS Function: FAILED</span><br>";
                echo "<span class='error'>Error: " . $paidResult['message'] . "</span><br>";
            }
            
            echo "<div class='code'>";
            echo "<strong>Function Response:</strong><br>";
            echo htmlspecialchars(print_r($paidResult, true));
            echo "</div>";
        } else {
            echo "<span class='error'>❌ sendVoucherSMS function not found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>4. Test Free Trial SMS Function</h2>
        
        <?php
        // Include process_free_trial.php to get the SMS functions
        include_once 'process_free_trial.php';
        
        if (function_exists('sendFreeTrialVoucherSMS')) {
            echo "<span class='info'>Testing sendFreeTrialVoucherSMS function...</span><br>";
            
            $freeResult = sendFreeTrialVoucherSMS(
                $test_phone,
                'TESTFREE123',
                'freeuser',
                'freepass',
                'Test Free Trial Package',
                $test_reseller_id
            );
            
            if ($freeResult['success']) {
                echo "<span class='success'>✅ Free Trial SMS Function: SUCCESS</span><br>";
                echo "<span class='info'>Message: " . $freeResult['message'] . "</span><br>";
            } else {
                echo "<span class='error'>❌ Free Trial SMS Function: FAILED</span><br>";
                echo "<span class='error'>Error: " . $freeResult['message'] . "</span><br>";
            }
            
            echo "<div class='code'>";
            echo "<strong>Function Response:</strong><br>";
            echo htmlspecialchars(print_r($freeResult, true));
            echo "</div>";
        } else {
            echo "<span class='error'>❌ sendFreeTrialVoucherSMS function not found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Check Recent Payment Transactions</h2>
        
        <?php
        $recentQuery = "SELECT * FROM payment_transactions WHERE status = 'completed' ORDER BY created_at DESC LIMIT 5";
        $recentResult = $portal_conn->query($recentQuery);
        
        if ($recentResult && $recentResult->num_rows > 0) {
            echo "<span class='info'>Recent completed payments:</span><br>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Reference</th><th>Phone</th><th>Package</th><th>Status</th><th>Created</th></tr>";
            
            while ($row = $recentResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['reference']) . "</td>";
                echo "<td>" . $row['phone_number'] . "</td>";
                echo "<td>" . $row['package_name'] . "</td>";
                echo "<td>" . $row['status'] . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='warning'>⚠️ No recent completed payments found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>6. Check Free Trial Usage</h2>
        
        <?php
        $freeTrialQuery = "SELECT * FROM free_trial_usage ORDER BY created_at DESC LIMIT 5";
        $freeTrialResult = $conn->query($freeTrialQuery);
        
        if ($freeTrialResult && $freeTrialResult->num_rows > 0) {
            echo "<span class='info'>Recent free trial usage:</span><br>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Reseller ID</th><th>Phone</th><th>Usage Count</th><th>Created</th></tr>";
            
            while ($row = $freeTrialResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['reseller_id'] . "</td>";
                echo "<td>" . $row['phone_number'] . "</td>";
                echo "<td>" . $row['usage_count'] . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='warning'>⚠️ No free trial usage records found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>7. Error Logs Analysis</h2>
        
        <?php
        // Check for recent error logs
        $logFiles = ['paystack_verify.log', 'error.log', 'php_errors.log'];
        
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                echo "<h4>$logFile (Last 20 lines):</h4>";
                $lines = file($logFile);
                $recentLines = array_slice($lines, -20);
                
                echo "<div class='log'>";
                foreach ($recentLines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
                echo "</div>";
            }
        }
        
        // Check PHP error log
        $phpErrorLog = ini_get('error_log');
        if ($phpErrorLog && file_exists($phpErrorLog)) {
            echo "<h4>PHP Error Log (Last 10 lines):</h4>";
            $lines = file($phpErrorLog);
            $recentLines = array_slice($lines, -10);
            
            echo "<div class='log'>";
            foreach ($recentLines as $line) {
                if (strpos($line, 'SMS') !== false || strpos($line, 'voucher') !== false) {
                    echo "<span class='error'>" . htmlspecialchars($line) . "</span><br>";
                } else {
                    echo htmlspecialchars($line) . "<br>";
                }
            }
            echo "</div>";
        }
        ?>
    </div>

    <div class="section">
        <h2>8. Recommendations</h2>
        
        <h3>🔧 Based on the analysis above:</h3>
        <ol>
            <li><strong>Check SMS Function Results:</strong> Review the test results for both paid and free trial SMS functions</li>
            <li><strong>Verify Database Connections:</strong> Ensure both functions are using the correct database connection</li>
            <li><strong>Check Error Logs:</strong> Look for specific SMS-related errors in the logs above</li>
            <li><strong>Test with Real Data:</strong> Try actual payment and free trial flows</li>
            <li><strong>Monitor Function Calls:</strong> Add logging to see if SMS functions are being called</li>
        </ol>
        
        <h3>📱 Next Steps:</h3>
        <ol>
            <li>If SMS functions show errors, fix the configuration issues</li>
            <li>If functions work here but not in real flow, add logging to the actual workflows</li>
            <li>Test with actual payment and free trial processes</li>
            <li>Monitor logs during real transactions</li>
        </ol>
    </div>

    <p><em>Debug completed at: <?php echo date('Y-m-d H:i:s'); ?></em></p>
</div>

</body>
</html>
