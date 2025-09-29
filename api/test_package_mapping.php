<?php
/**
 * Test Package Mapping for API
 * This script tests the package mapping logic to ensure API validity values
 * are correctly mapped to existing packages in the database
 */

header('Content-Type: text/html; charset=utf-8');

try {
    require_once '../connection_dp.php';
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }
    
    echo '<html><head><title>Package Mapping Test</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .test-result { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .test-success { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .test-failure { background-color: #f8d7da; border: 1px solid #f5c6cb; }
    </style></head><body>';
    
    echo '<h1>Package Mapping Test for API</h1>';
    
    // Get reseller ID (use first reseller for testing)
    $resellerResult = $conn->query("SELECT id, COALESCE(business_name, full_name) as name FROM resellers LIMIT 1");
    if (!$resellerResult || $resellerResult->num_rows === 0) {
        echo '<div class="error">❌ No resellers found in database</div>';
        echo '</body></html>';
        exit;
    }
    
    $reseller = $resellerResult->fetch_assoc();
    $resellerId = $reseller['id'];
    $resellerName = $reseller['name'];
    
    echo "<div class='info'>Testing with Reseller: $resellerName (ID: $resellerId)</div>";
    
    // Show existing packages
    echo "<div class='section'>";
    echo "<h2>Existing Packages in Database</h2>";
    
    $packagesResult = $conn->query("SELECT id, name, duration, type, price FROM packages WHERE reseller_id = $resellerId ORDER BY id");
    if ($packagesResult && $packagesResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Duration</th><th>Type</th><th>Price</th></tr>";
        
        while ($package = $packagesResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$package['id']}</td>";
            echo "<td>{$package['name']}</td>";
            echo "<td><strong>{$package['duration']}</strong></td>";
            echo "<td>{$package['type']}</td>";
            echo "<td>{$package['price']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No packages found for this reseller</div>";
        echo "<p>Create some packages first, then run this test again.</p>";
        echo "</div></body></html>";
        exit;
    }
    echo "</div>";
    
    // Include the findPackageByValidity function from the API
    function findPackageByValidity($conn, $resellerId, $validity) {
        // Convert validity to duration format that matches the actual database format
        // Based on the sample data from packages_table.sql and demo packages
        $durationMap = [
            '1h' => '1 Hour',
            '2h' => '2 Hours', 
            '3h' => '3 Hours',
            '6h' => '6 Hours',
            '12h' => '12 Hours',
            '1d' => '1 Day',
            '2d' => '2 Days',
            '3d' => '3 Days',
            '5d' => '5 Days',
            '7d' => '7 Days',
            '30d' => '30 Days'
        ];
        
        $duration = $durationMap[$validity] ?? $validity;
        
        // Also try alternative formats that might exist in the database
        $alternativeDurations = [];
        if (isset($durationMap[$validity])) {
            $alternativeDurations[] = $durationMap[$validity];
            
            // Add variations that might exist
            $baseDuration = $durationMap[$validity];
            $alternativeDurations[] = strtolower($baseDuration);
            $alternativeDurations[] = ucfirst(strtolower($baseDuration));
            
            // Handle plural/singular variations
            if (strpos($baseDuration, ' Hours') !== false) {
                $alternativeDurations[] = str_replace(' Hours', ' Hour', $baseDuration);
            } elseif (strpos($baseDuration, ' Hour') !== false) {
                $alternativeDurations[] = str_replace(' Hour', ' Hours', $baseDuration);
            }
            
            if (strpos($baseDuration, ' Days') !== false) {
                $alternativeDurations[] = str_replace(' Days', ' Day', $baseDuration);
            } elseif (strpos($baseDuration, ' Day') !== false) {
                $alternativeDurations[] = str_replace(' Day', ' Days', $baseDuration);
            }
        }
        
        // Remove duplicates
        $alternativeDurations = array_unique($alternativeDurations);
        
        // Check if is_active column exists in packages table
        $checkColumnQuery = "SHOW COLUMNS FROM packages LIKE 'is_active'";
        $columnResult = $conn->query($checkColumnQuery);
        $hasIsActiveColumn = ($columnResult && $columnResult->num_rows > 0);
        
        // Try to find package with exact duration match first
        foreach ($alternativeDurations as $testDuration) {
            if ($hasIsActiveColumn) {
                $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration = ? AND is_active = 1 LIMIT 1";
            } else {
                $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration = ? LIMIT 1";
            }
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param("is", $resellerId, $testDuration);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    return $row;
                }
            }
        }
        
        // If no exact match found, try partial matching (LIKE search)
        foreach ($alternativeDurations as $testDuration) {
            if ($hasIsActiveColumn) {
                $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration LIKE ? AND is_active = 1 LIMIT 1";
            } else {
                $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration LIKE ? LIMIT 1";
            }
            
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $likeDuration = '%' . $testDuration . '%';
                $stmt->bind_param("is", $resellerId, $likeDuration);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    return $row;
                }
            }
        }
        
        return false;
    }
    
    // Test API validity values
    echo "<div class='section'>";
    echo "<h2>Package Mapping Test Results</h2>";
    
    $testValidities = ['1h', '2h', '3h', '6h', '12h', '1d', '2d', '3d', '5d', '7d', '30d'];
    
    foreach ($testValidities as $validity) {
        echo "<div class='test-result ";
        
        $package = findPackageByValidity($conn, $resellerId, $validity);
        
        if ($package) {
            echo "test-success'>";
            echo "<strong>✅ $validity</strong> → Found Package: <strong>{$package['name']}</strong> (ID: {$package['id']}, Duration: '{$package['duration']}')";
        } else {
            echo "test-failure'>";
            echo "<strong>❌ $validity</strong> → No matching package found";
        }
        
        echo "</div>";
    }
    
    echo "</div>";
    
    // Show mapping details
    echo "<div class='section'>";
    echo "<h2>Mapping Details</h2>";
    echo "<p>The API uses this mapping logic:</p>";
    echo "<ol>";
    echo "<li>Convert API validity (1h, 1d, etc.) to database duration format</li>";
    echo "<li>Try exact matches with variations (plural/singular, case variations)</li>";
    echo "<li>Try partial matches using LIKE search</li>";
    echo "<li>Fall back to any available package if no match found</li>";
    echo "</ol>";
    
    echo "<h3>Duration Mapping:</h3>";
    echo "<table>";
    echo "<tr><th>API Input</th><th>Database Format</th></tr>";
    $durationMap = [
        '1h' => '1 Hour',
        '2h' => '2 Hours', 
        '3h' => '3 Hours',
        '6h' => '6 Hours',
        '12h' => '12 Hours',
        '1d' => '1 Day',
        '2d' => '2 Days',
        '3d' => '3 Days',
        '5d' => '5 Days',
        '7d' => '7 Days',
        '30d' => '30 Days'
    ];
    
    foreach ($durationMap as $api => $db) {
        echo "<tr><td>$api</td><td>$db</td></tr>";
    }
    echo "</table>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>Recommendations</h2>";
    echo "<ul>";
    echo "<li>If any tests failed (❌), create packages with matching durations</li>";
    echo "<li>Ensure your package durations match the expected format (e.g., '1 Hour', '3 Hours', '1 Day')</li>";
    echo "<li>Test the API again after creating missing packages</li>";
    echo "<li>Check that vouchers imported via API now show correct package names in Admin/voucher.php</li>";
    echo "</ul>";
    echo "</div>";
    
    echo '</body></html>';
    
} catch (Exception $e) {
    echo '<html><body>';
    echo '<h1>Database Connection Error</h1>';
    echo '<div style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<p>Make sure your database connection is working and try again.</p>';
    echo '</body></html>';
}
?>
