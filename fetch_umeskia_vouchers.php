<?php
/**
 * Fetch Umeskia Vouchers - Clean and Simple
 * 
 * This file handles:
 * 1. Finding completed payments without vouchers
 * 2. Assigning available vouchers to customers
 * 3. Sending voucher details via Umeskia SMS
 * 
 * Based on the working patterns from check_payment_status.php and database.sql
 */

require_once 'portal_connection.php';
require_once 'umeskia_sms.php';

/**
 * Log voucher processing activity
 */
function logVoucherActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents('fetch_vouchers.log', $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Find completed payments that need vouchers
 * 
 * @param mysqli $conn Database connection
 * @return array Array of transactions needing vouchers
 */
function findCompletedPaymentsNeedingVouchers($conn) {
    logVoucherActivity("=== SEARCHING FOR COMPLETED PAYMENTS NEEDING VOUCHERS ===");
    
    // Query for completed payments without voucher_code
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
            WHERE status = 'completed' 
            AND (voucher_code IS NULL OR voucher_code = '')
            ORDER BY updated_at DESC
            LIMIT 50";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        logVoucherActivity("ERROR: Failed to query transactions: " . $conn->error);
        return [];
    }
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    logVoucherActivity("Found " . count($transactions) . " completed payments needing vouchers");
    
    return $transactions;
}

/**
 * Find available voucher for package and reseller
 * 
 * @param mysqli $conn Database connection
 * @param int $package_id Package ID
 * @param int $reseller_id Reseller ID
 * @return array|null Voucher data or null if none available
 */
function findAvailableVoucher($conn, $package_id, $reseller_id) {
    logVoucherActivity("Searching for available voucher: package_id=$package_id, reseller_id=$reseller_id");
    
    $sql = "SELECT 
                id,
                code,
                username,
                password,
                package_id,
                reseller_id
            FROM vouchers 
            WHERE status = 'active' 
            AND package_id = ? 
            AND reseller_id = ?
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        logVoucherActivity("ERROR: Failed to prepare voucher query: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("ii", $package_id, $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        logVoucherActivity("No available vouchers found for package_id=$package_id, reseller_id=$reseller_id");
        return null;
    }
    
    $voucher = $result->fetch_assoc();
    logVoucherActivity("Found available voucher: " . $voucher['code']);
    
    return $voucher;
}

/**
 * Assign voucher to customer
 * 
 * @param mysqli $conn Database connection
 * @param array $voucher Voucher data
 * @param array $transaction Transaction data
 * @return bool Success status
 */
