<?php
/**
 * Test Voucher Fetching - Simulate the exact process that happens when customer clicks "I've Completed Payment"
 */

require_once 'portal_connection.php';
require_once 'vouchers_script/payment_voucher_handler.php';

echo "<h1>🧪 Test Voucher Fetching Process</h1>";

// Step 1: Find a completed transaction to test with
echo "<h2>Step 1: Find Completed Transaction</h2>";

$completedTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");

if ($completedTxn && $completedTxn->num_rows > 0) {
    $transaction = $completedTxn->fetch_assoc();
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Testing with Transaction:</h3>";
    echo "<p><strong>Checkout Request ID:</strong> " . $transaction['checkout_request_id'] . "</p>";
    echo "<p><strong>Package ID:</strong> " . $transaction['package_id'] . "</p>";
    echo "<p><strong>Reseller ID:</strong> " . $transaction['reseller_id'] . "</p>";
    echo "<p><strong>Phone Number:</strong> " . $transaction['phone_number'] . "</p>";
    echo "<p><strong>Status:</strong> " . $transaction['status'] . "</p>";
    echo "<p><strong>M-Pesa Receipt:</strong> " . ($transaction['mpesa_receipt'] ?: 'N/A') . "</p>";
    echo "</div>";
    
    // Step 2: Test the voucher handler function
    echo "<h2>Step 2: Test Voucher Handler Function</h2>";
    
    echo "<div id='voucher-test-result'>Testing...</div>";
    
    echo "<script>
    // Simulate the exact process from check_payment_status.php
    console.log('Starting voucher handler test...');
    
    // This simulates what happens in check_payment_status.php
    fetch('test_voucher_handler_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'checkout_request_id=' + encodeURIComponent('" . $transaction['checkout_request_id'] . "')
    })
    .then(response => response.json())
    .then(result => {
        const resultDiv = document.getElementById('voucher-test-result');
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #065f46; margin-top: 0;'>✅ Voucher Handler Success!</h4>
                    <p><strong>Voucher Code:</strong> <code>\${result.voucher_code}</code></p>
                    <p><strong>Username:</strong> <code>\${result.voucher_username}</code></p>
                    <p><strong>Password:</strong> <code>\${result.voucher_password}</code></p>
                    <p><strong>Package:</strong> \${result.package_name}</p>
                    <p><strong>Duration:</strong> \${result.duration || 'N/A'}</p>
                    <p><strong>Phone:</strong> \${result.phone_number}</p>
                    <p><strong>Receipt:</strong> \${result.receipt || 'N/A'}</p>
                    <p><strong>Message:</strong> \${result.message}</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #dc2626; margin-top: 0;'>❌ Voucher Handler Failed</h4>
                    <p><strong>Error:</strong> \${result.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('voucher-test-result').innerHTML = `
            <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                <h4 style='color: #dc2626; margin-top: 0;'>❌ Request Failed</h4>
                <p><strong>Error:</strong> \${error}</p>
            </div>
        `;
    });
    </script>";
    
} else {
    echo "<p style='color: red;'>❌ No completed transactions found to test with.</p>";
    echo "<p>You need to have at least one completed M-Pesa transaction to test the voucher fetching.</p>";
}

// Step 3: Show voucher availability
echo "<h2>Step 3: Check Voucher Availability</h2>";

$voucherCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($voucherCheck && $voucherCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
    
    $activeVouchers = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'");
    if ($activeVouchers) {
        $count = $activeVouchers->fetch_assoc()['count'];
        if ($count > 0) {
            echo "<p style='color: green;'>✅ $count active vouchers available</p>";
        } else {
            echo "<p style='color: red;'>❌ No active vouchers available</p>";
            echo "<p>You need to create some active vouchers for the system to work.</p>";
        }
    }
    
    // Show vouchers by package
    $vouchersByPackage = $conn->query("
        SELECT v.package_id, p.name as package_name, COUNT(*) as count, v.status 
        FROM vouchers v 
        LEFT JOIN packages p ON v.package_id = p.id 
        GROUP BY v.package_id, v.status 
        ORDER BY v.package_id, v.status
    ");
    
    if ($vouchersByPackage && $vouchersByPackage->num_rows > 0) {
        echo "<h3>Vouchers by Package:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Package ID</th><th>Package Name</th><th>Status</th><th>Count</th>";
        echo "</tr>";
        
        while ($row = $vouchersByPackage->fetch_assoc()) {
            $statusColor = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
            echo "<tr>";
            echo "<td>" . $row['package_id'] . "</td>";
            echo "<td>" . ($row['package_name'] ?: 'Unknown') . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "<td>" . $row['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Vouchers table does not exist</p>";
    echo "<p>This is why the voucher fetching is failing. You need to create the vouchers table first.</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
code { background: #f3f4f6; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
</style>
