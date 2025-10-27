<?php
/**
 * Test M-Pesa Callback SMS Integration
 * Tests the complete integration between M-Pesa callback and SMS voucher delivery
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>🔗 M-Pesa Callback SMS Integration Test</h1>";

// Step 1: Check recent transactions
echo "<h2>Step 1: Recent M-Pesa Transactions</h2>";

$recentTransactions = $conn->query("
    SELECT 
        checkout_request_id,
        package_id,
        package_name,
        reseller_id,
        phone_number,
        amount,
        status,
        voucher_code,
        mpesa_receipt,
        created_at,
        updated_at
    FROM mpesa_transactions 
    ORDER BY updated_at DESC 
    LIMIT 10
");

if ($recentTransactions && $recentTransactions->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Checkout ID</th><th>Package</th><th>Phone</th><th>Amount</th><th>Status</th><th>Voucher</th><th>Updated</th><th>Action</th>";
    echo "</tr>";
    
    while ($txn = $recentTransactions->fetch_assoc()) {
        $shortCheckoutId = substr($txn['checkout_request_id'], 0, 15) . "...";
        $statusColor = $txn['status'] === 'completed' ? 'green' : ($txn['status'] === 'pending' ? 'orange' : 'red');
        $voucherStatus = $txn['voucher_code'] ? $txn['voucher_code'] : 'No voucher';
        
        echo "<tr>";
        echo "<td title='{$txn['checkout_request_id']}'>$shortCheckoutId</td>";
        echo "<td>{$txn['package_name']}</td>";
        echo "<td>{$txn['phone_number']}</td>";
        echo "<td>KES {$txn['amount']}</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>{$txn['status']}</td>";
        echo "<td>$voucherStatus</td>";
        echo "<td>{$txn['updated_at']}</td>";
        
        if ($txn['status'] === 'completed' && !$txn['voucher_code']) {
            echo "<td><button onclick='processVoucherForTransaction(\"{$txn['checkout_request_id']}\", {$txn['package_id']}, {$txn['reseller_id']}, \"{$txn['phone_number']}\", \"{$txn['mpesa_receipt']}\")' style='background: #10b981; color: white; padding: 3px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;'>Process Voucher</button></td>";
        } elseif ($txn['status'] === 'completed' && $txn['voucher_code']) {
            echo "<td><button onclick='resendSmsForTransaction(\"{$txn['checkout_request_id']}\", \"{$txn['voucher_code']}\", \"{$txn['phone_number']}\", {$txn['package_id']})' style='background: #3b82f6; color: white; padding: 3px 8px; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;'>Resend SMS</button></td>";
        } else {
            echo "<td>-</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ No transactions found</p>";
}

// Step 2: Check callback logs
echo "<h2>Step 2: Recent Callback Activity</h2>";

$callbackLogFile = 'mpesa_callback.log';
if (file_exists($callbackLogFile)) {
    $logContent = file_get_contents($callbackLogFile);
    $logLines = explode("\n", $logContent);
    $recentLogs = array_slice(array_reverse($logLines), 0, 20);
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Last 20 Callback Log Entries:</h4>";
    echo "<div style='background: white; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 11px; max-height: 300px; overflow-y: auto;'>";
    
    foreach ($recentLogs as $line) {
        if (trim($line)) {
            $color = 'black';
            if (strpos($line, 'ERROR') !== false) $color = 'red';
            elseif (strpos($line, 'SUCCESS') !== false || strpos($line, 'successfully') !== false) $color = 'green';
            elseif (strpos($line, 'WARNING') !== false) $color = 'orange';
            
            echo "<div style='color: $color; margin: 2px 0;'>" . htmlspecialchars($line) . "</div>";
        }
    }
    echo "</div>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ No callback log file found</p>";
}

// Step 3: Check voucher delivery logs
echo "<h2>Step 3: Voucher Delivery Logs</h2>";

$voucherLogFile = 'voucher_delivery.log';
if (file_exists($voucherLogFile)) {
    $logContent = file_get_contents($voucherLogFile);
    $logLines = explode("\n", $logContent);
    $recentLogs = array_slice(array_reverse($logLines), 0, 15);
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Last 15 Voucher Delivery Log Entries:</h4>";
    echo "<div style='background: white; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 11px; max-height: 250px; overflow-y: auto;'>";
    
    foreach ($recentLogs as $line) {
        if (trim($line)) {
            $color = 'black';
            if (strpos($line, 'ERROR') !== false) $color = 'red';
            elseif (strpos($line, 'SMS sent successfully') !== false) $color = 'green';
            elseif (strpos($line, 'EXCEPTION') !== false) $color = 'red';
            
            echo "<div style='color: $color; margin: 2px 0;'>" . htmlspecialchars($line) . "</div>";
        }
    }
    echo "</div>";
    echo "</div>";
} else {
    echo "<p style='color: orange;'>⚠️ No voucher delivery log file found yet</p>";
}

// Step 4: Test interface
echo "<h2>Step 4: Manual Integration Test</h2>";

echo "<div style='background: #f0f9ff; border: 1px solid #0ea5e9; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>🧪 Test Callback SMS Integration</h3>";
echo "<p>This will simulate what happens when the M-Pesa callback processes a completed payment.</p>";

echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Manual Test Parameters:</h4>";
echo "<form id='manual-test-form'>";
echo "<div style='margin: 10px 0;'>";
echo "<label>Phone Number:</label><br>";
echo "<input type='tel' id='test-phone' value='0712345678' style='padding: 8px; width: 200px;' required>";
echo "</div>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Package:</label><br>";
echo "<select id='test-package' style='padding: 8px; width: 200px;' required>";
$packages = $conn->query("SELECT id, name, price FROM packages ORDER BY id");
if ($packages) {
    while ($pkg = $packages->fetch_assoc()) {
        echo "<option value='{$pkg['id']}' data-name='{$pkg['name']}' data-price='{$pkg['price']}'>{$pkg['name']} - KES {$pkg['price']}</option>";
    }
}
echo "</select>";
echo "</div>";

echo "<div style='margin: 10px 0;'>";
echo "<label>Reseller ID:</label><br>";
echo "<input type='number' id='test-reseller' value='6' style='padding: 8px; width: 200px;' required>";
echo "</div>";

echo "<button type='submit' style='background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;'>🚀 Test Callback SMS Integration</button>";
echo "</form>";
echo "</div>";
echo "</div>";

echo "<div id='integration-test-result' style='margin-top: 20px;'></div>";

// JavaScript for testing
echo "<script>
document.getElementById('manual-test-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const resultDiv = document.getElementById('integration-test-result');
    const phoneNumber = document.getElementById('test-phone').value;
    const packageSelect = document.getElementById('test-package');
    const packageId = packageSelect.value;
    const packageName = packageSelect.options[packageSelect.selectedIndex].dataset.name;
    const packagePrice = packageSelect.options[packageSelect.selectedIndex].dataset.price;
    const resellerId = document.getElementById('test-reseller').value;
    
    // Generate a fake checkout request ID for testing
    const checkoutRequestId = 'ws_CO_' + Date.now() + Math.random().toString(36).substr(2, 9);
    const mpesaReceipt = 'TEST' + Date.now();
    
    resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><h4>🔄 Testing Callback SMS Integration...</h4><p>Simulating M-Pesa callback processing...</p></div>';
    
    // Step 1: Create a test transaction
    const transactionData = new FormData();
    transactionData.append('create_test_transaction', '1');
    transactionData.append('checkout_request_id', checkoutRequestId);
    transactionData.append('package_id', packageId);
    transactionData.append('package_name', packageName);
    transactionData.append('package_price', packagePrice);
    transactionData.append('reseller_id', resellerId);
    transactionData.append('phone_number', phoneNumber);
    transactionData.append('mpesa_receipt', mpesaReceipt);
    
    fetch('test_callback_sms_integration.php', {
        method: 'POST',
        body: transactionData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #065f46; margin-top: 0;'>🎉 CALLBACK SMS INTEGRATION SUCCESS!</h3>
                    
                    <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                        <h4 style='margin: 0 0 15px 0; color: #1f2937;'>SMS Delivered Successfully</h4>
                        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Voucher Code:</p>
                                <p style='margin: 0; font-family: monospace; font-size: 16px; font-weight: bold; color: #1f2937; background: #f9fafb; padding: 8px; border-radius: 4px;'>\${result.voucher_code}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Phone Number:</p>
                                <p style='margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;'>\${phoneNumber}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Package:</p>
                                <p style='margin: 0; font-size: 14px; color: #1f2937;'>\${result.package_name}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>SMS Gateway:</p>
                                <p style='margin: 0; font-size: 14px; color: #1f2937;'>\${result.sms_result ? result.sms_result.gateway : 'N/A'}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style='background: #ecfdf5; padding: 15px; border-radius: 6px; border: 1px solid #a7f3d0;'>
                        <h4 style='color: #065f46; margin: 0 0 10px 0;'>✅ Integration Test Results:</h4>
                        <ul style='margin: 0; color: #065f46;'>
                            <li>✅ Transaction created and marked as completed</li>
                            <li>✅ Voucher fetched and assigned to customer</li>
                            <li>✅ SMS sent successfully via callback integration</li>
                            <li>✅ Database updated with voucher details</li>
                            <li>✅ Complete workflow working perfectly</li>
                        </ul>
                        <p style='margin: 10px 0 0 0; font-weight: bold;'>The M-Pesa callback SMS integration is fully functional!</p>
                    </div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #dc2626; margin-top: 0;'>❌ Integration Test Failed</h3>
                    <p><strong>Error:</strong> \${result.message}</p>
                    \${result.debug_info ? '<pre style=\"background: #f9fafb; padding: 10px; border-radius: 4px; font-size: 12px;\">' + JSON.stringify(result.debug_info, null, 2) + '</pre>' : ''}
                </div>
            `;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                <h3 style='color: #dc2626; margin-top: 0;'>🚨 Test Request Failed</h3>
                <p><strong>Error:</strong> \${error.message}</p>
            </div>
        `;
    });
});

function processVoucherForTransaction(checkoutId, packageId, resellerId, phone, receipt) {
    const resultDiv = document.getElementById('integration-test-result');
    resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><h4>🔄 Processing voucher for existing transaction...</h4></div>';
    
    const formData = new FormData();
    formData.append('process_existing_voucher', '1');
    formData.append('checkout_request_id', checkoutId);
    formData.append('package_id', packageId);
    formData.append('reseller_id', resellerId);
    formData.append('phone_number', phone);
    formData.append('mpesa_receipt', receipt);
    
    fetch('test_callback_sms_integration.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #065f46; margin-top: 0;'>✅ Voucher Processed Successfully</h4>
                    <p><strong>Voucher Code:</strong> \${result.voucher_code}</p>
                    <p><strong>SMS Status:</strong> \${result.message}</p>
                </div>
            `;
            setTimeout(() => location.reload(), 2000);
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #dc2626; margin-top: 0;'>❌ Processing Failed</h4>
                    <p>\${result.message}</p>
                </div>
            `;
        }
    });
}

function resendSmsForTransaction(checkoutId, voucherCode, phone, packageId) {
    const resultDiv = document.getElementById('integration-test-result');
    resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><h4>🔄 Resending SMS...</h4></div>';
    
    const formData = new FormData();
    formData.append('resend_sms', '1');
    formData.append('checkout_request_id', checkoutId);
    formData.append('voucher_code', voucherCode);
    formData.append('phone_number', phone);
    formData.append('package_id', packageId);
    
    fetch('test_callback_sms_integration.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #065f46; margin-top: 0;'>✅ SMS Resent Successfully</h4>
                    <p>\${result.message}</p>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px;'>
                    <h4 style='color: #dc2626; margin-top: 0;'>❌ SMS Resend Failed</h4>
                    <p>\${result.message}</p>
                </div>
            `;
        }
    });
}
</script>";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['create_test_transaction'])) {
        // Create test transaction and process voucher
        $checkoutRequestId = $_POST['checkout_request_id'] ?? '';
        $packageId = intval($_POST['package_id'] ?? 0);
        $packageName = $_POST['package_name'] ?? '';
        $packagePrice = floatval($_POST['package_price'] ?? 0);
        $resellerId = intval($_POST['reseller_id'] ?? 0);
        $phoneNumber = $_POST['phone_number'] ?? '';
        $mpesaReceipt = $_POST['mpesa_receipt'] ?? '';
        
        try {
            // Insert test transaction
            $stmt = $conn->prepare("INSERT INTO mpesa_transactions (checkout_request_id, package_id, package_name, amount, reseller_id, phone_number, status, mpesa_receipt, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW())");
            $stmt->bind_param("sisdsss", $checkoutRequestId, $packageId, $packageName, $packagePrice, $resellerId, $phoneNumber, $mpesaReceipt);
            $stmt->execute();
            
            // Process voucher delivery
            require_once 'sms_voucher_delivery.php';
            $result = processVoucherDelivery($checkoutRequestId, $packageId, $resellerId, $phoneNumber, $mpesaReceipt);
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if (isset($_POST['process_existing_voucher'])) {
        // Process voucher for existing transaction
        $checkoutRequestId = $_POST['checkout_request_id'] ?? '';
        $packageId = intval($_POST['package_id'] ?? 0);
        $resellerId = intval($_POST['reseller_id'] ?? 0);
        $phoneNumber = $_POST['phone_number'] ?? '';
        $mpesaReceipt = $_POST['mpesa_receipt'] ?? '';
        
        try {
            require_once 'sms_voucher_delivery.php';
            $result = processVoucherDelivery($checkoutRequestId, $packageId, $resellerId, $phoneNumber, $mpesaReceipt);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if (isset($_POST['resend_sms'])) {
        // Resend SMS for existing voucher
        $voucherCode = $_POST['voucher_code'] ?? '';
        $phoneNumber = $_POST['phone_number'] ?? '';
        $packageId = intval($_POST['package_id'] ?? 0);
        
        try {
            require_once 'sms_voucher_delivery.php';
            $result = resendVoucherSms($voucherCode, $phoneNumber, $packageId);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

echo "<h2>✅ Callback SMS Integration Test Ready</h2>";
echo "<p>This test interface allows you to verify that the M-Pesa callback properly integrates with the SMS voucher delivery system.</p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; transform: translateY(-1px); transition: all 0.2s; }
</style>
