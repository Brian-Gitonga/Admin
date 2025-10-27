<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

echo "<h1>Quick Debug</h1>";

// Check database connection
if ($conn) {
    echo "<p style='color: green;'>✅ Database connected</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}

// Check if vouchers table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p style='color: green;'>✅ Vouchers table exists</p>";
    
    // Check voucher count
    $voucherCount = $conn->query("SELECT COUNT(*) as total FROM vouchers WHERE status = 'active'");
    if ($voucherCount) {
        $count = $voucherCount->fetch_assoc()['total'];
        echo "<p>Active vouchers: $count</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Vouchers table does NOT exist</p>";
}

// Check recent completed transaction
$recentTxn = $conn->query("SELECT * FROM mpesa_transactions WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1");
if ($recentTxn && $recentTxn->num_rows > 0) {
    $txn = $recentTxn->fetch_assoc();
    echo "<h3>Recent Completed Transaction:</h3>";
    echo "<p>Checkout ID: " . $txn['checkout_request_id'] . "</p>";
    echo "<p>Status: " . $txn['status'] . "</p>";
    echo "<p>Package ID: " . $txn['package_id'] . "</p>";
    echo "<p>Phone: " . $txn['phone_number'] . "</p>";
    
    // Test the voucher handler function
    echo "<h3>Testing Voucher Handler:</h3>";
    
    try {
        require_once 'vouchers_script/payment_voucher_handler.php';
        
        $result = createVoucherAfterPayment(
            $txn['checkout_request_id'],
            $txn['package_id'],
            $txn['reseller_id'],
            $txn['phone_number'],
            $txn['mpesa_receipt']
        );
        
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ No completed transactions found</p>";
}

?>
