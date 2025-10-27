<?php
/**
 * Debug M-Pesa Payment Workflow - Comprehensive Investigation
 */

require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';

echo "<h1>🔍 M-Pesa Payment Workflow Debug</h1>";

// Step 1: Check recent M-Pesa transactions
echo "<h2>Step 1: Recent M-Pesa Transactions</h2>";

$recentTransactions = $portal_conn->query("
    SELECT * FROM mpesa_transactions 
    ORDER BY created_at DESC 
    LIMIT 10
");

if ($recentTransactions && $recentTransactions->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Phone</th><th>Amount</th><th>Status</th><th>Receipt</th><th>Created</th><th>Actions</th>";
    echo "</tr>";
    
    while ($row = $recentTransactions->fetch_assoc()) {
        $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='font-size: 11px;'>" . $row['checkout_request_id'] . "</td>";
        echo "<td>" . $row['phone_number'] . "</td>";
        echo "<td>KES " . $row['amount'] . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
        echo "<td>" . ($row['mpesa_receipt'] ?: 'N/A') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>";
        if ($row['status'] === 'pending') {
            echo "<a href='?test_checkout=" . urlencode($row['checkout_request_id']) . "' style='color: blue;'>Test Status Check</a>";
        } else {
            echo "N/A";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No M-Pesa transactions found.</p>";
}

// Step 2: Check vouchers table
echo "<h2>Step 2: Vouchers Table Status</h2>";

$vouchersTableCheck = $portal_conn->query("SHOW TABLES LIKE 'vouchers'");
if ($vouchersTableCheck && $vouchersTableCheck->num_rows > 0) {
    echo "<p>✅ Vouchers table exists</p>";
    
    // Check voucher availability
    $voucherCount = $portal_conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'");
    if ($voucherCount) {
        $count = $voucherCount->fetch_assoc()['count'];
        echo "<p>📊 Active vouchers available: <strong>$count</strong></p>";
        
        if ($count > 0) {
            // Show sample vouchers
            $sampleVouchers = $portal_conn->query("SELECT * FROM vouchers WHERE status = 'active' LIMIT 5");
            echo "<h4>Sample Active Vouchers:</h4>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr style='background-color: #f2f2f2;'>";
            echo "<th>ID</th><th>Code</th><th>Username</th><th>Password</th><th>Package ID</th><th>Status</th>";
            echo "</tr>";
            
            while ($voucher = $sampleVouchers->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $voucher['id'] . "</td>";
                echo "<td>" . $voucher['code'] . "</td>";
                echo "<td>" . $voucher['username'] . "</td>";
                echo "<td>" . $voucher['password'] . "</td>";
                echo "<td>" . $voucher['package_id'] . "</td>";
                echo "<td>" . $voucher['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} else {
    echo "<p>❌ Vouchers table does NOT exist</p>";
    echo "<p><strong>This is likely the main issue!</strong> The payment workflow expects a vouchers table.</p>";
}

// Step 3: Check SMS settings
echo "<h2>Step 3: SMS Configuration</h2>";

$smsSettings = getSmsSettings($portal_conn, 1); // Check for reseller ID 1
if ($smsSettings && $smsSettings['enable_sms']) {
    echo "<p>✅ SMS is enabled</p>";
    echo "<p>Provider: " . $smsSettings['sms_provider'] . "</p>";
    echo "<p>API Key: " . (empty($smsSettings['textsms_api_key']) ? '❌ Not set' : '✅ Set') . "</p>";
    echo "<p>Partner ID: " . (empty($smsSettings['textsms_partner_id']) ? '❌ Not set' : '✅ Set') . "</p>";
} else {
    echo "<p>❌ SMS is not configured or disabled</p>";
}

// Step 4: Check M-Pesa callback URL configuration
echo "<h2>Step 4: M-Pesa Callback Configuration</h2>";

$mpesaSettings = getMpesaSettings($portal_conn, 1);
if ($mpesaSettings) {
    echo "<p>✅ M-Pesa settings found</p>";
    echo "<p><strong>Callback URL:</strong> " . ($mpesaSettings['callback_url'] ?: 'Not set') . "</p>";
    echo "<p><strong>Environment:</strong> " . $mpesaSettings['environment'] . "</p>";
    echo "<p><strong>Payment Gateway:</strong> " . $mpesaSettings['payment_gateway'] . "</p>";
    
    // Check if callback URL is using ngrok
    if (strpos($mpesaSettings['callback_url'], 'ngrok') !== false) {
        echo "<p>✅ Using ngrok URL for callback</p>";
    } else {
        echo "<p>⚠️ Not using ngrok URL - this might cause callback issues</p>";
    }
} else {
    echo "<p>❌ M-Pesa settings not found</p>";
}

// Step 5: Test payment status check if requested
if (isset($_GET['test_checkout'])) {
    $testCheckoutId = $_GET['test_checkout'];
    echo "<h2>Step 5: Testing Payment Status Check</h2>";
    echo "<p><strong>Testing Checkout Request ID:</strong> $testCheckoutId</p>";
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Simulating 'I have completed payment' button click...</h4>";
    
    // Simulate the AJAX call
    echo "<script>
    fetch('check_payment_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'checkout_request_id=' + encodeURIComponent('$testCheckoutId')
    })
    .then(response => response.json())
    .then(result => {
        document.getElementById('test-result').innerHTML = '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
        
        if (result.success) {
            document.getElementById('test-status').innerHTML = '<p style=\"color: green;\">✅ Payment verification successful!</p>';
        } else {
            document.getElementById('test-status').innerHTML = '<p style=\"color: red;\">❌ Payment verification failed: ' + result.message + '</p>';
        }
    })
    .catch(error => {
        document.getElementById('test-result').innerHTML = '<p style=\"color: red;\">Error: ' + error + '</p>';
        document.getElementById('test-status').innerHTML = '<p style=\"color: red;\">❌ Request failed</p>';
    });
    </script>";
    
    echo "<div id='test-status'>Testing...</div>";
    echo "<div id='test-result'>Loading...</div>";
    echo "</div>";
}

// Step 6: Check recent logs
echo "<h2>Step 6: Recent Payment Status Check Logs</h2>";

if (file_exists('payment_status_checks.log')) {
    $logs = file_get_contents('payment_status_checks.log');
    $logLines = explode("\n", $logs);
    $recentLogs = array_slice($logLines, -20); // Last 20 lines
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 300px; overflow-y: auto;'>";
    foreach ($recentLogs as $line) {
        if (!empty(trim($line))) {
            // Highlight important messages
            $line = htmlspecialchars($line);
            if (strpos($line, 'successful') !== false) {
                $line = "<span style='color: green;'>$line</span>";
            } elseif (strpos($line, 'Error') !== false || strpos($line, 'failed') !== false) {
                $line = "<span style='color: red;'>$line</span>";
            } elseif (strpos($line, 'pending') !== false) {
                $line = "<span style='color: orange;'>$line</span>";
            }
            echo $line . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<p>No payment status check logs found.</p>";
}

// Step 7: Check M-Pesa callback logs
echo "<h2>Step 7: M-Pesa Callback Logs</h2>";

if (file_exists('mpesa_callback.log')) {
    $callbackLogs = file_get_contents('mpesa_callback.log');
    $callbackLines = explode("\n", $callbackLogs);
    $recentCallbackLogs = array_slice($callbackLines, -10); // Last 10 lines
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; max-height: 200px; overflow-y: auto;'>";
    foreach ($recentCallbackLogs as $line) {
        if (!empty(trim($line))) {
            echo htmlspecialchars($line) . "<br>";
        }
    }
    echo "</div>";
} else {
    echo "<p>No M-Pesa callback logs found.</p>";
}

echo "<h2>Summary & Next Steps</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🔍 Key Findings:</h3>";
echo "<ul>";
echo "<li><strong>Callback URL:</strong> " . (isset($mpesaSettings['callback_url']) && strpos($mpesaSettings['callback_url'], 'ngrok') !== false ? '✅ Properly configured with ngrok' : '❌ May need ngrok URL update') . "</li>";
echo "<li><strong>Vouchers Table:</strong> " . ($vouchersTableCheck && $vouchersTableCheck->num_rows > 0 ? '✅ Exists' : '❌ Missing - this is likely the main issue') . "</li>";
echo "<li><strong>SMS Configuration:</strong> " . (isset($smsSettings) && $smsSettings['enable_sms'] ? '✅ Configured' : '❌ Not configured') . "</li>";
echo "</ul>";
echo "</div>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
a { color: #007cba; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
