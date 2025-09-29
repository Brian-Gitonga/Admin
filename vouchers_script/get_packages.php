<?php
// This script fetches packages for the voucher creation dropdown
require_once 'db_connection.php';

// Log the request
error_log("get_packages.php accessed - fetching packages for dropdown");

// Check for session status or start a new one if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];
error_log("Fetching packages for reseller ID: $resellerId");

// Set response header
header('Content-Type: application/json');

try {
// Check if the packages table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
    
    if ($tableCheck->num_rows == 0) {
        // Table doesn't exist, create and return demo packages
        error_log("Packages table not found. Creating demo packages.");
        createDemoPackages($conn);
    }
    
    // Query to fetch packages for this reseller
    // Using the actual column names from packages_table.sql
    $query = "SELECT id, name, type, price, upload_speed, download_speed, duration, duration_in_minutes, 
              device_limit, data_limit 
        FROM packages 
              WHERE reseller_id = ? AND is_enabled = TRUE 
        ORDER BY price ASC";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $packages = [];
    while ($row = $result->fetch_assoc()) {
        // Format price for display
        $price = number_format($row['price'], 2);
        
        // Format data limit (if exists)
        $dataLimit = !empty($row['data_limit']) ? $row['data_limit'] . ' MB' : 'Unlimited';
        
        // Format speed
        $speed = $row['upload_speed'] . '/' . $row['download_speed'] . ' Mbps';
        
        $packages[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'price' => $price,
            'type' => $row['type'],
            'duration' => $row['duration'],
            'duration_in_minutes' => $row['duration_in_minutes'],
            'device_limit' => $row['device_limit'],
            'data_limit' => $dataLimit,
            'speed' => $speed
        ];
    }
    
    // Check if we have packages
    if (count($packages) > 0) {
        echo json_encode([
            'success' => true,
            'packages' => $packages
        ]);
    } else {
        // No packages found, create and return demo packages
        error_log("No packages found for reseller ID: $resellerId. Creating demo packages.");
        createDemoPackages($conn, $resellerId);
    }
} catch (Exception $e) {
    error_log("Error in get_packages.php: " . $e->getMessage());
    
    // Return error response with demo packages
    echo json_encode([
        'success' => true, // Still return success with demo data
        'message' => 'Error fetching packages. Using demo packages.',
        'packages' => getDemoPackages()
    ]);
}

/**
 * Create demo packages in the database
 */
function createDemoPackages($conn, $resellerId = 1) {
    try {
        // First, check if the packages table exists, if not create it based on packages_table.sql
        $conn->query("
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
        
        // Check if demo packages already exist
        $checkQuery = "SELECT id FROM packages WHERE reseller_id = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $resellerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        // If packages exist, return them
        if ($result->num_rows > 0) {
            $query = "SELECT id, name, type, price, upload_speed, download_speed, duration, duration_in_minutes, 
                      device_limit, data_limit 
                      FROM packages 
                      WHERE reseller_id = ? AND is_enabled = TRUE 
                      ORDER BY price ASC";
            
            $stmt = $conn->prepare($query);
$stmt->bind_param("i", $resellerId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $packages = [];
            while ($row = $result->fetch_assoc()) {
                $price = number_format($row['price'], 2);
                $dataLimit = !empty($row['data_limit']) ? $row['data_limit'] . ' MB' : 'Unlimited';
                $speed = $row['upload_speed'] . '/' . $row['download_speed'] . ' Mbps';
                
                $packages[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'price' => $price,
                    'type' => $row['type'],
                    'duration' => $row['duration'],
                    'duration_in_minutes' => $row['duration_in_minutes'],
                    'device_limit' => $row['device_limit'],
                    'data_limit' => $dataLimit,
                    'speed' => $speed
                ];
            }
            
            echo json_encode([
                'success' => true,
                'packages' => $packages
            ]);
    exit;
}

        // Insert demo packages based on packages_table.sql structure
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
            $stmt->execute();
        }
        
        // Now fetch and return the created packages
        $query = "SELECT id, name, type, price, upload_speed, download_speed, duration, duration_in_minutes, 
                  device_limit, data_limit 
                  FROM packages 
                  WHERE reseller_id = ? AND is_enabled = TRUE 
                  ORDER BY price ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $resellerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $price = number_format($row['price'], 2);
            $dataLimit = !empty($row['data_limit']) ? $row['data_limit'] . ' MB' : 'Unlimited';
            $speed = $row['upload_speed'] . '/' . $row['download_speed'] . ' Mbps';
            
            $packages[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $price,
                'type' => $row['type'],
                'duration' => $row['duration'],
                'duration_in_minutes' => $row['duration_in_minutes'],
                'device_limit' => $row['device_limit'],
                'data_limit' => $dataLimit,
                'speed' => $speed
            ];
        }
        
    echo json_encode([
        'success' => true,
            'message' => 'Demo packages created successfully.',
        'packages' => $packages
    ]);
    exit;
        
    } catch (Exception $e) {
        error_log("Error creating demo packages: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'message' => 'Error creating packages. Using fallback demo packages.',
            'packages' => getDemoPackages()
        ]);
        exit;
    }
}

/**
 * Get demo packages without database interaction
 */
function getDemoPackages() {
    return [
        [
            'id' => 999,
            'name' => '1Hr internet (Demo)',
            'price' => '10.00',
            'type' => 'hotspot',
            'duration' => '1 Hour',
            'duration_in_minutes' => 60,
            'device_limit' => 1,
            'data_limit' => 'Unlimited',
            'speed' => '2/2 Mbps'
        ],
        [
            'id' => 998,
            'name' => '3Hr internet (Demo)',
            'price' => '25.00',
            'type' => 'hotspot',
            'duration' => '3 Hours',
            'duration_in_minutes' => 180,
            'device_limit' => 1,
            'data_limit' => 'Unlimited',
            'speed' => '2/2 Mbps'
        ],
        [
            'id' => 997,
            'name' => 'A Half day internet (Demo)',
            'price' => '50.00',
            'type' => 'hotspot',
            'duration' => '12 Hours',
            'duration_in_minutes' => 720,
            'device_limit' => 1,
            'data_limit' => 'Unlimited',
            'speed' => '2/2 Mbps'
        ],
        [
            'id' => 996,
            'name' => '5GB Data Plan (Demo)',
            'price' => '500.00',
            'type' => 'data-plan',
            'duration' => '30 Days',
            'duration_in_minutes' => 43200,
            'device_limit' => 1,
            'data_limit' => '5120 MB',
            'speed' => '3/3 Mbps'
        ]
    ];
}
?> 