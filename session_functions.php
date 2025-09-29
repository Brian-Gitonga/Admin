<?php
/**
 * Session helper functions
 * This file contains utility functions for session management
 */

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get current username
 * @return string|null Username if logged in, null otherwise
 */
function getCurrentUserName() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
}

/**
 * Get current user email
 * @return string|null User email if logged in, null otherwise
 */
function getCurrentUserEmail() {
    return isset($_SESSION['user_email']) ? $_SESSION['user_email'] : null;
}

/**
 * Regenerate session ID (security measure to prevent session fixation)
 */
function regenerateSession() {
    // If this session is obsolete it means there already is a new id
    if (isset($_SESSION['OBSOLETE']) && $_SESSION['OBSOLETE'] == true) {
        return;
    }

    // Set current session to expire in 10 seconds
    $_SESSION['OBSOLETE'] = true;
    $_SESSION['EXPIRES'] = time() + 10;

    // Create new session without destroying the old one
    session_regenerate_id(false);

    // Grab current session ID and close both sessions to allow other scripts to use them
    $newSession = session_id();
    session_write_close();

    // Set session ID to the new one, and start it back up again
    session_id($newSession);
    session_start();

    // Now we unset the obsolete and expiration values for the session we want to keep
    unset($_SESSION['OBSOLETE']);
    unset($_SESSION['EXPIRES']);
}

/**
 * Check if the session is valid
 */
function validateSession() {
    if (isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES'])) {
        return false;
    }

    if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time()) {
        return false;
    }

    return true;
}

/**
 * Check a user's account status in the database
 * 
 * @param int $user_id The user ID to check
 * @return string The account status (active, pending, suspended, expired, or unknown)
 */
function checkAccountStatus($user_id) {
    // Default status if database check fails
    $status = 'unknown';
    
    try {
        global $conn; // Make sure $conn is available in this scope
        
        // If $conn is not available, try to include the connection file
        if (!isset($conn) || $conn === null) {
            require_once 'connection_dp.php';
            // If still not available after include, return the default status
            if (!isset($conn) || $conn === null) {
                error_log("Database connection not available in checkAccountStatus");
                return $status;
            }
        }
        
        // Prepare and execute the query to check user status
        $stmt = $conn->prepare("SELECT status FROM resellers WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $status = $user['status'];
            }
            
            $stmt->close();
        } else {
            // Log error but return the default status
            error_log("Database error when checking user status: " . $conn->error);
        }
    } catch (Exception $e) {
        // Log error but return the default status
        error_log("Error checking account status: " . $e->getMessage());
    }
    
    return $status;
}

/**
 * Record user activity for the current session
 * 
 * @param int $user_id The user ID
 * @param string $action The action being performed
 * @param string $page The page where the action was performed
 * @return boolean Whether the activity was successfully recorded
 */
function recordUserActivity($user_id, $action, $page = '') {
    if (empty($page)) {
        // Get current page if not specified
        $page = basename($_SERVER['PHP_SELF']);
    }
    
    try {
        require_once 'connection_dp.php';
        
        // Check if user_activity table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'user_activity'");
        if ($check_table->num_rows == 0) {
            // Table doesn't exist - log error but don't disrupt the user experience
            error_log("Activity logging failed: user_activity table not found");
            return false;
        }
        
        // Record the activity
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, page, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("isss", $user_id, $action, $page, $ip);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error recording user activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a secure random token
 * 
 * @param int $length The desired length of the token
 * @return string The generated token
 */
function generateSecureToken($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        // Fallback to less secure method if necessary
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
}
?> 