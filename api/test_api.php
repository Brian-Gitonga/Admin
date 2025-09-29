<?php
/**
 * Simple test script for the Batch Voucher API
 * This script helps verify that the API is working correctly
 */

// Configuration - Auto-detect the correct URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentDir = dirname($_SERVER['REQUEST_URI'] ?? '/Admin/api/');
$baseUrl = $protocol . '://' . $host . $currentDir . '/';

// Fallback URL if auto-detection fails
if (strpos($baseUrl, 'test_api.php') !== false) {
    $baseUrl = 'http://localhost/Wifi%20Billiling%20system/Admin/api/';
}

$apiKey = 'qtro_3c470fbe559e7bed6ec2321ce82c1cb43faac2b42f48bd62d4a0dfa266aadc38'; // Replace with actual API key

// Test data
$testData = [
    'router_id' => 'admin router', // Replace with actual router name
    'vouchers' => [
        [
            'voucher_code' => 'TEST005',
            'profile' => '2Mbps',
            'validity' => '2h',
            'created_at' => date('Y-m-d H:i:s'),
            'comment' => 'API test batch',
            'metadata' => [
                'mikhmon_version' => '3.0',
                'generated_by' => 'api_test',
                'password' => 'TEST005',
                'time_limit' => '2h',
                'data_limit' => '1073741824',
                'user_mode' => 'vc'
            ]
        ],
        [
            'voucher_code' => 'TEST006',
            'profile' => '5Mbps',
            'validity' => '1d',
            'created_at' => date('Y-m-d H:i:s'),
            'comment' => 'API test batch',
            'metadata' => [
                'mikhmon_version' => '3.0',
                'generated_by' => 'api_test',
                'password' => 'TEST006',
                'time_limit' => '1d',
                'data_limit' => '2147483648',
                'user_mode' => 'vc'
            ]
        ]
    ]
];

/**
 * Make API request
 */
function makeApiRequest($url, $data, $apiKey) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

// Check if this is a web request or command line
$isWeb = isset($_SERVER['HTTP_HOST']);

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><head><title>API Test</title><style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style></head><body>';
    echo '<h1>Batch Voucher API Test</h1>';
} else {
    echo "Batch Voucher API Test\n";
    echo "=====================\n\n";
}

// Test 1: Check if API key is set
if ($apiKey === 'YOUR_API_KEY_HERE') {
    $message = 'Please set your API key in the $apiKey variable at the top of this file.';
    if ($isWeb) {
        echo '<div class="error">❌ ' . $message . '</div>';
        echo '<p>You can generate an API key from the Router Management Dashboard.</p>';
        echo '</body></html>';
    } else {
        echo "❌ $message\n";
        echo "You can generate an API key from the Router Management Dashboard.\n";
    }
    exit;
}

// Test 2: Make API request
$url = $baseUrl . 'vouchers';

// Try alternative URL if the first one fails
$alternativeUrl = $baseUrl . 'vouchers.php';

if ($isWeb) {
    echo '<div class="info"><strong>Trying URL:</strong> ' . $url . '</div>';
}

$result = makeApiRequest($url, $testData, $apiKey);

// If we get 404, try the alternative URL
if ($result['http_code'] === 404) {
    if ($isWeb) {
        echo '<div class="warning">⚠️ First URL failed with 404, trying alternative: ' . $alternativeUrl . '</div>';
    }
    $url = $alternativeUrl;
    $result = makeApiRequest($url, $testData, $apiKey);
}

if ($isWeb) {
    echo '<h2>Test Results</h2>';
    echo '<div class="info"><strong>API Endpoint:</strong> ' . $url . '</div>';
    echo '<div class="info"><strong>HTTP Status Code:</strong> ' . $result['http_code'] . '</div>';
} else {
    echo "API Endpoint: $url\n";
    echo "HTTP Status Code: " . $result['http_code'] . "\n";
}

if ($result['error']) {
    $message = 'cURL Error: ' . $result['error'];
    if ($isWeb) {
        echo '<div class="error">❌ ' . $message . '</div>';
    } else {
        echo "❌ $message\n";
    }
} else {
    $responseData = json_decode($result['response'], true);
    
    if ($result['http_code'] === 200) {
        if ($responseData && $responseData['success']) {
            $message = 'API request successful!';
            if ($isWeb) {
                echo '<div class="success">✅ ' . $message . '</div>';
                echo '<h3>Response Data:</h3>';
                echo '<pre>' . json_encode($responseData, JSON_PRETTY_PRINT) . '</pre>';
            } else {
                echo "✅ $message\n";
                echo "Response Data:\n";
                echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            $message = 'API returned success=false: ' . ($responseData['message'] ?? 'Unknown error');
            if ($isWeb) {
                echo '<div class="error">❌ ' . $message . '</div>';
                echo '<h3>Response Data:</h3>';
                echo '<pre>' . json_encode($responseData, JSON_PRETTY_PRINT) . '</pre>';
            } else {
                echo "❌ $message\n";
                echo "Response Data:\n";
                echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
            }
        }
    } else {
        $message = 'API request failed with HTTP ' . $result['http_code'];
        if ($isWeb) {
            echo '<div class="error">❌ ' . $message . '</div>';
            echo '<h3>Response:</h3>';
            echo '<pre>' . htmlspecialchars($result['response']) . '</pre>';
        } else {
            echo "❌ $message\n";
            echo "Response:\n";
            echo $result['response'] . "\n";
        }
    }
}

// Test 3: Show test data that was sent
if ($isWeb) {
    echo '<h3>Test Data Sent:</h3>';
    echo '<pre>' . json_encode($testData, JSON_PRETTY_PRINT) . '</pre>';
    echo '<h3>Instructions:</h3>';
    echo '<ol>';
    echo '<li>Make sure you have run the database migration: <code>Admin/api_migration.sql</code></li>';
    echo '<li>Generate an API key from the MikroTik Dashboard</li>';
    echo '<li>Update the <code>$apiKey</code> variable in this file</li>';
    echo '<li>Update the <code>router_id</code> in the test data to match one of your routers</li>';
    echo '<li>Run this test again</li>';
    echo '</ol>';
    echo '</body></html>';
} else {
    echo "\nTest Data Sent:\n";
    echo json_encode($testData, JSON_PRETTY_PRINT) . "\n";
    echo "\nInstructions:\n";
    echo "1. Make sure you have run the database migration: Admin/api_migration.sql\n";
    echo "2. Generate an API key from the Router Management Dashboard\n";
    echo "3. Update the \$apiKey variable in this file\n";
    echo "4. Update the router_id in the test data to match one of your routers\n";
    echo "5. Run this test again\n";
}
?>
