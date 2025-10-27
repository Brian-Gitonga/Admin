<!DOCTYPE html>
<html>
<head>
    <title>Fix Package Names in Transactions</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        button.danger {
            background: #f44336;
        }
        button.danger:hover {
            background: #da190b;
        }
        .fixed {
            color: #4CAF50;
            font-weight: bold;
        }
        .broken {
            color: #f44336;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Package Names in Transactions</h1>
        
        <div class="info">
            <strong>📋 What this script does:</strong><br>
            This script finds all transactions where <code>package_name</code> is a number (package ID) 
            and updates it with the actual package name from the <code>packages</code> table.
        </div>
        
        <?php
        require_once 'portal_connection.php';
        
        // Check if fix button was clicked
        if (isset($_POST['fix_now'])) {
            echo "<h2>🔄 Fixing Package Names...</h2>";
            
            // Find all transactions with numeric package names
            $findQuery = "SELECT t.id, t.package_id, t.package_name, p.name as actual_name
                         FROM mpesa_transactions t
                         LEFT JOIN packages p ON t.package_id = p.id
                         WHERE t.package_name REGEXP '^[0-9]+$'";
            
            $result = $conn->query($findQuery);
            
            if ($result && $result->num_rows > 0) {
                $fixedCount = 0;
                $failedCount = 0;
                
                echo "<table>";
                echo "<tr>";
                echo "<th>Transaction ID</th>";
                echo "<th>Package ID</th>";
                echo "<th>Old Name (Wrong)</th>";
                echo "<th>New Name (Fixed)</th>";
                echo "<th>Status</th>";
                echo "</tr>";
                
                while ($row = $result->fetch_assoc()) {
                    $transactionId = $row['id'];
                    $packageId = $row['package_id'];
                    $oldName = $row['package_name'];
                    $newName = $row['actual_name'];
                    
                    if ($newName) {
                        // Update the transaction
                        $updateStmt = $conn->prepare("UPDATE mpesa_transactions SET package_name = ? WHERE id = ?");
                        $updateStmt->bind_param("si", $newName, $transactionId);
                        
                        if ($updateStmt->execute()) {
                            $fixedCount++;
                            $status = "<span class='fixed'>✅ FIXED</span>";
                        } else {
                            $failedCount++;
                            $status = "<span class='broken'>❌ FAILED</span>";
                        }
                        
                        $updateStmt->close();
                    } else {
                        $failedCount++;
                        $newName = "Package not found";
                        $status = "<span class='broken'>❌ PACKAGE NOT FOUND</span>";
                    }
                    
                    echo "<tr>";
                    echo "<td>$transactionId</td>";
                    echo "<td>$packageId</td>";
                    echo "<td><span class='broken'>$oldName</span></td>";
                    echo "<td><span class='fixed'>$newName</span></td>";
                    echo "<td>$status</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
                
                echo "<div class='success'>";
                echo "<strong>✅ Fix Complete!</strong><br>";
                echo "<strong>Fixed:</strong> $fixedCount transaction(s)<br>";
                if ($failedCount > 0) {
                    echo "<strong>Failed:</strong> $failedCount transaction(s)<br>";
                }
                echo "</div>";
            } else {
                echo "<div class='info'>";
                echo "<strong>ℹ️ No transactions need fixing.</strong><br>";
                echo "All transactions already have proper package names.";
                echo "</div>";
            }
        }
        
        // Show preview of transactions that need fixing
        echo "<h2>📊 Transactions with Incorrect Package Names</h2>";
        
        $previewQuery = "SELECT t.id, t.package_id, t.package_name, p.name as actual_name, t.phone_number, t.amount, t.status
                        FROM mpesa_transactions t
                        LEFT JOIN packages p ON t.package_id = p.id
                        WHERE t.package_name REGEXP '^[0-9]+$'
                        ORDER BY t.id DESC
                        LIMIT 20";
        
        $previewResult = $conn->query($previewQuery);
        
        if ($previewResult && $previewResult->num_rows > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Found " . $previewResult->num_rows . " transaction(s) with numeric package names!</strong><br>";
            echo "These transactions have package IDs stored instead of package names.";
            echo "</div>";
            
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Package ID</th>";
            echo "<th>Current Name (Wrong)</th>";
            echo "<th>Should Be</th>";
            echo "<th>Phone</th>";
            echo "<th>Amount</th>";
            echo "<th>Status</th>";
            echo "</tr>";
            
            while ($row = $previewResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['package_id']}</td>";
                echo "<td><span class='broken'>{$row['package_name']}</span></td>";
                echo "<td><span class='fixed'>" . ($row['actual_name'] ?: 'Package not found') . "</span></td>";
                echo "<td>{$row['phone_number']}</td>";
                echo "<td>KES {$row['amount']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            echo "<form method='POST'>";
            echo "<button type='submit' name='fix_now' class='danger'>🔧 Fix All Package Names Now</button>";
            echo "</form>";
            
            echo "<div class='warning'>";
            echo "<strong>⚠️ Warning:</strong> This will update all transactions with numeric package names. Make sure you have a backup before proceeding.";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "<strong>✅ All Good!</strong><br>";
            echo "All transactions have proper package names. No fixes needed.";
            echo "</div>";
        }
        
        // Show recent transactions for verification
        echo "<h2>📋 Recent Transactions (Verification)</h2>";
        
        $recentQuery = "SELECT t.id, t.package_id, t.package_name, t.phone_number, t.amount, t.status, t.created_at
                       FROM mpesa_transactions t
                       ORDER BY t.id DESC
                       LIMIT 10";
        
        $recentResult = $conn->query($recentQuery);
        
        if ($recentResult && $recentResult->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Package ID</th>";
            echo "<th>Package Name</th>";
            echo "<th>Phone</th>";
            echo "<th>Amount</th>";
            echo "<th>Status</th>";
            echo "<th>Created</th>";
            echo "</tr>";
            
            while ($row = $recentResult->fetch_assoc()) {
                $packageNameClass = is_numeric($row['package_name']) ? 'broken' : 'fixed';
                
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['package_id']}</td>";
                echo "<td><span class='$packageNameClass'>{$row['package_name']}</span></td>";
                echo "<td>{$row['phone_number']}</td>";
                echo "<td>KES {$row['amount']}</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
        
        $conn->close();
        ?>
        
        <div class="info">
            <strong>📝 What was fixed:</strong><br>
            <ol>
                <li><strong>process_payment.php</strong> - Now automatically fetches package name from database if POST data contains a number</li>
                <li><strong>Existing transactions</strong> - This script updates old transactions that have numeric package names</li>
            </ol>
        </div>
    </div>
</body>
</html>