function assignVoucherToCustomer($conn, $voucher, $transaction) {
    logVoucherActivity("Assigning voucher {$voucher['code']} to customer {$transaction['phone_number']}");
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update voucher status to 'used'
        $updateVoucherSql = "UPDATE vouchers SET 
                                status = 'used',
                                customer_phone = ?,
                                used_at = NOW()
                            WHERE id = ?";
        
        $stmt1 = $conn->prepare($updateVoucherSql);
        if (!$stmt1) {
            throw new Exception("Failed to prepare voucher update: " . $conn->error);
        }
        
        $stmt1->bind_param("si", $transaction['phone_number'], $voucher['id']);
        if (!$stmt1->execute()) {
            throw new Exception("Failed to update voucher: " . $stmt1->error);
        }
        
        // Update transaction with voucher code
        $updateTransactionSql = "UPDATE mpesa_transactions SET 
                                    voucher_code = ?,
                                    updated_at = NOW()
                                WHERE id = ?";
        
        $stmt2 = $conn->prepare($updateTransactionSql);
        if (!$stmt2) {
            throw new Exception("Failed to prepare transaction update: " . $conn->error);
        }
        
        $stmt2->bind_param("si", $voucher['code'], $transaction['id']);
        if (!$stmt2->execute()) {
            throw new Exception("Failed to update transaction: " . $stmt2->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        logVoucherActivity("Successfully assigned voucher {$voucher['code']} to customer {$transaction['phone_number']}");
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        logVoucherActivity("ERROR: Failed to assign voucher: " . $e->getMessage());
        return false;
    }
}

/**
 * Send voucher SMS to customer
 * 
 * @param array $voucher Voucher data
 * @param array $transaction Transaction data
 * @return array SMS result
 */
function sendVoucherSms($voucher, $transaction) {
    logVoucherActivity("Sending voucher SMS to {$transaction['phone_number']}");
    
    // Create professional SMS message
    $smsMessage = createVoucherSmsMessage(
        $voucher['code'],
        $voucher['username'] ?: $voucher['code'],
        $voucher['password'] ?: $voucher['code'],
        $transaction['package_name']
    );
    
    // Send SMS via Umeskia
    $smsResult = sendUmeskiaSms($transaction['phone_number'], $smsMessage);
    
    if ($smsResult['success']) {
        logVoucherActivity("SMS sent successfully to {$transaction['phone_number']}");
    } else {
        logVoucherActivity("SMS sending failed to {$transaction['phone_number']}: " . $smsResult['message']);
    }
    
    return $smsResult;
}

/**
 * Process all completed payments needing vouchers
 * 
 * @return array Processing results
 */
function processCompletedPayments() {
    global $conn;
    
    logVoucherActivity("=== STARTING VOUCHER PROCESSING ===");
    
    $results = [
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    // Find completed payments needing vouchers
    $transactions = findCompletedPaymentsNeedingVouchers($conn);
    
    if (empty($transactions)) {
        logVoucherActivity("No completed payments needing vouchers found");
        return $results;
    }
    
    // Process each transaction
    foreach ($transactions as $transaction) {
        $results['processed']++;
        
        logVoucherActivity("Processing transaction: {$transaction['checkout_request_id']}");
        
        // Find available voucher
        $voucher = findAvailableVoucher($conn, $transaction['package_id'], $transaction['reseller_id']);
        
        if (!$voucher) {
            $results['failed']++;
            $results['details'][] = [
                'transaction_id' => $transaction['checkout_request_id'],
                'status' => 'failed',
                'reason' => 'No available vouchers'
            ];
            continue;
        }
        
        // Assign voucher to customer
        if (!assignVoucherToCustomer($conn, $voucher, $transaction)) {
            $results['failed']++;
            $results['details'][] = [
                'transaction_id' => $transaction['checkout_request_id'],
                'status' => 'failed',
                'reason' => 'Failed to assign voucher'
            ];
            continue;
        }
        
        // Send SMS
        $smsResult = sendVoucherSms($voucher, $transaction);
        
        if ($smsResult['success']) {
            $results['successful']++;
            $results['details'][] = [
                'transaction_id' => $transaction['checkout_request_id'],
                'voucher_code' => $voucher['code'],
                'phone' => $transaction['phone_number'],
                'status' => 'success',
                'sms_sent' => true
            ];
        } else {
            $results['failed']++;
            $results['details'][] = [
                'transaction_id' => $transaction['checkout_request_id'],
                'voucher_code' => $voucher['code'],
                'phone' => $transaction['phone_number'],
                'status' => 'partial_success',
                'sms_sent' => false,
                'sms_error' => $smsResult['message']
            ];
        }
    }
    
    logVoucherActivity("=== PROCESSING COMPLETE ===");
    logVoucherActivity("Processed: {$results['processed']}, Successful: {$results['successful']}, Failed: {$results['failed']}");
    
    return $results;
}

// Handle direct execution or AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_vouchers'])) {
    header('Content-Type: application/json');
    
    try {
        $results = processCompletedPayments();
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Handle direct execution
if (isset($_GET['run']) && $_GET['run'] === '1') {
    echo "<h2>🎯 Processing Completed Payments</h2>";
    
    $results = processCompletedPayments();
    
    echo "<h3>📊 Results:</h3>";
    echo "<p><strong>Processed:</strong> {$results['processed']}</p>";
    echo "<p><strong>Successful:</strong> {$results['successful']}</p>";
    echo "<p><strong>Failed:</strong> {$results['failed']}</p>";
    
    if (!empty($results['details'])) {
        echo "<h4>📋 Details:</h4>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Transaction ID</th><th>Voucher Code</th><th>Phone</th><th>Status</th><th>SMS</th></tr>";
        
        foreach ($results['details'] as $detail) {
            $statusColor = $detail['status'] === 'success' ? 'green' : 'red';
            $smsStatus = isset($detail['sms_sent']) ? ($detail['sms_sent'] ? '✅' : '❌') : '-';
            
            echo "<tr>";
            echo "<td>" . substr($detail['transaction_id'], 0, 20) . "...</td>";
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
    <title>Fetch Umeskia Vouchers</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .action-button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; margin: 10px 5px; }
        .action-button:hover { background: #059669; }
        .info-box { background: #f0f9ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>🎯 Fetch Umeskia Vouchers</h1>
    
    <div class="info-box">
        <h3>📱 System Status</h3>
        <p><strong>SMS Gateway:</strong> Umeskia (UMS_SMS)</p>
        <p><strong>Database:</strong> Connected</p>
        <p><strong>Function:</strong> Process completed payments and send vouchers via SMS</p>
    </div>
    
    <div>
        <h3>🚀 Actions</h3>
        <a href="?run=1" class="action-button">🔄 Process Completed Payments</a>
        <button onclick="processVouchersAjax()" class="action-button">📱 Process via AJAX</button>
    </div>
    
    <div id="ajax-result"></div>
    
    <script>
    function processVouchersAjax() {
        const resultDiv = document.getElementById('ajax-result');
        resultDiv.innerHTML = '<div style="background: #f3f4f6; padding: 15px; border-radius: 5px;"><h4>🔄 Processing...</h4></div>';
        
        const formData = new FormData();
        formData.append('process_vouchers', '1');
        
        fetch('fetch_umeskia_vouchers.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const r = result.results;
                resultDiv.innerHTML = `
                    <div style="background: #d1fae5; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <h4>✅ Processing Complete</h4>
                        <p><strong>Processed:</strong> ${r.processed}</p>
                        <p><strong>Successful:</strong> ${r.successful}</p>
                        <p><strong>Failed:</strong> ${r.failed}</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style="background: #fef2f2; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <h4>❌ Processing Failed</h4>
                        <p>${result.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div style="background: #fef2f2; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>🚨 Request Failed</h4>
                    <p>${error.message}</p>
                </div>
            `;
        });
    }
    </script>
</body>
</html>
