<?php
/**
 * Debug Voucher Issue - Check what's happening with voucher fetching
 */

require_once 'portal_connection.php';
require_once 'vouchers_script/payment_voucher_handler.php';

echo "<h1>🔍 Debug Voucher Issue</h1>";

// Step 1: Check if vouchers table exists
echo "<h2>Step 1: Check Vouchers Table</h2>";

$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE vouchers");
    if ($structure) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th>";
        echo "</tr>";
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check available vouchers
    $availableVouchers = $conn->query("SELECT COUNT(*) as total, status FROM vouchers GROUP BY status");
    if ($availableVouchers) {
        echo "<h3>Voucher Status Summary:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Status</th><th>Count</th>";
        echo "</tr>";
        
        while ($row = $availableVouchers->fetch_assoc()) {
            $color = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
            echo "<tr>";
            echo "<td style='color: $color; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "<td>" . $row['total'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show sample active vouchers
    $sampleVouchers = $conn->query("SELECT * FROM vouchers WHERE status = 'active' ORDER BY package_id, created_at LIMIT 5");
    if ($sampleVouchers && $sampleVouchers->num_rows > 0) {
        echo "<h3>Sample Active Vouchers:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>ID</th><th>Code</th><th>Package ID</th><th>Username</th><th>Password</th><th>Status</th>";
        echo "</tr>";
        
        while ($row = $sampleVouchers->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td style='font-family: monospace;'>" . $row['code'] . "</td>";
            echo "<td>" . $row['package_id'] . "</td>";
            echo "<td style='font-family: monospace;'>" . ($row['username'] ?: 'N/A') . "</td>";
            echo "<td style='font-family: monospace;'>" . ($row['password'] ?: 'N/A') . "</td>";
            echo "<td style='color: green; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No active vouchers found!</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Vouchers table does NOT exist!</p>";
    echo "<p>This explains why the voucher fetching is failing.</p>";
}

// Step 2: Check recent completed transactions
echo "<h2>Step 2: Check Recent Completed Transactions</h2>";

$completedTransactions = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 5");
if ($completedTransactions && $completedTransactions->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Phone</th><th>Package ID</th><th>Voucher Code</th><th>Status</th><th>Updated</th>";
    echo "</tr>";
    
    while ($row = $completedTransactions->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='font-size: 11px;'>" . substr($row['checkout_request_id'], 0, 20) . "...</td>";
        echo "<td>" . $row['phone_number'] . "</td>";
        echo "<td>" . $row['package_id'] . "</td>";
        echo "<td style='font-family: monospace;'>" . ($row['voucher_code'] ?: 'NULL') . "</td>";
        echo "<td style='color: green; font-weight: bold;'>" . $row['status'] . "</td>";
        echo "<td>" . $row['updated_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No completed transactions found.</p>";
}

// Step 3: Test the voucher handler function
echo "<h2>Step 3: Test Voucher Handler Function</h2>";

if (isset($_GET['test_voucher_handler'])) {
    $testCheckoutId = $_GET['test_voucher_handler'];
    
    // Get transaction details
    $txnStmt = $conn->prepare("SELECT * FROM mpesa_transactions WHERE checkout_request_id = ?");
    $txnStmt->bind_param("s", $testCheckoutId);
    $txnStmt->execute();
    $txnResult = $txnStmt->get_result();
    
    if ($txnResult->num_rows > 0) {
        $txnData = $txnResult->fetch_assoc();
        
        echo "<h4>Testing with transaction:</h4>";
        echo "<p><strong>Checkout ID:</strong> " . $txnData['checkout_request_id'] . "</p>";
        echo "<p><strong>Package ID:</strong> " . $txnData['package_id'] . "</p>";
        echo "<p><strong>Reseller ID:</strong> " . $txnData['reseller_id'] . "</p>";
        echo "<p><strong>Phone:</strong> " . $txnData['phone_number'] . "</p>";
        
        // Test the voucher handler
        $voucherResult = createVoucherAfterPayment(
            $txnData['checkout_request_id'],
            $txnData['package_id'],
            $txnData['reseller_id'],
            $txnData['phone_number'],
            $txnData['mpesa_receipt']
        );
        
        echo "<h4>Voucher Handler Result:</h4>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>";
        print_r($voucherResult);
        echo "</pre>";
        
    } else {
        echo "<p style='color: red;'>Transaction not found.</p>";
    }
}

// Show available transactions for testing
$testTransactions = $conn->query("SELECT checkout_request_id, package_id, phone_number, status FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 3");
if ($testTransactions && $testTransactions->num_rows > 0) {
    echo "<h3>Test Voucher Handler:</h3>";
    echo "<p>Click on a transaction to test the voucher handler:</p>";
    while ($row = $testTransactions->fetch_assoc()) {
        echo "<p><a href='?test_voucher_handler=" . urlencode($row['checkout_request_id']) . "' style='color: blue;'>";
        echo "Test: " . substr($row['checkout_request_id'], 0, 25) . "... (Package: " . $row['package_id'] . ", Phone: " . $row['phone_number'] . ")";
        echo "</a></p>";
    }
}

// Step 4: Check packages table
echo "<h2>Step 4: Check Packages Table</h2>";

$packages = $conn->query("SELECT id, name, price, duration FROM packages ORDER BY id");
if ($packages && $packages->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Name</th><th>Price</th><th>Duration</th>";
    echo "</tr>";
    
    while ($row = $packages->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>KES " . $row['price'] . "</td>";
        echo "<td>" . $row['duration'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No packages found.</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
</style>
