<?php
/**
 * Dashboard data provider
 * Retrieves all the data needed for the dashboard
 */

// Include database connection
require_once 'connection_dp.php';

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 0;

// Initialize result array
$dashboard_data = [
    'monthly_payment' => 0,
    'hotspot_clients' => 0,
    'ppoe_clients' => 0,
    'weekly_revenue' => 0,
    'previous_month_data' => [
        'revenue' => 0,
        'hotspot_clients' => 0,
        'ppoe_clients' => 0,
        'weekly_revenue' => 0
    ],
    'customer_usage' => [],
    'package_performance' => []
];

/**
 * Get monthly payment for the reseller from mpesa_transactions table
 */
function getMonthlyPayment($conn, $user_id) {
    $amount = 0;
    try {
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total 
            FROM mpesa_transactions 
            WHERE reseller_id = ? 
            AND status = 'completed'
            AND MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $amount = $row['total'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting monthly payment: " . $e->getMessage());
    }
    return $amount;
}

/**
 * Get active hotspot clients count
 * Counts users whose expiry_date is in the future and don't have a PPPOE plan
 */
function getActiveHotspotClients($conn, $user_id) {
    $count = 0;
    try {
        // Get all hotspots owned by this reseller
        $hotspotsSql = "SELECT id FROM hotspots WHERE reseller_id = ?";
        $stmt = $conn->prepare($hotspotsSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $hotspotResult = $stmt->get_result();

        // Create an array of hotspot IDs
        $hotspotIds = [];
        while ($row = $hotspotResult->fetch_assoc()) {
            $hotspotIds[] = $row['id'];
        }
        $stmt->close();

        // If reseller has hotspots
        if (!empty($hotspotIds)) {
            // Convert array to string for IN clause
            $hotspotIdsString = implode(',', $hotspotIds);
            
            // Current date and time
            $currentDateTime = date('Y-m-d H:i:s');
            
            // Count active users excluding PPPOE plans
            $activeCountSql = "SELECT COUNT(*) as total FROM end_users 
                          WHERE hotspot_id IN ($hotspotIdsString) 
                          AND expiry_date > '$currentDateTime'
                          AND (current_plan NOT LIKE '%PPPOE%' OR current_plan IS NULL)";
            $activeResult = $conn->query($activeCountSql);
            $activeRow = $activeResult->fetch_assoc();
            $count = $activeRow['total'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting active hotspot clients: " . $e->getMessage());
    }
    return $count;
}

/**
 * Get PPPOE clients count
 * Counts users whose expiry_date is in the future and have a PPPOE plan
 */
function getPPOEClients($conn, $user_id) {
    $count = 0;
    try {
        // Get all hotspots owned by this reseller in the database and prepare to show in the frontend
        $hotspotsSql = "SELECT id FROM hotspots WHERE reseller_id = ?";
        $stmt = $conn->prepare($hotspotsSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $hotspotResult = $stmt->get_result();

        // Create an array of hotspot IDs
        $hotspotIds = [];
        while ($row = $hotspotResult->fetch_assoc()) {
            $hotspotIds[] = $row['id'];
        }
        $stmt->close();

        // If reseller has hotspots this is what will happen in this function
        if (!empty($hotspotIds)) {
            // Convert array to string for IN clause
            $hotspotIdsString = implode(',', $hotspotIds);
            
            // Current date and time
            $currentDateTime = date('Y-m-d H:i:s');
            
            // Count active PPPOE users
            $activeCountSql = "SELECT COUNT(*) as total FROM end_users 
                          WHERE hotspot_id IN ($hotspotIdsString) 
                          AND expiry_date > '$currentDateTime'
                          AND current_plan LIKE '%PPPOE%'";
            $activeResult = $conn->query($activeCountSql);
            $activeRow = $activeResult->fetch_assoc();
            $count = $activeRow['total'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting PPPOE clients: " . $e->getMessage());
    }
    return $count;
}

/**
 * Get current number of vouchers that were sold (used) by the reseller in that specific month per user from the database
 * A voucher is considered "sold" when it has been used (sent via SMS to customers)
 */
function getVouchersSold($conn, $user_id) {
    $count = 0;
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM vouchers
            WHERE reseller_id = ?
            AND status = 'used'
            AND MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $count = $row['total'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting vouchers sold: " . $e->getMessage());
    }
    return $count;
}

/**
 * Get comparison data from previous month
 */
function getPreviousMonthData($conn, $user_id) {
    $data = [
        'revenue' => 0,
        'hotspot_clients' => 0,
        'ppoe_clients' => 0,
        'weekly_revenue' => 0
    ];
    
    try {
        // Previous month revenue from mpesa_transactions
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total
            FROM mpesa_transactions
            WHERE reseller_id = ?
            AND status = 'completed'
            AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $data['revenue'] = $row['total'] ?? 0;
        }
        
        // Previous month vouchers sold (used vouchers only)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM vouchers
            WHERE reseller_id = ?
            AND status = 'used'
            AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $data['weekly_revenue'] = $row['total'] ?? 0;
        }
        
        // Note: For hotspot and PPPOE clients, we would need historical data
        // which may not be available. This would require a more complex tracking system.
        
    } catch (Exception $e) {
        error_log("Error getting previous month data: " . $e->getMessage());
    }
    
    return $data;
}

/**
 * Get customer usage data for the chart
 * Update to use mpesa_transactions
 */
function getCustomerUsageData($conn, $user_id, $timespan = 'week') {
    $data = [];
    $days = [];
    
    // Set the interval based on the timespan
    switch ($timespan) {
        case 'day':
            $interval = "24 HOUR";
            $format = "%H:00"; // Hours of the day
            $periods = 24;
            break;
        case 'month':
            $interval = "30 DAY";
            $format = "%d"; // Days of the month
            $periods = 30;
            break;
        case 'week':
        default:
            $interval = "7 DAY";
            $format = "%a"; // Day abbreviation (Mon, Tue, etc.)
            $periods = 7;
            break;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                DATE_FORMAT(created_at, ?) as period,
                SUM(amount) as usage_amount,
                COUNT(id) as transaction_count
            FROM mpesa_transactions
            WHERE reseller_id = ?
            AND status = 'completed'
            AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL $interval)
            GROUP BY period
            ORDER BY MIN(created_at)
        ");
        $stmt->bind_param("si", $format, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'period' => $row['period'],
                'amount' => (float)$row['usage_amount'],
                'count' => (int)$row['transaction_count']
            ];
            $days[] = $row['period'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting customer usage data: " . $e->getMessage());
    }
    
    return [
        'data' => $data,
        'days' => $days
    ];
}

/**
 * Get package performance data
 * Updated to use the current plan from end_users and get transaction data from mpesa_transactions
 */
function getPackagePerformance($conn, $user_id) {
    $packages = [];
    $total_users = 0;
    
    try {
        // Get all hotspots owned by this reseller
        $hotspotsSql = "SELECT id FROM hotspots WHERE reseller_id = ?";
        $stmt = $conn->prepare($hotspotsSql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $hotspotResult = $stmt->get_result();

        // Create an array of hotspot IDs
        $hotspotIds = [];
        while ($row = $hotspotResult->fetch_assoc()) {
            $hotspotIds[] = $row['id'];
        }
        $stmt->close();

        // If reseller has hotspots
        if (!empty($hotspotIds)) {
            // Convert array to string for IN clause
            $hotspotIdsString = implode(',', $hotspotIds);
            
            // Current date and time
            $currentDateTime = date('Y-m-d H:i:s');
            
            // First get total active users
            $stmt = $conn->prepare("
                SELECT COUNT(id) as total
                FROM end_users
                WHERE hotspot_id IN ($hotspotIdsString)
                AND expiry_date > '$currentDateTime'
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $total_users = $row['total'] ?? 0;
            }
            
            // Then get package details with user counts
            $stmt = $conn->prepare("
                SELECT 
                    current_plan as package_name,
                    COUNT(id) as active_users,
                    SUBSTRING_INDEX(current_plan, ' ', 1) as speed,
                    SUBSTRING_INDEX(current_plan, ' ', -1) as duration
                FROM end_users
                WHERE hotspot_id IN ($hotspotIdsString)
                AND expiry_date > '$currentDateTime'
                GROUP BY current_plan
                ORDER BY active_users DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // Calculate percentage of total users
                $percentage = ($total_users > 0) ? round(($row['active_users'] / $total_users) * 100) : 0;
                
                // Get transaction data for this package from mpesa_transactions
                $plan_name = $row['package_name'];
                $amount = 0;
                
                $stmt2 = $conn->prepare("
                    SELECT AVG(amount) as avg_amount
                    FROM mpesa_transactions
                    WHERE reseller_id = ?
                    AND package_name = ?
                    AND status = 'completed'
                ");
                $stmt2->bind_param("is", $user_id, $plan_name);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                if ($row2 = $result2->fetch_assoc()) {
                    $amount = $row2['avg_amount'] ?? 0;
                }
                
                $packages[] = [
                    'package_name' => $row['package_name'],
                    'active_users' => (int)$row['active_users'],
                    'amount' => $amount,
                    'duration' => $row['duration'],
                    'percentage' => $percentage
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting package performance: " . $e->getMessage());
    }
    
    return $packages;
}

/**
 * Get subscription information for the current reseller
 * Also updates the reseller status to 'expired' if the subscription has expired
 */
function getSubscriptionInfo($conn, $user_id) {
    $subscription = [
        'expiry_date' => null,
        'start_date' => null,
        'last_payment_date' => null,
        'amount_paid' => 0,
        'status' => 'inactive',
        'plan_name' => 'No Plan',
        'is_expired' => true
    ];
    
    try {
        // Get the most recent active subscription for this reseller
        $stmt = $conn->prepare("
            SELECT rs.*, sp.name as plan_name, r.status as reseller_status
            FROM reseller_subscriptions rs
            JOIN subscription_plans sp ON rs.plan_id = sp.id
            JOIN resellers r ON rs.reseller_id = r.id
            WHERE rs.reseller_id = ?
            ORDER BY rs.expiry_date DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $subscription['expiry_date'] = $row['expiry_date'];
            $subscription['start_date'] = $row['start_date'];
            $subscription['last_payment_date'] = $row['last_payment_date'];
            $subscription['amount_paid'] = $row['amount_paid'];
            $subscription['status'] = $row['status'];
            $subscription['plan_name'] = $row['plan_name'];
            
            // Check if subscription is expired
            $current_date = new DateTime();
            $expiry_date = new DateTime($row['expiry_date']);
            $subscription['is_expired'] = ($current_date > $expiry_date);
            
            // If subscription is expired but reseller status is still active, update it to expired
            if ($subscription['is_expired'] && $row['reseller_status'] === 'active') {
                // Update the reseller status to expired
                $update_stmt = $conn->prepare("UPDATE resellers SET status = 'expired' WHERE id = ?");
                if ($update_stmt) {
                    $update_stmt->bind_param("i", $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    
                    // Also update subscription status
                    $sub_update = $conn->prepare("
                        UPDATE reseller_subscriptions 
                        SET status = 'expired' 
                        WHERE reseller_id = ? AND status = 'active'
                    ");
                    
                    if ($sub_update) {
                        $sub_update->bind_param("i", $user_id);
                        $sub_update->execute();
                        $sub_update->close();
                    }
                    
                    // Log this action
                    error_log("Updated reseller ID $user_id status to expired due to subscription expiration");
                }
            }
            
            // Calculate next billing date (1 month from last payment)
            if ($row['last_payment_date']) {
                $last_payment = new DateTime($row['last_payment_date']);
                $last_payment->modify('+1 month');
                $subscription['next_billing_date'] = $last_payment->format('Y-m-d');
            } else {
                $subscription['next_billing_date'] = null;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting subscription info: " . $e->getMessage());
    }
    
    return $subscription;
}

// Fetch all the data if connection is established
if (is_db_connected()) {
    // Get all dashboard data
    $dashboard_data['monthly_payment'] = getMonthlyPayment($conn, $user_id);
    $dashboard_data['hotspot_clients'] = getActiveHotspotClients($conn, $user_id);
    $dashboard_data['ppoe_clients'] = getPPOEClients($conn, $user_id);
    $dashboard_data['weekly_revenue'] = getVouchersSold($conn, $user_id); // Changed to vouchers sold
    $dashboard_data['previous_month_data'] = getPreviousMonthData($conn, $user_id);
    
    // Get timespan from query param if set
    $timespan = isset($_GET['timespan']) ? $_GET['timespan'] : 'week';
    $customer_usage = getCustomerUsageData($conn, $user_id, $timespan);
    $dashboard_data['customer_usage'] = $customer_usage['data'];
    $dashboard_data['days'] = $customer_usage['days'];
    
    // Get package performance data
    $dashboard_data['package_performance'] = getPackagePerformance($conn, $user_id);

    // Add subscription info to dashboard data
    $dashboard_data['subscription'] = getSubscriptionInfo($conn, $user_id);
}

// Function to calculate percentage change
function calculatePercentageChange($current, $previous) {
    if ($previous <= 0) {
        return ['value' => 0, 'direction' => 'neutral']; // Avoid division by zero
    }
    
    $change = (($current - $previous) / $previous) * 100;
    $direction = ($change >= 0) ? 'positive' : 'negative';
    
    return ['value' => abs(round($change, 1)), 'direction' => $direction];
}

// Calculate percentage changes
$dashboard_data['revenue_change'] = calculatePercentageChange(
    $dashboard_data['monthly_payment'],
    $dashboard_data['previous_month_data']['revenue']
);

$dashboard_data['hotspot_clients_change'] = calculatePercentageChange(
    $dashboard_data['hotspot_clients'],
    $dashboard_data['previous_month_data']['hotspot_clients']
);

$dashboard_data['ppoe_clients_change'] = calculatePercentageChange(
    $dashboard_data['ppoe_clients'],
    $dashboard_data['previous_month_data']['ppoe_clients']
);

$dashboard_data['weekly_revenue_change'] = calculatePercentageChange(
    $dashboard_data['weekly_revenue'],
    $dashboard_data['previous_month_data']['weekly_revenue']
); 