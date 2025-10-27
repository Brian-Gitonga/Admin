<?php
/**
 * Test Payment Flow - Simulate the complete payment verification process
 */

// Start session
session_start();

// Include required files
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set test parameters
$test_reference = 'TEST_' . time();
$test_phone = '254700123456';
$test_amount = 10;
$test_package_id = 1;
$test_router_id = 1;
$test_reseller_id = 1;

echo "<h1>🧪 Payment Flow Test</h1>";

// Step 1: Create a test transaction
echo "<h2>Step 1: Creating Test Transaction</h2>";

$insertQuery = "INSERT INTO payment_transactions (
    reference, phone_number, amount, package_id, package_name,
    router_id, reseller_id, user_id, status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

$stmt = $portal_conn->prepare($insertQuery);
if ($stmt) {
    $package_name = "Test Package";
    $stmt->bind_param("ssdiiiii",
        $test_reference,
        $test_phone,
        $test_amount,
        $test_package_id,
        $package_name,
        $test_router_id,
        $test_reseller_id,
        $test_reseller_id  // user_id same as reseller_id
    );
    
    if ($stmt->execute()) {
        echo "<p>✅ Test transaction created with reference: <strong>$test_reference</strong></p>";
    } else {
        echo "<p>❌ Failed to create test transaction: " . $stmt->error . "</p>";
        exit;
    }
} else {
    echo "<p>❌ Failed to prepare transaction insert: " . $portal_conn->error . "</p>";
    exit;
}

// Step 2: Test voucher availability
echo "<h2>Step 2: Checking Voucher Availability</h2>";

$voucherQuery = "SELECT COUNT(*) as count FROM vouchers WHERE package_id = ? AND status = 'active'";
$voucherStmt = $portal_conn->prepare($voucherQuery);
$voucherStmt->bind_param("i", $test_package_id);
$voucherStmt->execute();
$voucherResult = $voucherStmt->get_result();
$voucherCount = $voucherResult->fetch_assoc()['count'];

if ($voucherCount > 0) {
    echo "<p>✅ Found $voucherCount active vouchers for package ID $test_package_id</p>";
} else {
    echo "<p>❌ No active vouchers found for package ID $test_package_id</p>";
    echo "<p>Creating a test voucher...</p>";
    
    // Create a test voucher
    $voucherCode = 'TEST' . rand(1000, 9999);
    $createVoucherQuery = "INSERT INTO vouchers (code, username, password, package_id, reseller_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    $createStmt = $portal_conn->prepare($createVoucherQuery);
    $createStmt->bind_param("sssii", $voucherCode, $voucherCode, $voucherCode, $test_package_id, $test_reseller_id);
    
    if ($createStmt->execute()) {
        echo "<p>✅ Created test voucher: $voucherCode</p>";
    } else {
        echo "<p>❌ Failed to create test voucher: " . $createStmt->error . "</p>";
        exit;
    }
}

// Step 3: Test SMS settings
echo "<h2>Step 3: Checking SMS Settings</h2>";

$smsSettings = getSmsSettings($portal_conn, $test_reseller_id);
if ($smsSettings && $smsSettings['enable_sms']) {
    echo "<p>✅ SMS settings found and enabled</p>";
    echo "<p>Provider: " . $smsSettings['sms_provider'] . "</p>";
    echo "<p>API Key: " . (empty($smsSettings['textsms_api_key']) ? 'Not set' : 'Set') . "</p>";
} else {
    echo "<p>❌ SMS settings not found or disabled</p>";
}

// Step 4: Simulate payment verification
echo "<h2>Step 4: Simulating Payment Verification</h2>";

// Set session data (normally set during payment initialization)
$_SESSION['paystack_reference'] = $test_reference;
$_SESSION['payment_initiated'] = 1;
$_SESSION['payment_email'] = $test_phone . '@customer.qtro.co.ke';
$_SESSION['payment_amount'] = $test_amount;
$_SESSION['payment_timestamp'] = time();

// Simulate the verification URL
$verification_url = "paystack_verify.php?reference=" . urlencode($test_reference);

echo "<p>✅ Session data set for payment verification</p>";
echo "<p><strong>Next step:</strong> <a href='$verification_url' target='_blank'>Click here to test payment verification</a></p>";

// Step 5: Show current logs
echo "<h2>Step 5: Current Logs</h2>";

if (file_exists('paystack_verify.log')) {
    $logs = file_get_contents('paystack_verify.log');
    $recentLogs = implode("\n", array_slice(explode("\n", $logs), -20));
    echo "<pre style='background: #f4f4f4; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars($recentLogs);
    echo "</pre>";
} else {
    echo "<p>No paystack_verify.log file found</p>";
}

// Step 6: Instructions
echo "<h2>Step 6: Test Instructions</h2>";
echo "<ol>";
echo "<li><strong>Click the verification link above</strong> to simulate payment verification</li>";
echo "<li><strong>Check if you're redirected to payment_success.php</strong> (success) or portal.php?payment_error=true (error)</li>";
echo "<li><strong>Check your phone</strong> for SMS delivery (if SMS settings are configured)</li>";
echo "<li><strong>Review the logs</strong> to see exactly what happened during verification</li>";
echo "<li><strong>Check voucher status</strong> - it should be updated to 'used' after successful verification</li>";
echo "</ol>";

// Step 7: Cleanup option
echo "<h2>Step 7: Cleanup</h2>";
echo "<p><a href='?cleanup=1' style='color: red;'>Click here to cleanup test data</a></p>";

// Handle cleanup
if (isset($_GET['cleanup'])) {
    // Delete test transaction
    $deleteTransaction = $portal_conn->prepare("DELETE FROM payment_transactions WHERE reference = ?");
    $deleteTransaction->bind_param("s", $test_reference);
    $deleteTransaction->execute();
    
    // Reset test voucher to active (if it was used)
    $resetVoucher = $portal_conn->prepare("UPDATE vouchers SET status = 'active', customer_phone = NULL, used_at = NULL WHERE code LIKE 'TEST%'");
    $resetVoucher->execute();
    
    echo "<p>✅ Test data cleaned up</p>";
}

echo "<hr>";
echo "<p><em>Test setup completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>
