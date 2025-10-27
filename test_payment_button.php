<?php
/**
 * Test Payment Button Click - Simulate the exact process that happens in portal.php
 */

require_once 'portal_connection.php';

echo "<h1>🧪 Test Payment Button Click</h1>";

// Get a completed transaction to test with
$completedTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");

if ($completedTxn && $completedTxn->num_rows > 0) {
    $transaction = $completedTxn->fetch_assoc();
    $checkoutRequestId = $transaction['checkout_request_id'];
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Testing with Transaction:</h3>";
    echo "<p><strong>Checkout Request ID:</strong> $checkoutRequestId</p>";
    echo "<p><strong>Status:</strong> " . $transaction['status'] . "</p>";
    echo "<p><strong>Package ID:</strong> " . $transaction['package_id'] . "</p>";
    echo "<p><strong>Phone:</strong> " . $transaction['phone_number'] . "</p>";
    echo "</div>";
    
    echo "<h2>Simulate Button Click</h2>";
    echo "<button onclick='testPaymentButton()' style='background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>🧪 Test \"I've Completed Payment\" Button</button>";
    
    echo "<div id='test-result' style='margin-top: 20px;'></div>";
    
    echo "<script>
    function testPaymentButton() {
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '<p>Testing payment button click...</p>';
        
        console.log('Starting payment button test...');
        console.log('Checkout Request ID: $checkoutRequestId');
        
        // This is the exact same request that portal.php makes
        fetch('check_payment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'checkout_request_id=' + encodeURIComponent('$checkoutRequestId')
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
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(result => {
            console.log('Payment status check result:', result);
            
            if (result.success) {
                // Payment successful - Display voucher details
                const voucherCode = result.voucher_code || 'N/A';
                const voucherUsername = result.voucher_username || voucherCode;
                const voucherPassword = result.voucher_password || voucherCode;
                const packageName = result.package_name || 'WiFi Package';
                const duration = result.duration || '';
                
                resultDiv.innerHTML = `
                    <div style='background: #d1fae5; border: 1px solid #10b981; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #065f46; margin-top: 0;'>✅ Payment Button Test Successful!</h3>
                        
                        <div style='background: white; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                            <h4 style='margin: 0 0 10px 0; color: #1f2937;'>Voucher Details:</h4>
                            <p><strong>Code:</strong> <code style='background: #f3f4f6; padding: 2px 6px; border-radius: 3px;'>\${voucherCode}</code></p>
                            <p><strong>Username:</strong> <code style='background: #f3f4f6; padding: 2px 6px; border-radius: 3px;'>\${voucherUsername}</code></p>
                            <p><strong>Password:</strong> <code style='background: #f3f4f6; padding: 2px 6px; border-radius: 3px;'>\${voucherPassword}</code></p>
                            <p><strong>Package:</strong> \${packageName}</p>
                            <p><strong>Duration:</strong> \${duration || 'N/A'}</p>
                            <p><strong>Phone:</strong> \${result.phone_number}</p>
                            <p><strong>Receipt:</strong> \${result.receipt || 'N/A'}</p>
                        </div>
                        
                        <p style='margin-bottom: 0; color: #065f46;'><strong>Message:</strong> \${result.message}</p>
                    </div>
                `;
            } else {
                // Payment not completed or error
                resultDiv.innerHTML = `
                    <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 20px; border-radius: 8px;'>
                        <h3 style='color: #dc2626; margin-top: 0;'>❌ Payment Button Test Failed</h3>
                        <p><strong>Error Message:</strong> \${result.message}</p>
                        <p style='margin-bottom: 0; color: #dc2626;'>This is the same error that customers see in the portal.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultDiv.innerHTML = `
                <div style='background: #fef2f2; border: 1px solid #ef4444; padding: 20px; border-radius: 8px;'>
                    <h3 style='color: #dc2626; margin-top: 0;'>❌ Request Failed</h3>
                    <p><strong>Error:</strong> \${error.message}</p>
                    <p style='margin-bottom: 0; color: #dc2626;'>Check the browser console for more details.</p>
                </div>
            `;
        });
    }
    </script>";
    
} else {
    echo "<p style='color: red;'>❌ No completed transactions found to test with.</p>";
    echo "<p>You need to have at least one completed M-Pesa transaction to test the payment button.</p>";
    
    // Show all transactions
    $allTxns = $conn->query("SELECT checkout_request_id, status, phone_number, package_id, updated_at FROM mpesa_transactions ORDER BY updated_at DESC LIMIT 5");
    if ($allTxns && $allTxns->num_rows > 0) {
        echo "<h3>Recent Transactions:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Checkout Request ID</th><th>Status</th><th>Phone</th><th>Package ID</th><th>Updated</th>";
        echo "</tr>";
        
        while ($row = $allTxns->fetch_assoc()) {
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
code { background: #f3f4f6; padding: 2px 4px; border-radius: 3px; font-family: monospace; }
button:hover { opacity: 0.9; }
</style>
