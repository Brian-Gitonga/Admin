<?php
/**
 * Debug Paystack Payment Issues
 * This script helps debug payment recording and verification issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Get reference from URL if provided
$reference = $_GET['reference'] ?? '';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Paystack Payment Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .form-group { margin: 10px 0; }
        input[type="text"] { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #007cba; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Paystack Payment Debug Tool</h1>
    
    <div class="section">
        <h2>1. Database Connection Status</h2>
        
        <?php
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
        ?>
    </div>

    <div class="section">
        <h2>2. Payment Transaction Lookup</h2>
        
        <form method="GET">
            <div class="form-group">
                <label>Enter Payment Reference:</label><br>
                <input type="text" name="reference" value="<?php echo htmlspecialchars($reference); ?>" placeholder="QTRO_68dc2d5ac0424_1759259994">
                <button type="submit">🔍 Search Transaction</button>
            </div>
        </form>
        
        <?php if (!empty($reference)): ?>
            <h3>Searching for reference: <?php echo htmlspecialchars($reference); ?></h3>
            
            <?php
            // Search in payment_transactions table
            $query = "SELECT * FROM payment_transactions WHERE reference = ?";
            $stmt = $portal_conn->prepare($query);
            $stmt->bind_param("s", $reference);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo "<span class='success'>✅ Transaction found in payment_transactions table</span><br>";
                $transaction = $result->fetch_assoc();
                
                echo "<table>";
                echo "<tr><th>Field</th><th>Value</th></tr>";
                foreach ($transaction as $key => $value) {
                    echo "<tr><td>$key</td><td>" . htmlspecialchars($value) . "</td></tr>";
                }
                echo "</table>";
                
                // Check if reseller has Paystack credentials
                echo "<h4>Checking Paystack Credentials for Reseller ID: " . $transaction['reseller_id'] . "</h4>";
                
                $mpesaCredentials = getMpesaCredentials($portal_conn, $transaction['reseller_id']);
                
                if ($mpesaCredentials) {
                    echo "<span class='info'>Payment Gateway: " . $mpesaCredentials['payment_gateway'] . "</span><br>";
                    
                    if ($mpesaCredentials['payment_gateway'] === 'paystack') {
                        echo "<span class='success'>✅ Reseller configured for Paystack</span><br>";
                        echo "<span class='info'>Public Key: " . (empty($mpesaCredentials['public_key']) ? 'Not set' : 'Set') . "</span><br>";
                        echo "<span class='info'>Secret Key: " . (empty($mpesaCredentials['secret_key']) ? 'Not set' : 'Set') . "</span><br>";
                    } else {
                        echo "<span class='error'>❌ Reseller not configured for Paystack (configured for: " . $mpesaCredentials['payment_gateway'] . ")</span><br>";
                    }
                } else {
                    echo "<span class='error'>❌ No payment credentials found for this reseller</span><br>";
                }
                
            } else {
                echo "<span class='error'>❌ Transaction NOT found in payment_transactions table</span><br>";
                
                // Check if it exists with different criteria
                echo "<h4>Searching with broader criteria...</h4>";
                
                $broadQuery = "SELECT * FROM payment_transactions WHERE reference LIKE ? OR reference = ?";
                $broadStmt = $portal_conn->prepare($broadQuery);
                $likeRef = '%' . $reference . '%';
                $broadStmt->bind_param("ss", $likeRef, $reference);
                $broadStmt->execute();
                $broadResult = $broadStmt->get_result();
                
                if ($broadResult->num_rows > 0) {
                    echo "<span class='warning'>⚠️ Found similar transactions:</span><br>";
                    echo "<table>";
                    echo "<tr><th>ID</th><th>Reference</th><th>Status</th><th>Amount</th><th>Created</th></tr>";
                    while ($row = $broadResult->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($row['reference']) . "</td>";
                        echo "<td>" . $row['status'] . "</td>";
                        echo "<td>" . $row['amount'] . "</td>";
                        echo "<td>" . $row['created_at'] . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<span class='error'>❌ No similar transactions found</span><br>";
                }
            }
            ?>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>3. Recent Payment Transactions</h2>
        
        <?php
        // Show recent transactions
        $recentQuery = "SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 10";
        $recentResult = $portal_conn->query($recentQuery);
        
        if ($recentResult && $recentResult->num_rows > 0) {
            echo "<span class='info'>Recent 10 transactions:</span><br>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Reference</th><th>Gateway</th><th>Status</th><th>Amount</th><th>Phone</th><th>Created</th></tr>";
            
            while ($row = $recentResult->fetch_assoc()) {
                $statusClass = $row['status'] === 'completed' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'error');
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['reference']) . "</td>";
                echo "<td>" . $row['payment_gateway'] . "</td>";
                echo "<td><span class='$statusClass'>" . $row['status'] . "</span></td>";
                echo "<td>" . $row['amount'] . "</td>";
                echo "<td>" . $row['phone_number'] . "</td>";
                echo "<td>" . $row['created_at'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='error'>❌ No payment transactions found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>4. Paystack Configuration Check</h2>
        
        <?php
        // Check Paystack configurations
        $paystackQuery = "SELECT r.id, r.business_name, m.payment_gateway, m.public_key, m.secret_key 
                          FROM resellers r 
                          LEFT JOIN resellers_mpesa_settings m ON r.id = m.reseller_id 
                          WHERE m.payment_gateway = 'paystack' OR m.payment_gateway IS NULL
                          ORDER BY r.id";
        $paystackResult = $portal_conn->query($paystackQuery);
        
        if ($paystackResult && $paystackResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Reseller ID</th><th>Business Name</th><th>Gateway</th><th>Public Key</th><th>Secret Key</th><th>Status</th></tr>";
            
            while ($row = $paystackResult->fetch_assoc()) {
                $gateway = $row['payment_gateway'] ?: 'Not configured';
                $publicKey = !empty($row['public_key']) ? 'Set' : 'Not set';
                $secretKey = !empty($row['secret_key']) ? 'Set' : 'Not set';
                
                $status = 'error';
                $statusText = '❌ Not ready';
                
                if ($row['payment_gateway'] === 'paystack' && !empty($row['public_key']) && !empty($row['secret_key'])) {
                    $status = 'success';
                    $statusText = '✅ Ready';
                }
                
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['business_name']) . "</td>";
                echo "<td>" . $gateway . "</td>";
                echo "<td>" . $publicKey . "</td>";
                echo "<td>" . $secretKey . "</td>";
                echo "<td><span class='$status'>$statusText</span></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='error'>❌ No resellers found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Callback URL Check</h2>
        
        <div class="code">
            <strong>Current Callback URL Pattern:</strong><br>
            http://<?php echo $_SERVER['HTTP_HOST']; ?>/SAAS/Wifi%20Billiling%20system/Admin/paystack_verify.php?reference=REFERENCE_HERE
        </div>
        
        <p><strong>Expected URL for your reference:</strong></p>
        <?php if (!empty($reference)): ?>
            <div class="code">
                http://<?php echo $_SERVER['HTTP_HOST']; ?>/SAAS/Wifi%20Billiling%20system/Admin/paystack_verify.php?reference=<?php echo htmlspecialchars($reference); ?>
            </div>
        <?php endif; ?>
        
        <p><strong>Actual URL you were redirected to:</strong></p>
        <div class="code">
            http://localhost/paystack_verify.php?reference=<?php echo htmlspecialchars($reference); ?>
        </div>
        
        <span class="error">❌ The callback URL is incorrect - it should include the full path to your admin directory</span>
    </div>

    <div class="section">
        <h2>6. Recommendations</h2>
        
        <h3>🔧 Immediate Actions:</h3>
        <ol>
            <li><strong>Fix Callback URL:</strong> The callback URL in process_paystack_payment.php has been updated to include the correct path</li>
            <li><strong>Test Payment Flow:</strong> Try a new payment to see if it records correctly</li>
            <li><strong>Check Paystack Credentials:</strong> Ensure your reseller has valid Paystack public and secret keys</li>
            <li><strong>Monitor Logs:</strong> Check paystack_verify.log for detailed error messages</li>
        </ol>
        
        <h3>📋 Testing Steps:</h3>
        <ol>
            <li>Go to your portal page</li>
            <li>Select a package and initiate payment</li>
            <li>Complete payment on Paystack</li>
            <li>Check if you're redirected to the correct URL</li>
            <li>Use this debug tool to verify the transaction was recorded</li>
        </ol>
    </div>

    <p><em>Debug completed at: <?php echo date('Y-m-d H:i:s'); ?></em></p>
</div>

</body>
</html>
