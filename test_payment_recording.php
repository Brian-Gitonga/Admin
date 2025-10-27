<?php
/**
 * Test Payment Recording
 * This script tests if payment transactions are being recorded correctly
 */

// Include required files
require_once 'connection_dp.php';
require_once 'portal_connection.php';
require_once 'mpesa_settings_operations.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Handle test transaction creation
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test'])) {
    $testReference = 'TEST_' . time() . '_' . rand(1000, 9999);
    $testResellerId = $_POST['reseller_id'] ?? 1;
    $testPackageId = $_POST['package_id'] ?? 1;
    $testAmount = $_POST['amount'] ?? 10;
    $testPhone = $_POST['phone'] ?? '254700123456';
    
    // Insert test transaction
    $insertQuery = "INSERT INTO payment_transactions 
                    (reference, reseller_id, package_id, package_name, amount, phone_number, 
                     payment_gateway, status, created_at) 
                    VALUES (?, ?, ?, 'Test Package', ?, ?, 'paystack', 'pending', NOW())";
    
    $stmt = $portal_conn->prepare($insertQuery);
    $stmt->bind_param("siiis", $testReference, $testResellerId, $testPackageId, $testAmount, $testPhone);
    
    if ($stmt->execute()) {
        $testResult = [
            'success' => true,
            'message' => 'Test transaction created successfully',
            'reference' => $testReference,
            'id' => $portal_conn->insert_id
        ];
    } else {
        $testResult = [
            'success' => false,
            'message' => 'Failed to create test transaction: ' . $stmt->error
        ];
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Recording Test</title>
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
        .form-group { margin: 10px 0; }
        input, select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; margin-right: 10px; }
        button { background-color: #007cba; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .code { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>💳 Payment Recording Test</h1>
    
    <div class="section">
        <h2>1. Database Connection Status</h2>
        
        <?php
        if ($portal_conn && !$portal_conn->connect_error) {
            echo "<span class='success'>✅ Portal Database: Connected</span><br>";
        } else {
            echo "<span class='error'>❌ Portal Database: Failed</span><br>";
            if ($portal_conn) echo "<span class='error'>Error: " . $portal_conn->connect_error . "</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. Payment Transactions Table Structure</h2>
        
        <?php
        // Check if payment_transactions table exists and show structure
        $tableCheck = $portal_conn->query("SHOW TABLES LIKE 'payment_transactions'");
        
        if ($tableCheck->num_rows > 0) {
            echo "<span class='success'>✅ payment_transactions table exists</span><br>";
            
            // Show table structure
            $structure = $portal_conn->query("DESCRIBE payment_transactions");
            if ($structure) {
                echo "<h4>Table Structure:</h4>";
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
                while ($row = $structure->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row['Field'] . "</td>";
                    echo "<td>" . $row['Type'] . "</td>";
                    echo "<td>" . $row['Null'] . "</td>";
                    echo "<td>" . $row['Key'] . "</td>";
                    echo "<td>" . $row['Default'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<span class='error'>❌ payment_transactions table does not exist</span><br>";
            echo "<span class='warning'>⚠️ You need to create the payment_transactions table first</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Create Test Transaction</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Reseller ID:</label>
                <input type="number" name="reseller_id" value="1" min="1" required>
                
                <label>Package ID:</label>
                <input type="number" name="package_id" value="1" min="1" required>
                
                <label>Amount:</label>
                <input type="number" name="amount" value="10" min="1" required>
                
                <label>Phone:</label>
                <input type="text" name="phone" value="254700123456" required>
                
                <button type="submit" name="create_test">Create Test Transaction</button>
            </div>
        </form>
        
        <?php if ($testResult): ?>
            <div class="<?php echo $testResult['success'] ? 'success' : 'error'; ?>">
                <?php echo $testResult['success'] ? '✅' : '❌'; ?> <?php echo $testResult['message']; ?>
                
                <?php if ($testResult['success']): ?>
                    <br><strong>Reference:</strong> <?php echo $testResult['reference']; ?>
                    <br><strong>Transaction ID:</strong> <?php echo $testResult['id']; ?>
                    
                    <div class="code">
                        Test URL: http://<?php echo $_SERVER['HTTP_HOST']; ?>/SAAS/Wifi%20Billiling%20system/Admin/paystack_verify.php?reference=<?php echo $testResult['reference']; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>4. Recent Transactions</h2>
        
        <?php
        // Show recent transactions
        $recentQuery = "SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 10";
        $recentResult = $portal_conn->query($recentQuery);
        
        if ($recentResult && $recentResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Reference</th><th>Gateway</th><th>Status</th><th>Amount</th><th>Phone</th><th>Created</th><th>Actions</th></tr>";
            
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
                echo "<td><a href='debug_paystack_payment.php?reference=" . urlencode($row['reference']) . "' target='_blank'>Debug</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='error'>❌ No payment transactions found</span><br>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Paystack Configuration Check</h2>
        
        <?php
        // Check resellers with Paystack configuration
        $paystackQuery = "SELECT r.id, r.business_name, m.payment_gateway, 
                                 CASE WHEN m.public_key IS NOT NULL AND m.public_key != '' THEN 'Set' ELSE 'Not Set' END as public_key_status,
                                 CASE WHEN m.secret_key IS NOT NULL AND m.secret_key != '' THEN 'Set' ELSE 'Not Set' END as secret_key_status
                          FROM resellers r 
                          LEFT JOIN resellers_mpesa_settings m ON r.id = m.reseller_id 
                          ORDER BY r.id LIMIT 5";
        $paystackResult = $portal_conn->query($paystackQuery);
        
        if ($paystackResult && $paystackResult->num_rows > 0) {
            echo "<table>";
            echo "<tr><th>Reseller ID</th><th>Business Name</th><th>Gateway</th><th>Public Key</th><th>Secret Key</th><th>Status</th></tr>";
            
            while ($row = $paystackResult->fetch_assoc()) {
                $gateway = $row['payment_gateway'] ?: 'Not configured';
                $status = 'error';
                $statusText = '❌ Not ready';
                
                if ($row['payment_gateway'] === 'paystack' && $row['public_key_status'] === 'Set' && $row['secret_key_status'] === 'Set') {
                    $status = 'success';
                    $statusText = '✅ Ready';
                }
                
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['business_name']) . "</td>";
                echo "<td>" . $gateway . "</td>";
                echo "<td>" . $row['public_key_status'] . "</td>";
                echo "<td>" . $row['secret_key_status'] . "</td>";
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
        <h2>6. Testing Instructions</h2>
        
        <h3>🧪 How to Test Payment Recording:</h3>
        <ol>
            <li><strong>Create Test Transaction:</strong> Use the form above to create a test transaction</li>
            <li><strong>Test Verification URL:</strong> Click the generated test URL to see if paystack_verify.php can find the transaction</li>
            <li><strong>Check Logs:</strong> Look at paystack_verify.log for detailed debugging information</li>
            <li><strong>Real Payment Test:</strong> Try a real payment with a small amount (KES 1-10)</li>
            <li><strong>Debug Issues:</strong> Use the debug links to investigate any problems</li>
        </ol>
        
        <h3>🔧 Common Issues and Solutions:</h3>
        <ul>
            <li><strong>Transaction not found:</strong> Check if the reference matches exactly</li>
            <li><strong>Wrong callback URL:</strong> Ensure process_paystack_payment.php has the correct callback URL</li>
            <li><strong>Database connection:</strong> Verify portal_connection.php is working</li>
            <li><strong>Paystack credentials:</strong> Ensure reseller has valid Paystack keys</li>
        </ul>
    </div>

    <p><em>Test completed at: <?php echo date('Y-m-d H:i:s'); ?></em></p>
</div>

</body>
</html>
