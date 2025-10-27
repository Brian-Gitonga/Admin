<?php
//africa talking api - atsk_5f691aae1e786fa082d238d9580878270abea15a0af08808689e3e00669baa05d1e6bf74
// Include the portal database connection
require_once 'portal_connection.php';

// Include the M-Pesa settings operations
require_once 'mpesa_settings_operations.php';

// Get router ID from URL parameter
$router_id = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;

// Get business name from URL parameter
$businessName = isset($_GET['business']) ? $_GET['business'] : 'Qtro Wifi';

// Get reseller ID from business name
$resellerId = getResellerIdByBusinessName($conn, $businessName);

// If reseller not found or not active, set a default business name
if (!$resellerId) {
    $businessName = 'Qtro Wifi';
    // Try again with default name
    $resellerId = getResellerIdByBusinessName($conn, $businessName);
    
    // If still not found, create a dummy ID for displaying default packages
    if (!$resellerId) {
        $resellerId = 0;
    }
}

// Get router details if router_id is provided
$routerInfo = null;
if ($router_id > 0) {
    $routerQuery = "SELECT * FROM hotspots WHERE id = ? AND reseller_id = ? AND is_active = 1";
    $routerStmt = $conn->prepare($routerQuery);
    $routerStmt->bind_param("ii", $router_id, $resellerId);
    $routerStmt->execute();
    $routerResult = $routerStmt->get_result();
    
    if ($routerResult && $routerResult->num_rows > 0) {
        $routerInfo = $routerResult->fetch_assoc();
    } else {
        // If router not found or doesn't belong to this reseller, reset router_id
        $router_id = 0;
    }
}

// Get reseller info
$resellerInfo = $resellerId ? getResellerInfo($conn, $resellerId) : null;

// Check if the packages table exists before trying to query it
$tableCheckQuery = "SHOW TABLES LIKE 'packages'";
$tableResult = $conn->query($tableCheckQuery);
$packagesTableExists = ($tableResult && $tableResult->num_rows > 0);

// Get packages by type - only if the table exists
if ($packagesTableExists) {
    // Add router_id filter if specified
    if ($router_id > 0) {
        $dailyPackages = getPackagesByTypeAndRouter($conn, $resellerId, 'daily', $router_id);
        $weeklyPackages = getPackagesByTypeAndRouter($conn, $resellerId, 'weekly', $router_id);
        $monthlyPackages = getPackagesByTypeAndRouter($conn, $resellerId, 'monthly', $router_id);
    } else {
        // Use our local function that's more robust than the one in portal_connection.php
        $dailyPackages = getPackagesByTypeLocal($conn, $resellerId, 'daily');
        $weeklyPackages = getPackagesByTypeLocal($conn, $resellerId, 'weekly');
        $monthlyPackages = getPackagesByTypeLocal($conn, $resellerId, 'monthly');
    }
} else {
    // Use the createEmptyResultSet function if the table doesn't exist
    $dailyPackages = createEmptyResultSet();
    $weeklyPackages = createEmptyResultSet();
    $monthlyPackages = createEmptyResultSet();
}

