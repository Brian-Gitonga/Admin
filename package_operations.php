<?php
/**
 * Package Operations
 * This file contains functions to handle CRUD operations for packages
 */

// Include database connection
require_once 'connection_dp.php';
require_once 'session_functions.php';

/**
 * Get all packages for a specific user
 * 
 * @param int $user_id The ID of the user (reseller) whose packages to get
 * @return array Array of packages or empty array if none found
 */
function getAllPackages($user_id) {
    global $conn;
    $packages = [];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM packages WHERE reseller_id = ? ORDER BY type, name");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting packages: " . $e->getMessage());
    }
    
    return $packages;
}

/**
 * Get packages by type for a specific user
 * 
 * @param int $user_id The ID of the user (reseller)
 * @param string $type The type of packages to get (hotspot, pppoe, or data-plan)
 * @return array Array of packages or empty array if none found
 */
function getPackagesByType($user_id, $type) {
    global $conn;
    $packages = [];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM packages WHERE reseller_id = ? AND type = ? ORDER BY name");
        $stmt->bind_param("is", $user_id, $type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $packages[] = $row;
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting packages by type: " . $e->getMessage());
    }
    
    return $packages;
}

/**
 * Get a count of packages by type for a specific user
 * 
 * @param int $user_id The ID of the user (reseller)
 * @return array Associative array with counts for each type
 */
function getPackageCounts($user_id) {
    global $conn;
    $counts = [
        'all' => 0,
        'hotspot' => 0,
        'pppoe' => 0,
        'data-plan' => 0
    ];
    
    try {
        // Get total count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM packages WHERE reseller_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $counts['all'] = $row['count'];
        }
        $stmt->close();
        
        // Get counts by type
        $stmt = $conn->prepare("SELECT type, COUNT(*) as count FROM packages WHERE reseller_id = ? GROUP BY type");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $counts[$row['type']] = $row['count'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting package counts: " . $e->getMessage());
    }
    
    return $counts;
}

/**
 * Create a new package
 * 
 * @param array $package_data Associative array with package data
 * @return int|bool Package ID if successful, false otherwise
 */
