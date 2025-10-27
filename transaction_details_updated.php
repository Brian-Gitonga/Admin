<?php
/**
 * Transaction Details
 * 
 * This file displays detailed information about a specific transaction (M-Pesa or Paystack)
 */

// Start session
session_start();

// Include database connection
require_once 'portal_connection.php';

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['payment_message'] = 'No transaction ID provided';
    $_SESSION['payment_status'] = 'error';
    header('Location: transations.php');
    exit;
}

$transaction_id = (int)$_GET['id'];
$gateway = isset($_GET['gateway']) ? $_GET['gateway'] : 'mpesa';

// First check if the vouchers table exists
$voucher_table_exists = false;
$check_table = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($check_table && $check_table->num_rows > 0) {
    $voucher_table_exists = true;
}

// Get transaction details based on gateway type
if ($gateway === 'paystack') {
    // Check if payment_transactions table exists
    $paymentTableExists = false;
    $checkPaymentTable = $conn->query("SHOW TABLES LIKE 'payment_transactions'");
    if ($checkPaymentTable && $checkPaymentTable->num_rows > 0) {
        $paymentTableExists = true;
    } else {
        $_SESSION['payment_message'] = 'Payment transactions table does not exist';
        $_SESSION['payment_status'] = 'error';
        header('Location: transations.php');
        exit;
    }
    
    if ($voucher_table_exists) {
        // If vouchers table exists, use JOIN
        $query = "
            SELECT t.*, v.code as voucher_code, v.status as voucher_status
            FROM payment_transactions t
            LEFT JOIN vouchers v ON (v.package_id = t.package_id AND v.customer_phone = t.phone_number AND v.created_at >= t.created_at AND v.created_at <= DATE_ADD(t.created_at, INTERVAL 5 MINUTE))
            WHERE t.id = ? AND t.payment_gateway = 'paystack'
            ORDER BY v.created_at DESC
            LIMIT 1
        ";
    } else {
        // If vouchers table doesn't exist, just get transaction details
        $query = "
            SELECT t.* 
            FROM payment_transactions t
            WHERE t.id = ? AND t.payment_gateway = 'paystack'
        ";
    }
} else {
    // Default to M-Pesa
    if ($voucher_table_exists) {
        // If vouchers table exists, use JOIN
        $query = "
            SELECT t.*, v.code as voucher_code, v.status as voucher_status
            FROM mpesa_transactions t
            LEFT JOIN vouchers v ON (v.package_id = t.package_id AND v.customer_phone = t.phone_number AND v.created_at >= t.created_at AND v.created_at <= DATE_ADD(t.created_at, INTERVAL 5 MINUTE))
            WHERE t.id = ?
            ORDER BY v.created_at DESC
            LIMIT 1
        ";
    } else {
        // If vouchers table doesn't exist, just get transaction details
        $query = "
            SELECT t.* 
            FROM mpesa_transactions t
            WHERE t.id = ?
        ";
    }
}

$stmt = $conn->prepare($query);

// Check if prepare was successful
if ($stmt === false) {
    $_SESSION['payment_message'] = 'Database error: ' . $conn->error;
    $_SESSION['payment_status'] = 'error';
    header('Location: transations.php');
    exit;
}

$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['payment_message'] = 'Transaction not found';
    $_SESSION['payment_status'] = 'error';
    header('Location: transations.php');
    exit;
}

$transaction = $result->fetch_assoc();

// Get reseller details if available
$resellerName = 'Unknown';
$resellerStmt = $conn->prepare("SELECT name FROM resellers WHERE id = ?");
if ($resellerStmt) {
    $resellerStmt->bind_param('i', $transaction['reseller_id']);
    $resellerStmt->execute();
    $resellerResult = $resellerStmt->get_result();
    if ($resellerResult->num_rows > 0) {
        $resellerRow = $resellerResult->fetch_assoc();
        $resellerName = $resellerRow['name'];
    }
}

