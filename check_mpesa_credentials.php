<?php
// Diagnostic script to check M-Pesa credentials for resellers
require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';

echo "<h2>M-Pesa Credentials Diagnostic</h2>\n";
echo "<pre>\n";

// Test reseller IDs from the logs
$testResellerIds = [6, 17, 21];

foreach ($testResellerIds as $resellerId) {
    echo "\n========================================\n";
    echo "RESELLER ID: $resellerId\n";
    echo "========================================\n";
    
    // Get M-Pesa settings
    $settings = getMpesaSettings($conn, $resellerId);
    
    echo "Payment Gateway: " . ($settings['payment_gateway'] ?? 'NOT SET') . "\n";
    echo "Environment: " . ($settings['environment'] ?? 'NOT SET') . "\n";
    echo "Is Active: " . ($settings['is_active'] ? 'YES' : 'NO') . "\n\n";
    
    // Get credentials
    $credentials = getMpesaCredentials($conn, $resellerId);
    
    echo "Consumer Key: " . (empty($credentials['consumer_key']) ? '❌ EMPTY' : '✅ SET (' . strlen($credentials['consumer_key']) . ' chars)') . "\n";
    echo "Consumer Secret: " . (empty($credentials['consumer_secret']) ? '❌ EMPTY' : '✅ SET (' . strlen($credentials['consumer_secret']) . ' chars)') . "\n";
    echo "Business Shortcode: " . (empty($credentials['business_shortcode']) ? '❌ EMPTY' : '✅ SET (' . $credentials['business_shortcode'] . ')') . "\n";
    echo "Passkey: " . (empty($credentials['passkey']) ? '❌ EMPTY' : '✅ SET (' . strlen($credentials['passkey']) . ' chars)') . "\n";
    echo "Callback URL: " . (empty($credentials['callback_url']) ? '❌ EMPTY' : '✅ SET (' . $credentials['callback_url'] . ')') . "\n";
    
    // Check if credentials are valid for API calls
    if (empty($credentials['consumer_key']) || empty($credentials['consumer_secret'])) {
        echo "\n⚠️ WARNING: Missing API credentials! Access token generation will FAIL!\n";
        echo "This reseller needs to configure their M-Pesa settings.\n";
    } else {
        echo "\n✅ Credentials appear to be configured.\n";
    }
}

echo "\n========================================\n";
echo "SYSTEM DEFAULT CREDENTIALS\n";
echo "========================================\n";

$systemCreds = getSystemMpesaApiCredentials();
echo "Shortcode: " . $systemCreds['shortcode'] . "\n";
echo "Consumer Key: " . substr($systemCreds['consumer_key'], 0, 20) . "...\n";
echo "Consumer Secret: " . substr($systemCreds['consumer_secret'], 0, 20) . "...\n";
echo "Callback URL: " . $systemCreds['callback_url'] . "\n";

echo "\n========================================\n";
echo "RECOMMENDATION\n";
echo "========================================\n";
echo "For resellers with empty credentials, the system should:\n";
echo "1. Use system default credentials as fallback, OR\n";
echo "2. Require resellers to configure M-Pesa settings before accepting payments\n";

echo "</pre>\n";
?>