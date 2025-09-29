<?php
/**
 * Helper Functions
 * 
 * This file contains various helper functions used throughout the application.
 */

// Include notifications.php if it exists
if (file_exists(dirname(__FILE__) . '/notifications.php')) {
    require_once dirname(__FILE__) . '/notifications.php';
}

// Only declare display_notification() if it doesn't already exist
if (!function_exists('display_notification')) {
    /**
     * Display notification messages to the user
     * 
     * This function checks for notification messages in the session and displays them
     */
    function display_notification() {
        if (isset($_SESSION['notification'])) {
            $notification = $_SESSION['notification'];
            $type = isset($notification['type']) ? $notification['type'] : 'info';
            $message = isset($notification['message']) ? $notification['message'] : '';
            
            if (!empty($message)) {
                echo '<div class="notification notification-' . $type . '">';
                echo '<span class="notification-message">' . $message . '</span>';
                echo '<button class="notification-close">&times;</button>';
                echo '</div>';
            }
            
            // Clear the notification after displaying it
            unset($_SESSION['notification']);
        }
    }
}

// Only declare set_notification() if it doesn't already exist
if (!function_exists('set_notification')) {
    /**
     * Set a notification message to be displayed on the next page load
     * 
     * @param string $message The notification message
     * @param string $type The type of notification (success, error, warning, info)
     */
    function set_notification($message, $type = 'info') {
        $_SESSION['notification'] = [
            'message' => $message,
            'type' => $type
        ];
    }
}

// Only declare getResellerIdByBusinessName() if it doesn't already exist
if (!function_exists('getResellerIdByBusinessName')) {
    /**
     * Get reseller ID by business name
     * 
     * @param mysqli $conn Database connection
     * @param string $businessName Business name to search for
     * @return int|false Reseller ID or false if not found
     */
    function getResellerIdByBusinessName($conn, $businessName) {
        // First check if the column is named business_name or business
        $columnCheckQuery = "SHOW COLUMNS FROM resellers LIKE 'business_name'";
        $columnResult = $conn->query($columnCheckQuery);
        
        if ($columnResult && $columnResult->num_rows > 0) {
            // Using business_name column
            $query = "SELECT id FROM resellers WHERE business_name = ? AND status = 'active'";
        } else {
            // Using business column
            $query = "SELECT id FROM resellers WHERE business = ? AND status = 'active'";
        }
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("s", $businessName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['id'];
        }
        
        return false;
    }
}

// Only declare getResellerInfo() if it doesn't already exist
if (!function_exists('getResellerInfo')) {
    /**
     * Get reseller information
     * 
     * @param mysqli $conn Database connection
     * @param int $resellerId Reseller ID
     * @return array|false Reseller information or false if not found
     */
    function getResellerInfo($conn, $resellerId) {
        $query = "SELECT * FROM resellers WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $resellerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        
        return false;
    }
}

// Only declare getPackagesByType() if it doesn't already exist
if (!function_exists('getPackagesByType')) {
    /**
     * Get packages by type
     * 
     * @param mysqli $conn Database connection
     * @param int $resellerId Reseller ID
     * @param string $packageType Package type (daily, weekly, monthly)
     * @return mysqli_result Result set containing packages
     */
    function getPackagesByType($conn, $resellerId, $packageType) {
        $query = "SELECT * FROM packages WHERE reseller_id = ? AND type = ? AND is_active = 1 ORDER BY price ASC";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return createEmptyResultSet();
        }
        
        $stmt->bind_param("is", $resellerId, $packageType);
        $stmt->execute();
        return $stmt->get_result();
    }
}

// Only declare createEmptyResultSet() if it doesn't already exist
if (!function_exists('createEmptyResultSet')) {
    /**
     * Create an empty result set
     * 
     * Used as a fallback when queries fail or tables don't exist
     * 
     * @return object An object that mimics an empty mysqli_result
     */
    function createEmptyResultSet() {
        return new class {
            public $num_rows = 0;
            public function fetch_assoc() { return null; }
        };
    }
}

// Only declare formatCurrency() if it doesn't already exist
if (!function_exists('formatCurrency')) {
    /**
     * Format currency
     * 
     * @param float $amount Amount to format
     * @param string $currency Currency symbol (default: KSh)
     * @return string Formatted currency
     */
    function formatCurrency($amount, $currency = 'KSh') {
        return $currency . ' ' . number_format($amount, 0);
    }
}

// Only declare isRouterOnline() if it doesn't already exist
if (!function_exists('isRouterOnline')) {
    /**
     * Check if a hotspot router is online
     * 
     * @param mysqli $conn Database connection
     * @param int $routerId Router ID
     * @return bool True if router is online
     */
    function isRouterOnline($conn, $routerId) {
        $query = "SELECT status FROM hotspots WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $routerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['status'] === 'online';
        }
        
        return false;
    }
}
?> 