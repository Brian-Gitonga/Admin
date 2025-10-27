<?php
/**
 * Fix M-Pesa Callback Database Issues
 */

require_once 'portal_connection.php';

echo "<h1>🔧 M-Pesa Callback Database Fix</h1>";

// Step 1: Check if mpesa_transactions table exists
echo "<h2>Step 1: Check mpesa_transactions Table</h2>";

$tableCheck = $portal_conn->query("SHOW TABLES LIKE 'mpesa_transactions'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p>✅ mpesa_transactions table exists</p>";
    
    // Check table structure
    $structure = $portal_conn->query("DESCRIBE mpesa_transactions");
    if ($structure) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>";
        
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check existing data
    $data = $portal_conn->query("SELECT * FROM mpesa_transactions ORDER BY created_at DESC LIMIT 10");
    if ($data && $data->num_rows > 0) {
        echo "<h3>Recent Transactions:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>ID</th><th>Checkout Request ID</th><th>Merchant Request ID</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th>";
        echo "</tr>";
        
        while ($row = $data->fetch_assoc()) {
            $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td style='font-size: 11px;'>" . $row['checkout_request_id'] . "</td>";
            echo "<td style='font-size: 11px;'>" . ($row['merchant_request_id'] ?: 'N/A') . "</td>";
            echo "<td>" . $row['phone_number'] . "</td>";
            echo "<td>KES " . $row['amount'] . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No transactions found in the table.</p>";
    }
    
} else {
    echo "<p>❌ mpesa_transactions table does NOT exist</p>";
    echo "<p><strong>Creating the table now...</strong></p>";
    
    // Create the table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS `mpesa_transactions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `checkout_request_id` varchar(255) NOT NULL,
        `merchant_request_id` varchar(255) DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL,
        `phone_number` varchar(20) NOT NULL,
        `package_id` int(11) DEFAULT NULL,
        `package_name` varchar(255) DEFAULT NULL,
        `reseller_id` int(11) DEFAULT NULL,
        `router_id` int(11) DEFAULT NULL,
        `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
        `mpesa_receipt` varchar(255) DEFAULT NULL,
        `transaction_date` datetime DEFAULT NULL,
        `result_code` int(11) DEFAULT NULL,
        `result_description` text DEFAULT NULL,
        `voucher_id` int(11) DEFAULT NULL,
        `voucher_code` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `checkout_request_id` (`checkout_request_id`),
        KEY `phone_number` (`phone_number`),
        KEY `status` (`status`),
        KEY `reseller_id` (`reseller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    if ($portal_conn->query($createTableSQL) === TRUE) {
        echo "<p>✅ mpesa_transactions table created successfully!</p>";
    } else {
        echo "<p>❌ Error creating table: " . $portal_conn->error . "</p>";
    }
}

// Step 2: Check callback logs for CheckoutRequestIDs that should exist
echo "<h2>Step 2: Analyze Callback vs Database Mismatch</h2>";

if (file_exists('mpesa_callback.log')) {
    $logs = file_get_contents('mpesa_callback.log');
    
    // Extract CheckoutRequestIDs from callback logs
    preg_match_all('/CheckoutRequestID[=:]\s*([ws_CO_\w\d]+)/', $logs, $matches);
    $callbackCheckoutIds = array_unique($matches[1]);
    
    echo "<h3>CheckoutRequestIDs from M-Pesa Callbacks:</h3>";
    echo "<ul>";
    foreach ($callbackCheckoutIds as $checkoutId) {
        echo "<li><code>$checkoutId</code></li>";
    }
    echo "</ul>";
    
    // Check which ones exist in database
    if (!empty($callbackCheckoutIds)) {
        echo "<h3>Database Check for Callback CheckoutRequestIDs:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>CheckoutRequestID</th><th>In Database?</th><th>Status</th><th>Action</th>";
        echo "</tr>";
        
        foreach ($callbackCheckoutIds as $checkoutId) {
            $checkStmt = $portal_conn->prepare("SELECT id, status FROM mpesa_transactions WHERE checkout_request_id = ?");
            $checkStmt->bind_param("s", $checkoutId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
                echo "<tr>";
                echo "<td style='font-size: 11px;'>$checkoutId</td>";
                echo "<td style='color: green;'>✅ Yes (ID: {$row['id']})</td>";
                echo "<td style='color: $statusColor; font-weight: bold;'>{$row['status']}</td>";
                echo "<td>OK</td>";
                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td style='font-size: 11px;'>$checkoutId</td>";
                echo "<td style='color: red;'>❌ No</td>";
                echo "<td>N/A</td>";
                echo "<td><a href='?create_missing=$checkoutId' style='color: blue;'>Create Missing</a></td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    }
}

// Step 3: Handle creating missing transactions
if (isset($_GET['create_missing'])) {
    $missingCheckoutId = $_GET['create_missing'];
    echo "<h3>Creating Missing Transaction: $missingCheckoutId</h3>";
    
    // Create a placeholder transaction for testing
    $insertSQL = "INSERT INTO mpesa_transactions (
        checkout_request_id, merchant_request_id, amount, phone_number, 
        package_id, package_name, reseller_id, router_id, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt = $portal_conn->prepare($insertSQL);
    if ($stmt) {
        $merchantId = str_replace('ws_CO_', 'ws_MR_', $missingCheckoutId);
        $amount = 10.00;
        $phone = '254700123456';
        $packageId = 1;
        $packageName = 'Test Package';
        $resellerId = 1;
        $routerId = 1;
        
        $stmt->bind_param("ssdiisii", 
            $missingCheckoutId,
            $merchantId,
            $amount,
            $phone,
            $packageId,
            $packageName,
            $resellerId,
            $routerId
        );
        
        if ($stmt->execute()) {
            echo "<p>✅ Missing transaction created with ID: " . $portal_conn->insert_id . "</p>";
            echo "<p><a href='?' style='color: blue;'>Refresh page</a></p>";
        } else {
            echo "<p>❌ Failed to create transaction: " . $stmt->error . "</p>";
        }
    }
}

// Step 4: Test callback processing
echo "<h2>Step 4: Test Callback Processing</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Test M-Pesa Callback Processing:</h4>";
echo "<p>This will simulate M-Pesa sending a successful payment callback.</p>";
echo "<button onclick='testCallback()' style='background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>🧪 Test Callback Processing</button>";
echo "<div id='callback-test-result' style='margin-top: 15px;'></div>";
echo "</div>";

echo "<h2>Step 5: Fix Callback URL Configuration</h2>";

// Check current callback URL in settings
require_once 'mpesa_settings_operations.php';
$mpesaSettings = getMpesaSettings($portal_conn, 1);
if ($mpesaSettings) {
    echo "<p><strong>Current Callback URL:</strong> " . $mpesaSettings['callback_url'] . "</p>";
    
    if (strpos($mpesaSettings['callback_url'], 'fd7f49c64822.ngrok-free.app') !== false) {
        echo "<p>✅ Using correct ngrok URL</p>";
    } else {
        echo "<p>⚠️ Callback URL may need updating</p>";
        echo "<p><strong>Should be:</strong> https://fd7f49c64822.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php</p>";
    }
} else {
    echo "<p>❌ M-Pesa settings not found</p>";
}

?>

<script>
function testCallback() {
    document.getElementById('callback-test-result').innerHTML = '<p>Testing callback processing...</p>';
    
    // Get a checkout ID from the database to test with
    fetch('?action=get_test_checkout_id')
    .then(response => response.text())
    .then(checkoutId => {
        if (checkoutId && checkoutId.trim() !== '') {
            // Simulate M-Pesa callback data
            const callbackData = {
                "Body": {
                    "stkCallback": {
                        "MerchantRequestID": "ws_MR_TEST_" + Date.now(),
                        "CheckoutRequestID": checkoutId.trim(),
                        "ResultCode": 0,
                        "ResultDesc": "The service request is processed successfully.",
                        "CallbackMetadata": {
                            "Item": [
                                {"Name": "Amount", "Value": 10},
                                {"Name": "MpesaReceiptNumber", "Value": "TEST" + Math.floor(Math.random() * 1000000)},
                                {"Name": "TransactionDate", "Value": "20251002" + new Date().toTimeString().replace(/:/g, '').substring(0, 6)},
                                {"Name": "PhoneNumber", "Value": "254700123456"}
                            ]
                        }
                    }
                }
            };
            
            // Send to callback
            fetch('mpesa_callback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(callbackData)
            })
            .then(response => response.text())
            .then(result => {
                document.getElementById('callback-test-result').innerHTML = 
                    '<h5>Callback Test Result:</h5>' +
                    '<p><strong>Checkout ID Used:</strong> ' + checkoutId.trim() + '</p>' +
                    '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">' + result + '</pre>' +
                    '<p><a href="?" style="color: blue;">Refresh page to see updated data</a></p>';
            })
            .catch(error => {
                document.getElementById('callback-test-result').innerHTML = '<p style="color: red;">❌ Test failed: ' + error + '</p>';
            });
        } else {
            document.getElementById('callback-test-result').innerHTML = '<p style="color: red;">❌ No checkout ID available for testing</p>';
        }
    })
    .catch(error => {
        document.getElementById('callback-test-result').innerHTML = '<p style="color: red;">❌ Failed to get test checkout ID: ' + error + '</p>';
    });
}
</script>

<?php
// Handle AJAX request for test checkout ID
if (isset($_GET['action']) && $_GET['action'] === 'get_test_checkout_id') {
    $result = $portal_conn->query("SELECT checkout_request_id FROM mpesa_transactions WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo $row['checkout_request_id'];
    } else {
        echo '';
    }
    exit;
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; }
</style>
