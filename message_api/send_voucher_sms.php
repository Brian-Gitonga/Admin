<?php
/**
 * Send Voucher SMS Controller
 * 
 * Handles requests to send voucher codes via SMS to customers
 */

// Start output buffering to prevent any unwanted output before JSON response
ob_start();

// Include required files
require_once 'sms_service.php';
require_once '../vouchers_script/db_connection.php';

// Debug logging
$logFile = 'sms_debug.log';
function sms_log($message) {
    global $logFile;
    error_log($message . "\n", 3, $logFile);
}

sms_log("--- SMS Voucher Request " . date('Y-m-d H:i:s') . " ---");
sms_log("POST data: " . print_r($_POST, true));

// Initialize the SMS Service
$smsService = new SMSService(
    'africatalking',  // Provider
    [],              // Default config from africatalking.php
    '254',          // Default country code (Kenya)
    true            // Debug mode
);

// Handle different action types
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'send_voucher':
            handleSendVoucher();
            break;
            
        case 'resend_voucher':
            handleResendVoucher();
            break;
            
        case 'test_connection':
            handleTestConnection();
            break;
            
        default:
            sendJsonResponse(false, "Unknown action: $action");
    }
} else {
    sendJsonResponse(false, "No action specified");
}

/**
 * Handle sending a voucher via SMS
 */
function handleSendVoucher() {
    global $conn, $smsService;
    
    // Check session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, "Unauthorized: Please log in to send vouchers");
        return;
    }
    
    // Get the reseller ID from the session
    $resellerId = $_SESSION['user_id'];
    
    // Validate required fields
    if (!isset($_POST['voucher_code']) || empty($_POST['voucher_code'])) {
        sendJsonResponse(false, "Voucher code is required");
        return;
    }
    
    if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
        sendJsonResponse(false, "Phone number is required");
        return;
    }
    
    $voucherCode = $_POST['voucher_code'];
    $phoneNumber = $_POST['phone_number'];
    
    // Validate that the voucher exists and belongs to this reseller
    $voucher = getVoucherDetails($conn, $voucherCode, $resellerId);
    
    if (!$voucher) {
        sendJsonResponse(false, "Invalid voucher code or voucher does not belong to you");
        return;
    }
    
    // Send the SMS
    $result = $smsService->sendVoucherSMS(
        $phoneNumber,
        $voucherCode,
        [
            'name' => $voucher['package_name'],
            'duration' => $voucher['package_duration'] ?? '',
            'price' => $voucher['price'] ?? ''
        ]
    );
    
    if ($result['success']) {
        // Update the voucher with the phone number
        updateVoucherPhoneNumber($conn, $voucherCode, $phoneNumber);
        
        sendJsonResponse(true, "Voucher sent successfully to $phoneNumber", [
            'voucher_code' => $voucherCode,
            'phone_number' => $phoneNumber
        ]);
    } else {
        sendJsonResponse(false, "Failed to send voucher SMS: " . $result['message']);
    }
}

/**
 * Handle resending a previously sent voucher
 */
function handleResendVoucher() {
    global $conn, $smsService;
    
    // Check session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, "Unauthorized: Please log in to resend vouchers");
        return;
    }
    
    // Get the reseller ID from the session
    $resellerId = $_SESSION['user_id'];
    
    // Validate required fields
    if (!isset($_POST['voucher_id']) || !is_numeric($_POST['voucher_id'])) {
        sendJsonResponse(false, "Invalid voucher ID");
        return;
    }
    
    $voucherId = (int)$_POST['voucher_id'];
    
    // Get voucher details
    $voucher = getVoucherById($conn, $voucherId, $resellerId);
    
    if (!$voucher) {
        sendJsonResponse(false, "Voucher not found or does not belong to you");
        return;
    }
    
    // Check if the voucher has a phone number
    if (empty($voucher['customer_phone']) || $voucher['customer_phone'] === 'admin') {
        sendJsonResponse(false, "This voucher has no associated phone number");
        return;
    }
    
    // Send the SMS
    $result = $smsService->sendVoucherSMS(
        $voucher['customer_phone'],
        $voucher['code'],
        [
            'name' => $voucher['package_name'],
            'duration' => $voucher['package_duration'] ?? '',
            'price' => $voucher['price'] ?? ''
        ]
    );
    
    if ($result['success']) {
        sendJsonResponse(true, "Voucher resent successfully to {$voucher['customer_phone']}", [
            'voucher_code' => $voucher['code'],
            'phone_number' => $voucher['customer_phone']
        ]);
    } else {
        sendJsonResponse(false, "Failed to resend voucher SMS: " . $result['message']);
    }
}

/**
 * Handle testing the SMS connection
 */
function handleTestConnection() {
    global $smsService;
    
    // Check session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, "Unauthorized: Please log in to test SMS connection");
        return;
    }
    
    // Validate required fields
    if (!isset($_POST['phone_number']) || empty($_POST['phone_number'])) {
        sendJsonResponse(false, "Phone number is required");
        return;
    }
    
    $phoneNumber = $_POST['phone_number'];
    
    // Send a test message
    $result = $smsService->sendSMS(
        $phoneNumber,
        "This is a test message from your WiFi voucher system. If you receive this, the SMS service is working properly."
    );
    
    if ($result['success']) {
        sendJsonResponse(true, "Test message sent successfully to $phoneNumber", [
            'phone_number' => $phoneNumber
        ]);
    } else {
        sendJsonResponse(false, "Failed to send test message: " . $result['message']);
    }
}

/**
 * Get voucher details by code
 */
function getVoucherDetails($conn, $voucherCode, $resellerId) {
    $sql = "SELECT v.*, p.name as package_name, p.duration as package_duration, p.price 
            FROM vouchers v 
            LEFT JOIN packages p ON v.package_id = p.id 
            WHERE v.code = ? AND v.reseller_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $voucherCode, $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Get voucher details by ID
 */
function getVoucherById($conn, $voucherId, $resellerId) {
    $sql = "SELECT v.*, p.name as package_name, p.duration as package_duration, p.price 
            FROM vouchers v 
            LEFT JOIN packages p ON v.package_id = p.id 
            WHERE v.id = ? AND v.reseller_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $voucherId, $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

/**
 * Update a voucher with the customer's phone number
 */
function updateVoucherPhoneNumber($conn, $voucherCode, $phoneNumber) {
    $sql = "UPDATE vouchers SET customer_phone = ? WHERE code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $phoneNumber, $voucherCode);
    return $stmt->execute();
}

/**
 * Send a JSON response to the client
 */
function sendJsonResponse($success, $message, $data = []) {
    // Clean any output that might have been generated before
    $unwantedOutput = ob_get_clean();
    if (!empty($unwantedOutput)) {
        sms_log("Unwanted output before JSON: " . $unwantedOutput);
    }
    
    // Set headers for JSON response
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Build the response
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    // Log the response
    sms_log("Response: " . print_r($response, true));
    
    // Send the JSON response
    echo json_encode($response);
    exit;
}
?> 