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

// Check if required parameters are provided
if (!isset($_POST['package_id']) || empty($_POST['package_id'])) {
    echo json_encode(['success' => false, 'message' => 'Package ID is required']);
    exit;
}

if (!isset($_POST['router_id']) || empty($_POST['router_id'])) {
    echo json_encode(['success' => false, 'message' => 'Router ID is required']);
    exit;
}

$packageId = intval($_POST['package_id']);
$routerId = intval($_POST['router_id']);

// Get package information
$packageQuery = "SELECT id, name, price, duration, type 
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

// Get router information
$routerQuery = "SELECT id, name, router_ip, status
               FROM hotspots
               WHERE id = ? AND reseller_id = ?";
$stmt = $conn->prepare($routerQuery);
$stmt->bind_param("ii", $routerId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Router not found']);
    exit;
}

$router = $result->fetch_assoc();

// Get vouchers for this package and router
$vouchersQuery = "SELECT id, code, username, password, customer_phone, status, 
                      used_at, created_at, expires_at
                 FROM vouchers
                 WHERE package_id = ? AND router_id = ? AND reseller_id = ?
                 ORDER BY created_at DESC";
$stmt = $conn->prepare($vouchersQuery);
$stmt->bind_param("iii", $packageId, $routerId, $resellerId);
$stmt->execute();
$result = $stmt->get_result();

$vouchers = [];
while ($row = $result->fetch_assoc()) {
    $vouchers[] = $row;
}

// Return package, router and voucher information
echo json_encode([
    'success' => true,
    'package' => $package,
    'router' => $router,
    'vouchers' => $vouchers
]); 