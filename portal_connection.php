<?php
// Database connection for portal page
$servername = "localhost";
$username = "root"; // Change this to your actual database username
$password = ""; // Change this to your actual database password
$dbname = "billing_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to get reseller ID by business name
function getResellerIdByBusinessName($conn, $businessName) {
    // Check if resellers table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'resellers'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        // Table doesn't exist
        return false;
    }
    
    // First check if the column is business_name or business
    $columnCheckQuery = "SHOW COLUMNS FROM resellers LIKE 'business_name'";
    $columnResult = $conn->query($columnCheckQuery);
    
    if ($columnResult->num_rows > 0) {
        // Using business_name column
        $sql = "SELECT id FROM resellers WHERE business_name = ? AND status = 'active'";
    } else {
        // Using business column
        $sql = "SELECT id FROM resellers WHERE business = ? AND status = 'active'";
    }
    
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $businessName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    } else {
        return false;
    }
}

// Function to get vouchers by reseller ID and type
function getVouchersByType($conn, $resellerId, $type) {
    // First check if the vouchers table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'vouchers'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        // Table doesn't exist, return empty result set
        return createEmptyResultSet();
    }
    
    $sql = "SELECT * FROM vouchers WHERE reseller_id = ? AND type = ? AND is_active = 1 ORDER BY price ASC";
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        // Return empty result set instead of failing
        return createEmptyResultSet();
    }
    
    // Bind parameters and execute
    $stmt->bind_param("is", $resellerId, $type);
    $stmt->execute();
    
    // Return the result
    return $stmt->get_result();
}

// Helper function to create an empty result set
function createEmptyResultSet() {
    // Create a dummy result set with the appropriate properties
    return new class {
        public $num_rows = 0;
        public function fetch_assoc() { return null; }
        public function free() {}
    };
}

// Function to get reseller info by ID
function getResellerInfo($conn, $resellerId) {
    // Check if resellers table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'resellers'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        // Table doesn't exist
        return false;
    }
    
    // Check which column name exists (business_name or business)
    $columnCheckQuery = "SHOW COLUMNS FROM resellers LIKE 'business_name'";
    $columnResult = $conn->query($columnCheckQuery);
    
    if ($columnResult->num_rows > 0) {
        // Using both to be safe
        $sql = "SELECT *, business_name AS business_display_name FROM resellers WHERE id = ?";
    } else {
        // Using business as the display name
        $sql = "SELECT *, business AS business_display_name FROM resellers WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Function to get packages by reseller ID and duration category (daily, weekly, monthly)
function getPackagesByType($conn, $resellerId, $category) {
    // First check if the packages table exists
    $tableCheckQuery = "SHOW TABLES LIKE 'packages'";
    $tableResult = $conn->query($tableCheckQuery);
    
    if ($tableResult->num_rows == 0) {
        // Table doesn't exist, return empty result set
        return createEmptyResultSet();
    }
    
    // Determine the duration range based on the category
    switch ($category) {
        case 'daily':
            // Packages with duration up to 1 day (1440 minutes)
            $minDuration = 0;
            $maxDuration = 1440; 
            break;
        case 'weekly':
            // Packages with duration between 1 day and 7 days (1440-10080 minutes)
            $minDuration = 1441;
            $maxDuration = 10080;
            break;
        case 'monthly':
            // Packages with duration more than 7 days (> 10080 minutes)
            $minDuration = 10081;
            $maxDuration = PHP_INT_MAX; // No upper limit
            break;
        default:
            return createEmptyResultSet();
    }
    
    $sql = "SELECT id, name, price, 
                   CONCAT(upload_speed, '/', download_speed, ' Mbps, ', duration) AS description
            FROM packages 
            WHERE reseller_id = ? 
            AND duration_in_minutes BETWEEN ? AND ? 
            AND is_enabled = TRUE 
            ORDER BY price ASC";
            
    $stmt = $conn->prepare($sql);
    
    // Check if prepare was successful
    if ($stmt === false) {
        // Log the error
        error_log("Error preparing statement: " . $conn->error);
        // Return empty result set instead of failing
        return createEmptyResultSet();
    }
    
    // Bind parameters and execute
    $stmt->bind_param("iii", $resellerId, $minDuration, $maxDuration);
    $stmt->execute();
    
    // Return the result
    return $stmt->get_result();
}
?> 