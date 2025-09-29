<?php
/**
 * Subscription Expiry Check Script
 * This script checks for expired subscriptions and updates the reseller status accordingly
 * It should be called by a cron job daily to ensure expired accounts are properly marked
 */

// Include database connection
require_once 'connection_dp.php';

// Function to check and update expired subscriptions
function checkAndUpdateExpiredSubscriptions() {
    global $conn;
    $updated_count = 0;
    $error_message = "";
    
    try {
        // Get current date
        $current_date = date('Y-m-d H:i:s');
        
        // First get all resellers with active subscriptions
        $stmt = $conn->prepare("
            SELECT r.id, r.status, rs.expiry_date 
            FROM resellers r
            LEFT JOIN reseller_subscriptions rs ON r.id = rs.reseller_id
            WHERE r.status = 'active'
            AND rs.status = 'active'
            ORDER BY rs.id DESC
        ");
        
        if (!$stmt) {
            throw new Exception("Database query error: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Update expired subscriptions
        while ($row = $result->fetch_assoc()) {
            $expiry_date = new DateTime($row['expiry_date']);
            $current_date_obj = new DateTime($current_date);
            
            // If subscription has expired, update reseller status
            if ($current_date_obj > $expiry_date) {
                $update_stmt = $conn->prepare("
                    UPDATE resellers 
                    SET status = 'expired' 
                    WHERE id = ? AND status = 'active'
                ");
                
                if (!$update_stmt) {
                    throw new Exception("Database update error: " . $conn->error);
                }
                
                $update_stmt->bind_param("i", $row['id']);
                $update_stmt->execute();
                
                // If affected rows is 1, a status was actually changed
                if ($update_stmt->affected_rows === 1) {
                    $updated_count++;
                    
                    // Also update the subscription status
                    $sub_update = $conn->prepare("
                        UPDATE reseller_subscriptions 
                        SET status = 'expired' 
                        WHERE reseller_id = ? AND status = 'active'
                    ");
                    
                    if ($sub_update) {
                        $sub_update->bind_param("i", $row['id']);
                        $sub_update->execute();
                        $sub_update->close();
                    }
                }
                
                $update_stmt->close();
            }
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = "Error checking subscriptions: " . $e->getMessage();
        error_log($error_message);
    }
    
    return [
        'updated_count' => $updated_count,
        'error_message' => $error_message
    ];
}

// If this script is called directly, run the check
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    // Only proceed if database connection exists
    if (isset($conn) && is_object($conn)) {
        $result = checkAndUpdateExpiredSubscriptions();
        
        if (!empty($result['error_message'])) {
            echo "Error: " . $result['error_message'];
            exit(1);
        } else {
            echo "Success: Updated " . $result['updated_count'] . " expired subscriptions.";
            exit(0);
        }
    } else {
        echo "Error: Database connection not available.";
        exit(1);
    }
}

