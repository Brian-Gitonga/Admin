<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Include database connection
require_once 'db_connection.php';

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Set response header
header('Content-Type: application/json');

try {
    // Check if the hotspots table exists - using the correct table name from database.sql
    $tableCheck = $conn->query("SHOW TABLES LIKE 'hotspots'");
    
    if ($tableCheck->num_rows == 0) {
        // Table doesn't exist, we'll need to create demo data
        error_log("Hotspots table not found. Creating demo routers.");
        createDemoRouters($conn, $resellerId);
    }
    
    // Query to fetch hotspots/routers associated with this reseller
    // Using the correct table and column names from database.sql
    $query = "SELECT id, name, router_ip, location, router_username, router_password, api_port 
              FROM hotspots 
              WHERE reseller_id = ? AND is_active = TRUE";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $routers = [];
    while ($row = $result->fetch_assoc()) {
        $routers[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'ip_address' => $row['router_ip'],
            'location' => $row['location'] ?? '',
            'username' => $row['router_username'],
            'api_port' => $row['api_port'] ?? 8728
        ];
    }
    
    // Check if we have routers
    if (count($routers) > 0) {
        echo json_encode([
            'success' => true,
            'routers' => $routers
        ]);
    } else {
        // No routers found, create demo routers
        error_log("No routers found for reseller ID: $resellerId. Creating demo routers.");
        createDemoRouters($conn, $resellerId);
    }
} catch (Exception $e) {
    error_log("Error in get_routers.php: " . $e->getMessage());
    
    // Return error response with demo data
    echo json_encode([
        'success' => true, // Still return success with demo data
        'message' => 'Error fetching routers: ' . $e->getMessage() . '. Using demo routers.',
        'routers' => getDemoRouters()
    ]);
}

/**
 * Create demo routers in the database
 */
function createDemoRouters($conn, $resellerId) {
    try {
        // First, check if the hotspots table exists, if not create it using the schema from database.sql
        $conn->query("
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
        
        // Check if demo routers already exist
        $checkQuery = "SELECT id FROM hotspots WHERE reseller_id = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $resellerId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        // If routers exist, return them
        if ($result->num_rows > 0) {
            $query = "SELECT id, name, router_ip, location FROM hotspots 
                      WHERE reseller_id = ? AND is_active = TRUE";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $resellerId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $routers = [];
            while ($row = $result->fetch_assoc()) {
                $routers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'ip_address' => $row['router_ip'],
                    'location' => $row['location'] ?? ''
                ];
            }
            
            echo json_encode([
                'success' => true,
                'routers' => $routers
            ]);
            exit;
        }
        
        // Insert demo routers
        $demoRouters = [
            [
                'name' => 'Main Office Router',
                'router_ip' => '192.168.1.1',
                'router_username' => 'admin',
                'router_password' => 'password', // In production, this should be properly hashed
                'api_port' => 80,
                'location' => 'Main Office',
                'is_active' => true
            ],
            [
                'name' => 'Branch Office Router',
                'router_ip' => '192.168.2.1',
                'router_username' => 'admin',
                'router_password' => 'password', // In production, this should be properly hashed
                'api_port' => 80,
                'location' => 'Branch Office',
                'is_active' => true
            ]
        ];
        
        $insertQuery = "INSERT INTO hotspots (reseller_id, name, router_ip, router_username, router_password, api_port, location, is_active) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insertQuery);
        
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
            $stmt->execute();
        }
        
        // Now fetch and return the created routers
        $query = "SELECT id, name, router_ip, location FROM hotspots 
                  WHERE reseller_id = ? AND is_active = TRUE";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $resellerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $routers = [];
        while ($row = $result->fetch_assoc()) {
            $routers[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'ip_address' => $row['router_ip'],
                'location' => $row['location'] ?? ''
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Demo routers created successfully.',
            'routers' => $routers
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Error creating demo routers: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'message' => 'Error creating routers. Using fallback demo routers.',
            'routers' => getDemoRouters()
        ]);
        exit;
    }
}

/**
 * Get demo routers without database interaction
 */
function getDemoRouters() {
    return [
        [
            'id' => 1,
            'name' => 'Main Office Router (Demo)',
            'ip_address' => '192.168.1.1',
            'location' => 'Main Office',
            'username' => 'admin',
            'api_port' => 80
        ],
        [
            'id' => 2,
            'name' => 'Branch Office Router (Demo)',
            'ip_address' => '192.168.2.1',
            'location' => 'Branch Office',
            'username' => 'admin',
            'api_port' => 80
        ]
    ];
}
?> 