<?php
// Debug Payment Flow - Test script to identify payment issues
session_start();

// Include required files
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Test parameters
$test_reseller_id = 1; // Change this to an actual reseller ID
$test_router_id = 1;   // Change this to an actual router ID

echo "<h1>Payment Flow Debug Report</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h2>1. Database Connection Test</h2>";
if ($conn && $conn->ping()) {
    echo "<p class='success'>✅ Database connection successful</p>";
} else {
    echo "<p class='error'>❌ Database connection failed</p>";
    exit;
}
echo "</div>";

// Test 2: Check if reseller exists
echo "<div class='section'>";
echo "<h2>2. Reseller Validation</h2>";
$stmt = $conn->prepare("SELECT id, business_name, email, status FROM resellers WHERE id = ?");
$stmt->bind_param("i", $test_reseller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $reseller = $result->fetch_assoc();
    echo "<p class='success'>✅ Reseller found: " . htmlspecialchars($reseller['business_name']) . "</p>";
    echo "<pre>" . print_r($reseller, true) . "</pre>";
} else {
    echo "<p class='error'>❌ Reseller not found with ID: $test_reseller_id</p>";
}
echo "</div>";

// Test 3: Check payment settings
echo "<div class='section'>";
echo "<h2>3. Payment Settings Test</h2>";
$mpesaSettings = getMpesaSettings($conn, $test_reseller_id);
if ($mpesaSettings) {
    echo "<p class='success'>✅ Payment settings found</p>";
    echo "<p><strong>Payment Gateway:</strong> " . htmlspecialchars($mpesaSettings['payment_gateway']) . "</p>";
    echo "<p><strong>Environment:</strong> " . htmlspecialchars($mpesaSettings['environment']) . "</p>";
    echo "<p><strong>Is Active:</strong> " . ($mpesaSettings['is_active'] ? 'Yes' : 'No') . "</p>";
    
    // Show relevant credentials (masked for security)
    if ($mpesaSettings['payment_gateway'] === 'paystack') {
        echo "<p><strong>Paystack Secret Key:</strong> " . (empty($mpesaSettings['paystack_secret_key']) ? '❌ Not set' : '✅ Set (' . substr($mpesaSettings['paystack_secret_key'], 0, 10) . '...)') . "</p>";
        echo "<p><strong>Paystack Public Key:</strong> " . (empty($mpesaSettings['paystack_public_key']) ? '❌ Not set' : '✅ Set (' . substr($mpesaSettings['paystack_public_key'], 0, 10) . '...)') . "</p>";
    } else {
        echo "<p><strong>Consumer Key:</strong> " . (empty($mpesaSettings['paybill_consumer_key']) ? '❌ Not set' : '✅ Set (' . substr($mpesaSettings['paybill_consumer_key'], 0, 10) . '...)') . "</p>";
        echo "<p><strong>Consumer Secret:</strong> " . (empty($mpesaSettings['paybill_consumer_secret']) ? '❌ Not set' : '✅ Set (' . substr($mpesaSettings['paybill_consumer_secret'], 0, 10) . '...)') . "</p>";
    }
} else {
    echo "<p class='error'>❌ No payment settings found</p>";
}
echo "</div>";

// Test 4: Check getMpesaCredentials function
echo "<div class='section'>";
echo "<h2>4. Payment Credentials Test</h2>";
$credentials = getMpesaCredentials($conn, $test_reseller_id);
if ($credentials) {
    echo "<p class='success'>✅ Payment credentials retrieved</p>";
    echo "<p><strong>Payment Gateway:</strong> " . htmlspecialchars($credentials['payment_gateway']) . "</p>";
    
    // Show credentials based on gateway type
    if ($credentials['payment_gateway'] === 'paystack') {
        echo "<p><strong>Secret Key:</strong> " . (empty($credentials['secret_key']) ? '❌ Missing' : '✅ Present') . "</p>";
        echo "<p><strong>Public Key:</strong> " . (empty($credentials['public_key']) ? '❌ Missing' : '✅ Present') . "</p>";
    } else {
        echo "<p><strong>Consumer Key:</strong> " . (empty($credentials['consumer_key']) ? '❌ Missing' : '✅ Present') . "</p>";
        echo "<p><strong>Consumer Secret:</strong> " . (empty($credentials['consumer_secret']) ? '❌ Missing' : '✅ Present') . "</p>";
        echo "<p><strong>Business Shortcode:</strong> " . (empty($credentials['business_shortcode']) ? '❌ Missing' : '✅ Present') . "</p>";
    }
} else {
    echo "<p class='error'>❌ Failed to get payment credentials</p>";
}
echo "</div>";

