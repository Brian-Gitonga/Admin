<?php
/**
 * Database Structure Check for API
 * This script checks if all required columns exist for the API to work
 */

header('Content-Type: text/html; charset=utf-8');

try {
    require_once '../connection_dp.php';
    
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }
    
    echo '<html><head><title>Database Structure Check</title>';
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
    </style></head><body>';
    
    echo '<h1>Database Structure Check for API</h1>';
    
    // Check required tables
    $requiredTables = [
        'resellers' => ['api_key'],
        'hotspots' => ['is_active'],
        'packages' => ['is_active'],
        'vouchers' => ['router_id', 'profile', 'validity', 'comment', 'metadata', 'api_created'],
        'api_logs' => []
    ];
    
    foreach ($requiredTables as $tableName => $requiredColumns) {
        echo "<div class='section'>";
        echo "<h2>Table: $tableName</h2>";
        
        // Check if table exists
        $result = $conn->query("SHOW TABLES LIKE '$tableName'");
        if (!$result || $result->num_rows === 0) {
            echo "<div class='error'>❌ Table '$tableName' does not exist!</div>";
            continue;
        }
        
        echo "<div class='success'>✅ Table '$tableName' exists</div>";
        
        // Get all columns in the table
        $result = $conn->query("DESCRIBE $tableName");
        if ($result) {
            echo "<h3>Current Columns:</h3>";
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            $existingColumns = [];
            while ($row = $result->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
                echo "<tr>";
                echo "<td>{$row['Field']}</td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "<td>{$row['Default']}</td>";
                echo "<td>{$row['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check required columns
            if (!empty($requiredColumns)) {
                echo "<h3>Required Columns Check:</h3>";
                foreach ($requiredColumns as $column) {
                    if (in_array($column, $existingColumns)) {
                        echo "<div class='success'>✅ Column '$column' exists</div>";
                    } else {
                        echo "<div class='error'>❌ Column '$column' is missing</div>";
                    }
                }
            }
        }
        
        echo "</div>";
    }
    
    // Check sample data
    echo "<div class='section'>";
    echo "<h2>Sample Data Check</h2>";
    
    // Check resellers with API keys
    $result = $conn->query("SELECT COUNT(*) as count FROM resellers WHERE api_key IS NOT NULL");
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            echo "<div class='success'>✅ Found {$row['count']} reseller(s) with API keys</div>";
        } else {
            echo "<div class='warning'>⚠️ No resellers have API keys yet</div>";
        }
    }
    
    // Check active hotspots
    $hotspotQuery = "SELECT COUNT(*) as count FROM hotspots";
    if (in_array('is_active', $existingColumns ?? [])) {
        $hotspotQuery .= " WHERE is_active = 1";
    }
    
    $result = $conn->query($hotspotQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            echo "<div class='success'>✅ Found {$row['count']} hotspot(s)</div>";
        } else {
            echo "<div class='warning'>⚠️ No hotspots found</div>";
        }
    }
    
    // Check packages
    $packageQuery = "SELECT COUNT(*) as count FROM packages";
    $result = $conn->query("SHOW COLUMNS FROM packages LIKE 'is_active'");
    if ($result && $result->num_rows > 0) {
        $packageQuery .= " WHERE is_active = 1";
    }
    
    $result = $conn->query($packageQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            echo "<div class='success'>✅ Found {$row['count']} package(s)</div>";
        } else {
            echo "<div class='warning'>⚠️ No packages found</div>";
        }
    }
    
    echo "</div>";
    
    // Provide SQL commands to fix missing columns
    echo "<div class='section'>";
    echo "<h2>SQL Commands to Fix Missing Columns</h2>";
    echo "<p>If any columns are missing above, run these SQL commands:</p>";
    echo "<pre>";
    
    $sqlCommands = [
        "-- Add API key to resellers table",
        "ALTER TABLE resellers ADD COLUMN api_key VARCHAR(255) UNIQUE NULL;",
        "",
        "-- Add is_active to hotspots table (if missing)",
        "ALTER TABLE hotspots ADD COLUMN is_active BOOLEAN DEFAULT TRUE;",
        "",
        "-- Add is_active to packages table (if missing)", 
        "ALTER TABLE packages ADD COLUMN is_active BOOLEAN DEFAULT TRUE;",
        "",
        "-- Add API columns to vouchers table",
        "ALTER TABLE vouchers ADD COLUMN router_id INT NULL;",
        "ALTER TABLE vouchers ADD COLUMN profile VARCHAR(100) NULL;",
        "ALTER TABLE vouchers ADD COLUMN validity VARCHAR(50) NULL;",
        "ALTER TABLE vouchers ADD COLUMN comment VARCHAR(255) NULL;",
        "ALTER TABLE vouchers ADD COLUMN metadata JSON NULL;",
        "ALTER TABLE vouchers ADD COLUMN api_created BOOLEAN DEFAULT FALSE;",
        "",
        "-- Create API logs table",
        "CREATE TABLE IF NOT EXISTS api_logs (",
        "    id INT AUTO_INCREMENT PRIMARY KEY,",
        "    reseller_id INT NOT NULL,",
        "    endpoint VARCHAR(100) NOT NULL,",
        "    method VARCHAR(10) NOT NULL,",
        "    request_data TEXT,",
        "    response_data TEXT,",
        "    status_code INT NOT NULL,",
        "    ip_address VARCHAR(45),",
        "    user_agent TEXT,",
        "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,",
        "    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE",
        ");",
        "",
        "-- Add foreign key constraint (run after cleaning up invalid router_id values)",
        "UPDATE vouchers SET router_id = NULL WHERE router_id IS NOT NULL AND router_id NOT IN (SELECT id FROM hotspots);",
        "ALTER TABLE vouchers ADD FOREIGN KEY (router_id) REFERENCES hotspots(id) ON DELETE SET NULL;"
    ];
    
    foreach ($sqlCommands as $command) {
        echo htmlspecialchars($command) . "\n";
    }
    
    echo "</pre>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>Next Steps</h2>";
    echo "<ol>";
    echo "<li>Run the SQL commands above for any missing columns</li>";
    echo "<li>Generate an API key from the Router Management Dashboard</li>";
    echo "<li>Make sure you have at least one hotspot and package</li>";
    echo "<li>Test the API again</li>";
    echo "</ol>";
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
