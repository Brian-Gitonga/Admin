<?php
/**
 * Fix Voucher System - Create table and sample vouchers if needed
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>🔧 Fix Voucher System</h1>";

// Step 1: Check if vouchers table exists
echo "<h2>Step 1: Check Vouchers Table</h2>";

$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Vouchers table does not exist. Creating...</p>";
    
    // Create vouchers table
    $createTable = "
    CREATE TABLE `vouchers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL,
        `username` varchar(50) DEFAULT NULL,
        `password` varchar(50) DEFAULT NULL,
        `package_id` int(11) NOT NULL,
        `reseller_id` int(11) NOT NULL,
        `customer_phone` varchar(20) DEFAULT NULL,
        `status` enum('active','used','expired') DEFAULT 'active',
        `used_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `package_id` (`package_id`),
        KEY `reseller_id` (`reseller_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($createTable)) {
        echo "<p style='color: green;'>✅ Vouchers table created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create vouchers table: " . $conn->error . "</p>";
        exit;
    }
}

// Step 2: Check available packages
echo "<h2>Step 2: Check Available Packages</h2>";

$packages = $conn->query("SELECT id, name, price, duration FROM packages ORDER BY id");
if ($packages && $packages->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Name</th><th>Price</th><th>Duration</th>";
    echo "</tr>";
    
    $packageIds = [];
    while ($row = $packages->fetch_assoc()) {
        $packageIds[] = $row['id'];
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>KES " . $row['price'] . "</td>";
        echo "<td>" . $row['duration'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No packages found</p>";
    exit;
}

// Step 3: Check active vouchers
echo "<h2>Step 3: Check Active Vouchers</h2>";

$activeVouchers = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'");
if ($activeVouchers) {
    $count = $activeVouchers->fetch_assoc()['count'];
    echo "<p>Active vouchers available: <strong>$count</strong></p>";
    
    if ($count == 0) {
        echo "<p style='color: orange;'>⚠️ No active vouchers found. Creating sample vouchers...</p>";
        
        // Get reseller IDs
        $resellers = $conn->query("SELECT id FROM resellers LIMIT 5");
        $resellerIds = [];
        if ($resellers) {
            while ($row = $resellers->fetch_assoc()) {
                $resellerIds[] = $row['id'];
            }
        }
        
        if (empty($resellerIds)) {
            $resellerIds = [1]; // Default reseller ID
        }
        
        // Create sample vouchers for each package
        $vouchersCreated = 0;
        foreach ($packageIds as $packageId) {
            foreach ($resellerIds as $resellerId) {
                // Create 5 vouchers per package per reseller
                for ($i = 1; $i <= 5; $i++) {
                    $code = "WIFI" . $packageId . "R" . $resellerId . "V" . str_pad($i, 3, '0', STR_PAD_LEFT);
                    $username = $code;
                    $password = $code;
                    
                    $insertVoucher = $conn->prepare("INSERT INTO vouchers (code, username, password, package_id, reseller_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
                    $insertVoucher->bind_param("sssii", $code, $username, $password, $packageId, $resellerId);
                    
                    if ($insertVoucher->execute()) {
                        $vouchersCreated++;
                    }
                }
            }
        }
        
        echo "<p style='color: green;'>✅ Created $vouchersCreated sample vouchers</p>";
    }
}

// Step 4: Show voucher summary
echo "<h2>Step 4: Voucher Summary</h2>";

$voucherSummary = $conn->query("
    SELECT v.package_id, p.name as package_name, v.status, COUNT(*) as count 
    FROM vouchers v 
    LEFT JOIN packages p ON v.package_id = p.id 
    GROUP BY v.package_id, v.status 
    ORDER BY v.package_id, v.status
");

if ($voucherSummary && $voucherSummary->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Package ID</th><th>Package Name</th><th>Status</th><th>Count</th>";
    echo "</tr>";
    
    while ($row = $voucherSummary->fetch_assoc()) {
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

// Step 5: Test voucher fetching with a completed transaction
echo "<h2>Step 5: Test Voucher Fetching</h2>";

$completedTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");
if ($completedTxn && $completedTxn->num_rows > 0) {
    $txn = $completedTxn->fetch_assoc();
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Testing with Transaction:</h4>";
    echo "<p><strong>Checkout ID:</strong> " . $txn['checkout_request_id'] . "</p>";
    echo "<p><strong>Package ID:</strong> " . $txn['package_id'] . "</p>";
    echo "<p><strong>Reseller ID:</strong> " . $txn['reseller_id'] . "</p>";
    echo "<p><strong>Phone:</strong> " . $txn['phone_number'] . "</p>";
    echo "</div>";
    
    // Test voucher handler
    try {
        require_once 'vouchers_script/payment_voucher_handler.php';
        
        $voucherResult = createVoucherAfterPayment(
            $txn['checkout_request_id'],
            $txn['package_id'],
            $txn['reseller_id'],
            $txn['phone_number'],
            $txn['mpesa_receipt']
        );
        
        if ($voucherResult['success']) {
            echo "<div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>";
            echo "<h4 style='color: #065f46; margin-top: 0;'>✅ Voucher Handler Test Successful!</h4>";
            echo "<p><strong>Voucher Code:</strong> " . $voucherResult['voucher_code'] . "</p>";
            echo "<p><strong>Username:</strong> " . ($voucherResult['voucher_username'] ?: 'N/A') . "</p>";
            echo "<p><strong>Password:</strong> " . ($voucherResult['voucher_password'] ?: 'N/A') . "</p>";
            echo "<p><strong>Message:</strong> " . $voucherResult['message'] . "</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>";
            echo "<h4 style='color: #dc2626; margin-top: 0;'>❌ Voucher Handler Failed</h4>";
            echo "<p><strong>Error:</strong> " . $voucherResult['message'] . "</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>";
        echo "<h4 style='color: #dc2626; margin-top: 0;'>❌ Exception Occurred</h4>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
    
} else {
    echo "<p style='color: red;'>❌ No completed transactions found to test with</p>";
}

echo "<h2>✅ Voucher System Setup Complete</h2>";
echo "<p>The voucher system should now be working properly. Try the payment button test again.</p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
</style>
