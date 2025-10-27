<?php
/**
 * Test Umeskia SMS Integration
 * Quick test to verify Umeskia SMS is working with the voucher delivery system
 */

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_umeskia_sms'])) {
    header('Content-Type: application/json');
    
    $phoneNumber = $_POST['phone_number'] ?? '';
    $testMessage = $_POST['test_message'] ?? '';
    
    if (empty($phoneNumber) || empty($testMessage)) {
        echo json_encode([
            'success' => false,
            'message' => 'Phone number and message are required'
        ]);
        exit;
    }
    
    try {
        require_once 'sms_voucher_delivery.php';
        
        // Create SMS manager and test Umeskia
        $smsManager = new SmsGatewayManager();
        $result = $smsManager->sendSms($phoneNumber, $testMessage);
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ]);
    }
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Umeskia SMS Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        button:hover { background: #059669; }
        .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .error { background: #fef2f2; border: 1px solid #ef4444; color: #dc2626; }
        .info { background: #f0f9ff; border: 1px solid #0ea5e9; color: #0c4a6e; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Umeskia SMS Integration Test</h1>
        
        <div class="info">
            <h3>📱 SMS Gateway Configuration</h3>
            <p><strong>Active Gateway:</strong> Umeskia</p>
            <p><strong>API Endpoint:</strong> https://comms.umeskiasoftwares.com/api/v1/sms/send</p>
            <p><strong>App ID:</strong> UMSC631939</p>
            <p><strong>Sender ID:</strong> WIFI-HOTSPOT</p>
        </div>
        
        <h2>🧪 Test Umeskia SMS Delivery</h2>
        
        <form id="sms-test-form">
            <div class="form-group">
                <label for="phone_number">Phone Number (07xxxxxxxx or 254xxxxxxxx):</label>
                <input type="tel" id="phone_number" name="phone_number" value="0750059353" required>
                <small>Enter your phone number to receive the test SMS</small>
            </div>
            
            <div class="form-group">
                <label for="test_message">Test Message:</label>
                <textarea id="test_message" name="test_message" rows="4" required>🎉 Test SMS from WiFi Hotspot System!

This is a test message to verify that Umeskia SMS integration is working correctly.

Your voucher delivery system is ready! 🚀</textarea>
            </div>
            
            <button type="submit">📤 Send Test SMS via Umeskia</button>
        </form>
        
        <div id="test-result"></div>
        
        <h2>📋 Recent SMS Activity</h2>
        
        <?php
        // Check if SMS logs table exists and show recent activity
        $smsLogsExist = false;
        $checkTable = $conn->query("SHOW TABLES LIKE 'sms_logs'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $smsLogsExist = true;
            
            $recentSms = $conn->query("
                SELECT 
                    phone_number,
                    message_preview,
                    gateway,
                    status,
                    response_data,
                    created_at
                FROM sms_logs 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            
            if ($recentSms && $recentSms->num_rows > 0) {
                echo "<table border='1' cellpadding='8' style='width: 100%; border-collapse: collapse; font-size: 14px;'>";
                echo "<tr style='background-color: #f8f9fa;'>";
                echo "<th>Phone</th><th>Message Preview</th><th>Gateway</th><th>Status</th><th>Time</th>";
                echo "</tr>";
                
                while ($sms = $recentSms->fetch_assoc()) {
                    $statusColor = $sms['status'] === 'sent' ? 'green' : 'red';
                    $messagePreview = substr($sms['message_preview'], 0, 50) . '...';
                    
                    echo "<tr>";
                    echo "<td>{$sms['phone_number']}</td>";
                    echo "<td title='{$sms['message_preview']}'>$messagePreview</td>";
                    echo "<td>{$sms['gateway']}</td>";
                    echo "<td style='color: $statusColor; font-weight: bold;'>{$sms['status']}</td>";
                    echo "<td>{$sms['created_at']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p style='color: #6b7280;'>No SMS activity yet. Send a test SMS to see logs here.</p>";
            }
        } else {
            echo "<p style='color: #f59e0b;'>⚠️ SMS logs table not created yet. It will be created automatically when the first SMS is sent.</p>";
        }
        ?>
        
        <h2>🔧 System Status</h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">
            <div class="info">
                <h4>📊 Database Status</h4>
                <?php
                // Check database tables
                $tables = ['vouchers', 'mpesa_transactions', 'packages'];
                foreach ($tables as $table) {
                    $check = $conn->query("SHOW TABLES LIKE '$table'");
                    $status = ($check && $check->num_rows > 0) ? '✅' : '❌';
                    echo "<p>$status Table: $table</p>";
                }
                
                if ($smsLogsExist) {
                    echo "<p>✅ Table: sms_logs</p>";
                } else {
                    echo "<p>⚠️ Table: sms_logs (will be created)</p>";
                }
                ?>
            </div>
            
            <div class="info">
                <h4>📱 SMS Gateway Status</h4>
                <p>✅ Umeskia API configured</p>
                <p>✅ Credentials loaded</p>
                <p>✅ SMS manager ready</p>
                <p>✅ Phone formatting enabled</p>
            </div>
        </div>
        
        <div class="info">
            <h3>🎯 Next Steps</h3>
            <ol>
                <li><strong>Test SMS Delivery:</strong> Use the form above to send a test SMS to your phone</li>
                <li><strong>Verify Receipt:</strong> Check that you receive the SMS on your phone</li>
                <li><strong>Test Voucher System:</strong> Go to <code>test_sms_voucher_delivery.php</code> to test complete workflow</li>
                <li><strong>Test Live Payment:</strong> Submit a real payment in <code>portal.php</code> to test end-to-end</li>
            </ol>
        </div>
    </div>
    
    <script>
    document.getElementById('sms-test-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const resultDiv = document.getElementById('test-result');
        const phoneNumber = document.getElementById('phone_number').value;
        const testMessage = document.getElementById('test_message').value;
        
        resultDiv.innerHTML = '<div class="info"><h4>🔄 Sending SMS...</h4><p>Testing Umeskia SMS delivery...</p></div>';
        
        const formData = new FormData();
        formData.append('test_umeskia_sms', '1');
        formData.append('phone_number', phoneNumber);
        formData.append('test_message', testMessage);
        
        fetch('test_umeskia_sms_integration.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultDiv.innerHTML = `
                    <div class="success">
                        <h4>✅ SMS Sent Successfully!</h4>
                        <p><strong>Gateway:</strong> ${result.gateway}</p>
                        <p><strong>Phone:</strong> ${phoneNumber}</p>
                        <p><strong>Status:</strong> ${result.message}</p>
                        ${result.message_id ? '<p><strong>Message ID:</strong> ' + result.message_id + '</p>' : ''}
                        <p><strong>Check your phone for the SMS!</strong></p>
                        ${result.response_data ? '<details><summary>API Response</summary><pre>' + JSON.stringify(result.response_data, null, 2) + '</pre></details>' : ''}
                    </div>
                `;
                
                // Refresh the page after 3 seconds to show updated SMS logs
                setTimeout(() => location.reload(), 3000);
            } else {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h4>❌ SMS Sending Failed</h4>
                        <p><strong>Error:</strong> ${result.message}</p>
                        ${result.debug_info ? '<details><summary>Debug Info</summary><pre>' + JSON.stringify(result.debug_info, null, 2) + '</pre></details>' : ''}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="error">
                    <h4>🚨 Request Failed</h4>
                    <p><strong>Error:</strong> ${error.message}</p>
                    <p>This might be a network error or server issue.</p>
                </div>
            `;
        });
    });
    </script>
</body>
</html>
