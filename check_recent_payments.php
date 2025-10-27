<?php
require_once 'portal_connection.php';

echo "<h2>Recent Payment Transactions</h2>";

// Check recent payment transactions
$result = $portal_conn->query("SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 10");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Reference</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th><th>Actions</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['reference']) . "</td>";
        echo "<td>" . $row['phone_number'] . "</td>";
        echo "<td>KES " . $row['amount'] . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "<td>";
        if ($row['status'] === 'pending') {
            $callback_url = "paystack_verify.php?reference=" . urlencode($row['reference']);
            echo "<a href='$callback_url' target='_blank' style='color: blue;'>Test Callback</a>";
        } else {
            echo "N/A";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment transactions found.</p>";
}

// Check if there are any vouchers available
echo "<h2>Voucher Availability</h2>";
$voucherResult = $portal_conn->query("
    SELECT v.package_id, p.name as package_name, COUNT(*) as active_count 
    FROM vouchers v 
    LEFT JOIN packages p ON v.package_id = p.id 
    WHERE v.status = 'active' 
    GROUP BY v.package_id, p.name
    ORDER BY active_count DESC
");

if ($voucherResult && $voucherResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Package ID</th><th>Package Name</th><th>Active Vouchers</th>";
    echo "</tr>";
    
    while ($row = $voucherResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['package_id'] . "</td>";
        echo "<td>" . ($row['package_name'] ?: 'Unknown') . "</td>";
        echo "<td>" . $row['active_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No active vouchers found.</p>";
}

// Check SMS settings
echo "<h2>SMS Settings Status</h2>";
require_once 'sms_settings_operations.php';

$smsSettings = getSmsSettings($portal_conn, 1); // Check for reseller ID 1
if ($smsSettings && $smsSettings['enable_sms']) {
    echo "<p>✅ SMS is enabled</p>";
    echo "<p>Provider: " . $smsSettings['sms_provider'] . "</p>";
    echo "<p>API Key: " . (empty($smsSettings['textsms_api_key']) ? '❌ Not set' : '✅ Set') . "</p>";
} else {
    echo "<p>❌ SMS is not configured or disabled</p>";
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ul>";
echo "<li>If you see pending transactions above, click 'Test Callback' to simulate Paystack callback</li>";
echo "<li>Check if vouchers are available for the package you're testing</li>";
echo "<li>Ensure SMS settings are properly configured</li>";
echo "<li>Monitor paystack_verify.log for callback activity</li>";
echo "</ul>";
?>
