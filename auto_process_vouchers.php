<?php
/**
 * Auto Process Vouchers - Simple Integration Point
 * 
 * This file can be called from the M-Pesa callback or run as a cron job
 * to automatically process completed payments and send vouchers via SMS
 */

require_once 'fetch_umeskia_vouchers.php';

/**
 * Process a specific transaction by checkout_request_id
 * 
 * @param string $checkout_request_id The specific transaction to process
 * @return array Processing result
 */
function processSpecificTransaction($checkout_request_id) {
    global $conn;
    
    logVoucherActivity("=== PROCESSING SPECIFIC TRANSACTION: $checkout_request_id ===");
    
    // Find the specific completed transaction
    $sql = "SELECT 
                id,
                checkout_request_id,
                phone_number,
                package_id,
                package_name,
                reseller_id,
                amount,
                mpesa_receipt,
                created_at,
                updated_at
            FROM mpesa_transactions 
            WHERE checkout_request_id = ?
            AND status = 'completed' 
            AND (voucher_code IS NULL OR voucher_code = '')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logVoucherActivity("ERROR: Failed to prepare transaction query: " . $conn->error);
        return ['success' => false, 'message' => 'Database error'];
    }
    
    $stmt->bind_param("s", $checkout_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logVoucherActivity("No completed transaction found for checkout_request_id: $checkout_request_id");
        return ['success' => false, 'message' => 'Transaction not found or already processed'];
    }
    
    $transaction = $result->fetch_assoc();
    logVoucherActivity("Found transaction for processing: {$transaction['phone_number']}, Package: {$transaction['package_name']}");
    
    // Find available voucher
    $voucher = findAvailableVoucher($conn, $transaction['package_id'], $transaction['reseller_id']);
    
    if (!$voucher) {
        logVoucherActivity("No available vouchers for package_id: {$transaction['package_id']}, reseller_id: {$transaction['reseller_id']}");
        return [
            'success' => false, 
            'message' => 'No available vouchers for this package',
            'transaction' => $transaction
        ];
    }
    
    // Assign voucher to customer
    if (!assignVoucherToCustomer($conn, $voucher, $transaction)) {
        logVoucherActivity("Failed to assign voucher to customer");
        return [
            'success' => false, 
            'message' => 'Failed to assign voucher to customer',
            'transaction' => $transaction,
            'voucher' => $voucher
        ];
    }
    
    // Send SMS
    $smsResult = sendVoucherSms($voucher, $transaction);
    
    $result = [
        'success' => true,
        'message' => 'Voucher processed successfully',
        'transaction' => $transaction,
        'voucher' => $voucher,
        'sms_result' => $smsResult
    ];
    
    if ($smsResult['success']) {
        logVoucherActivity("SUCCESS: Voucher {$voucher['code']} assigned and SMS sent to {$transaction['phone_number']}");
    } else {
        logVoucherActivity("PARTIAL SUCCESS: Voucher {$voucher['code']} assigned but SMS failed: " . $smsResult['message']);
        $result['message'] = 'Voucher assigned but SMS sending failed';
    }
    
    return $result;
}

