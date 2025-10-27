<?php
// Database connection for portal page
$servername = "localhost";
$username = "root"; // Change this to your actual database username
$password = ""; // Change this to your actual database password
$dbname = "billing_system";

// Create connection with error handling
try {
    // Try MySQLi first
    if (extension_loaded('mysqli')) {
        $portal_conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($portal_conn->connect_error) {
            throw new Exception("MySQLi connection failed: " . $portal_conn->connect_error);
        }

        // Set charset to utf8mb4
        if (!$portal_conn->set_charset("utf8mb4")) {
            throw new Exception("Error setting charset: " . $portal_conn->error);
        }

        // For backward compatibility, also set $conn
        $conn = $portal_conn;

    } elseif (extension_loaded('pdo_mysql')) {
        // Fallback to PDO
        require_once 'pdo_connection.php';

        if (!$portal_conn) {
            throw new Exception("PDO connection failed");
        }

    } else {
        throw new Exception("Neither MySQLi nor PDO MySQL extensions are available. Please enable one of them in php.ini");
    }

} catch (Exception $e) {
    error_log("Portal Database Connection Error: " . $e->getMessage());

    // Create a fallback connection variable
    $portal_conn = null;
    $conn = null;

    // Only show error message if we're in development mode
    if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1')) {
        echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; background: #ffeeee;'>";
        echo "<h3>Portal Database Connection Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check your database configuration and ensure MySQLi or PDO MySQL extension is enabled.</p>";
        echo "</div>";
    }
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
        // Using business_name column - REMOVED status check to allow all resellers (pending, active, etc.)
        $sql = "SELECT id FROM resellers WHERE business_name = ?";
    } else {
        // Using business column - REMOVED status check to allow all resellers (pending, active, etc.)
        $sql = "SELECT id FROM resellers WHERE business = ?";
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