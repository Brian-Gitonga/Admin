<?php
// Debug script to verify database connection and create/check tables
header('Content-Type: text/plain');
echo "Database Debug Information\n";
echo "=========================\n\n";

// Include database connection
require_once 'db_connection.php';

// Start session and check login
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Error: No user session found. Please log in first.\n";
    exit;
}

$resellerId = $_SESSION['user_id'];
echo "Reseller ID: $resellerId\n\n";

// Check database connection
echo "Database Connection: ";
if ($conn && $conn->ping()) {
    echo "Connected successfully\n";
} else {
    echo "Connection failed: " . ($conn ? $conn->error : "Unknown error") . "\n";
    exit;
}

// List all tables
echo "\nCurrent Tables in Database:\n";
$result = $conn->query("SHOW TABLES");
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_row()) {
            echo "- " . $row[0] . "\n";
        }
    } else {
        echo "No tables found in the database.\n";
    }
} else {
    echo "Error listing tables: " . $conn->error . "\n";
}

// Check for packages table
echo "\nChecking packages table: ";
$tableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
if ($tableCheck->num_rows == 0) {
    echo "Not found, creating it...\n";
    // Create packages table based on packages_table.sql
    $result = $conn->query("
        CREATE TABLE IF NOT EXISTS `packages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reseller_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `type` ENUM('hotspot', 'pppoe', 'data-plan') NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `upload_speed` INT NOT NULL,
            `download_speed` INT NOT NULL,
            `duration` VARCHAR(50) NOT NULL,
            `duration_in_minutes` INT NOT NULL,
            `device_limit` INT NOT NULL DEFAULT 1,
            `data_limit` INT NULL,
            `is_enabled` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    if ($result) {
        echo "Packages table created successfully.\n";
        
        // Insert demo packages based on packages_table.sql sample data
        echo "Inserting demo packages...\n";
        $demoPackages = [
            [
                'name' => '1Hr internet',
                'type' => 'hotspot',
                'price' => 10.00,
                'upload_speed' => 2,
                'download_speed' => 2,
                'duration' => '1 Hour',
                'duration_in_minutes' => 60,
                'device_limit' => 1,
                'data_limit' => NULL,
                'is_enabled' => true
            ],
            [
                'name' => '3Hr internet',
                'type' => 'hotspot',
                'price' => 25.00,
                'upload_speed' => 2,
                'download_speed' => 2,
                'duration' => '3 Hours',
                'duration_in_minutes' => 180,
                'device_limit' => 1,
                'data_limit' => NULL,
                'is_enabled' => true
            ],
            [
                'name' => 'A Half day internet',
                'type' => 'hotspot',
                'price' => 50.00,
                'upload_speed' => 2,
                'download_speed' => 2,
                'duration' => '12 Hours',
                'duration_in_minutes' => 720,
                'device_limit' => 1,
                'data_limit' => NULL,
                'is_enabled' => true
            ],
            [
                'name' => '5GB Data Plan',
                'type' => 'data-plan',
                'price' => 500.00,
                'upload_speed' => 3,
                'download_speed' => 3,
                'duration' => '30 Days',
                'duration_in_minutes' => 43200,
                'device_limit' => 1,
                'data_limit' => 5120,
                'is_enabled' => true
            ]
        ];
        
        $insertQuery = "INSERT INTO packages (reseller_id, name, type, price, upload_speed, download_speed, 
                       duration, duration_in_minutes, device_limit, data_limit, is_enabled) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        if ($stmt) {
            foreach ($demoPackages as $package) {
                $stmt->bind_param("issdiisissb", 
                    $resellerId, 
                    $package['name'], 
                    $package['type'], 
                    $package['price'], 
                    $package['upload_speed'],
                    $package['download_speed'],
                    $package['duration'],
                    $package['duration_in_minutes'],
                    $package['device_limit'],
                    $package['data_limit'],
                    $package['is_enabled']
                );
                if ($stmt->execute()) {
                    echo "- Added package: " . $package['name'] . "\n";
                } else {
                    echo "- Failed to add package: " . $package['name'] . " - " . $stmt->error . "\n";
                }
            }
        } else {
            echo "Error preparing statement: " . $conn->error . "\n";
        }
    } else {
        echo "Error creating packages table: " . $conn->error . "\n";
    }
} else {
    echo "Found. Checking for existing packages for reseller ID $resellerId...\n";
    $result = $conn->query("SELECT id, name, type, price, duration FROM packages WHERE reseller_id = $resellerId");
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "- ID: " . $row['id'] . ", Name: " . $row['name'] . ", Type: " . $row['type'] . ", Price: " . $row['price'] . ", Duration: " . $row['duration'] . "\n";
            }
        } else {
            echo "No packages found for this reseller. Consider adding some packages.\n";
        }
    } else {
        echo "Error checking packages: " . $conn->error . "\n";
    }
}

// Check for hotspots table
echo "\nChecking hotspots table: ";
$tableCheck = $conn->query("SHOW TABLES LIKE 'hotspots'");
if ($tableCheck->num_rows == 0) {
    echo "Not found, creating it...\n";
    // Create hotspots table
    $result = $conn->query("
        CREATE TABLE IF NOT EXISTS `hotspots` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reseller_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `router_ip` VARCHAR(50) NOT NULL,
            `router_username` VARCHAR(50) NOT NULL,
            `router_password` VARCHAR(255) NOT NULL,
            `api_port` INT DEFAULT 8728,
            `location` VARCHAR(255),
            `is_active` BOOLEAN DEFAULT TRUE,
            `status` ENUM('online', 'offline') DEFAULT 'offline',
            `last_checked` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    if ($result) {
        echo "Hotspots table created successfully.\n";
        
        // Insert demo routers
        echo "Inserting demo routers...\n";
        $demoRouters = [
            [
                'name' => 'Main Office Router',
                'router_ip' => '192.168.1.1',
                'router_username' => 'admin',
                'router_password' => 'password',
                'api_port' => 80,
                'location' => 'Main Office',
                'is_active' => true
            ],
            [
                'name' => 'Branch Office Router',
                'router_ip' => '192.168.2.1',
                'router_username' => 'admin',
                'router_password' => 'password',
                'api_port' => 80,
                'location' => 'Branch Office',
                'is_active' => true
            ]
        ];
        
        $insertQuery = "INSERT INTO hotspots (reseller_id, name, router_ip, router_username, router_password, api_port, location, is_active) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
        if ($stmt) {
            foreach ($demoRouters as $router) {
                $isActive = true;
                $stmt->bind_param("issssiib", 
                    $resellerId, 
                    $router['name'], 
                    $router['router_ip'], 
                    $router['router_username'], 
                    $router['router_password'],
                    $router['api_port'],
                    $router['location'],
                    $isActive
                );
                if ($stmt->execute()) {
                    echo "- Added router: " . $router['name'] . "\n";
                } else {
                    echo "- Failed to add router: " . $router['name'] . " - " . $stmt->error . "\n";
                }
            }
        } else {
            echo "Error preparing statement: " . $conn->error . "\n";
        }
    } else {
        echo "Error creating hotspots table: " . $conn->error . "\n";
    }
} else {
    echo "Found. Checking for existing routers for reseller ID $resellerId...\n";
    $result = $conn->query("SELECT id, name, router_ip, router_username FROM hotspots WHERE reseller_id = $resellerId");
    if ($result) {
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "- ID: " . $row['id'] . ", Name: " . $row['name'] . ", IP: " . $row['router_ip'] . "\n";
            }
        } else {
            echo "No routers found for this reseller. Consider adding some routers.\n";
        }
    } else {
        echo "Error checking routers: " . $conn->error . "\n";
    }
}

// Check for vouchers table
echo "\nChecking vouchers table: ";
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck->num_rows == 0) {
    echo "Not found, creating it...\n";
    // Create vouchers table
    $result = $conn->query("
        CREATE TABLE IF NOT EXISTS `vouchers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `code` varchar(20) NOT NULL,
            `username` varchar(50) DEFAULT NULL,
            `password` varchar(50) DEFAULT NULL,
            `package_id` int(11) NOT NULL,
            `reseller_id` int(11) NOT NULL,
            `customer_phone` varchar(20) DEFAULT 'admin',
            `status` enum('active','used','expired') NOT NULL DEFAULT 'active',
            `used_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `code` (`code`)
        )
    ");
    
    if ($result) {
        echo "Vouchers table created successfully.\n";
    } else {
        echo "Error creating vouchers table: " . $conn->error . "\n";
    }
} else {
    echo "Found. Checking structure...\n";
    
    // Check if table has username and password fields
    $result = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'username'");
    if ($result->num_rows == 0) {
        echo "Adding username field...\n";
        $conn->query("ALTER TABLE vouchers ADD COLUMN `username` VARCHAR(50) DEFAULT NULL AFTER `code`");
    }
    
    $result = $conn->query("SHOW COLUMNS FROM vouchers LIKE 'password'");
    if ($result->num_rows == 0) {
        echo "Adding password field...\n";
        $conn->query("ALTER TABLE vouchers ADD COLUMN `password` VARCHAR(50) DEFAULT NULL AFTER `username`");
    }
    
    // Show a sample of existing vouchers
    $result = $conn->query("SELECT id, code, username, password, package_id, status FROM vouchers WHERE reseller_id = $resellerId LIMIT 5");
    if ($result) {
        if ($result->num_rows > 0) {
            echo "Sample vouchers in the database:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: " . $row['id'] . ", Code: " . $row['code'] . 
                     ", Username: " . ($row['username'] ?: 'same as code') . 
                     ", Password: " . ($row['password'] ?: 'same as code') . 
                     ", Package: " . $row['package_id'] . 
                     ", Status: " . $row['status'] . "\n";
            }
        } else {
            echo "No vouchers found for this reseller.\n";
        }
    } else {
        echo "Error checking vouchers: " . $conn->error . "\n";
    }
}

echo "\n=========================\n";
echo "Debug completed successfully.\n";
?> 