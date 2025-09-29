<?php
/**
 * Super Admin Configuration File
 * Database connection for the standalone super admin approval system
 */

// Database configuration - matches the main system
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "billing_system";

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }
    
    // Set error reporting mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
} catch (Exception $e) {
    // Show error message for debugging
    echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; background: #ffeeee;'>";
    echo "<h3>Database Connection Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
    echo "</div>";
    exit;
}

/**
 * Helper function to get remote access requests with related data
 */
function getRemoteAccessRequests($conn, $status = null) {
    $sql = "SELECT 
                r.id,
                r.reseller_id,
                r.router_id,
                r.request_status,
                r.admin_comments,
                r.remote_username,
                r.remote_password,
                r.dns_name,
                r.remote_port,
                r.created_at,
                r.updated_at,
                r.approved_at,
                res.business_name as reseller_business_name,
                res.email as reseller_email,
                res.phone as reseller_phone,
                res.full_name as reseller_full_name,
                h.name as router_name,
                h.router_ip,
                h.location as router_location,
                h.status as router_status
            FROM remote_access_requests r
            LEFT JOIN resellers res ON r.reseller_id = res.id
            LEFT JOIN hotspots h ON r.router_id = h.id";
    
    if ($status) {
        $sql .= " WHERE r.request_status = ?";
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $status);
        $stmt->execute();
        return $stmt->get_result();
    } else {
        $sql .= " ORDER BY r.created_at DESC";
        return $conn->query($sql);
    }
}

/**
 * Helper function to update request status
 */
function updateRequestStatus($conn, $request_id, $status, $admin_comments = null, $credentials = null) {
    if ($status === 'approved' && $credentials) {
        $sql = "UPDATE remote_access_requests SET 
                    request_status = ?, 
                    admin_comments = ?, 
                    remote_username = ?, 
                    remote_password = ?, 
                    dns_name = ?, 
                    remote_port = ?, 
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssii", 
            $status, 
            $admin_comments, 
            $credentials['username'], 
            $credentials['password'], 
            $credentials['dns_name'], 
            $credentials['port'], 
            $request_id
        );
    } else {
        $sql = "UPDATE remote_access_requests SET 
                    request_status = ?, 
                    admin_comments = ?, 
                    updated_at = NOW()
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $admin_comments, $request_id);
    }
    
    return $stmt->execute();
}

/**
 * Helper function to get request statistics
 */
function getRequestStats($conn) {
    $stats = [];
    
    // Get counts by status
    $result = $conn->query("SELECT request_status, COUNT(*) as count FROM remote_access_requests GROUP BY request_status");
    while ($row = $result->fetch_assoc()) {
        $stats[$row['request_status']] = $row['count'];
    }
    
    // Ensure all statuses have a count
    $stats['ordered'] = $stats['ordered'] ?? 0;
    $stats['approved'] = $stats['approved'] ?? 0;
    $stats['rejected'] = $stats['rejected'] ?? 0;
    $stats['total'] = array_sum($stats);
    
    return $stats;
}
?>
