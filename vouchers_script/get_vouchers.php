<?php
// This script fetches vouchers from the database based on filters
require_once 'db_connection.php';

// Check for session status or start a new one if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, "Unauthorized: Please log in to view vouchers");
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Default values
$status = isset($_GET['status']) ? $_GET['status'] : 'unused';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = isset($_GET['offset']) && is_numeric($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : null;

// Convert status to database value
if ($status === 'unused') {
    $dbStatus = 'active';
} else if ($status === 'used') {
    $dbStatus = 'used';
} else {
    $dbStatus = 'active'; // Default to active
}

// Get vouchers from the database
$vouchers = getVouchersByStatus($conn, $dbStatus, $resellerId, $limit, $offset, $search);

// If fetch failed
if ($vouchers === false) {
    sendJsonResponse(false, "Failed to retrieve vouchers");
    exit;
}

// Count total vouchers for pagination
$totalVouchers = countVouchersByStatus($conn, $dbStatus, $resellerId, $search);

// Format the results
$formattedVouchers = [];
while ($voucher = $vouchers->fetch_assoc()) {
    $formattedVouchers[] = [
        'id' => $voucher['id'],
        'code' => $voucher['code'],
        'username' => $voucher['username'] ?? $voucher['code'], // Use code as username if not set
        'password' => $voucher['password'] ?? $voucher['code'], // Use code as password if not set
        'package_id' => $voucher['package_id'],
        'package_name' => $voucher['package_name'] ?? 'Unknown Package',
        'reseller_id' => $voucher['reseller_id'],
        'customer_phone' => $voucher['customer_phone'],
        'status' => $voucher['status'],
        'used_at' => $voucher['used_at'],
        'created_at' => $voucher['created_at'],
        'expires_at' => $voucher['expires_at']
    ];
}

// Send the response
sendJsonResponse(true, "Vouchers retrieved successfully", [
    'vouchers' => $formattedVouchers,
    'total' => $totalVouchers,
    'limit' => $limit,
    'offset' => $offset
]);

/**
 * Send a JSON response to the client
 */
function sendJsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'total' => $data['total'] ?? 0,
        'vouchers' => $data['vouchers'] ?? [],
        'limit' => $data['limit'] ?? 10,
        'offset' => $data['offset'] ?? 0
    ]);
    exit;
}
?> 