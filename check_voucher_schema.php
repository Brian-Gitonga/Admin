<?php
/**
 * Check Voucher System Database Schema
 * This script verifies that all required columns exist in the database tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Database Schema</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .success {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        .error {
            color: #721c24;
            background: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        th {
            background: #667eea;
            color: white;
        }
        
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .sql-fix {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 Database Schema Check</h1>
    
    <?php
    require_once 'connection_dp.php';
    
    if (!isset($conn) || $conn->connect_error) {
        echo '<div class="error">❌ Database connection failed</div>';
        exit;
    }
    
    echo '<div class="success">✅ Database connection successful</div>';
    
    // Check mpesa_transactions table
    echo '<div class="section">';
    echo '<h2>mpesa_transactions Table</h2>';
    
    $requiredColumns = [
        'id' => 'int',
        'phone_number' => 'varchar',
        'package_id' => 'int',
        'package_name' => 'varchar',
        'reseller_id' => 'int',
        'result_code' => 'int',
        'result_description' => 'varchar',
        'voucher_id' => 'varchar',
        'voucher_code' => 'varchar',
        'amount' => 'decimal',
        'transaction_date' => 'varchar',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
    
    $result = $conn->query("SHOW COLUMNS FROM mpesa_transactions");
    
    if ($result) {
        $existingColumns = [];
        echo '<table>';
        echo '<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Status</th></tr>';
        
        while ($row = $result->fetch_assoc()) {
            $existingColumns[$row['Field']] = $row['Type'];
            $isRequired = isset($requiredColumns[$row['Field']]);
            $status = $isRequired ? '✅ Required' : '➖ Optional';
            
            echo '<tr>';
            echo '<td><strong>' . $row['Field'] . '</strong></td>';
            echo '<td>' . $row['Type'] . '</td>';
            echo '<td>' . $row['Null'] . '</td>';
            echo '<td>' . $row['Key'] . '</td>';
            echo '<td>' . ($row['Default'] ?? 'NULL') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Check for missing columns
        $missingColumns = array_diff_key($requiredColumns, $existingColumns);
        
        if (empty($missingColumns)) {
            echo '<div class="success">✅ All required columns exist!</div>';
        } else {
            echo '<div class="error">❌ Missing columns: ' . implode(', ', array_keys($missingColumns)) . '</div>';
            echo '<div class="warning">⚠️ You need to add these columns to the mpesa_transactions table</div>';
            
            echo '<h3>SQL to Fix:</h3>';
            echo '<div class="sql-fix">';
            foreach ($missingColumns as $column => $type) {
                if ($column === 'voucher_id' || $column === 'voucher_code') {
                    echo "ALTER TABLE mpesa_transactions ADD COLUMN $column VARCHAR(20) DEFAULT NULL;<br>";
                }
            }
            echo '</div>';
        }
    } else {
        echo '<div class="error">❌ Table mpesa_transactions does not exist</div>';
    }
    
    echo '</div>';
    
    // Check vouchers table
    echo '<div class="section">';
    echo '<h2>vouchers Table</h2>';
    
    $requiredVoucherColumns = [
        'id' => 'int',
        'code' => 'varchar',
        'package_id' => 'int',
        'reseller_id' => 'int',
        'customer_phone' => 'varchar',
        'status' => 'enum',
        'used_at' => 'timestamp',
        'created_at' => 'timestamp'
    ];
    
    $result = $conn->query("SHOW COLUMNS FROM vouchers");
    
    if ($result) {
        $existingVoucherColumns = [];
        echo '<table>';
        echo '<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Status</th></tr>';
        
        while ($row = $result->fetch_assoc()) {
            $existingVoucherColumns[$row['Field']] = $row['Type'];
            $isRequired = isset($requiredVoucherColumns[$row['Field']]);
            $status = $isRequired ? '✅ Required' : '➖ Optional';
            
            echo '<tr>';
            echo '<td><strong>' . $row['Field'] . '</strong></td>';
            echo '<td>' . $row['Type'] . '</td>';
            echo '<td>' . $row['Null'] . '</td>';
            echo '<td>' . $row['Key'] . '</td>';
            echo '<td>' . ($row['Default'] ?? 'NULL') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        
        // Check for missing columns
        $missingVoucherColumns = array_diff_key($requiredVoucherColumns, $existingVoucherColumns);
        
        if (empty($missingVoucherColumns)) {
            echo '<div class="success">✅ All required columns exist!</div>';
        } else {
            echo '<div class="error">❌ Missing columns: ' . implode(', ', array_keys($missingVoucherColumns)) . '</div>';
        }
    } else {
        echo '<div class="error">❌ Table vouchers does not exist</div>';
    }
    
    echo '</div>';
    
    // Check for sample data
    echo '<div class="section">';
    echo '<h2>Data Summary</h2>';
    
    // Count transactions
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN result_code = 0 THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN result_code IS NULL THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN voucher_code IS NOT NULL THEN 1 ELSE 0 END) as with_voucher
        FROM mpesa_transactions");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo '<h3>M-Pesa Transactions</h3>';
        echo '<table>';
        echo '<tr><th>Metric</th><th>Count</th></tr>';
        echo '<tr><td>Total Transactions</td><td>' . $row['total'] . '</td></tr>';
        echo '<tr><td>Successful Payments</td><td>' . $row['successful'] . '</td></tr>';
        echo '<tr><td>Pending Payments</td><td>' . $row['pending'] . '</td></tr>';
        echo '<tr><td>With Voucher Assigned</td><td>' . $row['with_voucher'] . '</td></tr>';
        echo '</table>';
    }
    
    // Count vouchers
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
        FROM vouchers");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo '<h3>Vouchers</h3>';
        echo '<table>';
        echo '<tr><th>Metric</th><th>Count</th></tr>';
        echo '<tr><td>Total Vouchers</td><td>' . $row['total'] . '</td></tr>';
        echo '<tr><td>Active (Available)</td><td>' . $row['active'] . '</td></tr>';
        echo '<tr><td>Used</td><td>' . $row['used'] . '</td></tr>';
        echo '<tr><td>Expired</td><td>' . $row['expired'] . '</td></tr>';
        echo '</table>';
        
        if ($row['active'] == 0) {
            echo '<div class="warning">⚠️ No active vouchers available! Users won\'t be able to retrieve vouchers.</div>';
        }
    }
    
    echo '</div>';
    
    // System readiness check
    echo '<div class="section">';
    echo '<h2>System Readiness</h2>';
    
    $checks = [
        'Database Connection' => isset($conn) && !$conn->connect_error,
        'mpesa_transactions table exists' => $conn->query("SHOW TABLES LIKE 'mpesa_transactions'")->num_rows > 0,
        'vouchers table exists' => $conn->query("SHOW TABLES LIKE 'vouchers'")->num_rows > 0,
        'fetch_update_voucher.php exists' => file_exists('fetch_update_voucher.php'),
        'portal.php exists' => file_exists('portal.php')
    ];
    
    $allPassed = true;
    echo '<table>';
    echo '<tr><th>Check</th><th>Status</th></tr>';
    
    foreach ($checks as $check => $passed) {
        $status = $passed ? '<span style="color: green;">✅ Passed</span>' : '<span style="color: red;">❌ Failed</span>';
        echo '<tr><td>' . $check . '</td><td>' . $status . '</td></tr>';
        if (!$passed) $allPassed = false;
    }
    
    echo '</table>';
    
    if ($allPassed) {
        echo '<div class="success">🎉 System is ready! You can start testing the voucher retrieval feature.</div>';
        echo '<p><a href="test_voucher_system.php" style="display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 10px;">Go to Test Page</a></p>';
    } else {
        echo '<div class="error">❌ System is not ready. Please fix the issues above.</div>';
    }
    
    echo '</div>';
    
    $conn->close();
    ?>
</body>
</html>

