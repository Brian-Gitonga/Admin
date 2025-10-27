<!DOCTYPE html>
<html>
<head>
    <title>Test Package Name Issue</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #666;
            margin-top: 30px;
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
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Package Name Issue Diagnostic</h1>
        
        <?php
        require_once 'portal_connection.php';
        
        echo "<div class='info-box'>";
        echo "<strong>Purpose:</strong> This script checks if package_name is being stored correctly in mpesa_transactions table.";
        echo "</div>";
        
        // Test 1: Check recent transactions
        echo "<h2>📊 Recent M-Pesa Transactions</h2>";
        
        $query = "SELECT 
                    id,
                    checkout_request_id,
                    phone_number,
                    package_id,
                    package_name,
                    amount,
                    voucher_code,
                    status,
                    created_at
                  FROM mpesa_transactions 
                  ORDER BY id DESC 
                  LIMIT 10";
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Phone</th>";
            echo "<th>Package ID</th>";
            echo "<th>Package Name</th>";
            echo "<th>Amount</th>";
            echo "<th>Voucher</th>";
            echo "<th>Status</th>";
            echo "<th>Created</th>";
            echo "</tr>";
            
            $issueCount = 0;
            
            while ($row = $result->fetch_assoc()) {
                $packageNameDisplay = $row['package_name'];
                $isIssue = false;
                
                // Check if package_name looks like an ID (only numbers)
                if (is_numeric($packageNameDisplay)) {
                    $packageNameDisplay = "<span class='error'>⚠️ " . $packageNameDisplay . " (LOOKS LIKE ID!)</span>";
                    $isIssue = true;
                    $issueCount++;
                } elseif (empty($packageNameDisplay)) {
                    $packageNameDisplay = "<span class='warning'>EMPTY</span>";
                    $isIssue = true;
                    $issueCount++;
                } else {
                    $packageNameDisplay = "<span class='success'>" . htmlspecialchars($packageNameDisplay) . "</span>";
                }
                
                echo "<tr" . ($isIssue ? " style='background-color: #ffebee;'" : "") . ">";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['phone_number']}</td>";
                echo "<td>{$row['package_id']}</td>";
                echo "<td>$packageNameDisplay</td>";
                echo "<td>KES {$row['amount']}</td>";
                echo "<td>" . ($row['voucher_code'] ?: '<span class="warning">No voucher</span>') . "</td>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            if ($issueCount > 0) {
                echo "<div class='info-box' style='background: #ffebee; border-left-color: #f44336;'>";
                echo "<strong>⚠️ ISSUE FOUND:</strong> $issueCount transaction(s) have package_name that looks like an ID or is empty!";
                echo "</div>";
            } else {
                echo "<div class='info-box' style='background: #e8f5e9; border-left-color: #4CAF50;'>";
                echo "<strong>✅ NO ISSUES:</strong> All transactions have proper package names.";
                echo "</div>";
            }
        } else {
            echo "<p class='warning'>No transactions found in database.</p>";
        }
        
        // Test 2: Check packages table
        echo "<h2>📦 Available Packages</h2>";
        
        $packagesQuery = "SELECT id, name, price, duration FROM packages ORDER BY id ASC";
        $packagesResult = $conn->query($packagesQuery);
        
        if ($packagesResult && $packagesResult->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>Package ID</th>";
            echo "<th>Package Name</th>";
            echo "<th>Price</th>";
            echo "<th>Duration</th>";
            echo "</tr>";
            
            while ($pkg = $packagesResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$pkg['id']}</td>";
                echo "<td><strong>" . htmlspecialchars($pkg['name']) . "</strong></td>";
                echo "<td>KES {$pkg['price']}</td>";
                echo "<td>{$pkg['duration']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='warning'>No packages found in database.</p>";
        }
        
        // Test 3: Check if package_name matches package_id
        echo "<h2>🔗 Package Name vs Package ID Verification</h2>";
        
        $verifyQuery = "SELECT 
                            t.id,
                            t.package_id,
                            t.package_name as stored_name,
                            p.name as actual_name,
                            CASE 
                                WHEN t.package_name = p.name THEN 'MATCH'
                                WHEN t.package_name = t.package_id THEN 'ID_STORED'
                                ELSE 'MISMATCH'
                            END as status
                        FROM mpesa_transactions t
                        LEFT JOIN packages p ON t.package_id = p.id
                        ORDER BY t.id DESC
                        LIMIT 10";
        
        $verifyResult = $conn->query($verifyQuery);
        
        if ($verifyResult && $verifyResult->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>Transaction ID</th>";
            echo "<th>Package ID</th>";
            echo "<th>Stored Name</th>";
            echo "<th>Actual Name</th>";
            echo "<th>Status</th>";
            echo "</tr>";
            
            $mismatchCount = 0;
            
            while ($row = $verifyResult->fetch_assoc()) {
                $statusClass = '';
                $statusText = $row['status'];
                
                if ($row['status'] === 'MATCH') {
                    $statusClass = 'success';
                    $statusText = '✅ MATCH';
                } elseif ($row['status'] === 'ID_STORED') {
                    $statusClass = 'error';
                    $statusText = '❌ ID STORED INSTEAD OF NAME';
                    $mismatchCount++;
                } else {
                    $statusClass = 'warning';
                    $statusText = '⚠️ MISMATCH';
                    $mismatchCount++;
                }
                
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>{$row['package_id']}</td>";
                echo "<td>" . htmlspecialchars($row['stored_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['actual_name']) . "</td>";
                echo "<td class='$statusClass'>$statusText</td>";
                echo "</tr>";
            }
            
            echo "</table>";
            
            if ($mismatchCount > 0) {
                echo "<div class='info-box' style='background: #ffebee; border-left-color: #f44336;'>";
                echo "<strong>⚠️ PROBLEM CONFIRMED:</strong> $mismatchCount transaction(s) have package_id stored instead of package_name!";
                echo "<br><br>";
                echo "<strong>Root Cause:</strong> The code is storing package_id in the package_name column.";
                echo "<br>";
                echo "<strong>Fix Required:</strong> Update process_payment.php to fetch package name from packages table before saving transaction.";
                echo "</div>";
            } else {
                echo "<div class='info-box' style='background: #e8f5e9; border-left-color: #4CAF50;'>";
                echo "<strong>✅ ALL GOOD:</strong> All transactions have correct package names stored.";
                echo "</div>";
            }
        }
        
        // Test 4: Check SMS logs (if available)
        echo "<h2>📱 Recent SMS Deliveries (if logged)</h2>";
        
        $smsQuery = "SELECT * FROM sms_logs ORDER BY id DESC LIMIT 5";
        $smsResult = $conn->query($smsQuery);
        
        if ($smsResult && $smsResult->num_rows > 0) {
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th>";
            echo "<th>Phone</th>";
            echo "<th>Message Preview</th>";
            echo "<th>Status</th>";
            echo "<th>Sent At</th>";
            echo "</tr>";
            
            while ($sms = $smsResult->fetch_assoc()) {
                $messagePreview = substr($sms['message'], 0, 100) . '...';
                
                // Check if message contains package name or package ID
                $hasPackageId = preg_match('/Package: \d+/', $sms['message']);
                $messageClass = $hasPackageId ? 'error' : 'success';
                
                echo "<tr>";
                echo "<td>{$sms['id']}</td>";
                echo "<td>{$sms['phone_number']}</td>";
                echo "<td class='$messageClass'>" . htmlspecialchars($messagePreview) . "</td>";
                echo "<td>{$sms['status']}</td>";
                echo "<td>{$sms['created_at']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p class='warning'>No SMS logs table found or no SMS sent yet.</p>";
        }
        
        $conn->close();
        ?>
        
        <div class="info-box">
            <strong>📝 Next Steps:</strong>
            <ol>
                <li>If you see "ID STORED INSTEAD OF NAME" errors above, the issue is in <code>process_payment.php</code></li>
                <li>The code needs to fetch the package name from the <code>packages</code> table before saving the transaction</li>
                <li>Check the SMS messages to see if they contain package IDs or package names</li>
            </ol>
        </div>
    </div>
</body>
</html>

