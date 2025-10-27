<?php
/**
 * Test Payment Completion Workflow
 */

require_once 'portal_connection.php';

echo "<h1>🧪 Test Payment Completion Workflow</h1>";

// Step 1: Check if we have any pending M-Pesa transactions
echo "<h2>Step 1: Check Pending Transactions</h2>";

$pendingTransactions = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");

if ($pendingTransactions && $pendingTransactions->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Phone</th><th>Amount</th><th>Package ID</th><th>Action</th>";
    echo "</tr>";
    
    while ($row = $pendingTransactions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='font-size: 11px;'>" . substr($row['checkout_request_id'], 0, 25) . "...</td>";
        echo "<td>" . $row['phone_number'] . "</td>";
        echo "<td>KES " . $row['amount'] . "</td>";
        echo "<td>" . $row['package_id'] . "</td>";
        echo "<td><a href='?test_checkout_id=" . urlencode($row['checkout_request_id']) . "' style='color: blue;'>Test This Transaction</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No pending transactions found.</p>";
    echo "<p><a href='?create_test_transaction=1' style='color: blue;'>Create Test Transaction</a></p>";
}

// Step 2: Check available vouchers
echo "<h2>Step 2: Check Available Vouchers</h2>";

$availableVouchers = $conn->query("SELECT v.*, p.name as package_name FROM vouchers v LEFT JOIN packages p ON v.package_id = p.id WHERE v.status = 'active' ORDER BY v.package_id, v.created_at LIMIT 10");

if ($availableVouchers && $availableVouchers->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Code</th><th>Package</th><th>Username</th><th>Password</th><th>Status</th>";
    echo "</tr>";
    
    while ($row = $availableVouchers->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='font-family: monospace;'>" . $row['code'] . "</td>";
        echo "<td>" . $row['package_name'] . " (ID: " . $row['package_id'] . ")</td>";
        echo "<td style='font-family: monospace;'>" . $row['username'] . "</td>";
        echo "<td style='font-family: monospace;'>" . $row['password'] . "</td>";
        echo "<td style='color: green; font-weight: bold;'>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No active vouchers available!</p>";
    echo "<p>You need to create some vouchers first for the payment completion to work.</p>";
}

// Handle test transaction creation
if (isset($_GET['create_test_transaction'])) {
    echo "<h3>Creating Test Transaction...</h3>";
    
    // Create a test transaction
    $checkoutId = 'ws_CO_TEST_' . date('YmdHis') . rand(100, 999);
    $merchantId = 'ws_MR_TEST_' . date('YmdHis') . rand(100, 999);
    $phone = '254700123456';
    $amount = 10.00;
    $packageId = 1; // Assuming package ID 1 exists
    $resellerId = 1; // Assuming reseller ID 1 exists
    
    $insertSQL = "INSERT INTO mpesa_transactions (
        checkout_request_id, merchant_request_id, amount, phone_number, 
        package_id, reseller_id, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $conn->prepare($insertSQL);
    if ($stmt) {
        $stmt->bind_param("ssdsii", $checkoutId, $merchantId, $amount, $phone, $packageId, $resellerId);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Test transaction created successfully!</p>";
            echo "<p><strong>Checkout Request ID:</strong> $checkoutId</p>";
            echo "<p><a href='?test_checkout_id=" . urlencode($checkoutId) . "' style='color: blue;'>Test This Transaction</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create test transaction: " . $stmt->error . "</p>";
        }
    }
}

// Handle payment completion test
if (isset($_GET['test_checkout_id'])) {
    $testCheckoutId = $_GET['test_checkout_id'];
    
    echo "<h2>Step 3: Test Payment Completion</h2>";
    echo "<p><strong>Testing Checkout Request ID:</strong> $testCheckoutId</p>";
    
    // Simulate the check_payment_status.php request
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Simulating Payment Status Check...</h4>";
    echo "<div id='test-result'>Loading...</div>";
    echo "</div>";
    
    echo "<script>
    // Simulate the AJAX request that portal.php makes
    fetch('check_payment_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'checkout_request_id=' + encodeURIComponent('$testCheckoutId')
    })
    .then(response => response.json())
    .then(result => {
        const resultDiv = document.getElementById('test-result');
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='color: green;'>
                    <h5>✅ Payment Completion Successful!</h5>
                    <p><strong>Voucher Code:</strong> \${result.voucher_code}</p>
                    <p><strong>Username:</strong> \${result.voucher_username || result.voucher_code}</p>
                    <p><strong>Password:</strong> \${result.voucher_password || result.voucher_code}</p>
                    <p><strong>Package:</strong> \${result.package_name || 'N/A'}</p>
                    <p><strong>Phone:</strong> \${result.phone_number}</p>
                    <p><strong>Receipt:</strong> \${result.receipt || 'N/A'}</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='color: red;'>
                    <h5>❌ Payment Completion Failed</h5>
                    <p><strong>Error:</strong> \${result.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('test-result').innerHTML = `
            <div style='color: red;'>
                <h5>❌ Request Failed</h5>
                <p><strong>Error:</strong> \${error}</p>
            </div>
        `;
    });
    </script>";
}

// Step 4: Show logs
echo "<h2>Step 4: Recent Logs</h2>";

echo "<h3>Payment Status Check Logs (Last 10 lines):</h3>";
if (file_exists('payment_status_checks.log')) {
    $logs = file('payment_status_checks.log');
    $recentLogs = array_slice($logs, -10);
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; overflow-x: auto;'>";
    echo htmlspecialchars(implode('', $recentLogs));
    echo "</pre>";
} else {
    echo "<p>No payment status check logs found.</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
</style>
