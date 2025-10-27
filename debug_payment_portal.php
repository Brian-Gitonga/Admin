<?php
// Debug script for portal payment system
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';

// Check if the database connection is established
if (!is_db_connected()) {
    echo "Database connection failed!";
    exit();
}

echo "<h1>Portal Payment System Debug</h1>";

// 1. Check if the necessary files exist
echo "<h2>File System Check</h2>";
$requiredFiles = [
    'portal.php',
    'process_payment.php',
    'process_paystack_payment.php',
    'paystack_verify.php',
    'mpesa_settings_operations.php'
];

echo "<ul>";
foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "<li style='color:green'>✓ $file exists</li>";
    } else {
        echo "<li style='color:red'>✗ $file missing!</li>";
    }
}
echo "</ul>";

// 2. Check database tables
echo "<h2>Database Structure Check</h2>";
$tables = [
    'payment_transactions', 
    'mpesa_transactions',
    'resellers_mpesa_settings'
];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<div style='margin-bottom:20px'>";
        echo "<p style='color:green'>✓ Table '$table' exists</p>";
        
        // Show table structure
        $structure = $conn->query("DESCRIBE $table");
        if ($structure) {
            echo "<table border='1' cellpadding='5' style='font-size:14px;'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            while ($col = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        echo "</div>";
    } else {
        echo "<p style='color:red'>✗ Table '$table' missing!</p>";
    }
}

// 3. Check resellers with payment settings
echo "<h2>Reseller Payment Settings</h2>";
$query = "SELECT r.id, r.business_display_name, r.status, m.payment_gateway, m.is_active, m.environment 
          FROM resellers r
          LEFT JOIN resellers_mpesa_settings m ON r.id = m.reseller_id
          ORDER BY r.id";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='font-size:14px;'>";
    echo "<tr>
            <th>ID</th>
            <th>Business Name</th>
            <th>Status</th>
            <th>Payment Gateway</th>
            <th>Gateway Active</th>
            <th>Environment</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        $paymentGateway = $row['payment_gateway'] ?? 'Not Set';
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['business_display_name']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>$paymentGateway</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$row['environment']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No resellers found or error occurred: " . $conn->error . "</p>";
}

// 4. Check Paystack credentials for a specific reseller
if (isset($_GET['check_reseller'])) {
    $resellerId = intval($_GET['check_reseller']);
    echo "<h2>Detailed Check for Reseller ID: $resellerId</h2>";
    
    $mpesaCredentials = getMpesaCredentials($conn, $resellerId);
    
    echo "<h3>Payment Gateway Settings</h3>";
    echo "<pre>";
    echo "Payment Gateway: " . ($mpesaCredentials['payment_gateway'] ?? 'Not Set') . "\n";
    echo "Environment: " . ($mpesaCredentials['environment'] ?? 'Not Set') . "\n";
    
    if (isset($mpesaCredentials['payment_gateway'])) {
        switch ($mpesaCredentials['payment_gateway']) {
            case 'paystack':
                echo "\nPaystack Credentials:\n";
                echo "Public Key: " . (isset($mpesaCredentials['public_key']) ? substr($mpesaCredentials['public_key'], 0, 5) . "..." : 'Not Set') . "\n";
                echo "Secret Key: " . (isset($mpesaCredentials['secret_key']) ? substr($mpesaCredentials['secret_key'], 0, 5) . "..." : 'Not Set') . "\n";
                break;
                
            case 'phone':
            case 'paybill':
            case 'till':
                echo "\nM-Pesa Credentials:\n";
                echo "Consumer Key: " . (isset($mpesaCredentials['consumer_key']) ? substr($mpesaCredentials['consumer_key'], 0, 5) . "..." : 'Not Set') . "\n";
                echo "Consumer Secret: " . (isset($mpesaCredentials['consumer_secret']) ? substr($mpesaCredentials['consumer_secret'], 0, 5) . "..." : 'Not Set') . "\n";
                echo "Shortcode: " . ($mpesaCredentials['business_shortcode'] ?? 'Not Set') . "\n";
                break;
        }
    }
    
    echo "</pre>";
}

// 5. Check recent transactions
echo "<h2>Recent Transactions</h2>";

// Check M-Pesa transactions
$mpesaQuery = "SHOW TABLES LIKE 'mpesa_transactions'";
$mpesaResult = $conn->query($mpesaQuery);

if ($mpesaResult && $mpesaResult->num_rows > 0) {
    $transactionQuery = "SELECT * FROM mpesa_transactions ORDER BY id DESC LIMIT 5";
    $transactions = $conn->query($transactionQuery);
    
    if ($transactions && $transactions->num_rows > 0) {
        echo "<h3>Recent M-Pesa Transactions</h3>";
        echo "<table border='1' cellpadding='5' style='font-size:14px;'>";
        echo "<tr>
                <th>ID</th>
                <th>Checkout Request ID</th>
                <th>Amount</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Reseller ID</th>
                <th>Package</th>
              </tr>";
        
        while ($tx = $transactions->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>" . (isset($tx['checkout_request_id']) ? substr($tx['checkout_request_id'], 0, 10) . "..." : 'N/A') . "</td>";
            echo "<td>{$tx['amount']}</td>";
            echo "<td>{$tx['phone_number']}</td>";
            echo "<td>{$tx['status']}</td>";
            echo "<td>{$tx['reseller_id']}</td>";
            echo "<td>{$tx['package_name']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No recent M-Pesa transactions found.</p>";
    }
}

// Check Paystack transactions
$paystackQuery = "SHOW TABLES LIKE 'payment_transactions'";
$paystackResult = $conn->query($paystackQuery);

if ($paystackResult && $paystackResult->num_rows > 0) {
    $transactionQuery = "SELECT * FROM payment_transactions WHERE payment_gateway='paystack' ORDER BY id DESC LIMIT 5";
    $transactions = $conn->query($transactionQuery);
    
    if ($transactions && $transactions->num_rows > 0) {
        echo "<h3>Recent Paystack Transactions</h3>";
        echo "<table border='1' cellpadding='5' style='font-size:14px;'>";
        echo "<tr>
                <th>ID</th>
                <th>Reference</th>
                <th>Amount</th>
                <th>Email</th>
                <th>Status</th>
                <th>Reseller ID</th>
                <th>Package</th>
              </tr>";
        
        while ($tx = $transactions->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$tx['id']}</td>";
            echo "<td>" . (isset($tx['reference']) ? substr($tx['reference'], 0, 10) . "..." : 'N/A') . "</td>";
            echo "<td>{$tx['amount']}</td>";
            echo "<td>{$tx['email']}</td>";
            echo "<td>{$tx['status']}</td>";
            echo "<td>{$tx['reseller_id']}</td>";
            echo "<td>{$tx['package_name']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No recent Paystack transactions found.</p>";
    }
}

// 6. Server and environment info
echo "<h2>Server Environment</h2>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "HTTP_HOST: " . $_SERVER['HTTP_HOST'] . "\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "</pre>";

echo "<h2>Debug Links</h2>";
echo "<ul>";
echo "<li><a href='portal.php' target='_blank'>Open Portal</a></li>";

// Get list of resellers for detailed checks
$resellers = $conn->query("SELECT id, business_display_name FROM resellers ORDER BY id");
if ($resellers && $resellers->num_rows > 0) {
    while ($r = $resellers->fetch_assoc()) {
        echo "<li><a href='?check_reseller={$r['id']}'>Check Reseller: {$r['business_display_name']}</a></li>";
    }
}

echo "</ul>";
?>









