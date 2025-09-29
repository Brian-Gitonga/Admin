<?php
// Start session at the very beginning of the file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Include database connection
require_once 'vouchers_script/db_connection.php';

// Check if package ID is provided
if (!isset($_POST['package_id']) || empty($_POST['package_id'])) {
    echo json_encode(['success' => false, 'message' => 'Package ID is required']);
    exit;
}

$packageId = intval($_POST['package_id']);

// Get package information
$packageQuery = "SELECT id, name, description, price, duration, type, data_limit, speed, device_limit, is_active 
                FROM packages 
                WHERE id = ? AND reseller_id = ?";
$stmt = $conn->prepare($packageQuery);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $packageId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Package not found']);
    exit;
}

$package = $result->fetch_assoc();

// Get voucher counts by status
$voucherQuery = "SELECT 
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                    COUNT(CASE WHEN status = 'used' THEN 1 END) as used_count,
                    COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_count,
                    COUNT(*) as total_count
                FROM vouchers 
                WHERE package_id = ? AND reseller_id = ?";
$stmt = $conn->prepare($voucherQuery);
$stmt->bind_param("ii", $packageId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();
$voucherCounts = $result->fetch_assoc();

// Get vouchers by router
$routerVouchersQuery = "SELECT 
                        h.id as router_id,
                        h.name as router_name,
                        COUNT(CASE WHEN v.status = 'active' THEN 1 END) as active_count,
                        COUNT(CASE WHEN v.status = 'used' THEN 1 END) as used_count,
                        COUNT(CASE WHEN v.status = 'expired' THEN 1 END) as expired_count,
                        COUNT(v.id) as total_count
                    FROM vouchers v
                    LEFT JOIN hotspots h ON v.router_id = h.id
                    WHERE v.package_id = ? AND v.reseller_id = ?
                    GROUP BY h.id
                    ORDER BY h.name";
$stmt = $conn->prepare($routerVouchersQuery);
$stmt->bind_param("ii", $packageId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();

$routerVouchers = [];
while ($row = $result->fetch_assoc()) {
    // If router is null (router_id is NULL), set name to "Unassigned"
    if ($row['router_id'] === null) {
        $row['router_name'] = 'Unassigned';
    }
    $routerVouchers[] = $row;
}

// Get recent vouchers for this package (limit to 10)
$recentVouchersQuery = "SELECT v.id, v.code, v.username, v.password, v.customer_phone, v.status, 
                        v.used_at, v.created_at, v.expires_at, v.router_id,
                        h.name as router_name
                       FROM vouchers v
                       LEFT JOIN hotspots h ON v.router_id = h.id
                       WHERE v.package_id = ? AND v.reseller_id = ?
                       ORDER BY v.created_at DESC
                       LIMIT 10";
$stmt = $conn->prepare($recentVouchersQuery);
$stmt->bind_param("ii", $packageId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();

$recentVouchers = [];
while ($row = $result->fetch_assoc()) {
    // If router is null, set name to "Unassigned"
    if ($row['router_id'] === null) {
        $row['router_name'] = 'Unassigned';
    }
    $recentVouchers[] = $row;
}

// Return package details and voucher information
echo json_encode([
    'success' => true,
    'package' => $package,
    'voucher_counts' => $voucherCounts,
    'router_vouchers' => $routerVouchers,
    'recent_vouchers' => $recentVouchers
]); 