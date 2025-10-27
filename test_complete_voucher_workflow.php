<?php
/**
 * Test Complete Voucher Workflow
 * 
 * This tests the entire workflow:
 * 1. Check for completed payments
 * 2. Assign vouchers
 * 3. Send SMS via Umeskia
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';
require_once 'auto_process_vouchers.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Complete Voucher Workflow</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1, h2, h3 { color: #333; }
        .status-box { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .error { background: #fef2f2; border: 1px solid #ef4444; color: #dc2626; }
        .info { background: #f0f9ff; border: 1px solid #0ea5e9; color: #0c4a6e; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .action-button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .action-button:hover { background: #059669; }
        .action-button.secondary { background: #3b82f6; }
        .action-button.secondary:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Complete Voucher Workflow</h1>
        
        <div class="info status-box">
            <h3>📋 System Overview</h3>
            <p><strong>Purpose:</strong> Test the complete voucher assignment and SMS delivery workflow</p>
            <p><strong>SMS Gateway:</strong> Umeskia (UMS_SMS)</p>
            <p><strong>Integration:</strong> M-Pesa Callback → Auto Process Vouchers → Umeskia SMS</p>
        </div>
        
        <h2>📊 Step 1: Check Completed Payments</h2>
        
        <?php
        // Find completed payments needing vouchers
        $sql = "SELECT 
                    id,
                    checkout_request_id,
                    phone_number,
                    package_id,
                    package_name,
                    reseller_id,
                    amount,
                    mpesa_receipt,
                    voucher_code,
                    status,
                    created_at,
                    updated_at
                FROM mpesa_transactions 
                WHERE status = 'completed'
                ORDER BY updated_at DESC
                LIMIT 20";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $completedCount = 0;
            $needVoucherCount = 0;
            $hasVoucherCount = 0;
            
            echo "<table>";
            echo "<tr>";
            echo "<th>Checkout ID</th>";
            echo "<th>Phone</th>";
            echo "<th>Package</th>";
            echo "<th>Amount</th>";
            echo "<th>Voucher Code</th>";
            echo "<th>Status</th>";
            echo "<th>Action</th>";
            echo "</tr>";
            
            while ($txn = $result->fetch_assoc()) {
                $completedCount++;
                $shortCheckoutId = substr($txn['checkout_request_id'], 0, 20) . "...";
                $hasVoucher = !empty($txn['voucher_code']);
                
                if ($hasVoucher) {
                    $hasVoucherCount++;
                    $voucherDisplay = $txn['voucher_code'];
                    $statusColor = 'green';
                    $statusText = '✅ Has Voucher';
                    $actionButton = '<button onclick="resendSms(\'' . $txn['checkout_request_id'] . '\', \'' . $txn['voucher_code'] . '\', \'' . $txn['phone_number'] . '\')" class="action-button secondary" style="padding: 5px 10px; font-size: 12px;">📱 Resend SMS</button>';
                } else {
                    $needVoucherCount++;
                    $voucherDisplay = '<span style="color: red;">❌ No Voucher</span>';
                    $statusColor = 'red';
                    $statusText = '⚠️ Needs Voucher';
                    $actionButton = '<button onclick="processVoucher(\'' . $txn['checkout_request_id'] . '\')" class="action-button" style="padding: 5px 10px; font-size: 12px;">🎯 Process Now</button>';
                }
                
                echo "<tr>";
                echo "<td title='{$txn['checkout_request_id']}'>$shortCheckoutId</td>";
                echo "<td>{$txn['phone_number']}</td>";
                echo "<td>{$txn['package_name']}</td>";
                echo "<td>KES {$txn['amount']}</td>";
                echo "<td>$voucherDisplay</td>";
                echo "<td style='color: $statusColor; font-weight: bold;'>$statusText</td>";
                echo "<td>$actionButton</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<div class='info status-box'>";
            echo "<h4>📈 Summary:</h4>";
            echo "<p><strong>Total Completed Payments:</strong> $completedCount</p>";
            echo "<p><strong>Already Have Vouchers:</strong> $hasVoucherCount</p>";
            echo "<p><strong>Need Vouchers:</strong> $needVoucherCount</p>";
            echo "</div>";
            
            if ($needVoucherCount > 0) {
                echo "<div class='warning status-box'>";
                echo "<h4>⚠️ Action Required:</h4>";
                echo "<p>There are <strong>$needVoucherCount</strong> completed payments that need vouchers assigned.</p>";
                echo "<button onclick='processAllPending()' class='action-button'>🔄 Process All Pending Vouchers</button>";
                echo "</div>";
            } else {
                echo "<div class='success status-box'>";
                echo "<h4>✅ All Good!</h4>";
                echo "<p>All completed payments have vouchers assigned.</p>";
                echo "</div>";
            }
            
        } else {
            echo "<div class='warning status-box'>";
            echo "<p>⚠️ No completed payments found in the database.</p>";
            echo "</div>";
        }
        ?>
        
        <h2>🔍 Step 2: Check Available Vouchers</h2>
        
        <?php
        // Check available vouchers by package
        $voucherSql = "SELECT 
                        v.package_id,
                        p.name as package_name,
                        v.reseller_id,
                        COUNT(*) as available_count
                    FROM vouchers v
                    LEFT JOIN packages p ON v.package_id = p.id
                    WHERE v.status = 'active'
                    GROUP BY v.package_id, v.reseller_id, p.name
                    ORDER BY v.package_id, v.reseller_id";
        
        $voucherResult = $conn->query($voucherSql);
        
        if ($voucherResult && $voucherResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Package ID</th><th>Package Name</th><th>Reseller ID</th><th>Available Vouchers</th></tr>";
            
            $totalAvailable = 0;
            while ($vRow = $voucherResult->fetch_assoc()) {
                $totalAvailable += $vRow['available_count'];
                echo "<tr>";
                echo "<td>{$vRow['package_id']}</td>";
                echo "<td>{$vRow['package_name']}</td>";
                echo "<td>{$vRow['reseller_id']}</td>";
                echo "<td><strong>{$vRow['available_count']}</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='success status-box'>";
            echo "<p><strong>Total Available Vouchers:</strong> $totalAvailable</p>";
            echo "</div>";
        } else {
            echo "<div class='error status-box'>";
            echo "<p>❌ No active vouchers found in the database!</p>";
            echo "<p>Please generate vouchers before processing payments.</p>";
            echo "</div>";
        }
        ?>
        
        <h2>📱 Step 3: Test SMS Sending</h2>
        
        <div class="info status-box">
            <h4>🧪 Quick SMS Test</h4>
            <p>Test Umeskia SMS sending directly:</p>
            <a href="umeskia_sms.php?test=1&phone=0750059353&message=Test from voucher workflow" target="_blank" class="action-button secondary">📤 Test SMS</a>
        </div>
        
        <h2>📋 Step 4: Check Logs</h2>
        
        <?php
        // Check recent log entries
        $logFiles = [
            'fetch_vouchers.log' => 'Voucher Processing',
            'umeskia_sms.log' => 'SMS Sending',
            'mpesa_callback.log' => 'M-Pesa Callback'
        ];
        
        foreach ($logFiles as $logFile => $logName) {
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $logLines = explode("\n", $logContent);
                $recentLogs = array_slice(array_reverse($logLines), 0, 5);
                
                echo "<div class='info status-box'>";
                echo "<h4>📄 $logName ($logFile)</h4>";
                echo "<div style='background: white; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px;'>";
                
                foreach ($recentLogs as $line) {
                    if (trim($line)) {
                        $color = 'black';
                        if (strpos($line, 'ERROR') !== false || strpos($line, '❌') !== false) $color = 'red';
                        elseif (strpos($line, 'SUCCESS') !== false || strpos($line, '✅') !== false) $color = 'green';
                        
                        echo "<div style='color: $color; margin: 2px 0;'>" . htmlspecialchars($line) . "</div>";
                    }
                }
                echo "</div>";
                echo "</div>";
            }
        }
        ?>
        
        <div id="result-area"></div>
    </div>
    
    <script>
    function processVoucher(checkoutRequestId) {
        const resultArea = document.getElementById('result-area');
        resultArea.innerHTML = '<div class="info status-box"><h4>🔄 Processing...</h4><p>Assigning voucher and sending SMS...</p></div>';
        
        const formData = new FormData();
        formData.append('process_specific', '1');
        formData.append('checkout_request_id', checkoutRequestId);
        
        fetch('auto_process_vouchers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultArea.innerHTML = `
                    <div class="success status-box">
                        <h4>✅ Success!</h4>
                        <p><strong>Voucher Code:</strong> ${result.voucher.code}</p>
                        <p><strong>Customer Phone:</strong> ${result.transaction.phone_number}</p>
                        <p><strong>Package:</strong> ${result.transaction.package_name}</p>
                        <p><strong>SMS Status:</strong> ${result.sms_result.success ? '✅ Sent successfully' : '❌ Failed - ' + result.sms_result.message}</p>
                    </div>
                `;
                setTimeout(() => location.reload(), 2000);
            } else {
                resultArea.innerHTML = `
                    <div class="error status-box">
                        <h4>❌ Failed</h4>
                        <p>${result.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultArea.innerHTML = `
                <div class="error status-box">
                    <h4>🚨 Request Failed</h4>
                    <p>${error.message}</p>
                </div>
            `;
        });
    }
    
    function processAllPending() {
        const resultArea = document.getElementById('result-area');
        resultArea.innerHTML = '<div class="info status-box"><h4>🔄 Processing All Pending...</h4><p>This may take a moment...</p></div>';
        
        const formData = new FormData();
        formData.append('process_vouchers', '1');
        
        fetch('fetch_umeskia_vouchers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const r = result.results;
                resultArea.innerHTML = `
                    <div class="success status-box">
                        <h4>✅ Bulk Processing Complete</h4>
                        <p><strong>Processed:</strong> ${r.processed}</p>
                        <p><strong>Successful:</strong> ${r.successful}</p>
                        <p><strong>Failed:</strong> ${r.failed}</p>
                    </div>
                `;
                setTimeout(() => location.reload(), 2000);
            } else {
                resultArea.innerHTML = `
                    <div class="error status-box">
                        <h4>❌ Processing Failed</h4>
                        <p>${result.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultArea.innerHTML = `
                <div class="error status-box">
                    <h4>🚨 Request Failed</h4>
                    <p>${error.message}</p>
                </div>
            `;
        });
    }
    
    function resendSms(checkoutRequestId, voucherCode, phone) {
        const resultArea = document.getElementById('result-area');
        resultArea.innerHTML = '<div class="info status-box"><h4>📱 Resending SMS...</h4></div>';
        
        // For resending, we can use the SMS function directly
        alert('Resend SMS feature: Would send voucher ' + voucherCode + ' to ' + phone);
        // You can implement this by calling umeskia_sms.php with the voucher details
    }
    </script>
</body>
</html>
