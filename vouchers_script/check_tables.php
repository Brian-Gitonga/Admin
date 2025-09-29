<?php
// This script checks and updates database tables to ensure they have the correct structure

// Include database connection
require_once 'db_connection.php';

// Check if vouchers table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableExists->num_rows == 0) {
    // Create vouchers table
    $conn->query("
        CREATE TABLE `vouchers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `code` varchar(255) NOT NULL,
          `package_id` int(11) DEFAULT NULL,
          `router_id` int(11) DEFAULT NULL,
          `status` enum('active','used','expired') NOT NULL DEFAULT 'active',
          `customer_phone` varchar(20) DEFAULT NULL,
          `used_at` datetime DEFAULT NULL,
          `created_by` int(11) NOT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created vouchers table<br>";
} else {
    // Check if router_id column exists in vouchers table
    $columnExists = $conn->query("SHOW COLUMNS FROM `vouchers` LIKE 'router_id'");
    if ($columnExists->num_rows == 0) {
        // Add router_id column
        $conn->query("ALTER TABLE `vouchers` ADD COLUMN `router_id` int(11) DEFAULT NULL AFTER `package_id`");
        echo "Added router_id column to vouchers table<br>";
    } else {
        echo "router_id column already exists in vouchers table<br>";
    }
}

// Check if routers table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'routers'");
if ($tableExists->num_rows == 0) {
    // Create routers table
    $conn->query("
        CREATE TABLE `routers` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `ip_address` varchar(255) NOT NULL,
          `api_username` varchar(255) DEFAULT NULL,
          `api_password` varchar(255) DEFAULT NULL,
          `location` varchar(255) DEFAULT NULL,
          `reseller_id` int(11) NOT NULL,
          `is_shared` tinyint(1) NOT NULL DEFAULT 0,
          `status` enum('active','inactive') NOT NULL DEFAULT 'active',
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add demo routers
    $currentDate = date('Y-m-d H:i:s');
    $conn->query("
        INSERT INTO `routers` 
        (`name`, `ip_address`, `location`, `reseller_id`, `is_shared`, `status`, `created_at`) 
        VALUES 
        ('Main Router', '192.168.1.1', 'Main Office', 1, 1, 'active', '$currentDate'),
        ('Secondary Router', '192.168.2.1', 'Branch Office', 1, 1, 'active', '$currentDate')
    ");
    
    echo "Created routers table with demo data<br>";
} else {
    echo "routers table already exists<br>";
}

// Check if packages table exists
$tableExists = $conn->query("SHOW TABLES LIKE 'packages'");
if ($tableExists->num_rows == 0) {
    // Create packages table
    $conn->query("
        CREATE TABLE `packages` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `price` decimal(10,2) NOT NULL,
          `description` text DEFAULT NULL,
          `duration` varchar(50) DEFAULT NULL,
          `data_limit` bigint(20) DEFAULT NULL,
          `status` enum('active','inactive') NOT NULL DEFAULT 'active',
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add demo packages
    $currentDate = date('Y-m-d H:i:s');
    $conn->query("
        INSERT INTO `packages` 
        (`name`, `price`, `description`, `duration`, `status`, `created_at`) 
        VALUES 
        ('Daily Package', 50.00, '24 hours of internet access', '24 hours', 'active', '$currentDate'),
        ('Weekly Package', 250.00, '7 days of internet access', '7 days', 'active', '$currentDate'),
        ('Monthly Package', 800.00, '30 days of internet access', '30 days', 'active', '$currentDate')
    ");
    
    echo "Created packages table with demo data<br>";
} else {
    echo "packages table already exists<br>";
}

echo "Database check completed";
?> 