// Local function to get packages by type
function getPackagesByTypeLocal($conn, $resellerId, $packageType) {
    // First check if the packages table exists
    $packagesCheckQuery = "SHOW TABLES LIKE 'packages'";
    $packagesCheckResult = $conn->query($packagesCheckQuery);
    
    if (!$packagesCheckResult || $packagesCheckResult->num_rows == 0) {
        // Packages table doesn't exist
        error_log("Packages table doesn't exist");
        return createEmptyResultSet();
    }
    
    // Check which columns exist in the packages table
    $columnsQuery = "SHOW COLUMNS FROM packages";
    $columnsResult = $conn->query($columnsQuery);
    
    if (!$columnsResult) {
        error_log("Error checking packages columns: " . $conn->error);
        return createEmptyResultSet();
    }
    
    $columns = [];
    while ($column = $columnsResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
    
    // Check if necessary columns exist
    $hasTypeColumn = in_array('type', $columns);
    $hasActiveColumn = in_array('is_active', $columns);
    
    // Build query based on available columns
    $query = "SELECT * FROM packages WHERE reseller_id = ?";
    
    // Add type filter if column exists
    if ($hasTypeColumn) {
        $query .= " AND type = ?";
    }
    
    // Add active filter if column exists
    if ($hasActiveColumn) {
        $query .= " AND is_active = 1";
    }
    
    $query .= " ORDER BY price ASC";
    
    $stmt = $conn->prepare($query);
    
    // Check if prepare statement was successful
    if ($stmt === false) {
        error_log("Error preparing statement: " . $conn->error);
        return createEmptyResultSet();
    }
    
    // Bind parameters based on which columns exist
    if ($hasTypeColumn) {
        $stmt->bind_param("is", $resellerId, $packageType);
    } else {
        $stmt->bind_param("i", $resellerId);
    }
    
    // Execute the query
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get packages by type and router
function getPackagesByTypeAndRouter($conn, $resellerId, $packageType, $routerId) {
    // First check if the packages table exists
    $packagesCheckQuery = "SHOW TABLES LIKE 'packages'";
    $packagesCheckResult = $conn->query($packagesCheckQuery);
    
    if (!$packagesCheckResult || $packagesCheckResult->num_rows == 0) {
        // Packages table doesn't exist
        error_log("Packages table doesn't exist");
        return createEmptyResultSet();
    }
    
    // Check which columns exist in the packages table
    $columnsQuery = "SHOW COLUMNS FROM packages";
    $columnsResult = $conn->query($columnsQuery);
    
    if (!$columnsResult) {
        error_log("Error checking packages columns: " . $conn->error);
        return createEmptyResultSet();
    }
    
    $columns = [];
    while ($column = $columnsResult->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
    
    // Check if necessary columns exist
    $hasTypeColumn = in_array('type', $columns);
    $hasActiveColumn = in_array('is_active', $columns);
    
    // Now check if package_router table exists
    $checkQuery = "SHOW TABLES LIKE 'package_router'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        // If package_router table exists, use it to filter packages
        $query = "SELECT p.* FROM packages p 
                 INNER JOIN package_router pr ON p.id = pr.package_id 
                 WHERE p.reseller_id = ?";
        
        // Add type filter if column exists
        if ($hasTypeColumn) {
            $query .= " AND p.type = ?";
        }
        
        $query .= " AND pr.router_id = ?";
        
        // Add active filter if column exists
        if ($hasActiveColumn) {
            $query .= " AND p.is_active = 1";
        }
        
        $query .= " ORDER BY p.price ASC";
        
        $stmt = $conn->prepare($query);
        
        // Check if prepare statement was successful
        if ($stmt === false) {
            error_log("Error preparing statement: " . $conn->error);
            // Fall back to getting all packages for this reseller
            return getPackagesByType($conn, $resellerId, $packageType);
        }
        
        // Bind parameters based on which columns exist
        if ($hasTypeColumn) {
            $stmt->bind_param("isi", $resellerId, $packageType, $routerId);
        } else {
            $stmt->bind_param("ii", $resellerId, $routerId);
        }
    } else {
        // Otherwise, just get all packages for this reseller
        $query = "SELECT * FROM packages WHERE reseller_id = ?";
        
        // Add type filter if column exists
        if ($hasTypeColumn) {
            $query .= " AND type = ?";
        }
        
        // Add active filter if column exists
        if ($hasActiveColumn) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY price ASC";
        
        $stmt = $conn->prepare($query);
        
        // Check if prepare statement was successful
        if ($stmt === false) {
            error_log("Error preparing statement: " . $conn->error);
            return createEmptyResultSet();
        }
        
        // Bind parameters based on which columns exist
        if ($hasTypeColumn) {
            $stmt->bind_param("is", $resellerId, $packageType);
        } else {
            $stmt->bind_param("i", $resellerId);
        }
    }
    
    // Execute the query
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if we got results
    if ($result->num_rows == 0 && $routerId > 0) {
        // If no packages found for this router, fall back to all packages for this reseller
        error_log("No packages found for router $routerId, falling back to all packages");
        return getPackagesByType($conn, $resellerId, $packageType);
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($routerInfo ? $routerInfo['name'] . ' - ' : ''); ?><?php echo htmlspecialchars($resellerInfo && isset($resellerInfo['business_display_name']) ? $resellerInfo['business_display_name'] : $businessName); ?> - WiFi Hotspot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #f59e0b;
            --accent-green: #10b981;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --text-dark: #1e293b;
            --text-light: #f8fafc;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --bg-overlay: rgba(255, 255, 255, 0.9);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 12px;
            --radius-sm: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f0f0f0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 450px;
            border-radius: 10px;
            background-color: var(--bg-overlay);
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
        }

        .header {
            padding: 30px 20px 20px;
            text-align: center;
        }

        .logo {
            width: 80px;
            height: auto;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 20px;
            padding: 0 15px;
        }

        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            padding: 0 20px;
        }

        .tab {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            background-color: transparent;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .tab.active {
            background-color: var(--secondary-color);
            color: var(--text-light);
        }

        .tab:hover:not(.active) {
            background-color: rgba(245, 158, 11, 0.1);
        }

        .packages {
            padding: 0 20px 20px;
        }

        .package-card {
            background-color: var(--bg-card);
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .package-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .package-info {
            display: flex;
            align-items: center;
        }

        .package-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .dot-daily {
            background-color: var(--accent-green);
        }

        .dot-weekly {
            background-color: var(--accent-blue);
        }

        .dot-monthly {
            background-color: var(--accent-purple);
        }

        .package-details {
            display: flex;
            flex-direction: column;
        }

        .package-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .package-description {
            font-size: 13px;
            color: var(--text-muted);
        }

        .package-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .package-arrow {
            color: var(--text-muted);
            font-size: 16px;
        }

        .footer {
            padding: 20px;
            text-align: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            margin-top: 20px;
            margin-bottom: 20px;
            justify-content: center;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .contact-btn:hover {
            background-color: #2563eb;
        }

        .contact-btn i {
            margin-right: 8px;
        }




        /* Connection Options */
        .connection-options {
            margin-top: 25px;
            padding-top: 20px;
        }

        .connection-title {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .connection-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .connection-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 12px;
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .voucher-btn {
            background-color: var(--accent-green);
        }

        .voucher-btn:hover {
            background-color: #0d9488;
        }

        .mobile-btn {
            background-color: var(--accent-purple);
        }

        .mobile-btn:hover {
            background-color: #7c3aed;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            width: 90%;
            max-width: 400px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-package {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .modal-package-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .modal-package-info {
            flex: 1;
        }

        .modal-package-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .modal-package-description {
            font-size: 13px;
            color: var(--text-muted);
        }

        .modal-package-price {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border-radius: var(--radius-sm);
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-size: 15px;
            transition: border-color 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-help {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .pay-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background-color: var(--secondary-color);
            color: var(--text-light);
            border: none;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .pay-btn:hover {
            background-color: #d97706;
        }

        .modal-footer {
            padding: 15px 20px;
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Voucher Modal */
        .voucher-form-group {
            margin-bottom: 20px;
        }

        /* Mobile Login Modal */
        .mobile-form-group {
            margin-bottom: 20px;
        }

        /* Responsive Styles */
        @media (max-width: 480px) {
            .container {
                max-width: 100%;
            }
            
            .tabs {
                padding: 0 10px;
            }
            
            .tab {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .packages {
                padding: 0 15px 15px;
            }
            
            .package-name {
                font-size: 15px;
            }
            
            .package-price {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://i.postimg.cc/QdN7gX3V/Qtro-Tech-Logo-png.png" alt="WiFi Logo" class="logo">
            <h1 class="title">
                <?php echo htmlspecialchars($resellerInfo && isset($resellerInfo['business_display_name']) ? $resellerInfo['business_display_name'] : $businessName); ?>
                <?php if ($routerInfo): ?>
                    <div style="font-size: 16px; color: #64748b; margin-top: 5px;">
                        <i class="fas fa-router" style="margin-right: 5px;"></i>
                        <?php echo htmlspecialchars($routerInfo['name']); ?>
                    </div>
                <?php endif; ?>
            </h1>
            <p class="subtitle">Choose the best plan for you and connect with us. we are the best in town</p>
        </div>
        
        <div class="tabs">
            <button class="tab active" data-tab="daily">DAILY</button>
            <button class="tab" data-tab="weekly">WEEKLY</button>
            <button class="tab" data-tab="monthly">MONTHLY</button>
        </div>
        
        <!-- Daily Packages Tab Content -->
        <div class="tab-content active" id="daily-tab">
            <div class="packages">
                <?php 
                if ($dailyPackages->num_rows > 0) {
                    while ($package = $dailyPackages->fetch_assoc()) {
                ?>
                <div class="package-card" 
                     data-package="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-price="<?php echo htmlspecialchars($package['price']); ?>" 
                     data-name="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-description="<?php echo htmlspecialchars($package['description']); ?>"
                     data-id="<?php echo htmlspecialchars($package['id']); ?>">
                    <div class="package-info">
                        <div class="package-dot dot-daily"></div>
                        <div class="package-details">
                            <div class="package-name"><?php echo htmlspecialchars($package['name']); ?></div>
                            <div class="package-description"><?php echo htmlspecialchars($package['description']); ?></div>
                        </div>
                    </div>
                    <div class="package-price">
                        <?php if ((float)$package['price'] == 0): ?>
                            <span class="free-trial-badge" style="background-color: #4CAF50; color: white; padding: 5px 10px; border-radius: 12px; font-size: 14px; font-weight: bold;">Free Trial</span>
                        <?php else: ?>
                            KSh <?php echo number_format($package['price'], 0); ?>
                        <?php endif; ?>
                    </div>
                    <div class="package-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                <?php 
                    }
                } else {
                    // No packages found for daily
                ?>
                <div class="package-card">
                    <div class="package-info">
                        <div class="package-dot dot-daily"></div>
                        <div class="package-details">
                            <div class="package-name">No Packages Available</div>
                            <div class="package-description">No daily packages available at this time</div>
                        </div>
                    </div>
                    <div class="package-price"></div>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Weekly Packages Tab Content -->
        <div class="tab-content" id="weekly-tab">
            <div class="packages">
                <?php 
                if ($weeklyPackages->num_rows > 0) {
                    while ($package = $weeklyPackages->fetch_assoc()) {
                ?>
                <div class="package-card" 
                     data-package="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-price="<?php echo htmlspecialchars($package['price']); ?>" 
                     data-name="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-description="<?php echo htmlspecialchars($package['description']); ?>"
                     data-id="<?php echo htmlspecialchars($package['id']); ?>">
                    <div class="package-info">
                        <div class="package-dot dot-weekly"></div>
                        <div class="package-details">
                            <div class="package-name"><?php echo htmlspecialchars($package['name']); ?></div>
                            <div class="package-description"><?php echo htmlspecialchars($package['description']); ?></div>
                        </div>
                    </div>
                    <div class="package-price">
                        <?php if ((float)$package['price'] == 0): ?>
                            <span class="free-trial-badge" style="background-color: #4CAF50; color: white; padding: 5px 10px; border-radius: 12px; font-size: 14px; font-weight: bold;">Free Trial</span>
                        <?php else: ?>
                            KSh <?php echo number_format($package['price'], 0); ?>
                        <?php endif; ?>
                    </div>
                    <div class="package-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                <?php 
                    }
                } else {
                    // No packages found for weekly
                ?>
                <div class="package-card">
                    <div class="package-info">
                        <div class="package-dot dot-weekly"></div>
                        <div class="package-details">
                            <div class="package-name">No Packages Available</div>
                            <div class="package-description">No weekly packages available at this time</div>
                        </div>
                    </div>
                    <div class="package-price"></div>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Monthly Packages Tab Content -->
        <div class="tab-content" id="monthly-tab">
            <div class="packages">
                <?php 
                if ($monthlyPackages->num_rows > 0) {
                    while ($package = $monthlyPackages->fetch_assoc()) {
                ?>
                <div class="package-card" 
                     data-package="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-price="<?php echo htmlspecialchars($package['price']); ?>" 
                     data-name="<?php echo htmlspecialchars($package['name']); ?>" 
                     data-description="<?php echo htmlspecialchars($package['description']); ?>"
                     data-id="<?php echo htmlspecialchars($package['id']); ?>">
                    <div class="package-info">
                        <div class="package-dot dot-monthly"></div>
                        <div class="package-details">
                            <div class="package-name"><?php echo htmlspecialchars($package['name']); ?></div>
                            <div class="package-description"><?php echo htmlspecialchars($package['description']); ?></div>
                        </div>
                    </div>
                    <div class="package-price">
                        <?php if ((float)$package['price'] == 0): ?>
                            <span class="free-trial-badge" style="background-color: #4CAF50; color: white; padding: 5px 10px; border-radius: 12px; font-size: 14px; font-weight: bold;">Free Trial</span>
                        <?php else: ?>
                            KSh <?php echo number_format($package['price'], 0); ?>
                        <?php endif; ?>
                    </div>
                    <div class="package-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                <?php 
                    }
                } else {
                    // No packages found for monthly
                ?>
                <div class="package-card">
                    <div class="package-info">
                        <div class="package-dot dot-monthly"></div>
                        <div class="package-details">
                            <div class="package-name">No Packages Available</div>
                            <div class="package-description">No monthly packages available at this time</div>
                        </div>
                    </div>
                    <div class="package-price"></div>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <div class="footer">
            <!-- Connection Options -->
            <div class="connection-options">
                <p class="connection-title">Already have access?</p>
                <div class="connection-buttons">
                    <button class="connection-btn voucher-btn" id="voucher-btn">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Connect with Voucher</span>
                    </button>
                    <button class="connection-btn mobile-btn" id="mobile-btn">
                        <i class="fas fa-receipt"></i>
                        <span>View Voucher</span>
                    </button>
                </div>
            </div>
            <button class="contact-btn">
                <i class="fas fa-headset"></i>
                <span>Contact Support <a href="tel:<?php echo $resellerInfo ? htmlspecialchars($resellerInfo['phone']) : '+254750059353'; ?>"><?php echo $resellerInfo ? htmlspecialchars($resellerInfo['phone']) : '+254750059353'; ?></a></span>
            </button>


        </div>
    </div>
    
    <!-- Payment Modal -->
    <div class="modal-overlay" id="payment-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Complete Payment</h3>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-package">
                    <div class="modal-package-dot" id="modal-package-dot"></div>
                    <div class="modal-package-info">
                        <div class="modal-package-name" id="modal-package-name">Package Name</div>
                        <div class="modal-package-description" id="modal-package-description">Package Description</div>
                    </div>
                    <div class="modal-package-price" id="modal-package-price">KSh 0</div>
                </div>
                
                <form id="payment-form" method="post">
                    <input type="hidden" name="reseller_id" value="<?php echo $resellerId; ?>">
                    <input type="hidden" name="router_id" value="<?php echo $router_id; ?>">
                    <input type="hidden" name="package_name" id="form-package-name">
                    <input type="hidden" name="package_price" id="form-package-price">
                    <input type="hidden" name="package_id" id="form-package-id">
                    <input type="hidden" name="payment_gateway" id="payment-gateway" value="">
                    
                    <?php
                    // Get reseller's payment settings
                    $mpesaCredentials = getMpesaCredentials($conn, $resellerId);
                    $paymentGateway = isset($mpesaCredentials['payment_gateway']) ? $mpesaCredentials['payment_gateway'] : 'mpesa';
                    ?>
                    
                    <!-- Always ask for phone number regardless of payment gateway -->
                    <div class="form-group">
                        <label for="mpesa-number" class="form-label">Phone Number</label>
                        <input type="tel" id="mpesa-number" name="mpesa_number" class="form-input" placeholder="Enter your phone number (e.g., 07XX XXX XXX)" required>
                        <p class="form-help">Enter your phone number for payment and voucher delivery</p>
                    </div>
                    
                    <!-- Hidden field for email (will be generated from phone number) -->
                    <input type="hidden" id="paystack-email" name="paystack_email" value="">
                    
                    <button type="submit" class="pay-btn">
                        Pay Now
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                By proceeding with payment, you agree to our Terms & Conditions
            </div>
        </div>
    </div>
    
    <!-- Voucher Modal -->
    <div class="modal-overlay" id="voucher-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Connect with Voucher</h3>
                <button class="modal-close" id="voucher-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="voucher-form" method="post" action="connect_voucher.php">
                    <input type="hidden" name="reseller_id" value="<?php echo $resellerId; ?>">
                    <input type="hidden" name="router_id" value="<?php echo $router_id; ?>">
                    <div class="voucher-form-group">
                        <label for="voucher-code" class="form-label">Voucher Code</label>
                        <input type="text" id="voucher-code" name="voucher_code" class="form-input" placeholder="Enter your voucher code" required>
                        <p class="form-help">Enter the voucher code you received</p>
                    </div>
                    
                    <button type="submit" class="pay-btn">
                        Connect
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                By connecting, you agree to our Terms & Conditions
            </div>
        </div>
    </div>
    
    <!-- View Voucher Modal -->
    <div class="modal-overlay" id="mobile-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">View Your Voucher</h3>
                <button class="modal-close" id="mobile-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="mobile-form">
                    <div class="mobile-form-group">
                        <label for="mobile-number" class="form-label">Mobile Number</label>
                        <input type="tel" id="mobile-number" name="mobile_number" class="form-input" placeholder="Enter your mobile number (e.g., 07XX XXX XXX)" required>
                        <p class="form-help">Enter the phone number you used for payment</p>
                    </div>

                    <div class="mobile-form-group" id="voucher-display-area" style="display: none;">
                        <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 8px; padding: 20px; text-align: center;">
                            <p style="color: #15803d; font-size: 14px; margin-bottom: 10px; font-weight: 600;">Your Voucher Code</p>
                            <p id="voucher-code-display" style="font-size: 28px; font-weight: bold; color: #15803d; letter-spacing: 2px; margin: 10px 0;"></p>
                            <p id="voucher-package-display" style="color: #16a34a; font-size: 14px; margin-top: 10px;"></p>
                            <p id="voucher-amount-display" style="color: #16a34a; font-size: 13px; margin-top: 5px;"></p>
                        </div>
                    </div>

                    <div id="voucher-error-area" style="display: none;">
                        <div style="background: #fef2f2; border: 2px solid #ef4444; border-radius: 8px; padding: 15px; text-align: center;">
                            <p id="voucher-error-message" style="color: #dc2626; font-size: 14px;"></p>
                        </div>
                    </div>

                    <button type="submit" class="pay-btn" id="view-voucher-btn">
                        View Voucher
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                Enter your phone number to retrieve your voucher
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Remove active class from all tabs and add to clicked tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Hide all tab contents and show the selected one
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Package selection and modal functionality
        const packageCards = document.querySelectorAll('.package-card');
        const paymentModal = document.getElementById('payment-modal');
        const modalClose = document.getElementById('modal-close');
        const modalPackageDot = document.getElementById('modal-package-dot');
        const modalPackageName = document.getElementById('modal-package-name');
        const modalPackageDescription = document.getElementById('modal-package-description');
        const modalPackagePrice = document.getElementById('modal-package-price');
        const formPackageName = document.getElementById('form-package-name');
        const formPackagePrice = document.getElementById('form-package-price');
        const formPackageId = document.getElementById('form-package-id');
        const paymentForm = document.getElementById('payment-form');
        
        packageCards.forEach(card => {
            card.addEventListener('click', () => {
                // Check if this is a "No Packages Available" card
                if (!card.getAttribute('data-price') || !card.getAttribute('data-id')) {
                    return; // Don't proceed if this is a message card
                }
                
                const packageId = card.getAttribute('data-id');
                const packageName = card.getAttribute('data-name');
                const packageDescription = card.getAttribute('data-description');
                const packagePrice = card.getAttribute('data-price');
                
                // Check if this is a free trial package (price is 0)
                if (packagePrice == 0) {
                    // Show free trial modal instead of payment modal
                    showFreeTrialModal(packageId, packageName, packageDescription);
                    return;
                }
                
                // Set modal content
                modalPackageName.textContent = packageName;
                modalPackageDescription.textContent = packageDescription;
                modalPackagePrice.textContent = `KSh ${packagePrice}`;
                
                // Set form hidden fields
                formPackageName.value = packageName;
                formPackagePrice.value = packagePrice;
                formPackageId.value = packageId;
                
                // Set dot color based on package type
                if (card.closest('#daily-tab')) {
                    modalPackageDot.className = 'modal-package-dot dot-daily';
                } else if (card.closest('#weekly-tab')) {
                    modalPackageDot.className = 'modal-package-dot dot-weekly';
                } else if (card.closest('#monthly-tab')) {
                    modalPackageDot.className = 'modal-package-dot dot-monthly';
                }
                
                // Show modal
                paymentModal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            });
        });
        
        // Function to show free trial modal
        function showFreeTrialModal(packageId, packageName, packageDescription) {
            // Create and show free trial modal dynamically if it doesn't exist
            let freeTrialModal = document.getElementById('free-trial-modal');
            
            if (!freeTrialModal) {
                // Create the modal if it doesn't exist
                freeTrialModal = document.createElement('div');
                freeTrialModal.id = 'free-trial-modal';
                freeTrialModal.className = 'modal-overlay';
                
                // Build the modal HTML
                freeTrialModal.innerHTML = `
                    <div class="modal">
                        <div class="modal-header">
                            <h3 class="modal-title">Free Trial Access</h3>
                            <button class="modal-close" id="free-trial-modal-close">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="modal-package">
                                <div class="modal-package-dot" id="free-trial-package-dot"></div>
                                <div class="modal-package-info">
                                    <div class="modal-package-name" id="free-trial-package-name">${packageName}</div>
                                    <div class="modal-package-description" id="free-trial-package-description">${packageDescription}</div>
                                </div>
                                <div class="modal-package-price">
                                    <span class="free-trial-badge" style="background-color: #4CAF50; color: white; padding: 5px 10px; border-radius: 12px; font-size: 14px; font-weight: bold;">Free Trial</span>
                                </div>
                            </div>
                            
                            <form id="free-trial-form">
                                <input type="hidden" name="reseller_id" value="<?php echo $resellerId; ?>">
                                <input type="hidden" name="router_id" value="<?php echo $router_id; ?>">
                                <input type="hidden" name="package_id" id="free-trial-package-id" value="${packageId}">
                                
                                <div class="form-group">
                                    <label for="free-trial-phone" class="form-label">Phone Number</label>
                                    <input type="tel" id="free-trial-phone" name="phone_number" class="form-input" placeholder="Enter your phone number (e.g., 07XX XXX XXX)" required>
                                    <p class="form-help">Enter your phone number to receive your free trial voucher</p>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label class="form-check" style="display: flex; align-items: center;">
                                        <input type="checkbox" id="terms-agree" name="terms_agree" required style="margin-right: 10px;">
                                        <span>I agree to the terms and conditions</span>
                                    </label>
                                </div>
                                
                                <button type="submit" class="pay-btn" style="background-color: #4CAF50;">
                                    Get Free Trial
                                </button>
                            </form>
                        </div>
                        <div class="modal-footer">
                            Limited to one free trial per phone number. Terms and conditions apply.
                        </div>
                    </div>
                `;
                
                document.body.appendChild(freeTrialModal);
                
                // Add event listener for the close button
                const closeBtn = freeTrialModal.querySelector('#free-trial-modal-close');
                closeBtn.addEventListener('click', () => {
                    freeTrialModal.classList.remove('active');
                    document.body.style.overflow = ''; // Re-enable scrolling
                });
                
                // Close modal when clicking outside
                freeTrialModal.addEventListener('click', (e) => {
                    if (e.target === freeTrialModal) {
                        freeTrialModal.classList.remove('active');
                        document.body.style.overflow = ''; // Re-enable scrolling
                    }
                });
                
                // Add event listener for form submission
                const form = freeTrialModal.querySelector('#free-trial-form');
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Disable button to prevent multiple submissions
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processing...';
                    
                    // Get form data
                    const phoneNumber = document.getElementById('free-trial-phone').value;
                    const termsAgree = document.getElementById('terms-agree').checked;
                    const packageId = document.getElementById('free-trial-package-id').value;
                    
                    // Create form data to send
                    const formData = new FormData();
                    formData.append('phone_number', phoneNumber);
                    formData.append('terms_agree', termsAgree ? '1' : '0');
                    formData.append('package_id', packageId);
                    formData.append('reseller_id', this.querySelector('input[name="reseller_id"]').value);
                    formData.append('router_id', this.querySelector('input[name="router_id"]').value);
                    formData.append('mac_address', '<?php echo isset($_GET["mac"]) ? htmlspecialchars($_GET["mac"]) : ""; ?>');
                    formData.append('ip_address', '<?php echo isset($_SERVER["REMOTE_ADDR"]) ? htmlspecialchars($_SERVER["REMOTE_ADDR"]) : ""; ?>');
                    
                    // Send request to process_free_trial.php
                    fetch('process_free_trial.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Get phone number from form data
                            const phoneNumber = formData.get('phone_number');
                            
                            // Show simplified success message without voucher details
                            form.innerHTML = `
                                <div style="text-align: center; padding: 20px;">
                                    <div style="font-size: 60px; color: #4CAF50; margin-bottom: 20px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3 style="margin-bottom: 15px; color: #1e293b;">Success!</h3>
                                    
                                    <div style="margin: 15px 0; padding: 15px; background-color: #ecfdf5; border-radius: 6px; border: 1px solid #10b981;">
                                        <p style="margin: 0; font-size: 16px; text-align: center;">We have sent a message to ${phoneNumber}</p>
                                    </div>
                                    
                                    <p style="margin-bottom: 20px; color: #64748b;">
                                        Check your phone for WiFi access details.
                                    </p>
                                    
                                    <button type="button" id="close-free-trial-success" class="pay-btn" style="background-color: #4CAF50;">
                                        Connect to WiFi
                                    </button>
                                </div>
                            `;
                            
                            // Add event listener to the close button
                            document.getElementById('close-free-trial-success').addEventListener('click', () => {
                                freeTrialModal.classList.remove('active');
                                document.body.style.overflow = '';
                                window.location.href = 'http://connectwifi.qtro.co.ke'; // Redirect to WiFi login page
                            });
                        } else {
                            // Show error message
                            alert(data.message || 'An error occurred while processing your free trial request.');
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Get Free Trial';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Get Free Trial';
                    });
                });
            } else {
                // Update existing modal content
                document.getElementById('free-trial-package-name').textContent = packageName;
                document.getElementById('free-trial-package-description').textContent = packageDescription;
                document.getElementById('free-trial-package-id').value = packageId;
            }
            
            // Set dot color for the package
            const packageDot = freeTrialModal.querySelector('#free-trial-package-dot');
            const activeTab = document.querySelector('.tab.active');
            const activeTabId = activeTab.getAttribute('data-tab');
            
            if (activeTabId === 'daily') {
                packageDot.className = 'modal-package-dot dot-daily';
            } else if (activeTabId === 'weekly') {
                packageDot.className = 'modal-package-dot dot-weekly';
            } else if (activeTabId === 'monthly') {
                packageDot.className = 'modal-package-dot dot-monthly';
            }
            
            // Show the modal
            freeTrialModal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        // Close payment modal
        modalClose.addEventListener('click', () => {
            paymentModal.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
            paymentForm.reset(); // Reset form fields
        });
        
        // Close modal when clicking outside
        paymentModal.addEventListener('click', (e) => {
            if (e.target === paymentModal) {
                paymentModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                paymentForm.reset(); // Reset form fields
            }
        });
        
        // Form submission
        paymentForm.addEventListener('submit', (e) => {
            e.preventDefault();

            // Show loading message
            const payButton = document.querySelector('.pay-btn');
            payButton.innerHTML = 'Checking availability...';
            payButton.disabled = true;

            // Get form data
            const formData = new FormData(paymentForm);

            // Get package and router IDs for voucher availability check
            const packageId = document.getElementById('form-package-id').value;
            const routerId = formData.get('router_id');

            // Always check for phone number first
            const phoneNumber = document.getElementById('mpesa-number').value;
            if (!phoneNumber) {
                alert('Please enter your phone number');
                payButton.innerHTML = 'Pay Now';
                payButton.disabled = false;
                return;
            }

            // Check voucher availability before proceeding with payment
            fetch('check_voucher_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    package_id: packageId,
                    router_id: routerId
                })
            })
            .then(response => response.json())
            .then(voucherData => {
                if (!voucherData.success || !voucherData.available) {
                    // No vouchers available
                    alert('Sorry, no vouchers are currently available for this package. Please try a different package or contact support.');
                    payButton.innerHTML = 'Pay Now';
                    payButton.disabled = false;
                    return;
                }

                // Vouchers are available, proceed with payment
                payButton.innerHTML = 'Processing payment...';

                // Get the payment gateway
                const paymentGateway = '<?php echo $paymentGateway; ?>';
                formData.append('payment_gateway', paymentGateway);
                document.getElementById('payment-gateway').value = paymentGateway;

                // Determine which payment processor to use
                let processingEndpoint = 'process_payment.php'; // Default M-Pesa

                if (paymentGateway === 'paystack') {
                    processingEndpoint = 'process_paystack_payment.php';

                    // Generate email from phone number for Paystack
                    const sanitizedPhone = phoneNumber.replace(/\D/g, ''); // Remove non-digits
                    const generatedEmail = `${sanitizedPhone}@customer.qtro.co.ke`;
                    document.getElementById('paystack-email').value = generatedEmail;
                }

                console.log('Submitting payment to: ' + processingEndpoint);

                // Submit the form using fetch API
                fetch(processingEndpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                if (data.success) {
                    // Different handling based on payment gateway
                    if (paymentGateway === 'paystack') {
                        // For Paystack, redirect to authorization URL
                        if (data.authorization_url) {
                            console.log('Redirecting to Paystack:', data.authorization_url);
                            window.location.href = data.authorization_url;
                        } else {
                            alert('Payment initialized but no redirect URL received. Please try again.');
                            payButton.innerHTML = 'Try Again';
                            payButton.disabled = false;
                        }
                    } else {
                        // M-Pesa flow - get phone number for display
                        const mpesaNumber = document.getElementById('mpesa-number').value;
                        
                    // Payment initiated successfully
                    payButton.innerHTML = 'Check Your Phone';
                    
                    // Show success message
                    const modalBody = document.querySelector('.modal-body');
                    const formElement = document.getElementById('payment-form');
                    
                    // Save the form to restore it later
                    const formClone = formElement.cloneNode(true);
                    
                    // Replace form with message
                    formElement.innerHTML = `
                        <div style="text-align: center; padding: 20px;">
                            <div style="font-size: 60px; color: #10b981; margin-bottom: 20px;">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h3 style="margin-bottom: 15px; color: #1e293b;">Payment Initiated</h3>
                            <p style="margin-bottom: 20px; color: #64748b;">
                                Please check your phone and enter your M-Pesa PIN to complete the payment.
                            </p>
                            <div style="color: #64748b; font-size: 13px; margin-bottom: 10px;">
                                Transaction Reference: ${data.checkout_request_id || 'N/A'}
                            </div>

                            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 20px; background-color: #f8fafc;">
                                <h4 style="margin-bottom: 10px; color: #334155; font-size: 14px;">
                                    <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                                    How to Get Your Voucher:
                                </h4>
                                <ol style="text-align: left; color: #64748b; font-size: 13px; padding-left: 20px; line-height: 1.8;">
                                    <li>Complete the M-Pesa payment on your phone by entering your PIN.</li>
                                    <li>After payment is complete, click the <strong>"Close"</strong> button below.</li>
                                    <li>Click on <strong>"View Voucher"</strong> button on the portal.</li>
                                    <li>Enter your phone number (${mpesaNumber}) and click <strong>"View Voucher"</strong>.</li>
                                    <li>Your WiFi voucher code will be displayed on the screen.</li>
                                </ol>
                            </div>

                            <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                                <h4 style="margin-bottom: 10px; color: #856404; font-size: 14px;">
                                    <i class="fas fa-exclamation-triangle" style="margin-right: 5px;"></i>
                                    Need Help?
                                </h4>
                                <p style="color: #856404; font-size: 13px; margin: 0;">
                                    If you experience any problems retrieving your voucher, please contact the administrator using the phone number in the footer below.
                                </p>
                            </div>

                            <button type="button" id="close-payment-modal" class="pay-btn" style="background-color: #10b981;">
                                <i class="fas fa-check" style="margin-right: 5px;"></i>
                                Close
                            </button>
                        </div>
                    `;
                    
                    // Add event listener to the close button
                    document.getElementById('close-payment-modal').addEventListener('click', () => {
                        paymentModal.classList.remove('active');
                        document.body.style.overflow = '';

                        // Restore the form for next time
                        formElement.parentNode.replaceChild(formClone, formElement);
                    });
                    }
                } else {
                    // Payment failed
                    payButton.innerHTML = 'Try Again';
                    payButton.disabled = false;

                    // Show error message
                    alert(data.message || 'Payment failed. Please try again.');
                }
                })
                .catch(error => {
                    console.error('Payment Error:', error);
                    payButton.innerHTML = 'Try Again';
                    payButton.disabled = false;
                    alert('An error occurred during payment. Please try again.');
                });
            })
            .catch(error => {
                console.error('Voucher Availability Check Error:', error);
                payButton.innerHTML = 'Try Again';
                payButton.disabled = false;
                alert('Unable to check voucher availability. Please try again.');
            });
        });

        // Voucher Modal Functionality
        const voucherBtn = document.getElementById('voucher-btn');
        const voucherModal = document.getElementById('voucher-modal');
        const voucherModalClose = document.getElementById('voucher-modal-close');
        const voucherForm = document.getElementById('voucher-form');

        voucherBtn.addEventListener('click', () => {
            // Close the current page/tab
            window.close();
        });

        voucherModalClose.addEventListener('click', () => {
            voucherModal.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
            voucherForm.reset(); // Reset form fields
        });

        voucherModal.addEventListener('click', (e) => {
            if (e.target === voucherModal) {
                voucherModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                voucherForm.reset(); // Reset form fields
            }
        });

        voucherForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const voucherCode = document.getElementById('voucher-code').value;

            // Here you would validate the voucher code
            alert(`Connecting with voucher code: ${voucherCode}`);

            // Close the modal after submission
            setTimeout(() => {
                voucherModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                voucherForm.reset(); // Reset form fields
            }, 1000);
        });

        // View Voucher Modal Functionality
        const mobileBtn = document.getElementById('mobile-btn');
        const mobileModal = document.getElementById('mobile-modal');
        const mobileModalClose = document.getElementById('mobile-modal-close');
        const mobileForm = document.getElementById('mobile-form');
        const voucherDisplayArea = document.getElementById('voucher-display-area');
        const voucherErrorArea = document.getElementById('voucher-error-area');
        const voucherCodeDisplay = document.getElementById('voucher-code-display');
        const voucherPackageDisplay = document.getElementById('voucher-package-display');
        const voucherAmountDisplay = document.getElementById('voucher-amount-display');
        const voucherErrorMessage = document.getElementById('voucher-error-message');
        const viewVoucherBtn = document.getElementById('view-voucher-btn');

        mobileBtn.addEventListener('click', () => {
            mobileModal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
            // Reset display areas
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';
        });

        mobileModalClose.addEventListener('click', () => {
            mobileModal.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
            mobileForm.reset(); // Reset form fields
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';
        });

        mobileModal.addEventListener('click', (e) => {
            if (e.target === mobileModal) {
                mobileModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                mobileForm.reset(); // Reset form fields
                voucherDisplayArea.style.display = 'none';
                voucherErrorArea.style.display = 'none';
            }
        });

        mobileForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const mobileNumber = document.getElementById('mobile-number').value;

            // Hide previous results
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';

            // Disable button and show loading state
            viewVoucherBtn.disabled = true;
            viewVoucherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            // Make AJAX request to fetch voucher
            fetch('fetch_update_voucher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'phone_number=' + encodeURIComponent(mobileNumber)
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable button
                viewVoucherBtn.disabled = false;
                viewVoucherBtn.innerHTML = 'View Voucher';

                if (data.success) {
                    // Display voucher
                    voucherCodeDisplay.textContent = data.voucher_code;
                    voucherPackageDisplay.textContent = data.package_name;
                    voucherAmountDisplay.textContent = 'Amount: KSh ' + data.amount;
                    voucherDisplayArea.style.display = 'block';
                    voucherErrorArea.style.display = 'none';
                } else {
                    // Display error
                    voucherErrorMessage.textContent = data.message;
                    voucherErrorArea.style.display = 'block';
                    voucherDisplayArea.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Re-enable button
                viewVoucherBtn.disabled = false;
                viewVoucherBtn.innerHTML = 'View Voucher';

                // Display error
                voucherErrorMessage.textContent = 'An error occurred. Please try again.';
                voucherErrorArea.style.display = 'block';
                voucherDisplayArea.style.display = 'none';
            });
        });

        // Copy to clipboard function
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Use the modern clipboard API
                navigator.clipboard.writeText(text).then(() => {
                    // Show success message
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#3b82f6';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(text);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#3b82f6';
                    }, 2000);
                }
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(textArea);
        }


    </script>
</body>
</html>
                            } else {
                                // Payment not completed or error occurred
                                const errorMessage = result.message || 'Payment not yet confirmed';
                                console.warn('Payment status check returned false:', errorMessage);

                                // Determine if this is a "not completed" message or an actual error
                                const isPaymentPending = errorMessage.toLowerCase().includes('not') ||
                                                        errorMessage.toLowerCase().includes('pending') ||
                                                        errorMessage.toLowerCase().includes('complete');

                                if (isPaymentPending) {
                                    // Payment is still pending
                                    document.getElementById('payment-status').innerHTML = `
                                        <div style="color: #f59e0b; margin: 15px 0;">
                                            <i class="fas fa-clock" style="font-size: 24px;"></i>
                                            <p style="margin-top: 5px; font-weight: bold;">Payment Pending</p>
                                            <p style="font-size: 14px; margin-top: 5px;">${errorMessage}</p>
                                            <p style="font-size: 13px; margin-top: 5px; color: #92400e;">Please check your phone and complete the payment, then try again.</p>
                                        </div>
                                    `;
                                    statusButton.innerHTML = 'Check Again';
                                } else {
                                    // Actual error occurred - show detailed error information
                                    const debugInfo = result.debug_info ? JSON.stringify(result.debug_info, null, 2) : '';

                                    document.getElementById('payment-status').innerHTML = `
                                        <div style="color: #ef4444; margin: 15px 0;">
                                            <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                                            <p style="margin-top: 5px; font-weight: bold;">Payment Verification Failed</p>
                                            <div style="background: #fef2f2; padding: 10px; border-radius: 5px; border: 1px solid #fecaca; margin: 10px 0;">
                                                <p style="font-size: 14px; margin: 0 0 5px 0;"><strong>Error:</strong> ${errorMessage}</p>
                                                ${debugInfo ? '<details style="margin-top: 10px;"><summary style="cursor: pointer; font-size: 12px; color: #6b7280;">Show Technical Details</summary><pre style="font-size: 11px; background: #f9fafb; padding: 8px; border-radius: 4px; margin-top: 5px; white-space: pre-wrap;">' + debugInfo + '</pre></details>' : ''}
                                            </div>
                                            <div style="background: #f3f4f6; padding: 10px; border-radius: 5px; margin: 10px 0;">
                                                <p style="font-size: 13px; margin: 0; color: #374151;"><strong>🔍 Callback Status Check:</strong></p>
                                                <p style="font-size: 12px; margin: 5px 0 0 0; color: #6b7280;">
                                                    • Transaction ID: ${checkoutRequestId.substring(0, 20)}...<br>
                                                    • If payment was completed on your phone, the callback should update the status<br>
                                                    • Check browser console (F12) for detailed technical information
                                                </p>
                                            </div>
                                            <p style="font-size: 13px; margin-top: 5px; color: #6b7280;">
                                                If you completed the payment, please wait a moment and try again.
                                                Contact support if the issue persists.
                                            </p>
                                        </div>
                                    `;
                                    statusButton.innerHTML = 'Try Again';
                                }

                                statusButton.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Payment status check error:', error);

                            // Show detailed error message
                            const errorMessage = error.message || 'Network or server error occurred';

                            document.getElementById('payment-status').innerHTML = `
                                <div style="color: #ef4444; margin: 15px 0;">
                                    <i class="fas fa-times-circle" style="font-size: 24px;"></i>
                                    <p style="margin-top: 5px; font-weight: bold;">Connection Error</p>
                                    <div style="background: #fef2f2; padding: 10px; border-radius: 5px; border: 1px solid #fecaca; margin: 10px 0;">
                                        <p style="font-size: 14px; margin: 0;"><strong>Technical Error:</strong> ${errorMessage}</p>
                                    </div>
                                    <p style="font-size: 13px; margin-top: 5px; color: #6b7280;">
                                        This usually means there's a server issue. Please check:
                                        <br>• Your internet connection
                                        <br>• Try refreshing the page
                                        <br>• Contact support if the problem persists
                                    </p>
                                </div>
                            `;

                            // Re-enable the button
                            statusButton.innerHTML = 'Try Again';
                            statusButton.disabled = false;
                        });
                    });
                    }
                } else {
                    // Payment failed
                    payButton.innerHTML = 'Try Again';
                    payButton.disabled = false;
                    
                    // Show error message
                    alert(data.message || 'Payment failed. Please try again.');
                }
                })
                .catch(error => {
                    console.error('Payment Error:', error);
                    payButton.innerHTML = 'Try Again';
                    payButton.disabled = false;
                    alert('An error occurred during payment. Please try again.');
                });
            })
            .catch(error => {
                console.error('Voucher Availability Check Error:', error);
                payButton.innerHTML = 'Try Again';
                payButton.disabled = false;
                alert('Unable to check voucher availability. Please try again.');
            });
        });
        
        // Voucher Modal Functionality
        const voucherBtn = document.getElementById('voucher-btn');
        const voucherModal = document.getElementById('voucher-modal');
        const voucherModalClose = document.getElementById('voucher-modal-close');
        const voucherForm = document.getElementById('voucher-form');
        
        voucherBtn.addEventListener('click', () => {
            // Close the current page/tab
            window.close();
        });
        
        voucherModalClose.addEventListener('click', () => {
            voucherModal.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
            voucherForm.reset(); // Reset form fields
        });
        
        voucherModal.addEventListener('click', (e) => {
            if (e.target === voucherModal) {
                voucherModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                voucherForm.reset(); // Reset form fields
            }
        });
        
        voucherForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const voucherCode = document.getElementById('voucher-code').value;
            
            // Here you would validate the voucher code
            alert(`Connecting with voucher code: ${voucherCode}`);
            
            // Close the modal after submission
            setTimeout(() => {
                voucherModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                voucherForm.reset(); // Reset form fields
            }, 1000);
        });
        
        // View Voucher Modal Functionality
        const mobileBtn = document.getElementById('mobile-btn');
        const mobileModal = document.getElementById('mobile-modal');
        const mobileModalClose = document.getElementById('mobile-modal-close');
        const mobileForm = document.getElementById('mobile-form');
        const voucherDisplayArea = document.getElementById('voucher-display-area');
        const voucherErrorArea = document.getElementById('voucher-error-area');
        const voucherCodeDisplay = document.getElementById('voucher-code-display');
        const voucherPackageDisplay = document.getElementById('voucher-package-display');
        const voucherAmountDisplay = document.getElementById('voucher-amount-display');
        const voucherErrorMessage = document.getElementById('voucher-error-message');
        const viewVoucherBtn = document.getElementById('view-voucher-btn');

        mobileBtn.addEventListener('click', () => {
            mobileModal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
            // Reset display areas
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';
        });

        mobileModalClose.addEventListener('click', () => {
            mobileModal.classList.remove('active');
            document.body.style.overflow = ''; // Re-enable scrolling
            mobileForm.reset(); // Reset form fields
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';
        });

        mobileModal.addEventListener('click', (e) => {
            if (e.target === mobileModal) {
                mobileModal.classList.remove('active');
                document.body.style.overflow = ''; // Re-enable scrolling
                mobileForm.reset(); // Reset form fields
                voucherDisplayArea.style.display = 'none';
                voucherErrorArea.style.display = 'none';
            }
        });

        mobileForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const mobileNumber = document.getElementById('mobile-number').value;

            // Hide previous results
            voucherDisplayArea.style.display = 'none';
            voucherErrorArea.style.display = 'none';

            // Disable button and show loading state
            viewVoucherBtn.disabled = true;
            viewVoucherBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

            // Make AJAX request to fetch voucher
            fetch('fetch_update_voucher.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'phone_number=' + encodeURIComponent(mobileNumber)
            })
            .then(response => response.json())
            .then(data => {
                // Re-enable button
                viewVoucherBtn.disabled = false;
                viewVoucherBtn.innerHTML = 'View Voucher';

                if (data.success) {
                    // Display voucher
                    voucherCodeDisplay.textContent = data.voucher_code;
                    voucherPackageDisplay.textContent = data.package_name;
                    voucherAmountDisplay.textContent = 'Amount: KSh ' + data.amount;
                    voucherDisplayArea.style.display = 'block';
                    voucherErrorArea.style.display = 'none';
                } else {
                    // Display error
                    voucherErrorMessage.textContent = data.message;
                    voucherErrorArea.style.display = 'block';
                    voucherDisplayArea.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Re-enable button
                viewVoucherBtn.disabled = false;
                viewVoucherBtn.innerHTML = 'View Voucher';

                // Display error
                voucherErrorMessage.textContent = 'An error occurred. Please try again.';
                voucherErrorArea.style.display = 'block';
                voucherDisplayArea.style.display = 'none';
            });
        });

        // Copy to clipboard function
        function copyToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                // Use the modern clipboard API
                navigator.clipboard.writeText(text).then(() => {
                    // Show success message
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#3b82f6';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                    fallbackCopyTextToClipboard(text);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(text);
            }
        }

        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    const button = event.target;
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.backgroundColor = '#10b981';
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.backgroundColor = '#3b82f6';
                    }, 2000);
                }
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(textArea);
        }


    </script>
</body>
</html>