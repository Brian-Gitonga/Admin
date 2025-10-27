<?php
/**
 * Add voucher_code column to mpesa_transactions table
 * 
 * This script adds the missing voucher_code column that is needed
 * for storing assigned voucher codes in the mpesa_transactions table
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Voucher Code Column</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1, h2 { color: #333; }
        .status-box { padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .error { background: #fef2f2; border: 1px solid #ef4444; color: #dc2626; }
        .info { background: #f0f9ff; border: 1px solid #0ea5e9; color: #0c4a6e; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #059669; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Add Voucher Code Column</h1>
        
        <div class="info status-box">
            <h3>📋 What This Does:</h3>
            <p>This script adds the <code>voucher_code</code> column to the <code>mpesa_transactions</code> table.</p>
            <p>This column is required for storing assigned voucher codes after payment completion.</p>
        </div>
        
        <?php
        if (isset($_POST['add_column'])) {
            echo "<h2>🔄 Running Migration...</h2>";
            
            // Check if column already exists
            $checkSql = "SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_code'";
            $checkResult = $conn->query($checkSql);
            
            if ($checkResult && $checkResult->num_rows > 0) {
                echo "<div class='warning status-box'>";
                echo "<h3>⚠️ Column Already Exists</h3>";
                echo "<p>The <code>voucher_code</code> column already exists in the <code>mpesa_transactions</code> table.</p>";
                echo "<p>No changes needed!</p>";
                echo "</div>";
            } else {
                // Add the column
                $alterSql = "ALTER TABLE mpesa_transactions 
                            ADD COLUMN voucher_code VARCHAR(50) DEFAULT NULL AFTER result_description,
                            ADD COLUMN notes TEXT DEFAULT NULL AFTER voucher_code";
                
                if ($conn->query($alterSql)) {
                    echo "<div class='success status-box'>";
                    echo "<h3>✅ Migration Successful!</h3>";
                    echo "<p>The <code>voucher_code</code> and <code>notes</code> columns have been added to the <code>mpesa_transactions</code> table.</p>";
                    echo "<p><strong>SQL Executed:</strong></p>";
                    echo "<pre>$alterSql</pre>";
                    echo "</div>";
                    
                    // Verify the column was added
                    $verifySql = "SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_code'";
                    $verifyResult = $conn->query($verifySql);
                    
                    if ($verifyResult && $verifyResult->num_rows > 0) {
                        $columnInfo = $verifyResult->fetch_assoc();
                        echo "<div class='success status-box'>";
                        echo "<h3>✅ Verification Successful</h3>";
                        echo "<p><strong>Column Details:</strong></p>";
                        echo "<ul>";
                        echo "<li><strong>Field:</strong> {$columnInfo['Field']}</li>";
                        echo "<li><strong>Type:</strong> {$columnInfo['Type']}</li>";
                        echo "<li><strong>Null:</strong> {$columnInfo['Null']}</li>";
                        echo "<li><strong>Default:</strong> " . ($columnInfo['Default'] ?? 'NULL') . "</li>";
                        echo "</ul>";
                        echo "</div>";
                    }
                    
                    echo "<div class='info status-box'>";
                    echo "<h3>🎉 Next Steps:</h3>";
                    echo "<ol>";
                    echo "<li>The voucher assignment system is now ready to use</li>";
                    echo "<li>Test the workflow by making a payment in portal.php</li>";
                    echo "<li>After payment, voucher code will be automatically assigned</li>";
                    echo "<li>Check the database to verify voucher_code is populated</li>";
                    echo "</ol>";
                    echo "<a href='verify_workflow.php' class='btn'>🔍 Verify Workflow</a>";
                    echo "<a href='portal.php' class='btn'>🌐 Test Portal</a>";
                    echo "</div>";
                    
                } else {
                    echo "<div class='error status-box'>";
                    echo "<h3>❌ Migration Failed</h3>";
                    echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
                    echo "<p><strong>SQL:</strong></p>";
                    echo "<pre>$alterSql</pre>";
                    echo "</div>";
                }
            }
        } else {
            // Show current table structure
            echo "<h2>📊 Current Table Structure</h2>";
            
            $columnsSql = "SHOW COLUMNS FROM mpesa_transactions";
            $columnsResult = $conn->query($columnsSql);
            
            if ($columnsResult) {
                echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; margin: 15px 0;'>";
                echo "<tr style='background: #f8f9fa;'>";
                echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
                echo "</tr>";
                
                $hasVoucherCode = false;
                while ($column = $columnsResult->fetch_assoc()) {
                    if ($column['Field'] === 'voucher_code') {
                        $hasVoucherCode = true;
                        echo "<tr style='background: #d1fae5;'>";
                    } else {
                        echo "<tr>";
                    }
                    
                    echo "<td><strong>{$column['Field']}</strong></td>";
                    echo "<td>{$column['Type']}</td>";
                    echo "<td>{$column['Null']}</td>";
                    echo "<td>{$column['Key']}</td>";
                    echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
                    echo "<td>{$column['Extra']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                
                if ($hasVoucherCode) {
                    echo "<div class='success status-box'>";
                    echo "<h3>✅ Column Already Exists</h3>";
                    echo "<p>The <code>voucher_code</code> column is already present in the table (highlighted in green above).</p>";
                    echo "<p>No migration needed!</p>";
                    echo "<a href='verify_workflow.php' class='btn'>🔍 Verify Workflow</a>";
                    echo "</div>";
                } else {
                    echo "<div class='warning status-box'>";
                    echo "<h3>⚠️ Column Missing</h3>";
                    echo "<p>The <code>voucher_code</code> column is <strong>NOT</strong> present in the table.</p>";
                    echo "<p>This column is required for the voucher assignment system to work.</p>";
                    echo "<p><strong>Click the button below to add it:</strong></p>";
                    echo "<form method='POST'>";
                    echo "<button type='submit' name='add_column' class='btn'>🔧 Add Voucher Code Column</button>";
                    echo "</form>";
                    echo "</div>";
                    
                    echo "<div class='info status-box'>";
                    echo "<h3>📝 What Will Be Added:</h3>";
                    echo "<p>The following columns will be added to the <code>mpesa_transactions</code> table:</p>";
                    echo "<ul>";
                    echo "<li><code>voucher_code VARCHAR(50) DEFAULT NULL</code> - Stores the assigned voucher code</li>";
                    echo "<li><code>notes TEXT DEFAULT NULL</code> - Stores any error messages or notes</li>";
                    echo "</ul>";
                    echo "<p><strong>SQL Command:</strong></p>";
                    echo "<pre>ALTER TABLE mpesa_transactions 
ADD COLUMN voucher_code VARCHAR(50) DEFAULT NULL AFTER result_description,
ADD COLUMN notes TEXT DEFAULT NULL AFTER voucher_code;</pre>";
                    echo "</div>";
                }
            } else {
                echo "<div class='error status-box'>";
                echo "<p>❌ Failed to retrieve table structure: " . $conn->error . "</p>";
                echo "</div>";
            }
        }
        ?>
        
        <h2>🔗 Quick Links</h2>
        <div class="info status-box">
            <a href="verify_workflow.php" class="btn">🔍 Verify Workflow</a>
            <a href="portal.php" class="btn">🌐 Portal</a>
            <a href="auto_process_vouchers.php" class="btn">⚙️ Process Vouchers</a>
        </div>
    </div>
</body>
</html>
