<?php
/**
 * Debug SMS Issues - Comprehensive Debugging Script
 * This script helps identify and fix SMS delivery issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>SMS Issues Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .code { background-color: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>

<h1>🔍 SMS Issues Debug Report</h1>

<?php
// Test parameters
$test_reseller_id = 1;
$test_phone = '0700123456';

echo "<div class='section'>";
echo "<h2>1. Database Connection Status</h2>";

// Check main database
if ($conn && !$conn->connect_error) {
    echo "<span class='success'>✅ Main Database: Connected</span><br>";
} else {
    echo "<span class='error'>❌ Main Database: Failed</span><br>";
    if ($conn) echo "<span class='error'>Error: " . $conn->connect_error . "</span><br>";
}

// Check portal database
if ($portal_conn && !$portal_conn->connect_error) {
    echo "<span class='success'>✅ Portal Database: Connected</span><br>";
} else {
    echo "<span class='error'>❌ Portal Database: Failed</span><br>";
    if ($portal_conn) echo "<span class='error'>Error: " . $portal_conn->connect_error . "</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>2. SMS Settings Analysis</h2>";

$smsSettings = getSmsSettings($conn, $test_reseller_id);

if ($smsSettings) {
    echo "<span class='success'>✅ SMS Settings Found</span><br>";
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";
    
    $settings_to_check = [
        'enable_sms' => $smsSettings['enable_sms'] ? 'Enabled' : 'Disabled',
        'sms_provider' => $smsSettings['sms_provider'] ?: 'Not Set',
        'textsms_api_key' => !empty($smsSettings['textsms_api_key']) ? 'Set (****)' : 'Not Set',
        'textsms_partner_id' => !empty($smsSettings['textsms_partner_id']) ? 'Set (****)' : 'Not Set',
        'textsms_sender_id' => $smsSettings['textsms_sender_id'] ?: 'Not Set',
        'at_username' => !empty($smsSettings['at_username']) ? 'Set (****)' : 'Not Set',
        'at_api_key' => !empty($smsSettings['at_api_key']) ? 'Set (****)' : 'Not Set',
        'payment_template' => !empty($smsSettings['payment_template']) ? 'Set' : 'Using Default'
    ];
    
    foreach ($settings_to_check as $key => $value) {
        $status_class = 'info';
        $status_text = 'OK';
        
        if ($key === 'enable_sms' && !$smsSettings['enable_sms']) {
            $status_class = 'error';
            $status_text = 'DISABLED';
        } elseif (in_array($key, ['sms_provider']) && empty($smsSettings[$key])) {
            $status_class = 'error';
            $status_text = 'MISSING';
        } elseif ($smsSettings['sms_provider'] === 'textsms' && in_array($key, ['textsms_api_key', 'textsms_partner_id']) && empty($smsSettings[$key])) {
            $status_class = 'error';
            $status_text = 'REQUIRED';
        } elseif ($smsSettings['sms_provider'] === 'africas-talking' && in_array($key, ['at_username', 'at_api_key']) && empty($smsSettings[$key])) {
            $status_class = 'error';
            $status_text = 'REQUIRED';
        }
        
        echo "<tr>";
        echo "<td>$key</td>";
        echo "<td>$value</td>";
        echo "<td><span class='$status_class'>$status_text</span></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<span class='error'>❌ No SMS Settings Found</span><br>";
    echo "<span class='warning'>⚠️ You need to configure SMS settings first!</span><br>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>3. Test Data Availability</h2>";

// Check for test reseller
$resellerQuery = "SELECT * FROM resellers WHERE id = ?";
$resellerStmt = $conn->prepare($resellerQuery);
$resellerStmt->bind_param("i", $test_reseller_id);
$resellerStmt->execute();
$resellerResult = $resellerStmt->get_result();

if ($resellerResult->num_rows > 0) {
    $reseller = $resellerResult->fetch_assoc();
    echo "<span class='success'>✅ Test Reseller Found: " . $reseller['business_name'] . "</span><br>";
} else {
    echo "<span class='error'>❌ Test Reseller Not Found (ID: $test_reseller_id)</span><br>";
}

// Check for packages
$packageQuery = "SELECT COUNT(*) as count FROM packages WHERE reseller_id = ?";
$packageStmt = $conn->prepare($packageQuery);
$packageStmt->bind_param("i", $test_reseller_id);
$packageStmt->execute();
$packageResult = $packageStmt->get_result();
$packageCount = $packageResult->fetch_assoc()['count'];

echo "<span class='" . ($packageCount > 0 ? 'success' : 'error') . "'>";
echo ($packageCount > 0 ? '✅' : '❌') . " Packages Available: $packageCount</span><br>";

// Check for vouchers
$voucherQuery = "SELECT COUNT(*) as count FROM vouchers WHERE reseller_id = ? AND status = 'active'";
$voucherStmt = $conn->prepare($voucherQuery);
$voucherStmt->bind_param("i", $test_reseller_id);
$voucherStmt->execute();
$voucherResult = $voucherStmt->get_result();
$voucherCount = $voucherResult->fetch_assoc()['count'];

echo "<span class='" . ($voucherCount > 0 ? 'success' : 'error') . "'>";
echo ($voucherCount > 0 ? '✅' : '❌') . " Active Vouchers: $voucherCount</span><br>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>4. Function Availability Test</h2>";

// Check if required functions exist
$functions_to_check = [
    'getSmsSettings' => function_exists('getSmsSettings'),
    'sendFreeTrialVoucherSMS' => false, // Will check after include
    'sendVoucherSMS' => false // Will check after include
];

// Include files to check functions
if (file_exists('process_free_trial.php')) {
    include_once 'process_free_trial.php';
    $functions_to_check['sendFreeTrialVoucherSMS'] = function_exists('sendFreeTrialVoucherSMS');
}

if (file_exists('paystack_verify.php')) {
    include_once 'paystack_verify.php';
    $functions_to_check['sendVoucherSMS'] = function_exists('sendVoucherSMS');
}

foreach ($functions_to_check as $func => $exists) {
    echo "<span class='" . ($exists ? 'success' : 'error') . "'>";
    echo ($exists ? '✅' : '❌') . " Function $func: " . ($exists ? 'Available' : 'Missing') . "</span><br>";
}

echo "</div>";

if ($smsSettings && $smsSettings['enable_sms']) {
    echo "<div class='section'>";
    echo "<h2>5. SMS Function Test</h2>";
    
    if (function_exists('sendFreeTrialVoucherSMS')) {
        echo "<h3>Testing Free Trial SMS Function</h3>";
        
        $testResult = sendFreeTrialVoucherSMS(
            $test_phone,
            'TEST123',
            'testuser',
            'testpass',
            'Test Package',
            $test_reseller_id
        );
        
        if ($testResult['success']) {
            echo "<span class='success'>✅ Free Trial SMS Test: SUCCESS</span><br>";
            echo "<span class='info'>Message: " . $testResult['message'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Free Trial SMS Test: FAILED</span><br>";
            echo "<span class='error'>Error: " . $testResult['message'] . "</span><br>";
        }
    }
    
    if (function_exists('sendVoucherSMS')) {
        echo "<h3>Testing Paid Package SMS Function</h3>";
        
        $testResult2 = sendVoucherSMS(
            $test_phone,
            'PAID123',
            'paiduser',
            'paidpass',
            'Paid Package',
            $test_reseller_id
        );
        
        if ($testResult2['success']) {
            echo "<span class='success'>✅ Paid Package SMS Test: SUCCESS</span><br>";
            echo "<span class='info'>Message: " . $testResult2['message'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Paid Package SMS Test: FAILED</span><br>";
            echo "<span class='error'>Error: " . $testResult2['message'] . "</span><br>";
        }
    }
    
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>6. Recommendations</h2>";

echo "<h3>🔧 Immediate Actions Required:</h3>";
echo "<ol>";

if (!$smsSettings) {
    echo "<li><strong>Configure SMS Settings:</strong> Go to Settings → SMS Settings and set up your SMS provider</li>";
} else {
    if (!$smsSettings['enable_sms']) {
        echo "<li><strong>Enable SMS:</strong> Turn on SMS in your settings</li>";
    }
    
    if (empty($smsSettings['sms_provider'])) {
        echo "<li><strong>Select SMS Provider:</strong> Choose TextSMS or Africa's Talking</li>";
    }
    
    if ($smsSettings['sms_provider'] === 'textsms' && (empty($smsSettings['textsms_api_key']) || empty($smsSettings['textsms_partner_id']))) {
        echo "<li><strong>Add TextSMS Credentials:</strong> Set your API key and Partner ID</li>";
    }
    
    if ($smsSettings['sms_provider'] === 'africas-talking' && (empty($smsSettings['at_username']) || empty($smsSettings['at_api_key']))) {
        echo "<li><strong>Add Africa's Talking Credentials:</strong> Set your username and API key</li>";
    }
}

if ($packageCount == 0) {
    echo "<li><strong>Create Packages:</strong> Add internet packages for your reseller</li>";
}

if ($voucherCount == 0) {
    echo "<li><strong>Upload Vouchers:</strong> Add vouchers for your packages</li>";
}

echo "<li><strong>Test with Real Phone:</strong> Use your actual phone number for testing</li>";
echo "<li><strong>Check SMS Balance:</strong> Ensure your SMS provider account has credit</li>";
echo "</ol>";

echo "<h3>📱 For Testing:</h3>";
echo "<div class='code'>";
echo "1. Go to: <strong>Settings → SMS Settings</strong><br>";
echo "2. Enable SMS and select provider<br>";
echo "3. Add credentials (API keys, etc.)<br>";
echo "4. Set template: <strong>Your voucher: {voucher}, Username: {username}, Password: {password}</strong><br>";
echo "5. Test free trial on portal page<br>";
echo "6. Test payment flow with small amount<br>";
echo "</div>";

echo "</div>";

echo "<p><em>Debug completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>

</body>
</html>