// Normalize field names based on gateway
$receipt = $gateway === 'paystack' ? ($transaction['reference'] ?? 'N/A') : ($transaction['mpesa_receipt'] ?? 'N/A');
$checkoutRequestId = $gateway === 'paystack' ? ($transaction['reference'] ?? 'N/A') : ($transaction['checkout_request_id'] ?? 'N/A');
$merchantRequestId = $gateway === 'paystack' ? 'N/A' : ($transaction['merchant_request_id'] ?? 'N/A');
$resultCode = $gateway === 'paystack' ? 'N/A' : ($transaction['result_code'] ?? 'N/A');
$resultDescription = $gateway === 'paystack' ? 'N/A' : ($transaction['result_description'] ?? 'N/A');
$email = $gateway === 'paystack' ? ($transaction['email'] ?? 'N/A') : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details - Qtro ISP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .details-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .transaction-id {
            font-size: 0.9rem;
            color: #777;
        }
        
        .back-button {
            color: #333;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        
        .details-section {
            margin-bottom: 30px;
        }
        
        .details-section h2 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .gateway-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 10px;
        }
        
        .gateway-badge.mpesa {
            background-color: #c1e7ff;
            color: #0078d4;
        }
        
        .gateway-badge.paystack {
            background-color: #d4f7dc;
            color: #0a8724;
        }
        
        .voucher-container {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        
        .voucher-code {
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 10px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .qr-code {
            margin: 15px auto;
            max-width: 150px;
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="main-content" id="main-content">
        <div class="details-container">
            <!-- Transaction Header -->
            <div class="transaction-header">
                <div>
                    <h1>
                        Transaction Details
                        <?php if ($gateway === 'mpesa'): ?>
                            <span class="gateway-badge mpesa">M-Pesa</span>
                        <?php elseif ($gateway === 'paystack'): ?>
                            <span class="gateway-badge paystack">Paystack</span>
                        <?php endif; ?>
                    </h1>
                    <span class="transaction-id">ID: <?php echo $transaction_id; ?></span>
                </div>
                <a href="transations.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Transactions
                </a>
            </div>
            
            <!-- Display any messages -->
            <?php if (isset($_SESSION['payment_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['payment_status'] ?? 'info'; ?>">
                    <?php 
                        echo $_SESSION['payment_message']; 
                        unset($_SESSION['payment_message']);
                        unset($_SESSION['payment_status']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Transaction Status -->
            <div class="details-section">
                <div style="text-align: center; margin-bottom: 20px;">
                    <?php if ($transaction['status'] == 'completed'): ?>
                        <div class="badge badge-success">COMPLETED</div>
                    <?php elseif ($transaction['status'] == 'pending'): ?>
                        <div class="badge badge-pending">PENDING</div>
                    <?php else: ?>
                        <div class="badge badge-failed">FAILED</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($transaction['status'] == 'completed' && !empty($transaction['voucher_code'])): ?>
                    <div class="voucher-container">
                        <h3>WiFi Access Voucher</h3>
                        <div class="voucher-code"><?php echo htmlspecialchars($transaction['voucher_code']); ?></div>
                        
                        <?php if (!empty($transaction['voucher_code'])): ?>
                            <!-- QR Code for the voucher -->
                            <div class="qr-code">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($transaction['voucher_code']); ?>&size=150x150" alt="Voucher QR Code">
                            </div>
                        <?php endif; ?>
                        
                        <p>Use this code to access the WiFi hotspot</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Transaction Details -->
            <div class="details-section">
                <h2>Payment Information</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Amount</span>
                        <span class="detail-value">KSH <?php echo number_format($transaction['amount'], 2); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label"><?php echo $gateway === 'paystack' ? 'Payment Reference' : 'M-Pesa Receipt'; ?></span>
                        <span class="detail-value"><?php echo htmlspecialchars($receipt); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Payment Date</span>
                        <span class="detail-value"><?php echo date('d M Y H:i', strtotime($transaction['created_at'])); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Package</span>
                        <span class="detail-value"><?php echo htmlspecialchars($transaction['package_name']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Customer Information -->
            <div class="details-section">
                <h2>Customer Information</h2>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Phone Number</span>
                        <span class="detail-value"><?php echo htmlspecialchars($transaction['phone_number']); ?></span>
                    </div>
                    
                    <?php if ($gateway === 'paystack' && !empty($email) && $email !== 'N/A'): ?>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <span class="detail-label">Reseller</span>
                        <span class="detail-value"><?php echo htmlspecialchars($resellerName); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Transaction Technical Details -->
            <div class="details-section">
                <h2>Technical Details</h2>
                <div class="details-grid">
                    <?php if ($gateway === 'mpesa'): ?>
                    <div class="detail-item">
                        <span class="detail-label">Checkout Request ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($checkoutRequestId); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Merchant Request ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($merchantRequestId); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Result Code</span>
                        <span class="detail-value"><?php echo $resultCode; ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Result Description</span>
                        <span class="detail-value"><?php echo htmlspecialchars($resultDescription); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="detail-item">
                        <span class="detail-label">Transaction Reference</span>
                        <span class="detail-value"><?php echo htmlspecialchars($checkoutRequestId); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Payment Gateway</span>
                        <span class="detail-value">Paystack</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($transaction['status'] == 'pending'): ?>
                    <?php if ($gateway === 'mpesa'): ?>
                    <a href="transations_script/check_transaction.php?id=<?php echo $transaction_id; ?>&gateway=mpesa" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Check M-Pesa Status
                    </a>
                    <?php else: ?>
                    <a href="transations_script/check_paystack_transaction.php?id=<?php echo $transaction_id; ?>&gateway=paystack" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Check Paystack Status
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html>









