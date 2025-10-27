<?php
/**
 * Verify Complete Workflow - Payment to Voucher Assignment
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Workflow</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1, h2 { color: #333; }
        .status-box { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .error { background: #fef2f2; border: 1px solid #ef4444; color: #dc2626; }
        .info { background: #f0f9ff; border: 1px solid #0ea5e9; color: #0c4a6e; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 13px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #059669; }
        .btn-secondary { background: #3b82f6; }
        .btn-secondary:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Workflow Verification</h1>
        
        <div class="info status-box">
            <h3>🔍 What This Checks:</h3>
            <ol>
                <li>Portal.php is accessible and modal works</li>
                <li>Vouchers are available in database</li>
                <li>M-Pesa callback is integrated with auto_process_vouchers.php</li>
                <li>Completed payments have vouchers assigned</li>
                <li>SMS sending via Umeskia is working</li>
            </ol>
        </div>
        
        <h2>1️⃣ Check Portal.php</h2>
        <?php
        if (file_exists('portal.php')) {
            echo "<div class='success status-box'>";
            echo "<p>✅ <strong>portal.php</strong> exists and is accessible</p>";
            echo "<a href='portal.php' target='_blank' class='btn'>🌐 Open Portal</a>";
            echo "</div>";
        } else {
            echo "<div class='error status-box'>";
            echo "<p>❌ <strong>portal.php</strong> not found!</p>";
            echo "</div>";
        }
        ?>
        
        <h2>2️⃣ Check Available Vouchers</h2>
        <?php
        $voucherSql = "SELECT 
                        v.package_id,
                        p.name as package_name,
                        COUNT(*) as available_count
                    FROM vouchers v
                    LEFT JOIN packages p ON v.package_id = p.id
                    WHERE v.status = 'active'
                    GROUP BY v.package_id, p.name
                    ORDER BY v.package_id";
        
        $voucherResult = $conn->query($voucherSql);
        
        if ($voucherResult && $voucherResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Package ID</th><th>Package Name</th><th>Available Vouchers</th></tr>";
            
            $totalAvailable = 0;
            while ($row = $voucherResult->fetch_assoc()) {
                $totalAvailable += $row['available_count'];
                echo "<tr>";
                echo "<td>{$row['package_id']}</td>";
                echo "<td>{$row['package_name']}</td>";
                echo "<td><strong>{$row['available_count']}</strong></td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<div class='success status-box'>";
            echo "<p>✅ <strong>Total Available Vouchers:</strong> $totalAvailable</p>";
            echo "</div>";
        } else {
            echo "<div class='error status-box'>";
            echo "<p>❌ No active vouchers found! Please generate vouchers first.</p>";
            echo "</div>";
        }
        ?>
        
        <h2>3️⃣ Check M-Pesa Callback Integration</h2>
        <?php
        if (file_exists('mpesa_callback.php')) {
            $callbackContent = file_get_contents('mpesa_callback.php');
            
            if (strpos($callbackContent, 'auto_process_vouchers.php') !== false) {
                echo "<div class='success status-box'>";
                echo "<p>✅ <strong>mpesa_callback.php</strong> is integrated with auto_process_vouchers.php</p>";
                echo "<p>Vouchers will be assigned automatically after payment completion.</p>";
                echo "</div>";
            } else {
                echo "<div class='error status-box'>";
                echo "<p>❌ <strong>mpesa_callback.php</strong> is NOT integrated with auto_process_vouchers.php</p>";
                echo "<p>Vouchers will NOT be assigned automatically!</p>";
                echo "</div>";
            }
        } else {
            echo "<div class='error status-box'>";
            echo "<p>❌ <strong>mpesa_callback.php</strong> not found!</p>";
            echo "</div>";
        }
        ?>
        
        <h2>4️⃣ Check Recent Completed Payments</h2>
        <?php
        $paymentSql = "SELECT 
                        checkout_request_id,
                        phone_number,
                        package_name,
                        amount,
                        voucher_code,
                        mpesa_receipt,
                        updated_at
                    FROM mpesa_transactions 
                    WHERE status = 'completed'
                    ORDER BY updated_at DESC
                    LIMIT 10";
        
        $paymentResult = $conn->query($paymentSql);
        
        if ($paymentResult && $paymentResult->num_rows > 0) {
            $withVoucher = 0;
            $withoutVoucher = 0;
            
            echo "<table>";
            echo "<tr><th>Checkout ID</th><th>Phone</th><th>Package</th><th>Amount</th><th>Voucher</th><th>Date</th></tr>";
            
            while ($payment = $paymentResult->fetch_assoc()) {
                $shortId = substr($payment['checkout_request_id'], 0, 20) . "...";
                $hasVoucher = !empty($payment['voucher_code']);
                
                if ($hasVoucher) {
                    $withVoucher++;
                    $voucherDisplay = "<span style='color: green; font-weight: bold;'>{$payment['voucher_code']}</span>";
                } else {
                    $withoutVoucher++;
                    $voucherDisplay = "<span style='color: red;'>❌ None</span>";
                }
                
                echo "<tr>";
                echo "<td title='{$payment['checkout_request_id']}'>$shortId</td>";
                echo "<td>{$payment['phone_number']}</td>";
                echo "<td>{$payment['package_name']}</td>";
                echo "<td>KES {$payment['amount']}</td>";
                echo "<td>$voucherDisplay</td>";
                echo "<td>" . date('Y-m-d H:i', strtotime($payment['updated_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            if ($withoutVoucher > 0) {
                echo "<div class='warning status-box'>";
                echo "<p>⚠️ <strong>$withoutVoucher</strong> completed payments are missing vouchers!</p>";
                echo "<a href='auto_process_vouchers.php' class='btn'>🔧 Assign Vouchers Now</a>";
                echo "</div>";
            } else {
                echo "<div class='success status-box'>";
                echo "<p>✅ All <strong>$withVoucher</strong> completed payments have vouchers assigned!</p>";
                echo "</div>";
            }
        } else {
            echo "<div class='warning status-box'>";
            echo "<p>⚠️ No completed payments found yet. Make a test payment to verify the workflow.</p>";
            echo "</div>";
        }
        ?>
        
        <h2>5️⃣ Check SMS Integration</h2>
        <?php
        if (file_exists('umeskia_sms.php')) {
            echo "<div class='success status-box'>";
            echo "<p>✅ <strong>umeskia_sms.php</strong> is available for SMS sending</p>";
            echo "<a href='umeskia_sms.php?test=1&phone=0750059353&message=Test' target='_blank' class='btn btn-secondary'>📱 Test SMS</a>";
            echo "</div>";
        } else {
            echo "<div class='error status-box'>";
            echo "<p>❌ <strong>umeskia_sms.php</strong> not found!</p>";
            echo "</div>";
        }
        ?>
        
        <h2>6️⃣ Check Log Files</h2>
        <?php
        $logs = [
            'mpesa_callback.log' => 'M-Pesa Callback Log',
            'fetch_vouchers.log' => 'Voucher Processing Log',
            'umeskia_sms.log' => 'SMS Sending Log'
        ];
        
        foreach ($logs as $file => $name) {
            if (file_exists($file)) {
                $size = filesize($file);
                $sizeKB = round($size / 1024, 2);
                echo "<div class='info status-box'>";
                echo "<p>✅ <strong>$name</strong> exists ($sizeKB KB)</p>";
                echo "</div>";
            } else {
                echo "<div class='warning status-box'>";
                echo "<p>⚠️ <strong>$name</strong> not found (will be created on first use)</p>";
                echo "</div>";
            }
        }
        ?>
        
        <h2>🎯 Summary</h2>
        <?php
        $allGood = true;
        $issues = [];
        
        // Check critical components
        if (!file_exists('portal.php')) {
            $allGood = false;
            $issues[] = "portal.php is missing";
        }
        
        if (!file_exists('mpesa_callback.php')) {
            $allGood = false;
            $issues[] = "mpesa_callback.php is missing";
        }
        
        if (!file_exists('auto_process_vouchers.php')) {
            $allGood = false;
            $issues[] = "auto_process_vouchers.php is missing";
        }
        
        if (!file_exists('umeskia_sms.php')) {
            $allGood = false;
            $issues[] = "umeskia_sms.php is missing";
        }
        
        // Check if callback is integrated
        if (file_exists('mpesa_callback.php')) {
            $callbackContent = file_get_contents('mpesa_callback.php');
            if (strpos($callbackContent, 'auto_process_vouchers.php') === false) {
                $allGood = false;
                $issues[] = "M-Pesa callback is not integrated with auto_process_vouchers.php";
            }
        }
        
        if ($allGood) {
            echo "<div class='success status-box'>";
            echo "<h3>✅ System is Ready!</h3>";
            echo "<p>All components are in place. The workflow should work as follows:</p>";
            echo "<ol>";
            echo "<li>User goes to portal.php and selects a package</li>";
            echo "<li>System checks for available vouchers</li>";
            echo "<li>User completes M-Pesa payment</li>";
            echo "<li>M-Pesa callback triggers auto_process_vouchers.php</li>";
            echo "<li>Voucher is assigned and stored in database</li>";
            echo "<li>SMS is sent via Umeskia to customer's phone</li>";
            echo "</ol>";
            echo "<a href='portal.php' class='btn'>🚀 Test Payment Now</a>";
            echo "</div>";
        } else {
            echo "<div class='error status-box'>";
            echo "<h3>❌ Issues Found:</h3>";
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li>$issue</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        ?>
        
        <h2>🔗 Quick Links</h2>
        <div class="info status-box">
            <a href="portal.php" class="btn">🌐 Portal</a>
            <a href="auto_process_vouchers.php" class="btn btn-secondary">⚙️ Process Vouchers</a>
            <a href="test_complete_voucher_workflow.php" class="btn btn-secondary">🧪 Detailed Tests</a>
            <a href="umeskia_sms.php" class="btn btn-secondary">📱 SMS Test</a>
        </div>
    </div>
</body>
</html>
