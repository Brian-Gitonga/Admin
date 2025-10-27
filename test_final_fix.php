<?php
/**
 * Final Test - Verify all fixes are working
 */

require_once 'portal_connection.php';

echo "<h1>🧪 Final Payment Completion Test</h1>";

// Step 1: Check system status
echo "<h2>Step 1: System Status Check</h2>";

// Check database connection
if ($conn) {
    echo "<p style='color: green;'>✅ Database connected</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}

// Check vouchers table
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
    
    $activeCount = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'")->fetch_assoc()['count'];
    echo "<p>Active vouchers: <strong>$activeCount</strong></p>";
    
    if ($activeCount == 0) {
        echo "<p style='color: red;'>❌ No active vouchers available</p>";
        echo "<p><a href='fix_voucher_system.php' style='color: blue;'>Click here to create sample vouchers</a></p>";
    }
} else {
    echo "<p style='color: red;'>❌ Vouchers table missing</p>";
    echo "<p><a href='fix_voucher_system.php' style='color: blue;'>Click here to create vouchers table</a></p>";
}

// Step 2: Find completed transaction
echo "<h2>Step 2: Test with Completed Transaction</h2>";

$completedTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");
if ($completedTxn && $completedTxn->num_rows > 0) {
    $txn = $completedTxn->fetch_assoc();
    $checkoutId = $txn['checkout_request_id'];
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Testing with Transaction:</h4>";
    echo "<p><strong>Checkout ID:</strong> " . substr($checkoutId, 0, 30) . "...</p>";
    echo "<p><strong>Status:</strong> " . $txn['status'] . "</p>";
    echo "<p><strong>Package ID:</strong> " . $txn['package_id'] . "</p>";
    echo "<p><strong>Phone:</strong> " . $txn['phone_number'] . "</p>";
    echo "</div>";
    
    // Step 3: Test the payment completion button
    echo "<h2>Step 3: Test Payment Completion Button</h2>";
    echo "<button onclick='testPaymentCompletion()' style='background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold;'>🧪 Test \"I've Completed Payment\" Button</button>";
    
    echo "<div id='test-result' style='margin-top: 20px;'></div>";
    
    echo "<script>
    function testPaymentCompletion() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><p>🔄 Testing payment completion process...</p></div>';
        
        console.log('=== PAYMENT COMPLETION TEST STARTED ===');
        console.log('Checkout Request ID: $checkoutId');
        
        // This is the exact same request that portal.php makes
        fetch('check_payment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'checkout_request_id=' + encodeURIComponent('$checkoutId')
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: \${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Raw response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response text that failed to parse:', text);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
                }
            });
        })
        .then(result => {
            console.log('Payment completion result:', result);
            
            if (result.success) {
                // SUCCESS - Display voucher details
                const voucherCode = result.voucher_code || 'N/A';
                const voucherUsername = result.voucher_username || voucherCode;
                const voucherPassword = result.voucher_password || voucherCode;
                const packageName = result.package_name || 'WiFi Package';
                const duration = result.duration || '';
                
                resultDiv.innerHTML = `
                    <div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #065f46; margin-top: 0; display: flex; align-items: center;'>
                            <span style='font-size: 24px; margin-right: 10px;'>🎉</span>
                            Payment Completion Test SUCCESSFUL!
                        </h3>
                        
                        <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #d1fae5;'>
                            <h4 style='margin: 0 0 15px 0; color: #1f2937; text-align: center;'>Your WiFi Voucher</h4>
                            
                            <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;'>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Voucher Code:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 16px; font-weight: bold; color: #1f2937; background: #f9fafb; padding: 8px; border-radius: 4px; border: 1px solid #e5e7eb;'>\${voucherCode}</p>
                                </div>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Package:</p>
                                    <p style='margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;'>\${packageName}</p>
                                </div>
                            </div>
                            
                            <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;'>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Username:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px; border: 1px solid #e5e7eb;'>\${voucherUsername}</p>
                                </div>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Password:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px; border: 1px solid #e5e7eb;'>\${voucherPassword}</p>
                                </div>
                            </div>
                            
                            <div style='text-align: center; margin-top: 15px;'>
                                <button onclick='copyToClipboard(\"\${voucherCode}\")' style='background: #3b82f6; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;'>
                                    📋 Copy Voucher Code
                                </button>
                            </div>
                        </div>
                        
                        <div style='background: #ecfdf5; padding: 10px; border-radius: 4px; border: 1px solid #a7f3d0;'>
                            <p style='margin: 0; font-size: 14px; color: #065f46;'>
                                <strong>✅ Test Result:</strong> The payment completion workflow is working perfectly! 
                                Customers will see this voucher display when they click \"I've Completed Payment\".
                            </p>
                        </div>
                    </div>
                `;
            } else {
                // ERROR - Show detailed error information
                const errorMessage = result.message || 'Unknown error occurred';
                
                resultDiv.innerHTML = `
                    <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #dc2626; margin-top: 0; display: flex; align-items: center;'>
                            <span style='font-size: 24px; margin-right: 10px;'>❌</span>
                            Payment Completion Test FAILED
                        </h3>
                        
                        <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #fecaca;'>
                            <p style='margin: 0 0 10px 0; font-weight: bold; color: #dc2626;'>Error Message:</p>
                            <p style='margin: 0; font-size: 14px; color: #1f2937; background: #fef2f2; padding: 10px; border-radius: 4px; border: 1px solid #fecaca;'>\${errorMessage}</p>
                        </div>
                        
                        \${result.debug_info ? `
                        <div style='background: #f9fafb; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #e5e7eb;'>
                            <p style='margin: 0 0 10px 0; font-weight: bold; color: #374151;'>Debug Information:</p>
                            <pre style='margin: 0; font-size: 12px; color: #6b7280; white-space: pre-wrap;'>\${JSON.stringify(result.debug_info, null, 2)}</pre>
                        </div>
                        ` : ''}
                        
                        <div style='background: #fef2f2; padding: 10px; border-radius: 4px; border: 1px solid #fecaca;'>
                            <p style='margin: 0; font-size: 14px; color: #dc2626;'>
                                <strong>🔧 Next Steps:</strong> Check the error message above and run the voucher system fix if needed.
                            </p>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Payment completion test error:', error);
            
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #dc2626; margin-top: 0; display: flex; align-items: center;'>
                        <span style='font-size: 24px; margin-right: 10px;'>🚨</span>
                        Connection/Server Error
                    </h3>
                    
                    <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #fecaca;'>
                        <p style='margin: 0 0 10px 0; font-weight: bold; color: #dc2626;'>Technical Error:</p>
                        <p style='margin: 0; font-size: 14px; color: #1f2937; background: #fef2f2; padding: 10px; border-radius: 4px; border: 1px solid #fecaca;'>\${error.message}</p>
                    </div>
                    
                    <div style='background: #fef2f2; padding: 10px; border-radius: 4px; border: 1px solid #fecaca;'>
                        <p style='margin: 0; font-size: 14px; color: #dc2626;'>
                            <strong>🔧 This indicates:</strong> Server error, PHP fatal error, or network issue. Check browser console for details.
                        </p>
                    </div>
                </div>
            `;
        });
    }
    
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Voucher code copied to clipboard: ' + text);
        }, function(err) {
            console.error('Could not copy text: ', err);
            alert('Failed to copy. Please manually copy: ' + text);
        });
    }
    </script>";
    
} else {
    echo "<p style='color: red;'>❌ No completed transactions found to test with.</p>";
    echo "<p>You need to complete at least one M-Pesa payment to test the voucher display.</p>";
    
    // Show recent transactions
    $recentTxns = $conn->query("SELECT checkout_request_id, status, phone_number, package_id, updated_at FROM mpesa_transactions ORDER BY updated_at DESC LIMIT 3");
    if ($recentTxns && $recentTxns->num_rows > 0) {
        echo "<h3>Recent Transactions:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Checkout Request ID</th><th>Status</th><th>Phone</th><th>Package ID</th><th>Updated</th>";
        echo "</tr>";
        
        while ($row = $recentTxns->fetch_assoc()) {
            $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
            echo "<tr>";
            echo "<td style='font-size: 11px;'>" . substr($row['checkout_request_id'], 0, 25) . "...</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "<td>" . $row['phone_number'] . "</td>";
            echo "<td>" . $row['package_id'] . "</td>";
            echo "<td>" . $row['updated_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; transform: translateY(-1px); transition: all 0.2s; }
</style>
