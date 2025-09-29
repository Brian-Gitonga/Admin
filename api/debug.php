<?php
/**
 * API Debug Script
 * This script helps debug API issues by checking various components
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>API Debug Information</h1>

    <div class="section">
        <h2>1. File System Check</h2>
        <?php
        $apiFiles = [
            'vouchers.php' => 'Main API endpoint',
            'generate_api_key.php' => 'API key generation',
            'test_api.php' => 'Test script'
        ];
        
        foreach ($apiFiles as $file => $description) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo "<div class='success'>✅ $file - $description (exists)</div>";
            } else {
                echo "<div class='error'>❌ $file - $description (missing)</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>2. Database Connection</h2>
        <?php
        try {
            require_once '../connection_dp.php';
            if (isset($conn) && $conn instanceof mysqli) {
                echo "<div class='success'>✅ Database connection successful</div>";
                echo "<div class='info'>Database: " . $conn->get_server_info() . "</div>";
            } else {
                echo "<div class='error'>❌ Database connection failed - \$conn not set or not mysqli</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Database connection error: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Database Tables Check</h2>
        <?php
        if (isset($conn)) {
            $requiredTables = ['resellers', 'hotspots', 'packages', 'vouchers', 'api_logs'];
            
            foreach ($requiredTables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo "<div class='success'>✅ Table '$table' exists</div>";
                    
                    // Check for API-specific columns
                    if ($table === 'resellers') {
                        $result = $conn->query("SHOW COLUMNS FROM resellers LIKE 'api_key'");
                        if ($result && $result->num_rows > 0) {
                            echo "<div class='success'>  ✅ api_key column exists</div>";
                        } else {
                            echo "<div class='error'>  ❌ api_key column missing</div>";
                        }
                    }
                    
                    if ($table === 'vouchers') {
                        $apiColumns = ['router_id', 'profile', 'validity', 'comment', 'metadata', 'api_created'];
                        foreach ($apiColumns as $column) {
                            $result = $conn->query("SHOW COLUMNS FROM vouchers LIKE '$column'");
                            if ($result && $result->num_rows > 0) {
                                echo "<div class='success'>  ✅ $column column exists</div>";
                            } else {
                                echo "<div class='warning'>  ⚠️ $column column missing (run migration)</div>";
                            }
                        }
                    }
                } else {
                    echo "<div class='error'>❌ Table '$table' missing</div>";
                }
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>4. API Key Check</h2>
        <?php
        if (isset($conn)) {
            $result = $conn->query("SELECT id, COALESCE(business_name, full_name) as name, api_key FROM resellers WHERE api_key IS NOT NULL LIMIT 5");
            if ($result && $result->num_rows > 0) {
                echo "<div class='success'>✅ Found " . $result->num_rows . " reseller(s) with API keys:</div>";
                while ($row = $result->fetch_assoc()) {
                    $maskedKey = substr($row['api_key'], 0, 10) . '...' . substr($row['api_key'], -10);
                    echo "<div class='info'>  • ID: {$row['id']}, Name: {$row['name']}, Key: $maskedKey</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ No resellers with API keys found</div>";
                echo "<div class='info'>Generate an API key from the MikroTik Dashboard</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Router Check</h2>
        <?php
        if (isset($conn)) {
            $result = $conn->query("SELECT id, name, reseller_id, is_active FROM hotspots WHERE is_active = 1 LIMIT 5");
            if ($result && $result->num_rows > 0) {
                echo "<div class='success'>✅ Found " . $result->num_rows . " active router(s):</div>";
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='info'>  • ID: {$row['id']}, Name: '{$row['name']}', Reseller: {$row['reseller_id']}</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ No active routers found</div>";
                echo "<div class='info'>Add routers from the Link Router page</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>6. Package Check</h2>
        <?php
        if (isset($conn)) {
            $result = $conn->query("SELECT id, name, duration, reseller_id, is_active FROM packages WHERE is_active = 1 LIMIT 5");
            if ($result && $result->num_rows > 0) {
                echo "<div class='success'>✅ Found " . $result->num_rows . " active package(s):</div>";
                while ($row = $result->fetch_assoc()) {
                    echo "<div class='info'>  • ID: {$row['id']}, Name: '{$row['name']}', Duration: '{$row['duration']}', Reseller: {$row['reseller_id']}</div>";
                }
            } else {
                echo "<div class='warning'>⚠️ No active packages found</div>";
                echo "<div class='info'>Create packages from the Packages page</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>7. URL Information</h2>
        <?php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $apiPath = str_replace('/debug.php', '/vouchers', $currentPath);
        $fullApiUrl = $protocol . '://' . $host . $apiPath;
        
        echo "<div class='info'>Current URL: " . $protocol . '://' . $host . $currentPath . "</div>";
        echo "<div class='info'>API Endpoint URL: $fullApiUrl</div>";
        echo "<div class='info'>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</div>";
        echo "<div class='info'>Script Path: " . __FILE__ . "</div>";
        ?>
    </div>

    <div class="section">
        <h2>8. Test API Endpoint</h2>
        <?php
        $vouchersFile = __DIR__ . '/vouchers.php';
        if (file_exists($vouchersFile)) {
            echo "<div class='success'>✅ vouchers.php file exists</div>";
            
            // Test if the file is accessible via HTTP
            $testUrl = $protocol . '://' . $host . str_replace('/debug.php', '/vouchers', $currentPath);
            echo "<div class='info'>Test URL: <a href='$testUrl' target='_blank'>$testUrl</a></div>";
            echo "<div class='info'>Try accessing this URL directly - you should get a 405 error (Method not allowed) which means the endpoint is working</div>";
        } else {
            echo "<div class='error'>❌ vouchers.php file missing</div>";
        }
        ?>
    </div>

    <div class="section">
        <h2>9. Recommendations</h2>
        <ul>
            <li>If you see any ❌ errors above, fix those first</li>
            <li>If you see ⚠️ warnings, run the database migration: <code>Admin/api_migration.sql</code></li>
            <li>Make sure you have generated an API key from the Router Management Dashboard</li>
            <li>Make sure you have at least one active router and package</li>
            <li>Test the API endpoint URL directly in your browser (should show 405 error)</li>
            <li>Use the correct router name in your API requests (check section 5 above)</li>
        </ul>
    </div>

</body>
</html>