function createPackage($package_data) {
    global $conn;
    
    try {
        // Check if we need to add free trial fields
        $has_free_trial_fields = isset($package_data['is_free_trial']) && $package_data['is_free_trial'];
        
        if ($has_free_trial_fields) {
            // First, check if the free_trial_limit column exists
            $result = $conn->query("SHOW COLUMNS FROM packages LIKE 'free_trial_limit'");
            $column_exists = $result->num_rows > 0;
            
            // Add the column if it doesn't exist
            if (!$column_exists) {
                $conn->query("ALTER TABLE packages ADD COLUMN is_free_trial BOOLEAN DEFAULT FALSE");
                $conn->query("ALTER TABLE packages ADD COLUMN free_trial_limit INT DEFAULT 1");
            }
            
            $stmt = $conn->prepare("INSERT INTO packages (reseller_id, name, type, price, upload_speed, download_speed, 
                                duration, duration_in_minutes, device_limit, data_limit, is_enabled, is_free_trial, free_trial_limit) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $data_limit = ($package_data['data_limit'] > 0) ? $package_data['data_limit'] : null;
            $is_free_trial = $package_data['is_free_trial'] ? 1 : 0;
            
            $stmt->bind_param("issdddsiiisii", 
                $package_data['reseller_id'],
                $package_data['name'],
                $package_data['type'],
                $package_data['price'],
                $package_data['upload_speed'],
                $package_data['download_speed'],
                $package_data['duration'],
                $package_data['duration_in_minutes'],
                $package_data['device_limit'],
                $data_limit,
                $package_data['is_enabled'],
                $is_free_trial,
                $package_data['free_trial_limit']
            );
        } else {
            $stmt = $conn->prepare("INSERT INTO packages (reseller_id, name, type, price, upload_speed, download_speed, 
                                duration, duration_in_minutes, device_limit, data_limit, is_enabled) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $data_limit = ($package_data['data_limit'] > 0) ? $package_data['data_limit'] : null;
            
            $stmt->bind_param("issdddsiiis", 
                $package_data['reseller_id'],
                $package_data['name'],
                $package_data['type'],
                $package_data['price'],
                $package_data['upload_speed'],
                $package_data['download_speed'],
                $package_data['duration'],
                $package_data['duration_in_minutes'],
                $package_data['device_limit'],
                $data_limit,
                $package_data['is_enabled']
            );
        }
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            return $new_id;
        } else {
            error_log("Error executing package creation: " . $stmt->error);
            $stmt->close();
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error creating package: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing package
 * 
 * @param int $package_id The ID of the package to update
 * @param array $package_data Associative array with updated package data
 * @return bool True if successful, false otherwise
 */
function updatePackage($package_id, $package_data) {
    global $conn;
    
    try {
        // Check if we're dealing with a free trial package
        $is_free_trial = isset($package_data['is_free_trial']) && $package_data['is_free_trial'];
        
        // First, check if the free_trial_limit column exists
        $result = $conn->query("SHOW COLUMNS FROM packages LIKE 'free_trial_limit'");
        $column_exists = $result->num_rows > 0;
        
        // Add the column if it doesn't exist
        if (!$column_exists && $is_free_trial) {
            $conn->query("ALTER TABLE packages ADD COLUMN is_free_trial BOOLEAN DEFAULT FALSE");
            $conn->query("ALTER TABLE packages ADD COLUMN free_trial_limit INT DEFAULT 1");
        }
        
        if ($is_free_trial && $column_exists) {
            $stmt = $conn->prepare("UPDATE packages SET 
                                name = ?, 
                                type = ?, 
                                price = ?, 
                                upload_speed = ?, 
                                download_speed = ?, 
                                duration = ?, 
                                duration_in_minutes = ?, 
                                device_limit = ?, 
                                data_limit = ?, 
                                is_enabled = ?,
                                is_free_trial = ?,
                                free_trial_limit = ?
                                WHERE id = ? AND reseller_id = ?");
            
            $data_limit = ($package_data['data_limit'] > 0) ? $package_data['data_limit'] : null;
            $free_trial = $is_free_trial ? 1 : 0;
            
            $stmt->bind_param("ssdddsiiiisiii", 
                $package_data['name'],
                $package_data['type'],
                $package_data['price'],
                $package_data['upload_speed'],
                $package_data['download_speed'],
                $package_data['duration'],
                $package_data['duration_in_minutes'],
                $package_data['device_limit'],
                $data_limit,
                $package_data['is_enabled'],
                $free_trial,
                $package_data['free_trial_limit'],
                $package_id,
                $package_data['reseller_id']
            );
        } else {
            $stmt = $conn->prepare("UPDATE packages SET 
                                name = ?, 
                                type = ?, 
                                price = ?, 
                                upload_speed = ?, 
                                download_speed = ?, 
                                duration = ?, 
                                duration_in_minutes = ?, 
                                device_limit = ?, 
                                data_limit = ?, 
                                is_enabled = ? 
                                WHERE id = ? AND reseller_id = ?");
            
            $data_limit = ($package_data['data_limit'] > 0) ? $package_data['data_limit'] : null;
            
            $stmt->bind_param("ssdddsiiisii", 
                $package_data['name'],
                $package_data['type'],
                $package_data['price'],
                $package_data['upload_speed'],
                $package_data['download_speed'],
                $package_data['duration'],
                $package_data['duration_in_minutes'],
                $package_data['device_limit'],
                $data_limit,
                $package_data['is_enabled'],
                $package_id,
                $package_data['reseller_id']
            );
        }
        
        $result = $stmt->execute();
        $stmt->close();
        return $result;
        
    } catch (Exception $e) {
        error_log("Error updating package: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a package
 * 
 * @param int $package_id The ID of the package to delete
 * @param int $user_id The ID of the user (reseller) who owns the package
 * @return bool True if successful, false otherwise
 */
function deletePackage($package_id, $user_id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM packages WHERE id = ? AND reseller_id = ?");
        $stmt->bind_param("ii", $package_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
        
    } catch (Exception $e) {
        error_log("Error deleting package: " . $e->getMessage());
        return false;
    }
}

/**
 * Convert duration string to minutes
 * 
 * @param string $duration The duration string (e.g., "1 Hour", "30 Days")
 * @return int Duration in minutes
 */
function durationToMinutes($duration) {
    $duration = strtolower($duration);
    
    if (strpos($duration, 'hour') !== false) {
        $hours = (int)$duration;
        return $hours * 60;
    } elseif (strpos($duration, 'day') !== false) {
        $days = (int)$duration;
        return $days * 24 * 60;
    } elseif (strpos($duration, 'week') !== false) {
        $weeks = (int)$duration;
        return $weeks * 7 * 24 * 60;
    } elseif (strpos($duration, 'month') !== false) {
        $months = (int)$duration;
        return $months * 30 * 24 * 60;
    }
    
    // Default fallback
    return 60; // 1 hour
}

/**
 * Format the duration for display
 * 
 * @param int $minutes Duration in minutes
 * @return string Formatted duration
 */
function formatDuration($minutes) {
    if ($minutes < 60) {
        return "{$minutes} Minutes";
    } elseif ($minutes < 60 * 24) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $mins > 0 ? "{$hours} Hour {$mins} Minutes" : "{$hours} Hour" . ($hours > 1 ? "s" : "");
    } elseif ($minutes < 60 * 24 * 7) {
        $days = floor($minutes / (60 * 24));
        return "{$days} Day" . ($days > 1 ? "s" : "");
    } else {
        $days = floor($minutes / (60 * 24));
        return "{$days} Day" . ($days > 1 ? "s" : "");
    }
}
?> 