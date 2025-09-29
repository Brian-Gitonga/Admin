<?php
// Start output buffering to prevent any unwanted output before JSON response
ob_start();

// Actions for voucher management (create, delete, etc.)
require_once 'db_connection.php';
require_once 'generate_after_payment.php'; // Ensure this file is included

// Debug logging
$logFile = 'voucher_debug.log';
function debug_log($message) {
    global $logFile;
    error_log($message . "\n", 3, $logFile);
}

debug_log("--- Voucher Action Request " . date('Y-m-d H:i:s') . " ---");
debug_log("POST data: " . print_r($_POST, true));

// MikroTik integration removed - vouchers will be generated without router communication
$mikrotikAvailable = false;

/**
 * Fallback function for router integration - router communication disabled
 */
if (!function_exists('addVoucherToRouter')) {
    function addVoucherToRouter($voucherCode, $packageId, $resellerId, $customerPhone, $conn) {
        error_log("Router integration disabled - voucher generated without router communication: $voucherCode");
        // Return true as vouchers are now generated without router communication
        return true;
    }
}

// Check for session status or start a new one if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Include helper functions for voucher creation
require_once 'handleCreateVoucher.php';

// Handle different action types based on the request
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create':
            handleCreateVoucher();
            break;
            
        case 'delete':
            handleDeleteVoucher();
            break;
            
        case 'bulk_create':
            handleBulkCreateVouchers();
            break;
            
        case 'upload':
            handleUploadVouchers();
            break;
            
        default:
            sendJsonResponse(false, "Unknown action: $action");
    }
} else {
    sendJsonResponse(false, "No action specified");
}

/**
 * Handle creation of a single voucher
 */
function handleCreateVoucher() {
    global $conn, $resellerId;
    
    // Validate required fields
    if (!isset($_POST['package_id']) || !is_numeric($_POST['package_id'])) {
        sendJsonResponse(false, "Invalid package selected");
        return;
    }
    
    $packageId = (int)$_POST['package_id'];
    debug_log("Creating voucher for package ID: $packageId, reseller ID: $resellerId");
    
    // Use the improved handler function
    $result = handleCreateVoucherImproved($conn, $resellerId, $packageId);
    
    // Send the response
    sendJsonResponse(
        $result['success'],
        $result['message'],
        isset($result['voucher_code']) ? ['voucher_code' => $result['voucher_code']] : []
    );
}

/**
 * Handle creation of multiple vouchers at once
 */
function handleBulkCreateVouchers() {
    global $conn, $resellerId;
    
    // Validate required fields
    if (!isset($_POST['package_id']) || !is_numeric($_POST['package_id'])) {
        sendJsonResponse(false, "Invalid package selected");
        return;
    }
    
    if (!isset($_POST['count']) || !is_numeric($_POST['count']) || $_POST['count'] < 1) {
        sendJsonResponse(false, "Invalid number of vouchers");
        return;
    }
    
    $packageId = (int)$_POST['package_id'];
    $count = min((int)$_POST['count'], 100); // Cap at 100 vouchers
    
    debug_log("Creating $count vouchers for package ID: $packageId, reseller ID: $resellerId");
    
    // Use the improved handler function
    $result = handleBulkCreateVouchersImproved($conn, $resellerId, $packageId, $count);
    
    // Send the response
    if (isset($result['voucher_codes'])) {
        sendJsonResponse(
            $result['success'],
            $result['message'],
            [
                'count' => $result['count'] ?? count($result['voucher_codes']),
                'voucher_codes' => $result['voucher_codes']
            ]
        );
    } else {
        sendJsonResponse($result['success'], $result['message']);
    }
}

/**
 * Handle deletion of a voucher
 */
function handleDeleteVoucher() {
    global $conn, $resellerId;
    
    // Validate required fields
    if (!isset($_POST['voucher_id']) || !is_numeric($_POST['voucher_id'])) {
        sendJsonResponse(false, "Invalid voucher ID");
        return;
    }
    
    $voucherId = (int)$_POST['voucher_id'];
    
    // Verify the voucher belongs to this reseller and is not used
    $checkSql = "SELECT id FROM vouchers WHERE id = ? AND reseller_id = ? AND status = 'active'";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $voucherId, $resellerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJsonResponse(false, "Voucher not found, already used, or does not belong to you");
        return;
    }
    
    // Delete the voucher
    $deleteSql = "DELETE FROM vouchers WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("i", $voucherId);
    
    if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
        sendJsonResponse(true, "Voucher deleted successfully");
    } else {
        sendJsonResponse(false, "Failed to delete voucher");
    }
}

/**
 * Handle upload of vouchers from CSV/Excel file
 */
function handleUploadVouchers() {
    global $conn, $resellerId;
    
    // Check if file is uploaded
    if (!isset($_FILES['voucher_file']) || $_FILES['voucher_file']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(false, "No file uploaded or upload error");
        return;
    }
    
    $file = $_FILES['voucher_file'];
    $fileName = $file['name'];
    $fileType = $file['type'];
    $fileTmpName = $file['tmp_name'];
    
    // Validate file type
    $allowedTypes = [
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($fileType, $allowedTypes)) {
        sendJsonResponse(false, "Invalid file type. Please upload a CSV or Excel file");
        return;
    }
    
    // Process CSV file (simple implementation)
    if ($fileType === 'text/csv') {
        $handle = fopen($fileTmpName, 'r');
        
        if ($handle === false) {
            sendJsonResponse(false, "Failed to open file");
            return;
        }
        
        $successCount = 0;
        $errorCount = 0;
        $row = 0;
        
        // Start a transaction for bulk insertion
        $conn->begin_transaction();
        
        try {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row++;
                
                // Skip header row
                if ($row === 1) {
                    continue;
                }
                
                // Check if we have both package ID and voucher code
                if (count($data) < 2 || empty($data[0]) || empty($data[1])) {
                    $errorCount++;
                    continue;
                }
                
                $packageId = (int)$data[0];
                $voucherCode = trim($data[1]);
                
                // Verify the package belongs to this reseller
                $checkSql = "SELECT id FROM packages WHERE id = ? AND reseller_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $packageId, $resellerId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows === 0) {
                    $errorCount++;
                    continue;
                }
                
                // Insert the voucher
                $insertSql = "INSERT INTO vouchers (code, package_id, reseller_id, status, expires_at) 
                              VALUES (?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY))";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("sii", $voucherCode, $packageId, $resellerId);
                
                if ($insertStmt->execute()) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            
            fclose($handle);
            
            // Commit the transaction if at least some vouchers were added
            if ($successCount > 0) {
                $conn->commit();
                sendJsonResponse(true, "$successCount vouchers uploaded successfully. $errorCount errors occurred.");
            } else {
                $conn->rollback();
                sendJsonResponse(false, "No vouchers were uploaded. Please check the CSV format.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            sendJsonResponse(false, "Error processing file: " . $e->getMessage());
        }
    } else {
        // For Excel files, you would need PHPExcel or similar library
        sendJsonResponse(false, "Excel file processing not implemented in this example");
    }
}

/**
 * Send a JSON response to the client
 */
function sendJsonResponse($success, $message, $data = []) {
    global $logFile;
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    
    debug_log("Response: " . print_r($response, true));
    
    // Clean any output that might have been generated before
    $unwantedOutput = ob_get_clean();
    if (!empty($unwantedOutput)) {
        debug_log("Unwanted output before JSON: " . $unwantedOutput);
    }
    
    // Set headers to ensure proper JSON response
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    // Send the JSON response
    echo json_encode($response);
    exit;
}
?> 