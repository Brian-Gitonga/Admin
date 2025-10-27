<?php
require_once 'portal_connection.php';

echo "<h2>Voucher Availability Check</h2>";

// Check vouchers table
$result = $portal_conn->query("SELECT COUNT(*) as total FROM vouchers WHERE status = 'active'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "<p><strong>Total active vouchers:</strong> " . $row['total'] . "</p>";
} else {
    echo "<p>Error checking vouchers: " . $portal_conn->error . "</p>";
}

// Check vouchers by package
$result2 = $portal_conn->query("
    SELECT v.package_id, p.name as package_name, COUNT(*) as voucher_count 
    FROM vouchers v 
    LEFT JOIN packages p ON v.package_id = p.id 
    WHERE v.status = 'active' 
    GROUP BY v.package_id, p.name
    ORDER BY voucher_count DESC
");

if ($result2) {
    echo "<h3>Active vouchers by package:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Package ID</th><th>Package Name</th><th>Voucher Count</th></tr>";
    
    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['package_id'] . "</td>";
        echo "<td>" . ($row['package_name'] ?: 'Unknown') . "</td>";
        echo "<td>" . $row['voucher_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error checking vouchers by package: " . $portal_conn->error . "</p>";
}

// Check recent transactions
$result3 = $portal_conn->query("SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 3");
if ($result3) {
    echo "<h3>Recent payment transactions:</h3>";
    echo "<table border='1' cellpadding='5'>";
    $first = true;
    while ($row = $result3->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No transactions found or error: " . $portal_conn->error . "</p>";
}

// Test voucher fetching
echo "<h3>Test Voucher Fetching:</h3>";
$testPackageId = 1;
$testRouterId = 1;
$testPhone = '254700123456';

require_once 'fetch_voucher.php';

$testVoucher = getVoucherForPayment($testPackageId, $testRouterId, $testPhone, 'TEST_' . time());

if ($testVoucher) {
    echo "<p><strong>✅ Test voucher fetched successfully:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Code:</strong> " . $testVoucher['code'] . "</li>";
    echo "<li><strong>Username:</strong> " . ($testVoucher['username'] ?: $testVoucher['code']) . "</li>";
    echo "<li><strong>Password:</strong> " . ($testVoucher['password'] ?: $testVoucher['code']) . "</li>";
    echo "<li><strong>Package ID:</strong> " . $testVoucher['package_id'] . "</li>";
    echo "<li><strong>Status:</strong> " . $testVoucher['status'] . "</li>";
    echo "</ul>";
} else {
    echo "<p><strong>❌ No voucher available for test (Package ID: $testPackageId, Router ID: $testRouterId)</strong></p>";
}
?>
