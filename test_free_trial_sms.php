<?php
/**
 * Test Free Trial SMS Functionality
 * This script tests the free trial SMS sending functionality
 */

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set content type to JSON
header('Content-Type: application/json');

echo "<h2>Free Trial SMS Test</h2>";

// Test parameters
$test_phone = '0700123456'; // Test phone number
$test_reseller_id = 1; // Test reseller ID
$test_package_id = 1; // Test package ID

echo "<h3>Step 1: Check Database Connections</h3>";

// Check main database connection
if ($conn && !$conn->connect_error) {
    echo "✅ Main database connection: OK<br>";
} else {
    echo "❌ Main database connection: FAILED<br>";
    if ($conn) echo "Error: " . $conn->connect_error . "<br>";
}

// Check portal database connection
if ($portal_conn && !$portal_conn->connect_error) {
    echo "✅ Portal database connection: OK<br>";
} else {
    echo "❌ Portal database connection: FAILED<br>";
    if ($portal_conn) echo "Error: " . $portal_conn->connect_error . "<br>";
}

echo "<h3>Step 2: Check SMS Settings</h3>";

// Test SMS settings retrieval
$smsSettings = getSmsSettings($conn, $test_reseller_id);

if ($smsSettings) {
    echo "✅ SMS settings found for reseller $test_reseller_id<br>";
    echo "SMS Provider: " . ($smsSettings['sms_provider'] ?: 'Not set') . "<br>";
    echo "SMS Enabled: " . ($smsSettings['enable_sms'] ? 'Yes' : 'No') . "<br>";
    
    if ($smsSettings['enable_sms']) {
        echo "✅ SMS is enabled<br>";
    } else {
        echo "❌ SMS is disabled<br>";
    }
} else {
    echo "❌ No SMS settings found for reseller $test_reseller_id<br>";
    
    // Try to create default SMS settings
    echo "<h4>Creating default SMS settings...</h4>";
    
    $defaultSettings = [
        'reseller_id' => $test_reseller_id,
        'enable_sms' => 1,
        'sms_provider' => 'textsms',
        'textsms_api_key' => 'test_api_key',
        'textsms_partner_id' => 'test_partner_id',
        'textsms_sender_id' => 'TEST_SMS',
        'payment_template' => 'Thank you for your free trial of {package}. Username: {username}, Password: {password}, Voucher: {voucher}'
    ];
    
    $result = saveSmsSettings($conn, $defaultSettings);
    
    if ($result['success']) {
        echo "✅ Default SMS settings created<br>";
        $smsSettings = getSmsSettings($conn, $test_reseller_id);
    } else {
        echo "❌ Failed to create SMS settings: " . $result['message'] . "<br>";
    }
}

echo "<h3>Step 3: Check Package Information</h3>";

// Get package information
$query = "SELECT * FROM packages WHERE id = ? AND reseller_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $test_package_id, $test_reseller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $package = $result->fetch_assoc();
    echo "✅ Package found: " . $package['name'] . "<br>";
    echo "Package Price: KES " . $package['price'] . "<br>";
    echo "Package Duration: " . $package['duration'] . "<br>";
} else {
    echo "❌ No package found with ID $test_package_id for reseller $test_reseller_id<br>";
    
    // Create a test package
    echo "<h4>Creating test package...</h4>";
    $insertQuery = "INSERT INTO packages (reseller_id, name, price, duration, description) VALUES (?, 'Free Trial Package', 0, '1 Hour', 'Free trial internet package')";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("i", $test_reseller_id);
    
    if ($insertStmt->execute()) {
        $test_package_id = $conn->insert_id;
        echo "✅ Test package created with ID: $test_package_id<br>";
        
        // Re-fetch package
        $stmt->bind_param("ii", $test_package_id, $test_reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $package = $result->fetch_assoc();
    } else {
        echo "❌ Failed to create test package<br>";
    }
}

echo "<h3>Step 4: Check Voucher Availability</h3>";

// Check for available vouchers
$voucherQuery = "SELECT COUNT(*) as count FROM vouchers WHERE package_id = ? AND reseller_id = ? AND status = 'active'";
$voucherStmt = $conn->prepare($voucherQuery);
$voucherStmt->bind_param("ii", $test_package_id, $test_reseller_id);
$voucherStmt->execute();
$voucherResult = $voucherStmt->get_result();
$voucherCount = $voucherResult->fetch_assoc()['count'];

echo "Available vouchers: $voucherCount<br>";

if ($voucherCount == 0) {
    echo "❌ No vouchers available<br>";
    echo "<h4>Creating test voucher...</h4>";
    
    // Create a test voucher
    $voucherCode = 'TEST' . rand(1000, 9999);
    $insertVoucherQuery = "INSERT INTO vouchers (reseller_id, package_id, code, username, password, status) VALUES (?, ?, ?, ?, ?, 'active')";
    $insertVoucherStmt = $conn->prepare($insertVoucherQuery);
    $insertVoucherStmt->bind_param("iisss", $test_reseller_id, $test_package_id, $voucherCode, $voucherCode, $voucherCode);
    
    if ($insertVoucherStmt->execute()) {
        echo "✅ Test voucher created: $voucherCode<br>";
        $voucherCount = 1;
    } else {
        echo "❌ Failed to create test voucher<br>";
    }
} else {
    echo "✅ Vouchers available<br>";
}

echo "<h3>Step 5: Test SMS Sending Function</h3>";

if ($smsSettings && $smsSettings['enable_sms'] && $voucherCount > 0 && isset($package)) {
    echo "Testing SMS sending...<br>";
    
    // Include the process_free_trial.php functions
    include_once 'process_free_trial.php';
    
    // Test the SMS function
    $smsResult = sendFreeTrialVoucherSMS(
        $test_phone,
        'TEST1234',
        'testuser',
        'testpass',
        $package['name'],
        $test_reseller_id
    );
    
    if ($smsResult['success']) {
        echo "✅ SMS function test: SUCCESS<br>";
        echo "Message: " . $smsResult['message'] . "<br>";
    } else {
        echo "❌ SMS function test: FAILED<br>";
        echo "Error: " . $smsResult['message'] . "<br>";
    }
} else {
    echo "❌ Cannot test SMS sending - prerequisites not met<br>";
    if (!$smsSettings) echo "- SMS settings missing<br>";
    if ($smsSettings && !$smsSettings['enable_sms']) echo "- SMS disabled<br>";
    if ($voucherCount == 0) echo "- No vouchers available<br>";
    if (!isset($package)) echo "- Package not found<br>";
}

echo "<h3>Test Complete</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If SMS settings are missing, configure them in the admin panel</li>";
echo "<li>If vouchers are missing, upload vouchers for the package</li>";
echo "<li>If SMS sending fails, check API credentials and provider settings</li>";
echo "<li>Test with a real phone number to verify SMS delivery</li>";
echo "</ul>";
?>
