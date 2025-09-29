<?php
// Script to update the database schema for Paystack support

// Include database connection
require_once 'connection_dp.php';

// Check connection
if (!is_db_connected()) {
    die("Database connection failed!");
}

// SQL statements from paystack_db_update.sql
$sqlStatements = [
    "ALTER TABLE resellers_mpesa_settings MODIFY COLUMN payment_gateway ENUM('phone', 'paybill', 'till', 'paystack') NOT NULL DEFAULT 'phone'",
    "ALTER TABLE resellers_mpesa_settings ADD COLUMN paystack_secret_key VARCHAR(255) AFTER till_consumer_secret",
    "ALTER TABLE resellers_mpesa_settings ADD COLUMN paystack_public_key VARCHAR(255) AFTER paystack_secret_key",
    "ALTER TABLE resellers_mpesa_settings ADD COLUMN paystack_email VARCHAR(100) AFTER paystack_public_key"
];

// Execute each statement
echo "<h1>Database Update for Paystack Integration</h1>";
echo "<div style='font-family: monospace; background: #f4f4f4; padding: 20px; border-radius: 5px;'>";

foreach ($sqlStatements as $index => $sql) {
    echo "<p>Executing statement " . ($index + 1) . ":</p>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    try {
        // Check if the column already exists before adding it
        if (strpos($sql, 'ADD COLUMN') !== false) {
            $matches = [];
            if (preg_match('/ADD COLUMN\s+(\w+)/', $sql, $matches)) {
                $columnName = $matches[1];
                
                $checkResult = $conn->query("SHOW COLUMNS FROM resellers_mpesa_settings LIKE '$columnName'");
                if ($checkResult->num_rows > 0) {
                    echo "<p style='color: orange;'>Column $columnName already exists. Skipping.</p>";
                    continue;
                }
            }
        }
        
        $result = $conn->query($sql);
        
        if ($result === false) {
            echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
        } else {
            echo "<p style='color: green;'>Success!</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Exception: " . $e->getMessage() . "</p>";
    }
}

echo "</div>";

// Check the updated structure
echo "<h2>Current Table Structure</h2>";
$result = $conn->query("SHOW CREATE TABLE resellers_mpesa_settings");

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
} else {
    echo "<p>Error getting table structure: " . $conn->error . "</p>";
}

// List columns
echo "<h3>Columns in resellers_mpesa_settings:</h3>";
$columns = $conn->query("SHOW COLUMNS FROM resellers_mpesa_settings");

if ($columns && $columns->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>Error getting columns: " . $conn->error . "</p>";
}








