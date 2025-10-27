<?php
/**
 * Fix Voucher Database Schema - Add missing columns and fix table structure
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>🔧 Fix Voucher Database Schema</h1>";

// Step 1: Check and add missing voucher_code column to mpesa_transactions
echo "<h2>Step 1: Fix mpesa_transactions Table</h2>";

$columnCheck = $conn->query("SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_code'");
if (!$columnCheck || $columnCheck->num_rows === 0) {
    echo "<p style='color: orange;'>⚠️ Adding voucher_code column to mpesa_transactions table...</p>";
    
    $addColumn = "ALTER TABLE mpesa_transactions ADD COLUMN voucher_code VARCHAR(50) DEFAULT NULL AFTER result_description";
    if ($conn->query($addColumn)) {
        echo "<p style='color: green;'>✅ voucher_code column added successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add voucher_code column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ voucher_code column already exists</p>";
}

// Step 2: Check and add missing voucher_id column to mpesa_transactions
$voucherIdCheck = $conn->query("SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_id'");
if (!$voucherIdCheck || $voucherIdCheck->num_rows === 0) {
    echo "<p style='color: orange;'>⚠️ Adding voucher_id column to mpesa_transactions table...</p>";
    
    $addVoucherIdColumn = "ALTER TABLE mpesa_transactions ADD COLUMN voucher_id INT DEFAULT NULL AFTER voucher_code";
    if ($conn->query($addVoucherIdColumn)) {
        echo "<p style='color: green;'>✅ voucher_id column added successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add voucher_id column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ voucher_id column already exists</p>";
}

// Step 3: Ensure vouchers table has correct structure
echo "<h2>Step 2: Verify Vouchers Table Structure</h2>";

$vouchersTableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if (!$vouchersTableCheck || $vouchersTableCheck->num_rows === 0) {
    echo "<p style='color: orange;'>⚠️ Creating vouchers table...</p>";
    
    $createVouchersTable = "
    CREATE TABLE `vouchers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(50) NOT NULL,
        `username` varchar(50) DEFAULT NULL,
        `password` varchar(50) DEFAULT NULL,
        `package_id` int(11) NOT NULL,
        `reseller_id` int(11) NOT NULL,
        `customer_phone` varchar(20) DEFAULT NULL,
        `status` enum('active','used','expired') NOT NULL DEFAULT 'active',
        `used_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `expires_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `code` (`code`),
        KEY `package_id` (`package_id`),
        KEY `reseller_id` (`reseller_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($conn->query($createVouchersTable)) {
        echo "<p style='color: green;'>✅ Vouchers table created successfully</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create vouchers table: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
    
    // Check if customer_phone column allows NULL (it should for unused vouchers)
    $customerPhoneCheck = $conn->query("SHOW COLUMNS FROM vouchers WHERE Field = 'customer_phone'");
    if ($customerPhoneCheck) {
        $columnInfo = $customerPhoneCheck->fetch_assoc();
        if ($columnInfo['Null'] === 'NO') {
            echo "<p style='color: orange;'>⚠️ Modifying customer_phone column to allow NULL...</p>";
            $modifyColumn = "ALTER TABLE vouchers MODIFY COLUMN customer_phone VARCHAR(20) DEFAULT NULL";
            if ($conn->query($modifyColumn)) {
                echo "<p style='color: green;'>✅ customer_phone column modified to allow NULL</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to modify customer_phone column: " . $conn->error . "</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ customer_phone column structure is correct</p>";
        }
    }
}

// Step 4: Create sample vouchers if none exist
echo "<h2>Step 3: Create Sample Vouchers</h2>";

$activeVouchersCount = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'")->fetch_assoc()['count'];
echo "<p>Current active vouchers: <strong>$activeVouchersCount</strong></p>";

if ($activeVouchersCount == 0) {
    echo "<p style='color: orange;'>⚠️ Creating sample vouchers...</p>";
    
    // Get packages and resellers
    $packages = $conn->query("SELECT id, name FROM packages ORDER BY id LIMIT 10");
    $resellers = $conn->query("SELECT id FROM resellers ORDER BY id LIMIT 5");
    
    $packageIds = [];
    $resellerIds = [];
    
    if ($packages) {
        while ($row = $packages->fetch_assoc()) {
            $packageIds[] = $row['id'];
        }
    }
    
    if ($resellers) {
        while ($row = $resellers->fetch_assoc()) {
            $resellerIds[] = $row['id'];
        }
    }
    
    // Use defaults if no data found
    if (empty($packageIds)) $packageIds = [15]; // Default package ID
    if (empty($resellerIds)) $resellerIds = [6]; // Default reseller ID
    
    $vouchersCreated = 0;
    foreach ($packageIds as $packageId) {
        foreach ($resellerIds as $resellerId) {
            // Create 20 vouchers per package per reseller
            for ($i = 1; $i <= 20; $i++) {
                $code = "WIFI" . $packageId . "R" . $resellerId . "V" . str_pad($i, 3, '0', STR_PAD_LEFT);
                $username = $code;
                $password = $code;
                
                $insertVoucher = $conn->prepare("INSERT INTO vouchers (code, username, password, package_id, reseller_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
                if ($insertVoucher) {
                    $insertVoucher->bind_param("sssii", $code, $username, $password, $packageId, $resellerId);
                    if ($insertVoucher->execute()) {
                        $vouchersCreated++;
                    }
                }
            }
        }
    }
    
    echo "<p style='color: green;'>✅ Created $vouchersCreated sample vouchers</p>";
} else {
    echo "<p style='color: green;'>✅ Sufficient vouchers already exist</p>";
}

// Step 5: Test voucher fetching function
echo "<h2>Step 4: Test Voucher Fetching Function</h2>";

// Find a completed transaction to test with
$completedTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");
if ($completedTxn && $completedTxn->num_rows > 0) {
    $txn = $completedTxn->fetch_assoc();
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Testing with Transaction:</h4>";
    echo "<p><strong>Checkout ID:</strong> " . substr($txn['checkout_request_id'], 0, 30) . "...</p>";
    echo "<p><strong>Package ID:</strong> " . $txn['package_id'] . "</p>";
    echo "<p><strong>Reseller ID:</strong> " . $txn['reseller_id'] . "</p>";
    echo "<p><strong>Phone:</strong> " . $txn['phone_number'] . "</p>";
    echo "</div>";
    
    echo "<button onclick='testVoucherFetching()' style='background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🧪 Test Voucher Fetching</button>";
    
    echo "<div id='voucher-test-result' style='margin-top: 20px;'></div>";
    
    echo "<script>
    function testVoucherFetching() {
        const resultDiv = document.getElementById('voucher-test-result');
        resultDiv.innerHTML = '<p>🔄 Testing voucher fetching...</p>';
        
        // Test the voucher handler directly
        const formData = new FormData();
        formData.append('test_voucher_handler', '1');
        formData.append('checkout_request_id', '{$txn['checkout_request_id']}');
        formData.append('package_id', '{$txn['package_id']}');
        formData.append('reseller_id', '{$txn['reseller_id']}');
        formData.append('phone_number', '{$txn['phone_number']}');
        formData.append('mpesa_receipt', '{$txn['mpesa_receipt']}');
        
        fetch('test_voucher_handler_direct.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultDiv.innerHTML = `
                    <div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>
                        <h4 style='color: #065f46; margin-top: 0;'>✅ Voucher Fetching Test Successful!</h4>
                        <p><strong>Voucher Code:</strong> \${result.voucher_code}</p>
                        <p><strong>Username:</strong> \${result.voucher_username || 'N/A'}</p>
                        <p><strong>Password:</strong> \${result.voucher_password || 'N/A'}</p>
                        <p><strong>Message:</strong> \${result.message}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                        <h4 style='color: #dc2626; margin-top: 0;'>❌ Voucher Fetching Test Failed</h4>
                        <p><strong>Error:</strong> \${result.message}</p>
                        \${result.debug_info ? '<pre style=\"font-size: 12px; background: #f9fafb; padding: 10px; border-radius: 4px;\">' + JSON.stringify(result.debug_info, null, 2) + '</pre>' : ''}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #dc2626; margin-top: 0;'>🚨 Test Request Failed</h4>
                    <p><strong>Error:</strong> \${error.message}</p>
                </div>
            `;
        });
    }
    </script>";
    
} else {
    echo "<p style='color: orange;'>⚠️ No completed transactions found for testing</p>";
    echo "<p>Create a test transaction first using the payment system</p>";
}

// Step 6: Show current system status
echo "<h2>Step 5: Current System Status</h2>";

echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;'>";

// Database tables status
echo "<div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;'>";
echo "<h4>Database Tables</h4>";
$tables = ['mpesa_transactions', 'vouchers', 'packages', 'resellers'];
foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    $exists = $check && $check->num_rows > 0;
    echo "<p>$table: " . ($exists ? "<span style='color: green;'>✅ Exists</span>" : "<span style='color: red;'>❌ Missing</span>") . "</p>";
}
echo "</div>";

// Voucher statistics
echo "<div style='background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;'>";
echo "<h4>Voucher Statistics</h4>";
$voucherStats = $conn->query("SELECT status, COUNT(*) as count FROM vouchers GROUP BY status");
if ($voucherStats) {
    while ($row = $voucherStats->fetch_assoc()) {
        $color = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
        echo "<p>{$row['status']}: <span style='color: $color; font-weight: bold;'>{$row['count']}</span></p>";
    }
} else {
    echo "<p style='color: red;'>No voucher data found</p>";
}
echo "</div>";

echo "</div>";

echo "<h2>✅ Database Schema Fix Complete</h2>";
echo "<p>The database schema has been updated and the voucher system should now work properly.</p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
button:hover { opacity: 0.9; }
</style>
