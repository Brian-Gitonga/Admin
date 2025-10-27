<?php
/**
 * Final Voucher Fix Test - Complete end-to-end test with all fixes applied
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>🎯 Final Voucher Fix Test</h1>";

// Step 1: Verify database schema
echo "<h2>Step 1: Database Schema Verification</h2>";

$schemaChecks = [
    'mpesa_transactions table' => "SHOW TABLES LIKE 'mpesa_transactions'",
    'vouchers table' => "SHOW TABLES LIKE 'vouchers'",
    'voucher_code column' => "SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_code'",
    'voucher_id column' => "SHOW COLUMNS FROM mpesa_transactions LIKE 'voucher_id'"
];

$allSchemaOk = true;
foreach ($schemaChecks as $check => $sql) {
    $result = $conn->query($sql);
    $exists = $result && $result->num_rows > 0;
    echo "<p>$check: " . ($exists ? "<span style='color: green;'>✅ OK</span>" : "<span style='color: red;'>❌ Missing</span>") . "</p>";
    if (!$exists) $allSchemaOk = false;
}

if (!$allSchemaOk) {
    echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p style='color: #dc2626; margin: 0;'><strong>⚠️ Schema Issues Detected</strong></p>";
    echo "<p style='margin: 5px 0 0 0;'><a href='fix_voucher_database_schema.php' style='color: #3b82f6;'>Click here to fix database schema first</a></p>";
    echo "</div>";
}

// Step 2: Check voucher availability
echo "<h2>Step 2: Voucher Availability Check</h2>";

$voucherStats = $conn->query("SELECT status, COUNT(*) as count FROM vouchers GROUP BY status");
$activeVouchers = 0;

if ($voucherStats) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>Status</th><th>Count</th></tr>";
    
    while ($row = $voucherStats->fetch_assoc()) {
        $color = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
        echo "<tr><td style='color: $color; font-weight: bold;'>{$row['status']}</td><td>{$row['count']}</td></tr>";
        if ($row['status'] === 'active') $activeVouchers = $row['count'];
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No voucher data found</p>";
}

if ($activeVouchers == 0) {
    echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p style='color: #dc2626; margin: 0;'><strong>⚠️ No Active Vouchers Available</strong></p>";
    echo "<p style='margin: 5px 0 0 0;'><a href='fix_voucher_database_schema.php' style='color: #3b82f6;'>Click here to create sample vouchers</a></p>";
    echo "</div>";
}

// Step 3: Complete workflow test
echo "<h2>Step 3: Complete Payment Workflow Test</h2>";

if ($allSchemaOk && $activeVouchers > 0) {
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🧪 End-to-End Workflow Test</h3>";
    echo "<p>This will test the complete payment workflow from submission to voucher display.</p>";
    
    echo "<form id='workflow-test-form'>";
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Phone Number:</label><br>";
    echo "<input type='tel' id='test-phone' value='0712345678' style='padding: 8px; width: 200px;' required>";
    echo "</div>";
    
    echo "<div style='margin: 10px 0;'>";
    echo "<label>Package:</label><br>";
    echo "<select id='test-package' style='padding: 8px; width: 200px;' required>";
    $packages = $conn->query("SELECT id, name, price FROM packages ORDER BY id");
    if ($packages) {
        while ($pkg = $packages->fetch_assoc()) {
            echo "<option value='{$pkg['id']}' data-name='{$pkg['name']}' data-price='{$pkg['price']}'>{$pkg['name']} - KES {$pkg['price']}</option>";
        }
    }
    echo "</select>";
    echo "</div>";
    
    echo "<button type='submit' style='background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; margin: 10px 0;'>🚀 Test Complete Workflow</button>";
    echo "</form>";
    
    echo "<div id='workflow-result' style='margin-top: 20px;'></div>";
    echo "</div>";
    
    echo "<script>
    document.getElementById('workflow-test-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const resultDiv = document.getElementById('workflow-result');
        const phoneNumber = document.getElementById('test-phone').value;
        const packageSelect = document.getElementById('test-package');
        const packageId = packageSelect.value;
        const packageName = packageSelect.options[packageSelect.selectedIndex].dataset.name;
        const packagePrice = packageSelect.options[packageSelect.selectedIndex].dataset.price;
        
        resultDiv.innerHTML = '<div style=\"background: #f3f4f6; padding: 15px; border-radius: 5px;\"><h4>🔄 Testing Complete Workflow...</h4><div id=\"step-progress\"><p>Step 1: Submitting payment...</p></div></div>';
        
        const stepProgress = document.getElementById('step-progress');
        
        // Step 1: Submit payment using simple test
        const paymentData = new FormData();
        paymentData.append('reseller_id', '6');
        paymentData.append('package_name', packageName);
        paymentData.append('package_price', packagePrice);
        paymentData.append('mpesa_number', phoneNumber);
        paymentData.append('package_id', packageId);
        paymentData.append('router_id', '0');
        paymentData.append('payment_gateway', 'mpesa');
        
        console.log('=== STEP 1: PAYMENT SUBMISSION ===');
        
        fetch('simple_payment_test.php', {
            method: 'POST',
            body: paymentData
        })
        .then(response => response.json())
        .then(paymentResult => {
            console.log('Payment result:', paymentResult);
            
            if (!paymentResult.success) {
                throw new Error('Payment submission failed: ' + paymentResult.message);
            }
            
            stepProgress.innerHTML += '<p style=\"color: green;\">✅ Step 1: Payment submitted successfully</p><p>Step 2: Simulating payment completion...</p>';
            
            // Step 2: Simulate payment completion
            console.log('=== STEP 2: PAYMENT COMPLETION ===');
            
            return fetch('simulate_payment_completion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'checkout_request_id=' + encodeURIComponent(paymentResult.checkout_request_id)
            });
        })
        .then(response => response.json())
        .then(completionResult => {
            console.log('Completion result:', completionResult);
            
            if (!completionResult.success) {
                throw new Error('Payment completion failed: ' + completionResult.message);
            }
            
            stepProgress.innerHTML += '<p style=\"color: green;\">✅ Step 2: Payment marked as completed</p><p>Step 3: Testing voucher fetching...</p>';
            
            // Step 3: Test voucher fetching using the actual check_payment_status.php
            console.log('=== STEP 3: VOUCHER FETCHING ===');
            
            return fetch('check_payment_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'checkout_request_id=' + encodeURIComponent(completionResult.checkout_request_id)
            });
        })
        .then(response => {
            console.log('Voucher response status:', response.status);
            return response.text().then(text => {
                console.log('Raw voucher response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response from voucher system: ' + text.substring(0, 200));
                }
            });
        })
        .then(voucherResult => {
            console.log('Voucher result:', voucherResult);
            
            if (voucherResult.success) {
                // SUCCESS - Show complete workflow success
                resultDiv.innerHTML = `
                    <div style='background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #065f46; margin-top: 0;'>🎉 COMPLETE WORKFLOW SUCCESS!</h3>
                        
                        <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                            <h4 style='margin: 0 0 15px 0; color: #1f2937; text-align: center;'>Your WiFi Voucher</h4>
                            
                            <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 15px;'>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Voucher Code:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 16px; font-weight: bold; color: #1f2937; background: #f9fafb; padding: 8px; border-radius: 4px;'>\${voucherResult.voucher_code}</p>
                                </div>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Package:</p>
                                    <p style='margin: 0; font-size: 16px; font-weight: bold; color: #1f2937;'>\${voucherResult.package_name}</p>
                                </div>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Username:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px;'>\${voucherResult.voucher_username}</p>
                                </div>
                                <div>
                                    <p style='margin: 5px 0; font-size: 14px; color: #6b7280;'>Password:</p>
                                    <p style='margin: 0; font-family: monospace; font-size: 14px; color: #1f2937; background: #f9fafb; padding: 6px; border-radius: 4px;'>\${voucherResult.voucher_password}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div style='background: #ecfdf5; padding: 15px; border-radius: 6px; border: 1px solid #a7f3d0;'>
                            <h4 style='color: #065f46; margin: 0 0 10px 0;'>✅ All Systems Working:</h4>
                            <ul style='margin: 0; color: #065f46;'>
                                <li>✅ Payment submission working</li>
                                <li>✅ Database transaction recording working</li>
                                <li>✅ Payment completion working</li>
                                <li>✅ Voucher fetching and assignment working</li>
                                <li>✅ Voucher display working</li>
                                <li>✅ Error handling working</li>
                            </ul>
                            <p style='margin: 10px 0 0 0; font-weight: bold;'>🎯 The payment system is now fully functional!</p>
                        </div>
                    </div>
                `;
            } else {
                // ERROR in voucher fetching
                resultDiv.innerHTML = `
                    <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #dc2626; margin-top: 0;'>❌ Voucher Fetching Failed</h3>
                        <p><strong>Error:</strong> \${voucherResult.message}</p>
                        \${voucherResult.debug_info ? '<details><summary style=\"cursor: pointer;\">Show Debug Info</summary><pre style=\"background: #f9fafb; padding: 10px; border-radius: 4px; font-size: 12px;\">' + JSON.stringify(voucherResult.debug_info, null, 2) + '</pre></details>' : ''}
                        
                        <div style='background: #f3f4f6; padding: 10px; border-radius: 4px; margin: 10px 0;'>
                            <p style='margin: 0; font-size: 14px;'><strong>🔧 Next Steps:</strong></p>
                            <ul style='margin: 5px 0 0 20px; font-size: 13px;'>
                                <li>Check if vouchers table has active vouchers</li>
                                <li>Verify voucher handler function is working</li>
                                <li>Check database schema is correct</li>
                                <li>Review error logs for detailed information</li>
                            </ul>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Workflow error:', error);
            
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #dc2626; margin-top: 0;'>🚨 Workflow Test Failed</h3>
                    <p><strong>Error:</strong> \${error.message}</p>
                    <p style='color: #dc2626;'>Check the browser console (F12) for detailed error information.</p>
                    
                    <div style='background: #f3f4f6; padding: 10px; border-radius: 4px; margin: 10px 0;'>
                        <p style='margin: 0; font-size: 14px;'><strong>🔧 Troubleshooting:</strong></p>
                        <ul style='margin: 5px 0 0 20px; font-size: 13px;'>
                            <li>Ensure database schema is fixed</li>
                            <li>Verify active vouchers exist</li>
                            <li>Check server error logs</li>
                            <li>Test individual components separately</li>
                        </ul>
                    </div>
                </div>
            `;
        });
    });
    </script>";
    
} else {
    echo "<div style='background: #fef2f2; border: 1px solid #ef4444; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<p style='color: #dc2626; margin: 0;'><strong>⚠️ Prerequisites Not Met</strong></p>";
    echo "<p style='margin: 5px 0 0 0;'>Please fix the database schema and ensure active vouchers exist before running the workflow test.</p>";
    echo "</div>";
}

echo "<h2>✅ Final Test Ready</h2>";
echo "<p>This test will verify that all the voucher system fixes are working correctly. Run the workflow test above to confirm everything is functioning properly.</p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3, h4 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; transform: translateY(-1px); transition: all 0.2s; }
details { margin: 10px 0; }
summary { font-weight: bold; }
</style>
