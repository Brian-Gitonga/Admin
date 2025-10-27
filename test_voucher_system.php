<?php
/**
 * Test Voucher Retrieval System
 * This script helps you test the voucher retrieval functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Voucher System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .status {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 6px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .test-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #5568d3;
        }
        
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        #result {
            margin-top: 20px;
        }
        
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Voucher System Test</h1>
        <p class="subtitle">Test the voucher retrieval functionality</p>
        
        <!-- Database Connection Test -->
        <div class="section">
            <h2>1. Database Connection</h2>
            <?php
            require_once 'connection_dp.php';
            
            if (isset($conn) && !$conn->connect_error) {
                echo '<div class="status success">✅ Database connection successful</div>';
            } else {
                echo '<div class="status error">❌ Database connection failed</div>';
                exit;
            }
            ?>
        </div>
        
        <!-- Tables Check -->
        <div class="section">
            <h2>2. Required Tables</h2>
            <?php
            $tables = ['mpesa_transactions', 'vouchers'];
            $allTablesExist = true;
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result->num_rows > 0) {
                    echo "<div class='status success'>✅ Table '$table' exists</div>";
                } else {
                    echo "<div class='status error'>❌ Table '$table' does not exist</div>";
                    $allTablesExist = false;
                }
            }
            ?>
        </div>
        
        <!-- Sample Data -->
        <div class="section">
            <h2>3. Sample Transactions</h2>
            <?php
            $query = "SELECT id, phone_number, package_name, amount, result_code, voucher_code, 
                      DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as created 
                      FROM mpesa_transactions 
                      ORDER BY created_at DESC 
                      LIMIT 5";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                echo "<div class='status info'>Found " . $result->num_rows . " recent transactions</div>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Phone</th><th>Package</th><th>Amount</th><th>Result Code</th><th>Voucher</th><th>Created</th></tr>";
                
                while ($row = $result->fetch_assoc()) {
                    $resultCodeColor = '';
                    if ($row['result_code'] === null) {
                        $resultCodeColor = 'style="color: orange;"';
                    } elseif ($row['result_code'] == 0) {
                        $resultCodeColor = 'style="color: green;"';
                    } else {
                        $resultCodeColor = 'style="color: red;"';
                    }
                    
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['phone_number'] . "</td>";
                    echo "<td>" . $row['package_name'] . "</td>";
                    echo "<td>KSh " . $row['amount'] . "</td>";
                    echo "<td $resultCodeColor>" . ($row['result_code'] === null ? 'Pending' : $row['result_code']) . "</td>";
                    echo "<td>" . ($row['voucher_code'] ? $row['voucher_code'] : '<em>Not assigned</em>') . "</td>";
                    echo "<td>" . $row['created'] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<div class='status warning'>⚠️ No transactions found in database</div>";
            }
            ?>
        </div>
        
        <!-- Available Vouchers -->
        <div class="section">
            <h2>4. Available Vouchers</h2>
            <?php
            $query = "SELECT COUNT(*) as total, status FROM vouchers GROUP BY status";
            $result = $conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Status</th><th>Count</th></tr>";
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . ucfirst($row['status']) . "</td>";
                    echo "<td>" . $row['total'] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<div class='status warning'>⚠️ No vouchers found in database</div>";
            }
            ?>
        </div>
        
        <!-- Test Form -->
        <div class="section">
            <h2>5. Test Voucher Retrieval</h2>
            <div class="status info">Enter a phone number to test the voucher retrieval system</div>
            
            <div class="test-form">
                <form id="testForm">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" placeholder="e.g., 0712345678 or 254712345678" required>
                    </div>
                    <button type="submit" id="submitBtn">Test Voucher Retrieval</button>
                </form>
                
                <div id="result"></div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const phone = document.getElementById('phone').value;
            const submitBtn = document.getElementById('submitBtn');
            const resultDiv = document.getElementById('result');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.textContent = 'Testing...';
            resultDiv.innerHTML = '<div class="status info">⏳ Processing request...</div>';
            
            // Make AJAX request
            fetch('fetch_update_voucher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'phone_number=' + encodeURIComponent(phone)
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Test Voucher Retrieval';
                
                // Display result
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="status success">
                            <strong>✅ Success!</strong><br>
                            <strong>Voucher Code:</strong> ${data.voucher_code}<br>
                            <strong>Package:</strong> ${data.package_name}<br>
                            <strong>Amount:</strong> KSh ${data.amount}<br>
                            <strong>Message:</strong> ${data.message}
                        </div>
                        <div class="code-block">${JSON.stringify(data, null, 2)}</div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="status error">
                            <strong>❌ Error</strong><br>
                            ${data.message}
                        </div>
                        <div class="code-block">${JSON.stringify(data, null, 2)}</div>
                    `;
                }
            })
            .catch(error => {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Test Voucher Retrieval';
                
                resultDiv.innerHTML = `
                    <div class="status error">
                        <strong>❌ Network Error</strong><br>
                        ${error.message}
                    </div>
                `;
            });
        });
    </script>
</body>
</html>