// Handle AJAX calls for specific transaction processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_specific'])) {
    header('Content-Type: application/json');
    
    $checkout_request_id = $_POST['checkout_request_id'] ?? '';
    
    if (empty($checkout_request_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'checkout_request_id is required'
        ]);
        exit;
    }
    
    try {
        $result = processSpecificTransaction($checkout_request_id);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle direct execution for specific transaction
if (isset($_GET['checkout_request_id'])) {
    $checkout_request_id = $_GET['checkout_request_id'];
    
    echo "<h2>🎯 Processing Specific Transaction</h2>";
    echo "<p><strong>Checkout Request ID:</strong> $checkout_request_id</p>";
    
    $result = processSpecificTransaction($checkout_request_id);
    
    if ($result['success']) {
        echo "<div style='background: #d1fae5; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3>✅ Success!</h3>";
        echo "<p><strong>Message:</strong> {$result['message']}</p>";
        echo "<p><strong>Voucher Code:</strong> {$result['voucher']['code']}</p>";
        echo "<p><strong>Customer Phone:</strong> {$result['transaction']['phone_number']}</p>";
        echo "<p><strong>Package:</strong> {$result['transaction']['package_name']}</p>";
        
        if ($result['sms_result']['success']) {
            echo "<p><strong>SMS Status:</strong> ✅ Sent successfully</p>";
        } else {
            echo "<p><strong>SMS Status:</strong> ❌ Failed - {$result['sms_result']['message']}</p>";
        }
        echo "</div>";
    } else {
        echo "<div style='background: #fef2f2; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "<h3>❌ Failed</h3>";
        echo "<p><strong>Message:</strong> {$result['message']}</p>";
        echo "</div>";
    }
}

// Handle bulk processing
if (isset($_GET['run_all']) && $_GET['run_all'] === '1') {
    echo "<h2>🔄 Processing All Completed Payments</h2>";
    
    $results = processCompletedPayments();
    
    echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h3>📊 Bulk Processing Results</h3>";
    echo "<p><strong>Processed:</strong> {$results['processed']}</p>";
    echo "<p><strong>Successful:</strong> {$results['successful']}</p>";
    echo "<p><strong>Failed:</strong> {$results['failed']}</p>";
    echo "</div>";
    
    if (!empty($results['details'])) {
        echo "<h4>📋 Details:</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Transaction ID</th><th>Voucher Code</th><th>Phone</th><th>Status</th><th>SMS</th></tr>";
        
        foreach ($results['details'] as $detail) {
            $statusColor = $detail['status'] === 'success' ? 'green' : 'red';
            $smsStatus = isset($detail['sms_sent']) ? ($detail['sms_sent'] ? '✅' : '❌') : '-';
            
            echo "<tr>";
            echo "<td>" . substr($detail['transaction_id'], 0, 25) . "...</td>";
            echo "<td>" . ($detail['voucher_code'] ?? '-') . "</td>";
            echo "<td>" . ($detail['phone'] ?? '-') . "</td>";
            echo "<td style='color: $statusColor;'>{$detail['status']}</td>";
            echo "<td>$smsStatus</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Auto Process Vouchers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .action-button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 10px 5px; text-decoration: none; display: inline-block; }
        .action-button:hover { background: #059669; }
        .info-box { background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .test-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        input { padding: 8px; margin: 5px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>🚀 Auto Process Vouchers</h1>
    
    <div class="info-box">
        <h3>📱 Integration Point</h3>
        <p>This file can be called from the M-Pesa callback or run manually to process completed payments and send vouchers via Umeskia SMS.</p>
        <p><strong>SMS Gateway:</strong> Umeskia (UMS_SMS)</p>
        <p><strong>Database:</strong> Connected</p>
    </div>
    
    <div>
        <h3>🎯 Actions</h3>
        <a href="?run_all=1" class="action-button">🔄 Process All Completed Payments</a>
    </div>
    
    <div class="test-form">
        <h3>🧪 Test Specific Transaction</h3>
        <form method="get">
            <label>Checkout Request ID:</label><br>
            <input type="text" name="checkout_request_id" placeholder="ws_CO_xxxxxxxxxxxxxxxxx" required>
            <button type="submit" class="action-button">🎯 Process This Transaction</button>
        </form>
    </div>
    
    <div class="test-form">
        <h3>📱 AJAX Test</h3>
        <input type="text" id="ajax-checkout-id" placeholder="ws_CO_xxxxxxxxxxxxxxxxx">
        <button onclick="processSpecificAjax()" class="action-button">📤 Process via AJAX</button>
        <div id="ajax-result" style="margin-top: 15px;"></div>
    </div>
    
    <div class="info-box">
        <h3>🔗 Integration Usage</h3>
        <h4>From M-Pesa Callback:</h4>
        <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px;">
// In mpesa_callback.php, after updating transaction status to 'completed':
require_once 'auto_process_vouchers.php';
$result = processSpecificTransaction($checkoutRequestID);

if ($result['success']) {
    // Voucher assigned and SMS sent
    log_callback("Voucher processed: " . $result['voucher']['code']);
} else {
    // Handle error
    log_callback("Voucher processing failed: " . $result['message']);
}
        </pre>
        
        <h4>As Cron Job:</h4>
        <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px;">
# Run every 5 minutes to process any pending vouchers
*/5 * * * * curl -s "http://localhost/SAAS/Wifi%20Billiling%20system/Admin/auto_process_vouchers.php?run_all=1"
        </pre>
    </div>
    
    <script>
    function processSpecificAjax() {
        const checkoutId = document.getElementById('ajax-checkout-id').value;
        const resultDiv = document.getElementById('ajax-result');
        
        if (!checkoutId) {
            resultDiv.innerHTML = '<div style="background: #fef2f2; padding: 10px; border-radius: 4px; color: #dc2626;">Please enter a checkout request ID</div>';
            return;
        }
        
        resultDiv.innerHTML = '<div style="background: #f3f4f6; padding: 10px; border-radius: 4px;">🔄 Processing...</div>';
        
        const formData = new FormData();
        formData.append('process_specific', '1');
        formData.append('checkout_request_id', checkoutId);
        
        fetch('auto_process_vouchers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                resultDiv.innerHTML = `
                    <div style="background: #d1fae5; padding: 15px; border-radius: 5px;">
                        <h4 style="color: #065f46; margin-top: 0;">✅ Success!</h4>
                        <p><strong>Message:</strong> ${result.message}</p>
                        <p><strong>Voucher Code:</strong> ${result.voucher.code}</p>
                        <p><strong>Customer Phone:</strong> ${result.transaction.phone_number}</p>
                        <p><strong>Package:</strong> ${result.transaction.package_name}</p>
                        <p><strong>SMS Status:</strong> ${result.sms_result.success ? '✅ Sent' : '❌ Failed'}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style="background: #fef2f2; padding: 15px; border-radius: 5px;">
                        <h4 style="color: #dc2626; margin-top: 0;">❌ Failed</h4>
                        <p><strong>Message:</strong> ${result.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div style="background: #fef2f2; padding: 15px; border-radius: 5px;">
                    <h4 style="color: #dc2626; margin-top: 0;">🚨 Request Failed</h4>
                    <p>${error.message}</p>
                </div>
            `;
        });
    }
    </script>
</body>
</html>
