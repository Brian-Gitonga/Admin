<?php
// Start output buffering to catch any unwanted output
ob_start();

// Database connection for voucher-related operations
$servername = "localhost";
$username = "root"; // Change to your actual database username
$password = ""; // Change to your actual database password
$dbname = "billing_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get vouchers by status and reseller ID
function getVouchersByStatus($conn, $status, $resellerId, $limit = 10, $offset = 0, $search = null) {
    // If search parameter is provided, add a WHERE clause for code or package name
    $searchCondition = "";
    if ($search) {
        $search = "%$search%";
        $searchCondition = " AND (v.code LIKE ? OR p.name LIKE ?)";
    }
    
    // Build the SQL query with inner join to packages table
    $sql = "SELECT v.id, v.code, v.username, v.password, v.package_id, v.reseller_id, v.customer_phone, v.status, v.used_at, v.created_at, v.expires_at, p.name as package_name 
            FROM vouchers v 
            LEFT JOIN packages p ON v.package_id = p.id 
            WHERE v.status = ? AND v.reseller_id = ?" . $searchCondition . "
            ORDER BY v.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        return false;
    }
    
    // Bind parameters based on whether search is included
    if ($search) {
        $stmt->bind_param("sissii", $status, $resellerId, $search, $search, $limit, $offset);
    } else {
        $stmt->bind_param("siii", $status, $resellerId, $limit, $offset);
    }
    
    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result;
}

// Function to count total vouchers by status and reseller ID
function countVouchersByStatus($conn, $status, $resellerId, $search = null) {
    // If search parameter is provided, add a WHERE clause for code or package name
    $searchCondition = "";
    if ($search) {
        $search = "%$search%";
        $searchCondition = " AND (v.code LIKE ? OR p.name LIKE ?)";
    }
    
    // Build the SQL query
    $sql = "SELECT COUNT(*) as total 
            FROM vouchers v 
            LEFT JOIN packages p ON v.package_id = p.id 
            WHERE v.status = ? AND v.reseller_id = ?" . $searchCondition;
    
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        return 0;
    }
    
    // Bind parameters based on whether search is included
    if ($search) {
        $stmt->bind_param("siss", $status, $resellerId, $search, $search);
    } else {
        $stmt->bind_param("si", $status, $resellerId);
    }
    
    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

// Function to generate a random voucher code
function generateVoucherCode($length = 8) {
    // Characters to use for the voucher code (excluding similar-looking characters)
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    // Generate random string
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

// Function to create a new voucher
function createVoucher($conn, $packageId, $resellerId, $customerPhone = null) {
    // Generate a unique voucher code
    $voucherCode = generateVoucherCode();
    
    // Check if the code already exists
    $checkSql = "SELECT id FROM vouchers WHERE code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $voucherCode);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    // If code exists, try again with a new code
    while ($result->num_rows > 0) {
        $voucherCode = generateVoucherCode();
        $checkStmt->bind_param("s", $voucherCode);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
    }
    
    // Special handling for demo packages
    $isDemoPackage = ($packageId == 998 || $packageId == 999);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days')); // Default 30 days expiry
    
    if (!$isDemoPackage) {
        // Get package details to calculate expiry date
        $packageSql = "SELECT duration FROM packages WHERE id = ?";
        $packageStmt = $conn->prepare($packageSql);
        $packageStmt->bind_param("i", $packageId);
        $packageStmt->execute();
        $packageResult = $packageStmt->get_result();
        $package = $packageResult->fetch_assoc();
        
        // Calculate expiry date based on package duration if available
        if ($package) {
            // You could calculate based on duration here if needed
            // For now, we'll keep the default 30 days
        }
    }
    
    // Set default customer phone if not provided
    if ($customerPhone === null) {
        $customerPhone = "admin";
    }
    
    // Set username and password to be the same as the voucher code
    $username = $voucherCode;
    $password = $voucherCode;
    
    // Insert the new voucher with username and password
    $insertSql = "INSERT INTO vouchers (code, username, password, package_id, reseller_id, customer_phone, status, expires_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 'active', ?)";
    $insertStmt = $conn->prepare($insertSql);
    $insertStmt->bind_param("sssiiss", $voucherCode, $username, $password, $packageId, $resellerId, $customerPhone, $expiresAt);
    
    if ($insertStmt->execute()) {
        return $voucherCode;
    } else {
        error_log("Error creating voucher: " . $conn->error);
        return false;
    }
}

// Function to mark a voucher as used
function useVoucher($conn, $voucherCode, $customerPhone) {
    // Update the voucher status
    $sql = "UPDATE vouchers 
            SET status = 'used', used_at = NOW(), customer_phone = ? 
            WHERE code = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $customerPhone, $voucherCode);
    
    if ($stmt->execute()) {
        // Check if any rows were affected
        if ($stmt->affected_rows > 0) {
            return true;
        } else {
            error_log("No active voucher found with code: $voucherCode");
            return false;
        }
    } else {
        error_log("Error updating voucher status: " . $conn->error);
        return false;
    }
}

// Function to get voucher details by code
function getVoucherByCode($conn, $voucherCode) {
    $sql = "SELECT v.*, p.name as package_name, p.duration as package_duration 
            FROM vouchers v 
            LEFT JOIN packages p ON v.package_id = p.id 
            WHERE v.code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $voucherCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}
?> 