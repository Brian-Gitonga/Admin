<?php
/**
 * Test SMS Voucher Delivery System
 * Tests the complete voucher assignment and SMS delivery workflow
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';
require_once 'sms_voucher_delivery.php';

// Handle AJAX test requests FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_voucher_delivery'])) {
    // Clear any output buffers and set JSON header
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');

    $checkoutRequestId = $_POST['checkout_request_id'] ?? '';
    $packageId = intval($_POST['package_id'] ?? 0);
    $resellerId = intval($_POST['reseller_id'] ?? 0);
    $customerPhone = $_POST['customer_phone'] ?? '';
    $mpesaReceipt = $_POST['mpesa_receipt'] ?? '';

    if (empty($checkoutRequestId) || empty($packageId) || empty($resellerId) || empty($customerPhone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required test parameters',
            'debug_info' => [
                'checkout_request_id' => $checkoutRequestId,
                'package_id' => $packageId,
                'reseller_id' => $resellerId,
                'customer_phone' => $customerPhone
            ]
        ]);
        exit;
    }

    try {
        // Capture any PHP errors/warnings
        ob_start();
        $result = processVoucherDelivery($checkoutRequestId, $packageId, $resellerId, $customerPhone, $mpesaReceipt);
        $output = ob_get_clean();

        // If there was any unexpected output, include it in debug info
        if (!empty($output)) {
            $result['debug_output'] = $output;
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'debug_info' => [
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]
        ]);
    }
    exit;
}

echo "<h1>🧪 SMS Voucher Delivery System Test</h1>";

// Step 1: System Status Check
echo "<h2>Step 1: System Status Check</h2>";

$statusChecks = [
    'Database Connection' => $conn ? true : false,
    'SMS Voucher Delivery File' => file_exists('sms_voucher_delivery.php'),
    'TextSMS API File' => file_exists('sms api/textsms_api.php'),
    'mpesa_transactions Table' => $conn->query("SHOW TABLES LIKE 'mpesa_transactions'") ? true : false,
    'vouchers Table' => $conn->query("SHOW TABLES LIKE 'vouchers'") ? true : false,
    'sms_logs Table' => $conn->query("SHOW TABLES LIKE 'sms_logs'") ? true : false
];

foreach ($statusChecks as $check => $status) {
    $icon = $status ? "✅" : "❌";
    $color = $status ? "green" : "red";
    echo "<p>$check: <span style='color: $color;'>$icon " . ($status ? "OK" : "FAILED") . "</span></p>";
}

// Step 2: Check Available Vouchers
echo "<h2>Step 2: Available Vouchers Check</h2>";

$voucherStats = $conn->query("
    SELECT 
        v.package_id, 
        p.name as package_name,
        v.reseller_id,
        v.status,
        COUNT(*) as count 
    FROM vouchers v 
    LEFT JOIN packages p ON v.package_id = p.id 
    GROUP BY v.package_id, v.reseller_id, v.status 
    ORDER BY v.package_id, v.reseller_id, v.status
");

if ($voucherStats && $voucherStats->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Package ID</th><th>Package Name</th><th>Reseller ID</th><th>Status</th><th>Count</th>";
    echo "</tr>";
    
    $activeVouchersFound = false;
    while ($row = $voucherStats->fetch_assoc()) {
        $color = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>{$row['package_id']}</td>";
        echo "<td>{$row['package_name']}</td>";
        echo "<td>{$row['reseller_id']}</td>";
        echo "<td style='color: $color; font-weight: bold;'>{$row['status']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
        
        if ($row['status'] === 'active') $activeVouchersFound = true;
    }
    echo "</table>";
    
    if (!$activeVouchersFound) {
        echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<p style='color: #dc2626; margin: 0;'><strong>⚠️ No Active Vouchers Found</strong></p>";
        echo "<p style='margin: 5px 0 0 0;'><a href='fix_voucher_database_schema.php' style='color: #3b82f6;'>Click here to create sample vouchers</a></p>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>❌ No voucher data found</p>";
}

// Step 3: Find a Completed Transaction for Testing
echo "<h2>Step 3: Test Transaction Selection</h2>";

$completedTxn = $conn->query("
    SELECT 
        checkout_request_id, 
        package_id, 
        reseller_id, 
        phone_number, 
        mpesa_receipt,
        package_name,
        amount,
        created_at
    FROM mpesa_transactions 
    WHERE status = 'completed' 
    ORDER BY updated_at DESC 
    LIMIT 5
");

if ($completedTxn && $completedTxn->num_rows > 0) {
    echo "<p>Found completed transactions for testing:</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Checkout ID</th><th>Package</th><th>Phone</th><th>Amount</th><th>Date</th><th>Action</th>";
    echo "</tr>";
    
    $testTransactions = [];
    while ($txn = $completedTxn->fetch_assoc()) {
        $testTransactions[] = $txn;
        $shortCheckoutId = substr($txn['checkout_request_id'], 0, 20) . "...";
        echo "<tr>";
        echo "<td title='{$txn['checkout_request_id']}'>$shortCheckoutId</td>";
        echo "<td>{$txn['package_name']} (ID: {$txn['package_id']})</td>";
        echo "<td>{$txn['phone_number']}</td>";
        echo "<td>KES {$txn['amount']}</td>";
        echo "<td>{$txn['created_at']}</td>";
        echo "<td><button onclick='testVoucherDelivery(\"{$txn['checkout_request_id']}\", {$txn['package_id']}, {$txn['reseller_id']}, \"{$txn['phone_number']}\", \"{$txn['mpesa_receipt']}\")' style='background: #3b82f6; color: white; padding: 5px 10px; border: none; border-radius: 3px; cursor: pointer;'>Test</button></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Use the first transaction for the main test
    $testTxn = $testTransactions[0];
    
} else {
    echo "<p style='color: orange;'>⚠️ No completed transactions found for testing</p>";
    echo "<p>You can create a test transaction using the payment system first.</p>";
    $testTxn = null;
}

// Step 4: SMS Gateway Test
echo "<h2>Step 4: SMS Gateway Configuration Test</h2>";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>SMS Gateway Manager Test</h4>";

try {
    $smsManager = new SmsGatewayManager();
    echo "<p style='color: green;'>✅ SMS Gateway Manager initialized successfully</p>";
    
    // Test gateway switching
    $gateways = ['textsms', 'host_pinacle', 'umeskia'];
    foreach ($gateways as $gateway) {
        $result = $smsManager->setActiveGateway($gateway);
        $status = $result ? "✅ Available" : "❌ Not Available";
        echo "<p>$gateway: $status</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ SMS Gateway Manager Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Step 5: Live Test Interface
if ($testTxn) {
    echo "<h2>Step 5: Live Voucher Delivery Test</h2>";
    
    echo "<div style='background: #f0f9ff; border: 1px solid #0ea5e9; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🚀 Test Voucher Delivery</h3>";
    echo "<p>This will test the complete voucher assignment and SMS delivery process using a real completed transaction.</p>";
    
    echo "<div style='background: white; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Test Transaction Details:</h4>";
    echo "<p><strong>Checkout ID:</strong> " . substr($testTxn['checkout_request_id'], 0, 30) . "...</p>";
    echo "<p><strong>Package:</strong> {$testTxn['package_name']} (ID: {$testTxn['package_id']})</p>";
    echo "<p><strong>Reseller ID:</strong> {$testTxn['reseller_id']}</p>";
    echo "<p><strong>Customer Phone:</strong> {$testTxn['phone_number']}</p>";
    echo "<p><strong>Amount:</strong> KES {$testTxn['amount']}</p>";
    echo "</div>";
    
    echo "<button onclick='testMainVoucherDelivery()' style='background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;'>🧪 Test Voucher Delivery & SMS</button>";
    echo "</div>";
}

echo "<div id='test-result' style='margin-top: 20px;'></div>";

// JavaScript for testing
echo "<script>
function testVoucherDelivery(checkoutId, packageId, resellerId, phone, receipt) {
    const resultDiv = document.getElementById('test-result');
    resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><h4>🔄 Testing Voucher Delivery...</h4><p>Processing voucher assignment and SMS delivery...</p></div>';
    
    const formData = new FormData();
    formData.append('test_voucher_delivery', '1');
    formData.append('checkout_request_id', checkoutId);
    formData.append('package_id', packageId);
    formData.append('reseller_id', resellerId);
    formData.append('customer_phone', phone);
    formData.append('mpesa_receipt', receipt);
    
    fetch('test_sms_voucher_delivery.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            resultDiv.innerHTML = `
                <div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #065f46; margin-top: 0;'>🎉 VOUCHER DELIVERY SUCCESS!</h3>
                    
                    <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                        <h4 style='margin: 0 0 15px 0; color: #1f2937;'>Voucher Details Delivered via SMS</h4>
                        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Voucher Code:</p>
                                <p style='margin: 0; font-family: monospace; font-size: 16px; font-weight: bold; color: #1f2937; background: #f9fafb; padding: 8px; border-radius: 4px;'>\${result.voucher_code}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Package:</p>
                                <p style='margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;'>\${result.package_name}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Username:</p>
                                <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px;'>\${result.username}</p>
                            </div>
                            <div>
                                <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Password:</p>
                                <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px;'>\${result.password}</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style='background: #ecfdf5; padding: 15px; border-radius: 6px; border: 1px solid #a7f3d0;'>
                        <h4 style='color: #065f46; margin: 0 0 10px 0;'>✅ SMS Delivery Status:</h4>
                        <p style='margin: 0; color: #065f46;'>\${result.message}</p>
                        \${result.sms_result ? '<p style=\"margin: 5px 0 0 0; font-size: 14px; color: #065f46;\">Gateway: ' + result.sms_result.gateway + '</p>' : ''}
                    </div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #dc2626; margin-top: 0;'>❌ Voucher Delivery Failed</h3>
                    <p><strong>Error:</strong> \${result.message}</p>
                    \${result.voucher_code ? '<p><strong>Voucher Code:</strong> ' + result.voucher_code + ' (assigned but SMS failed)</p>' : ''}
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
}

function testMainVoucherDelivery() {
    testVoucherDelivery(
        '{$testTxn['checkout_request_id']}',
        {$testTxn['package_id']},
        {$testTxn['reseller_id']},
        '{$testTxn['phone_number']}',
        '{$testTxn['mpesa_receipt']}'
    );
}
</script>";



echo "<h2>✅ SMS Voucher Delivery Test Ready</h2>";
echo "<p>The SMS voucher delivery system is ready for testing. Use the test buttons above to verify the complete workflow.</p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; transform: translateY(-1px); transition: all 0.2s; }
</style>
