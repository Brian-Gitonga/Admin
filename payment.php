<?php
// Include session check
require_once 'session_check.php';

// Include database connection and functions
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';

// Get the reseller ID from the session
$reseller_id = $_SESSION['user_id'];

// Initialize variables
$paymentInterval = 'monthly'; // Default
$paymentMethod = null;
$cycleEndDate = null;
$nextPaymentDate = null;
$currentBalance = 0;
$hasSettings = false;
$settingsError = false;
$paymentHistory = [];

try {
    // Get current payment interval from resellers table
    $query = "SELECT payment_interval FROM resellers WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $intervalResult = $stmt->get_result();
    
    if ($intervalResult->num_rows > 0) {
        $intervalRow = $intervalResult->fetch_assoc();
        $paymentInterval = $intervalRow['payment_interval'];
    }

    // Get M-Pesa settings for the current reseller
    $mpesaSettings = getMpesaSettings($conn, $reseller_id);
    
    if ($mpesaSettings && isset($mpesaSettings['payment_gateway'])) {
        $paymentMethod = $mpesaSettings['payment_gateway'];
        $hasSettings = true;
    }

    // Calculate cycle end dates and next payment date based on payment interval
    $today = new DateTime();
    
    if ($paymentInterval == 'weekly') {
        // Get this Saturday (end of weekly cycle)
        $cycleEndDate = new DateTime();
        $daysToSaturday = 6 - $cycleEndDate->format('w'); // 6 is Saturday
        if ($daysToSaturday < 0) $daysToSaturday += 7;
        $cycleEndDate->modify("+$daysToSaturday days");
        
        // Next payment date is Sunday after cycle end
        $nextPaymentDate = clone $cycleEndDate;
        $nextPaymentDate->modify('+1 day');
    } else {
        // Monthly - cycle ends on last day of month
        $cycleEndDate = new DateTime('last day of this month');
        
        // Next payment date is the 2nd of next month
        $nextPaymentDate = new DateTime('first day of next month');
        $nextPaymentDate->modify('+1 day');
    }

    // Format dates for display
    $formattedCycleEndDate = $cycleEndDate->format('Y-m-d');
    $formattedNextPaymentDate = $nextPaymentDate->format('Y-m-d');

    // Calculate days until next payment
    $interval = $today->diff($nextPaymentDate);
    $daysUntilPayment = $interval->days;

    // Get payment history from database if payment method is phone
    if ($paymentMethod == 'phone') {
        // In the future, this will fetch actual payment history from the database
        // For now, we'll keep this empty and display a message if no records are found
        $query = "SELECT * FROM payouts WHERE reseller_id = ? ORDER BY due_date DESC LIMIT 10";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $status = $row['status'];
                $cycle_status = $status == 'pending' ? 'current' : 'completed';
                
                $paymentHistory[] = [
                    'id' => 'PAY-' . $row['id'],
                    'date' => $row['due_date'],
                    'amount' => $row['amount'],
                    'status' => ucfirst($status),
                    'reference' => $row['transaction_id'] ?? ($status == 'pending' ? 'CURRENT-CYCLE' : ''),
                    'cycle_status' => $cycle_status
                ];
            }
        } else {
            // No payment history yet - we'll handle this in the UI
        }
        
        // Get current balance from transactions
        // This is a placeholder query - in real implementation you'd sum customer transactions
        // for the current billing cycle that haven't been paid out yet
        $query = "SELECT SUM(amount) as total FROM transactions 
                 WHERE hotspot_id IN (SELECT id FROM hotspots WHERE reseller_id = ?) 
                 AND timestamp >= ? 
                 AND timestamp <= NOW()";
                 
        $cycleStartDate = clone $cycleEndDate;
        if ($paymentInterval == 'weekly') {
            $cycleStartDate->modify('-6 days'); // Sunday to Saturday
        } else {
            $cycleStartDate->modify('first day of this month'); // 1st to last day of month
        }
        
        $formattedCycleStartDate = $cycleStartDate->format('Y-m-d');
                 
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $reseller_id, $formattedCycleStartDate);
        $stmt->execute();
        $balanceResult = $stmt->get_result();
        
        if ($balanceResult->num_rows > 0) {
            $balanceRow = $balanceResult->fetch_assoc();
            $currentBalance = $balanceRow['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    $settingsError = true;
    error_log("Error in payment.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Payment Tracking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .payment-summary-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .payment-card {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }
        
        .payment-card.primary {
            background: linear-gradient(135deg, var(--bg-accent) 0%, var(--bg-accent-dark) 100%);
            color: white;
        }
        
        .payment-card h3 {
            margin-top: 0;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }
        
        .payment-card.primary h3 {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .payment-card h3 i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }
        
        .payment-amount {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.75rem 0;
            letter-spacing: -0.01em;
        }
        
        .payment-description {
            color: var(--text-secondary);
            margin-top: auto;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .payment-card.primary .payment-description {
            color: rgba(255, 255, 255, 0.85);
        }
        
        .cycle-countdown {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            background-color: rgba(0, 0, 0, 0.05);
            display: inline-block;
        }
        
        .payment-card.primary .cycle-countdown {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .payment-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .payment-filter {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .payment-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        .payment-table th {
            background-color: var(--bg-secondary);
            text-align: left;
            padding: 0.9rem 1.25rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            font-size: 0.95rem;
        }
        
        .payment-table td {
            padding: 1.1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.15s ease;
        }
        
        .payment-table tr:hover td {
            background-color: var(--bg-secondary);
        }
        
        .payment-table tr.current-cycle {
            background-color: rgba(var(--bg-accent-rgb), 0.05);
        }
        
        .payment-table tr.current-cycle td {
            border-left: 3px solid var(--bg-accent);
        }
        
        .payment-id {
            font-family: monospace;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .payment-status {
            display: inline-block;
            padding: 0.35rem 0.9rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .payment-status.paid {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .payment-status.pending {
            background-color: rgba(255, 193, 7, 0.15);
            color: #e6a500;
        }
        
        .action-btn {
            background-color: var(--bg-secondary);
            border: none;
            color: var(--text-primary);
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
        }
        
        .action-btn:hover {
            background-color: var(--bg-accent-light);
            color: var(--text-accent);
            transform: translateY(-2px);
        }
        
        .action-btn i {
            font-size: 1rem;
        }
        
        .action-btn.primary {
            background-color: var(--bg-accent);
            color: white;
        }
        
        .action-btn.primary:hover {
            background-color: var(--bg-accent-dark);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            align-items: center;
        }
        
        .pagination-entries {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .pagination-controls {
            display: flex;
            gap: 0.35rem;
        }
        
        .page-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            text-align: center;
            border-radius: 50%;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .page-item.active {
            background-color: var(--bg-accent);
            color: white;
            box-shadow: 0 2px 6px rgba(var(--bg-accent-rgb), 0.4);
            font-weight: 600;
        }
        
        .page-item:hover:not(.active) {
            background-color: var(--bg-accent-light);
            transform: translateY(-2px);
        }
        
        .info-card {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            line-height: 1.6;
        }
        
        .info-card p {
            margin: 0.75rem 0;
        }
        
        .info-card a {
            color: var(--text-accent);
            text-decoration: none;
            font-weight: 500;
        }
        
        .info-card a:hover {
            text-decoration: underline;
        }
        
        .notice-card {
            display: flex;
            align-items: flex-start;
            padding: 1.5rem;
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .notice-card .icon {
            font-size: 2rem;
            margin-right: 1.5rem;
            color: var(--text-accent);
        }
        
        .notice-card .content h3 {
            margin-top: 0;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .notice-card .content p {
            margin: 0.5rem 0;
            color: var(--text-secondary);
        }
        
        .notice-card .content .btn-container {
            margin-top: 1.25rem;
        }
        
        .no-data-message {
            padding: 3rem;
            text-align: center;
            color: var(--text-secondary);
        }
        
        .no-data-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .no-data-message h4 {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .payment-history-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .payment-table {
                display: block;
                overflow-x: auto;
            }
            
            .payment-card {
                padding: 1.25rem;
            }
            
            .payment-amount {
                font-size: 1.6rem;
            }
            
            .action-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
            }
            
            .action-btn {
                font-size: 0.85rem;
                padding: 0.6rem 1.2rem;
            }
            
            .pagination {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .notice-card {
                flex-direction: column;
            }
            
            .notice-card .icon {
                margin-bottom: 1rem;
                margin-right: 0;
            }
        }
        
        /* CSS Variables for accent color in RGB format */
        :root {
            --bg-accent-rgb: 16, 86, 182; /* Replace with your accent color in RGB */
            --bg-accent-dark: #0a4278; /* Darker accent color for gradients */
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">
                    Payment Tracking
                    <i class="fas fa-info-circle info-icon" title="Track your payments"></i>
                </h1>
                <p class="page-subtitle">Monitor your earnings and settlement schedule</p>
            </div>
            <div class="action-buttons">
                <a href="payment.php" class="action-btn primary">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>
        
        <?php if ($settingsError): ?>
        <!-- Error State -->
        <div class="notice-card">
            <div class="icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="content">
                <h3>Error Loading Payment Settings</h3>
                <p>We encountered an error while trying to load your payment settings. This might be due to a database connection issue or missing configuration.</p>
                <p>Please try again later or contact support if the problem persists.</p>
                <div class="btn-container">
                    <a href="settings.php?tab=payment" class="action-btn">
                        <i class="fas fa-cog"></i>
                        <span>Go To Settings</span>
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif (!$hasSettings): ?>
        <!-- No Settings State -->
        <div class="notice-card">
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="content">
                <h3>Payment Settings Not Configured</h3>
                <p>You haven't configured your payment settings yet. To start tracking payments and receiving settlements, please set up your preferred payment method.</p>
                <div class="btn-container">
                    <a href="settings.php?tab=payment" class="action-btn">
                        <i class="fas fa-cog"></i>
                        <span>Configure Payment Settings</span>
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif ($paymentMethod != 'phone'): ?>
        <!-- Direct Payment Gateway State -->
        <div class="notice-card">
            <div class="icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="content">
                <h3>Direct Payment Configuration</h3>
                <p>You are currently receiving payments directly through your <strong><?php echo ucfirst($paymentMethod); ?> payment gateway</strong>. This means customers' payments go directly to your account and are not tracked in this system.</p>
                <p>To enable payment tracking and scheduled settlements, you can change your payment method to "Phone" in the settings.</p>
                <div class="btn-container">
                    <a href="settings.php?tab=payment" class="action-btn">
                        <i class="fas fa-cog"></i>
                        <span>Manage Payment Settings</span>
                    </a>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Phone Payment Gateway - Show Payment Tracking -->
        
        <!-- Payment Summary Cards -->
        <div class="payment-summary-container">
            <div class="payment-card primary">
                <h3><i class="fas fa-wallet"></i> Current Balance</h3>
                <div class="payment-amount">KSh <?php echo number_format($currentBalance, 2); ?></div>
                <div class="payment-description">
                    Total amount to be settled on next payment
                    <div class="cycle-countdown">
                        <i class="fas fa-clock"></i> 
                        Current cycle ends in <?php echo $interval->days; ?> days
                    </div>
                </div>
            </div>
            
            <div class="payment-card">
                <h3><i class="fas fa-calendar-alt"></i> Next Payment Date</h3>
                <div class="payment-amount"><?php echo date('d M Y', strtotime($formattedNextPaymentDate)); ?></div>
                <div class="payment-description">
                    <?php echo ucfirst($paymentInterval); ?> payment as per your schedule.
                    <?php if ($paymentInterval == 'weekly'): ?>
                        Payment cycle ends every Saturday.
                    <?php else: ?>
                        Payment cycle ends on the last day of each month.
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="payment-card">
                <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                <div class="payment-amount">
                    <i class="fas fa-mobile-alt"></i> M-Pesa Phone
                </div>
                <div class="payment-description">
                    Admin collects payments on your behalf and settles according to your selected payment cycle.
                </div>
            </div>
        </div>
        
        <!-- Payment History Section -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-history"></i>
                Payment History
            </h3>
            
            <div class="payment-history-header">
                <div class="payment-filter">
                    <label for="period-filter" class="form-label">Period:</label>
                    <select id="period-filter" class="form-select">
                        <option value="all">All Time</option>
                        <option value="this-month" selected>This Month</option>
                        <option value="last-month">Last Month</option>
                        <option value="last-3-months">Last 3 Months</option>
                        <option value="this-year">This Year</option>
                    </select>
                </div>
                
                <div class="payment-filter">
                    <label for="status-filter" class="form-label">Status:</label>
                    <select id="status-filter" class="form-select">
                        <option value="all" selected>All Statuses</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                
                <div class="payment-search">
                    <input type="text" placeholder="Search payments..." class="form-input">
                </div>
            </div>
            
            <?php if (empty($paymentHistory)): ?>
            <div class="no-data-message">
                <i class="fas fa-file-invoice-dollar"></i>
                <h4>No Payment History Yet</h4>
                <p>Payments will appear here as they are processed during your billing cycle.</p>
                <p>Your first payment will be processed on <?php echo date('d M Y', strtotime($formattedNextPaymentDate)); ?>.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $payment): ?>
                        <tr class="<?php echo ($payment['cycle_status'] == 'current') ? 'current-cycle' : ''; ?>">
                            <td class="payment-id"><?php echo $payment['id']; ?>
                                <?php if ($payment['cycle_status'] == 'current'): ?>
                                    <span class="badge" title="Current active cycle"><i class="fas fa-circle" style="color: var(--bg-accent); font-size: 8px;"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($payment['date'])); ?></td>
                            <td>KSh <?php echo number_format($payment['amount'], 2); ?></td>
                            <td>
                                <span class="payment-status <?php echo strtolower($payment['status']); ?>">
                                    <?php echo $payment['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $payment['reference']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($paymentHistory) > 5): ?>
            <div class="pagination">
                <div class="pagination-entries">
                    Showing 1 to <?php echo min(count($paymentHistory), 5); ?> of <?php echo count($paymentHistory); ?> entries
                </div>
                <div class="pagination-controls">
                    <a class="page-item"><i class="fas fa-chevron-left"></i></a>
                    <a class="page-item active">1</a>
                    <?php if (count($paymentHistory) > 5): ?>
                    <a class="page-item">2</a>
                    <?php endif; ?>
                    <?php if (count($paymentHistory) > 10): ?>
                    <a class="page-item">3</a>
                    <?php endif; ?>
                    <a class="page-item"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Settlement Information Section -->
        <div class="settings-section">
            <h3 class="settings-section-title">
                <i class="fas fa-info-circle"></i>
                Settlement Information
            </h3>
            
            <div class="info-card">
                <p><strong>Payment Cycle:</strong> Your current settlement schedule is set to <strong><?php echo ucfirst($paymentInterval); ?></strong>.</p>
                
                <?php if ($paymentInterval == 'weekly'): ?>
                <p><strong>Weekly payments:</strong> Your payment cycle runs from Sunday to Saturday. All transactions collected during this period are processed and paid out on the following Sunday.</p>
                <?php else: ?>
                <p><strong>Monthly payments:</strong> Your payment cycle runs from the 1st to the last day of each month. All transactions collected during this period are processed and paid out on the 2nd day of the following month.</p>
                <?php endif; ?>
                
                <p>To change your settlement schedule or update your payment details, please visit the 
                <a href="settings.php?tab=payment">Settings page</a> and select the Payment tab.</p>
                
                <p><strong>Note:</strong> There may be a processing period of 1-2 business days for funds to reflect in your account 
                after the settlement date. For any payment inquiries, please contact our support team.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="page-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Whatsapp Support</a>
                <a href="#" class="footer-link">Privacy & Terms</a>
                <a href="#" class="footer-link">Help Center</a>
            </div>
            <div class="copyright">© 2025 Qtro ISP. All Rights Reserved.</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Simple form submission handling for filters
            $('#period-filter, #status-filter').change(function() {
                // This would normally submit the form
                // For now, just reload the page
                window.location.href = 'payment.php?period=' + $('#period-filter').val() + '&status=' + $('#status-filter').val();
            });
        });
    </script>
</body>
</html>
