<?php
/**
 * Test SMS Delivery for Both Free Trial and Paid Packages
 * This script tests the complete SMS delivery workflow
 */

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'sms_settings_operations.php';

// Set content type to HTML for better display
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><title>SMS Delivery Test</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} h3{border-bottom:1px solid #ccc;}</style>";
echo "</head><body>";

echo "<h1>SMS Delivery Test Results</h1>";

// Test parameters
$test_phone = '0700123456'; // Test phone number
$test_reseller_id = 1; // Test reseller ID

echo "<h2>🔍 System Diagnostics</h2>";

// Check database connections
echo "<h3>Database Connections</h3>";
if ($conn && !$conn->connect_error) {
    echo "<span class='success'>✅ Main database connection: OK</span><br>";
} else {
    echo "<span class='error'>❌ Main database connection: FAILED</span><br>";
    if ($conn) echo "<span class='error'>Error: " . $conn->connect_error . "</span><br>";
}

if ($portal_conn && !$portal_conn->connect_error) {
    echo "<span class='success'>✅ Portal database connection: OK</span><br>";
} else {
    echo "<span class='error'>❌ Portal database connection: FAILED</span><br>";
    if ($portal_conn) echo "<span class='error'>Error: " . $portal_conn->connect_error . "</span><br>";
}

// Check SMS settings
echo "<h3>SMS Configuration</h3>";
$smsSettings = getSmsSettings($conn, $test_reseller_id);

if ($smsSettings) {
    echo "<span class='success'>✅ SMS settings found for reseller $test_reseller_id</span><br>";
    echo "<span class='info'>SMS Provider: " . ($smsSettings['sms_provider'] ?: 'Not set') . "</span><br>";
    echo "<span class='info'>SMS Enabled: " . ($smsSettings['enable_sms'] ? 'Yes' : 'No') . "</span><br>";
    
    if ($smsSettings['sms_provider'] === 'textsms') {
        echo "<span class='info'>TextSMS API Key: " . (empty($smsSettings['textsms_api_key']) ? 'Not set' : 'Set') . "</span><br>";
        echo "<span class='info'>TextSMS Partner ID: " . (empty($smsSettings['textsms_partner_id']) ? 'Not set' : 'Set') . "</span><br>";
        echo "<span class='info'>TextSMS Sender ID: " . ($smsSettings['textsms_sender_id'] ?: 'Not set') . "</span><br>";
    }
    
    if ($smsSettings['sms_provider'] === 'africas-talking') {
        echo "<span class='info'>Africa's Talking Username: " . (empty($smsSettings['at_username']) ? 'Not set' : 'Set') . "</span><br>";
        echo "<span class='info'>Africa's Talking API Key: " . (empty($smsSettings['at_api_key']) ? 'Not set' : 'Set') . "</span><br>";
    }
    
    echo "<span class='info'>Payment Template: " . ($smsSettings['payment_template'] ?: 'Using default') . "</span><br>";
} else {
    echo "<span class='error'>❌ No SMS settings found for reseller $test_reseller_id</span><br>";
    echo "<span class='info'>💡 You need to configure SMS settings in the admin panel first</span><br>";
}

// Check packages and vouchers
echo "<h3>Packages and Vouchers</h3>";
$packageQuery = "SELECT p.*, COUNT(v.id) as voucher_count 
                 FROM packages p 
                 LEFT JOIN vouchers v ON p.id = v.package_id AND v.status = 'active'
                 WHERE p.reseller_id = ? 
                 GROUP BY p.id 
                 ORDER BY p.id";
$packageStmt = $conn->prepare($packageQuery);
$packageStmt->bind_param("i", $test_reseller_id);
$packageStmt->execute();
$packageResult = $packageStmt->get_result();

if ($packageResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Package ID</th><th>Name</th><th>Price</th><th>Available Vouchers</th><th>Status</th></tr>";
    
    while ($package = $packageResult->fetch_assoc()) {
        $statusClass = $package['voucher_count'] > 0 ? 'success' : 'error';
        $statusText = $package['voucher_count'] > 0 ? '✅ Ready' : '❌ No vouchers';
        
        echo "<tr>";
        echo "<td>" . $package['id'] . "</td>";
        echo "<td>" . $package['name'] . "</td>";
        echo "<td>KES " . $package['price'] . "</td>";
        echo "<td>" . $package['voucher_count'] . "</td>";
        echo "<td><span class='$statusClass'>$statusText</span></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<span class='error'>❌ No packages found for reseller $test_reseller_id</span><br>";
}

echo "<h2>🧪 SMS Function Tests</h2>";

if ($smsSettings && $smsSettings['enable_sms']) {
    
    // Test 1: Free Trial SMS Function
    echo "<h3>Test 1: Free Trial SMS Function</h3>";
    
    // Include the free trial functions
    include_once 'process_free_trial.php';
    
    if (function_exists('sendFreeTrialVoucherSMS')) {
        echo "<span class='info'>Testing free trial SMS function...</span><br>";
        
        $freeTrialResult = sendFreeTrialVoucherSMS(
            $test_phone,
            'FREETRIAL123',
            'freetrial',
            'freetrial123',
            'Free Trial Package',
            $test_reseller_id
        );
        
        if ($freeTrialResult['success']) {
            echo "<span class='success'>✅ Free trial SMS function: SUCCESS</span><br>";
            echo "<span class='info'>Message: " . $freeTrialResult['message'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Free trial SMS function: FAILED</span><br>";
            echo "<span class='error'>Error: " . $freeTrialResult['message'] . "</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Free trial SMS function not found</span><br>";
    }
    
    // Test 2: Paid Package SMS Function
    echo "<h3>Test 2: Paid Package SMS Function</h3>";
    
    // Include the paystack verify functions
    include_once 'paystack_verify.php';
    
    if (function_exists('sendVoucherSMS')) {
        echo "<span class='info'>Testing paid package SMS function...</span><br>";
        
        $paidResult = sendVoucherSMS(
            $test_phone,
            'PAID123',
            'paiduser',
            'paidpass123',
            'Premium Package',
            $test_reseller_id
        );
        
        if ($paidResult['success']) {
            echo "<span class='success'>✅ Paid package SMS function: SUCCESS</span><br>";
            echo "<span class='info'>Message: " . $paidResult['message'] . "</span><br>";
        } else {
            echo "<span class='error'>❌ Paid package SMS function: FAILED</span><br>";
            echo "<span class='error'>Error: " . $paidResult['message'] . "</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Paid package SMS function not found</span><br>";
    }
    
} else {
    echo "<span class='error'>❌ Cannot test SMS functions - SMS is not enabled or configured</span><br>";
}

echo "<h2>📋 Recommendations</h2>";

echo "<div style='background-color:#f0f8ff;padding:15px;border-left:4px solid #0066cc;'>";
echo "<h3>To Fix SMS Issues:</h3>";
echo "<ol>";

if (!$smsSettings) {
    echo "<li><strong>Configure SMS Settings:</strong> Go to Settings → SMS Settings and configure your SMS provider credentials</li>";
}

if ($smsSettings && !$smsSettings['enable_sms']) {
    echo "<li><strong>Enable SMS:</strong> Enable SMS in your SMS settings</li>";
}

if ($smsSettings && empty($smsSettings['textsms_api_key']) && $smsSettings['sms_provider'] === 'textsms') {
    echo "<li><strong>Set TextSMS Credentials:</strong> Add your TextSMS API key and Partner ID</li>";
}

if ($smsSettings && empty($smsSettings['at_api_key']) && $smsSettings['sms_provider'] === 'africas-talking') {
    echo "<li><strong>Set Africa's Talking Credentials:</strong> Add your Africa's Talking username and API key</li>";
}

echo "<li><strong>Test with Real Phone:</strong> Replace the test phone number ($test_phone) with your actual phone number</li>";
echo "<li><strong>Check SMS Provider Balance:</strong> Ensure your SMS provider account has sufficient balance</li>";
echo "<li><strong>Verify Phone Format:</strong> Ensure phone numbers are in correct format (254XXXXXXXXX for Kenya)</li>";
echo "<li><strong>Monitor Logs:</strong> Check error logs for detailed SMS sending errors</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔧 Quick Fixes</h2>";

echo "<div style='background-color:#fff8dc;padding:15px;border-left:4px solid #ffa500;'>";
echo "<h3>If you want to test immediately:</h3>";
echo "<ol>";
echo "<li>Go to <strong>Settings → SMS Settings</strong></li>";
echo "<li>Enable SMS and select a provider (TextSMS recommended for Kenya)</li>";
echo "<li>Add your SMS provider credentials</li>";
echo "<li>Set a payment template like: <code>Thank you for purchasing {package}. Username: {username}, Password: {password}, Voucher: {voucher}</code></li>";
echo "<li>Upload vouchers for your packages</li>";
echo "<li>Test free trial and payment flows</li>";
echo "</ol>";
echo "</div>";

echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";

echo "</body></html>";
?>
