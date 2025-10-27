<?php
// Include session check
require_once 'session_check.php';

// Include the dashboard data
require_once 'dashboard_data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="favicon.png">
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="top-bar">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Search customers, plans, tickets...">
            </div>
            
            <div class="top-actions">
                <?php include 'header-common.php'; ?>
                <button class="time-btn" id="expiryDateBtn">
                    <?php
                    if (isset($dashboard_data['subscription']['expiry_date']) && $dashboard_data['subscription']['expiry_date']) {
                        $expiry_date = new DateTime($dashboard_data['subscription']['expiry_date']);
                        echo "Expiry Date " . $expiry_date->format('F d, Y');
                    } else {
                        echo "No Active Subscription";
                    }
                    ?>
                </button>
            </div>
        </div>
        
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p class="welcome-subtitle">Here's what's happening with your ISP business today.</p>
        </div>
        
        <!-- Subscription Renewal Modal -->
        <div id="subscriptionModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Subscription Details</h2>
                    <button class="modal-close" onclick="document.getElementById('subscriptionModal').style.display='none';">&times;</button>
                </div>
                <div class="modal-body">
                    <!-- Subscription History Section -->
                    <div class="subscription-history">
                        <h3>Subscription History</h3>
                    <div class="subscription-info">
                        <div class="info-row">
                            <span class="info-label">Last Renewal:</span>
                            <span class="info-value" id="lastRenewalDate">
                                <?php
                                if (isset($dashboard_data['subscription']['start_date']) && $dashboard_data['subscription']['start_date']) {
                                    $start_date = new DateTime($dashboard_data['subscription']['start_date']);
                                    echo $start_date->format('F d, Y');
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Subscription Updated:</span>
                            <span class="info-value" id="subscriptionUpdatedDate">
                                <?php
                                if (isset($dashboard_data['subscription']['last_payment_date']) && $dashboard_data['subscription']['last_payment_date']) {
                                    $last_payment_date = new DateTime($dashboard_data['subscription']['last_payment_date']);
                                    echo $last_payment_date->format('F d, Y');
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Last Payment Amount:</span>
                            <span class="info-value" id="lastPaymentAmount">
                                <?php
                                if (isset($dashboard_data['subscription']['amount_paid']) && $dashboard_data['subscription']['amount_paid'] > 0) {
                                    echo "Ksh " . number_format($dashboard_data['subscription']['amount_paid'], 2);
                                } else {
                                    echo "Ksh 0.00";
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Next Billing Date:</span>
                            <span class="info-value" id="nextBillingDate">
                                <?php
                                if (isset($dashboard_data['subscription']['next_billing_date']) && $dashboard_data['subscription']['next_billing_date']) {
                                    $next_billing_date = new DateTime($dashboard_data['subscription']['next_billing_date']);
                                    echo $next_billing_date->format('F d, Y');
                                } else {
                                    echo "N/A";
                                }
                                ?>
                            </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Subscription Status:</span>
                                <span class="info-value <?php echo isset($dashboard_data['subscription']['status']) ? strtolower($dashboard_data['subscription']['status']) : 'inactive'; ?>">
                                    <?php echo isset($dashboard_data['subscription']['status']) ? ucfirst($dashboard_data['subscription']['status']) : 'Inactive'; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Plan:</span>
                                <span class="info-value">
                                    <?php 
                                    echo isset($dashboard_data['subscription']['plan_name']) 
                                        ? htmlspecialchars($dashboard_data['subscription']['plan_name']) 
                                        : "No Plan"; 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Divider -->
                    <div class="modal-divider"></div>
                    
                    <!-- Current Billing Section -->
                    <div class="subscription-plan">
                        <h3>Current Billing Period</h3>
                        <p>The business plan offers unlimited requests for teams with premium support.</p>
                        
                        <div class="plan-cost">
                            <div class="cost-header">
                                <span>Usage</span>
                                <span>Cost</span>
                            </div>
                            
                            <div class="cost-row">
                                <span class="cost-label">Revenue Share (3%)</span>
                                <span class="cost-value" id="revenueShareAmount">Ksh <?php echo max(500, $dashboard_data['monthly_payment'] * 0.03); ?></span>
                            </div>
                            
                            <div class="cost-row">
                                <span class="cost-label">SMS Charges (<?php echo $dashboard_data['weekly_revenue']; ?> vouchers × 0.9)</span>
                                <span class="cost-value" id="smsChargesAmount">Ksh <?php echo number_format($dashboard_data['weekly_revenue'] * 0.9, 2); ?></span>
                            </div>
                            
                            <div class="cost-row total">
                                <span class="cost-label">Total</span>
                                <span class="cost-value" id="totalSubscriptionAmount">
                                    Ksh <?php 
                                        $revenueShare = max(500, $dashboard_data['monthly_payment'] * 0.03);
                                        $smsCharges = $dashboard_data['weekly_revenue'] * 0.9;
                                        echo number_format($revenueShare + $smsCharges, 2); 
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="minimum-notice">
                            <i class="fas fa-info-circle"></i>
                            <span>Minimum revenue share payment is Ksh 500. Additional usage costs will be added to this amount.</span>
                        </div>
                    </div>
                    
                    <div class="payment-action">
                        <?php
                            // Get current day of month and calculate days left in month
                            $currentDay = (int)date('d');
                            $lastDayOfMonth = (int)date('t');
                            $daysLeftInMonth = $lastDayOfMonth - $currentDay;
                            
                            // Check if subscription exists and if we're in the renewal period
                            $isSubscriptionActive = isset($dashboard_data['subscription']['status']) && 
                                                   $dashboard_data['subscription']['status'] === 'active';
                            
                            // Determine if in renewal period (last 7 days of the month)
                            $isRenewalPeriod = ($daysLeftInMonth < 7);
                            
                            // Set button class based on conditions
                            $buttonClass = ($isRenewalPeriod && $isSubscriptionActive) ? "payment-btn active" : "payment-btn disabled";
                        ?>
                        <button id="paySubscriptionBtn" class="<?php echo $buttonClass; ?>">
                            <?php 
                            if (!$isSubscriptionActive) {
                                echo "No Active Subscription";
                            } elseif ($isRenewalPeriod) {
                                echo "Pay Subscription";
                            } else {
                                echo "Subscription Payment Available in Last Week of Month";
                            }
                            ?>
                        </button>
                        
                        <?php if ($isSubscriptionActive && !$isRenewalPeriod): ?>
                            <div class="renewal-period-notice">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Subscription renewal is available during the last week of the month.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-title">
                    <i class="fas fa-money-bill"></i>
                    Revenue This Month
                </div>
                <div class="stat-value">Ksh <?php echo number_format($dashboard_data['monthly_payment']); ?></div>
                <div class="stat-comparison">
                    <span>vs last month</span>
                    <div class="stat-change <?php echo $dashboard_data['revenue_change']['direction']; ?>">
                        <?php if($dashboard_data['revenue_change']['direction'] == 'neutral'): ?>
                            <i class="fas fa-minus"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-<?php echo ($dashboard_data['revenue_change']['direction'] == 'positive') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                        <span><?php echo $dashboard_data['revenue_change']['value']; ?>%</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card green">
                <div class="stat-title">
                    <i class="fas fa-users"></i>
                    Subscribed hotspot clients
                </div>
                <div class="stat-value"><?php echo $dashboard_data['hotspot_clients']; ?></div>
                <div class="stat-comparison">
                    <span>vs last month</span>
                    <div class="stat-change <?php echo $dashboard_data['hotspot_clients_change']['direction']; ?>">
                        <?php if($dashboard_data['hotspot_clients_change']['direction'] == 'neutral'): ?>
                            <i class="fas fa-minus"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-<?php echo ($dashboard_data['hotspot_clients_change']['direction'] == 'positive') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                        <span><?php echo $dashboard_data['hotspot_clients_change']['value']; ?>%</span>
                    </div>
                </div>
            </div>
                        <!-- here we will show subscribed clients from your hotspot-->
            <div class="stat-card red">
                <div class="stat-title">
                    <i class="fas fa-users"></i>
                    Subscribed PPPOE clients
                </div>
                <div class="stat-value"><?php echo $dashboard_data['ppoe_clients']; ?></div>
                <div class="stat-comparison">
                    <span>vs last month</span>
                    <div class="stat-change <?php echo $dashboard_data['ppoe_clients_change']['direction']; ?>">
                        <?php if($dashboard_data['ppoe_clients_change']['direction'] == 'neutral'): ?>
                            <i class="fas fa-minus"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-<?php echo ($dashboard_data['ppoe_clients_change']['direction'] == 'positive') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                        <span><?php echo $dashboard_data['ppoe_clients_change']['value']; ?>%</span>
                    </div>
                </div>
            </div>
            
            <div class="stat-card orange">
                <div class="stat-title">
                    <i class="fas fa-ticket-alt"></i>
                    Vouchers Sold
                </div>
                <div class="stat-value"><?php echo number_format($dashboard_data['weekly_revenue']); ?></div>
                <div class="stat-comparison">
                    <span>vs last month</span>
                    <div class="stat-change <?php echo $dashboard_data['weekly_revenue_change']['direction']; ?>">
                        <?php if($dashboard_data['weekly_revenue_change']['direction'] == 'neutral'): ?>
                            <i class="fas fa-minus"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-<?php echo ($dashboard_data['weekly_revenue_change']['direction'] == 'positive') ? 'up' : 'down'; ?>"></i>
                        <?php endif; ?>
                        <span><?php echo $dashboard_data['weekly_revenue_change']['value']; ?>%</span>
                    </div>
                </div>
            </div>

        </div>
        
        <div class="bandwidth-section">
            <div class="section-header">
                <h2 class="section-title">Customer Usage</h2>
                <div class="time-filter">
                    <a href="index.php?timespan=day" class="time-btn active <?php echo ($_GET['timespan'] ?? 'week') == 'day' ? 'active' : ''; ?>">Day</a>
                    <a href="index.php?timespan=week" class="time-btn <?php echo ($_GET['timespan'] ?? 'week') == 'week' ?  'active' : ''; ?>">Week</a>
                    <a href="index.php?timespan=month" class="time-btn <?php echo ($_GET['timespan'] ?? 'week') == 'month' ? 'active' : ''; ?>">Month</a>
                </div>
            </div>
            
            <div class="bandwidth-chart" id="bandwidth-chart">
                <!-- Chart bars will be generated by JavaScript -->
            </div>
        </div>
        
        <div class="customers-section">
            <div class="section-header">
                <h2 class="section-title">Hostspot Package Performance</h2>
                <button class="time-btn">View All</button>
            </div>
            
            <div class="table-container">
                <table class="customers-table">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Active Users</th>
                            <th>Amount</th>
                            <th>Duration</th>
                            <th>Analytics</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dashboard_data['package_performance'])): ?>
                        <tr>
                            <td colspan="5" class="text-center">No package data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($dashboard_data['package_performance'] as $package): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                                <td><?php echo $package['active_users']; ?></td>
                                <td>Ksh <?php echo number_format($package['amount']); ?></td>
                                <td><?php echo htmlspecialchars($package['duration']); ?></td>
                                <td><?php echo $package['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Contact Admin Modal -->
    <div id="contactAdminModal" class="modal">
        <div class="modal-content contact-modal">
            <div class="modal-header">
                <h2>Subscription Payment</h2>
                <button class="modal-close" id="contactModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <div class="contact-info">
                    <div class="contact-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Your Subscription Request Has Been Submitted</h3>
                    <div class="subscription-request-info">
                        <p>Thank you for submitting your subscription request. Our admin team will review it and activate your subscription soon.</p>
                        <div class="request-details">
                            <div class="info-row">
                                <span class="info-label">Amount:</span>
                                <span class="info-value">
                                    Ksh <?php 
                                        $revenueShare = max(500, $dashboard_data['monthly_payment'] * 0.03);
                                        $smsCharges = $dashboard_data['weekly_revenue'] * 0.9;
                                        echo number_format($revenueShare + $smsCharges, 2); 
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Requested On:</span>
                                <span class="info-value"><?php echo date('F d, Y'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value">Pending Admin Approval</span>
                            </div>
                        </div>
                    </div>
                    
                    <h4>If you have any questions, please contact our admin team:</h4>
                    <div class="phone-numbers">
                        <div class="phone-number">
                            <i class="fas fa-phone-alt"></i>
                            <span>0750059353</span>
                        </div>
                        <div class="phone-number">
                            <i class="fas fa-phone-alt"></i>
                            <span>0114669532</span>
                        </div>
                    </div>
                    
                    <div class="payment-notice">
                        <i class="fas fa-info-circle"></i>
                        <p>We're working on implementing online checkout for subscription payments. For now, your request will be processed manually by our admin team.</p>
                    </div>
                    
                    <button class="contact-close-btn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="notifications.js"></script>
    <script src="script.js"></script>
    <script src="debug.js"></script>
    <?php
    // Display payment messages if present
    if (isset($_SESSION['payment_message']) && isset($_SESSION['payment_status'])) {
        $message = $_SESSION['payment_message'];
        $status = $_SESSION['payment_status'];
        
        echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show payment notification after page loads
            if (typeof showNotification === 'function') {
                showNotification('$message', '$status');
            } else {
                // Fallback notification if function is not available
                alert('$message');
            }
        });
        </script>";
        
        // Clear the messages
        unset($_SESSION['payment_message']);
        unset($_SESSION['payment_status']);
    }
    ?>
    <script>
    // Pass PHP data to JavaScript
    const chartData = <?php echo json_encode($dashboard_data['customer_usage'] ?? []); ?>;
    const chartDays = <?php echo json_encode($dashboard_data['days'] ?? []); ?>;
    
    // Pass subscription and user data to JavaScript
    window.subscriptionData = <?php echo json_encode($dashboard_data['subscription'] ?? []); ?>;
    window.userId = <?php echo $_SESSION['user_id'] ?? 0; ?>;
    window.userEmail = <?php echo json_encode($_SESSION['user_email'] ?? ''); ?>;
    
    // Enhanced Chart Rendering
    function renderDynamicBandwidthChart() {
        const bandwidthChart = document.getElementById('bandwidth-chart');
        if (!bandwidthChart) return;
        
        bandwidthChart.innerHTML = '';
        
        // If we have data from the backend
        if (chartData && chartData.length > 0) {
            // Find the max value for scaling
            const maxAmount = Math.max(...chartData.map(item => item.amount));
            
            chartData.forEach((item, index) => {
                const heightPercentage = (maxAmount > 0) ? (item.amount / maxAmount) * 100 : 0;
                
                const bar = document.createElement('div');
                bar.className = 'chart-bar';
                bar.style.height = `${Math.max(heightPercentage, 5)}%`; // Min 5% height for visibility
                bar.setAttribute('data-label', item.period);
                bar.setAttribute('data-value', `${item.amount.toFixed(2)}`);
                
                // Add tooltip
                bar.setAttribute('title', `${item.period}: ${item.amount.toFixed(2)} - ${item.count} transactions`);
                
                bandwidthChart.appendChild(bar);
            });
        } else {
            // Fallback to random data if no data available
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            days.forEach(day => {
                const value = Math.floor(Math.random() * 80) + 20;
                const bar = document.createElement('div');
                bar.className = 'chart-bar';
                bar.style.height = `${value}%`;
                bar.setAttribute('data-label', day);
                bandwidthChart.appendChild(bar);
            });
        }
    }

    // Call the enhanced chart renderer when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        renderDynamicBandwidthChart();
        
        // Replace the old chart renderer
        window.renderBandwidthChart = renderDynamicBandwidthChart;
    });
    </script>
    <script src="subscription_modal.js"></script>
</body>
</html>