// Test 5: Check router and packages
echo "<div class='section'>";
echo "<h2>5. Router and Packages Test</h2>";
$stmt = $conn->prepare("SELECT id, name, router_ip, status FROM hotspots WHERE id = ? AND reseller_id = ?");
$stmt->bind_param("ii", $test_router_id, $test_reseller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $router = $result->fetch_assoc();
    echo "<p class='success'>✅ Router found: " . htmlspecialchars($router['name']) . "</p>";
    
    // Check packages for this router
    $stmt = $conn->prepare("SELECT id, name, price, validity FROM packages WHERE reseller_id = ?");
    $stmt->bind_param("i", $test_reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p class='success'>✅ Packages found: " . $result->num_rows . " packages</p>";
        while ($package = $result->fetch_assoc()) {
            echo "<p>- " . htmlspecialchars($package['name']) . " (KES " . $package['price'] . ")</p>";
        }
    } else {
        echo "<p class='warning'>⚠️ No packages found for this reseller</p>";
    }
} else {
    echo "<p class='error'>❌ Router not found or doesn't belong to reseller</p>";
}
echo "</div>";

// Test 6: Check required files
echo "<div class='section'>";
echo "<h2>6. Required Files Test</h2>";
$required_files = [
    'process_payment.php',
    'process_paystack_payment.php',
    'check_payment_status.php',
    'mpesa_settings_operations.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ $file exists</p>";
    } else {
        echo "<p class='error'>❌ $file missing</p>";
    }
}
echo "</div>";

// Test 7: Session and URL parameters
echo "<div class='section'>";
echo "<h2>7. Session and URL Parameters Test</h2>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive') . "</p>";
echo "<p><strong>Current URL:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI']) . "</p>";
echo "<p><strong>HTTP Host:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST']) . "</p>";

// Test URL generation
$test_router_id_param = isset($_GET['router_id']) ? intval($_GET['router_id']) : $test_router_id;
$test_business_param = isset($_GET['business']) ? $_GET['business'] : 'Demo';

echo "<p><strong>Test Portal URL:</strong> portal.php?router_id=$test_router_id_param&business=" . urlencode($test_business_param) . "</p>";
echo "</div>";

// Test 8: Payment Processing Endpoints
echo "<div class='section'>";
echo "<h2>8. Payment Processing Endpoints Test</h2>";

// Test M-Pesa endpoint
if (file_exists('process_payment.php')) {
    echo "<p class='success'>✅ M-Pesa processing endpoint available</p>";
} else {
    echo "<p class='error'>❌ M-Pesa processing endpoint missing</p>";
}

// Test Paystack endpoint
if (file_exists('process_paystack_payment.php')) {
    echo "<p class='success'>✅ Paystack processing endpoint available</p>";
} else {
    echo "<p class='error'>❌ Paystack processing endpoint missing</p>";
}

// Test subscription endpoint (for index.php)
if (file_exists('paystack_initialize.php')) {
    echo "<p class='success'>✅ Subscription payment endpoint available</p>";
    
    // Check if it uses hardcoded credentials (correct for platform payments)
    $content = file_get_contents('paystack_initialize.php');
    if (strpos($content, 'sk_live_') !== false || strpos($content, 'sk_test_') !== false) {
        echo "<p class='success'>✅ Subscription endpoint uses hardcoded platform credentials (correct)</p>";
    } else {
        echo "<p class='warning'>⚠️ Subscription endpoint credentials need verification</p>";
    }
} else {
    echo "<p class='error'>❌ Subscription payment endpoint missing</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Summary</h2>";
echo "<p>This debug report helps identify issues in the payment flow.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Ensure payment settings are configured for test reseller</li>";
echo "<li>Verify API credentials are valid</li>";
echo "<li>Test actual payment processing with small amounts</li>";
echo "<li>Check logs for any runtime errors</li>";
echo "</ul>";
echo "</div>";
?>
