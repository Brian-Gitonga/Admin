<?php
/**
 * Test script to verify voucher counting consistency
 * This script compares the voucher counts from dashboard vs voucher page
 */

// Start session
session_start();

// Check if user is logged in (for testing purposes, we'll use a test user ID)
if (!isset($_SESSION['user_id'])) {
    echo "Please log in to test voucher counts.\n";
    exit;
}

$user_id = $_SESSION['user_id'];

echo "=== Voucher Count Consistency Test ===\n";
echo "Testing for User ID: $user_id\n\n";

// Include dashboard data
require_once 'dashboard_data.php';

// Include voucher database functions
require_once 'vouchers_script/db_connection.php';

echo "1. Dashboard Data (getVouchersSold function):\n";
echo "   - Vouchers Sold (Used): " . $dashboard_data['weekly_revenue'] . "\n\n";

echo "2. Voucher Page Data (countVouchersByStatus function):\n";
$usedCount = countVouchersByStatus($conn, 'used', $user_id);
$unusedCount = countVouchersByStatus($conn, 'active', $user_id);
echo "   - Used Vouchers: $usedCount\n";
echo "   - Unused Vouchers: $unusedCount\n";
echo "   - Total Vouchers: " . ($usedCount + $unusedCount) . "\n\n";

echo "3. Consistency Check:\n";
if ($dashboard_data['weekly_revenue'] == $usedCount) {
    echo "   ✅ PASS: Dashboard and Voucher page show same used voucher count\n";
} else {
    echo "   ❌ FAIL: Dashboard shows {$dashboard_data['weekly_revenue']}, Voucher page shows $usedCount\n";
}

echo "\n4. Billing Calculation Test:\n";
$revenueShare = max(500, $dashboard_data['monthly_payment'] * 0.03);
$smsCharges = $dashboard_data['weekly_revenue'] * 0.9;
$totalBilling = $revenueShare + $smsCharges;

echo "   - Revenue Share (3%): Ksh " . number_format($revenueShare, 2) . "\n";
echo "   - SMS Charges ({$dashboard_data['weekly_revenue']} used vouchers × 0.9): Ksh " . number_format($smsCharges, 2) . "\n";
echo "   - Total Billing: Ksh " . number_format($totalBilling, 2) . "\n";

echo "\n5. Database Query Verification:\n";
echo "   Testing direct SQL queries...\n";

// Test the exact query used in dashboard
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
$dashboardQuery = $result->fetch_assoc()['total'] ?? 0;

// Test the exact query used in voucher page
$stmt2 = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM vouchers v 
    LEFT JOIN packages p ON v.package_id = p.id 
    WHERE v.status = 'used' AND v.reseller_id = ?
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$voucherPageQuery = $result2->fetch_assoc()['total'] ?? 0;

echo "   - Dashboard query result: $dashboardQuery\n";
echo "   - Voucher page query result: $voucherPageQuery\n";

if ($dashboardQuery == $voucherPageQuery) {
    echo "   ✅ PASS: Both queries return the same count\n";
} else {
    echo "   ❌ FAIL: Query results differ\n";
}

echo "\n=== Test Complete ===\n";
?>
