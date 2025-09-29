<?php
/**
 * Database Connection Test Script
 * This file tests your database connection and table structure
 * Run this file in your browser to see detailed diagnostics
 */

// For debugging - show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Database Connection Test</h1>";

try {
    // Include database connection
    require_once 'connection_dp.php';
    
    echo "<p style='color:green;'>✓ Database connection successful!</p>";
    
    // Check if resellers table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'resellers'");
    if ($check_table->num_rows > 0) {
        echo "<p style='color:green;'>✓ Resellers table exists!</p>";
        
        // Check table structure
        $result = $conn->query("DESCRIBE resellers");
        echo "<h2>Resellers Table Structure:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $expected_columns = [
            'id', 'full_name', 'email', 'phone', 'password', 
            'payment_interval', 'status', 'approval_required', 
            'created_at', 'last_login', 'approved_at', 'approved_by'
        ];
        $missing_columns = $expected_columns;
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
            
            // Remove found columns from missing list
            if (($key = array_search($row['Field'], $missing_columns)) !== false) {
                unset($missing_columns[$key]);
            }
        }
        echo "</table>";
        
        // Show missing columns
        if (!empty($missing_columns)) {
            echo "<h2>Missing Columns:</h2>";
            echo "<ul style='color:red;'>";
            foreach ($missing_columns as $column) {
                echo "<li>$column</li>";
            }
            echo "</ul>";
            
            echo "<h3>SQL to Add Missing Columns:</h3>";
            echo "<pre>";
            foreach ($missing_columns as $column) {
                switch ($column) {
                    case 'last_login':
                        echo "ALTER TABLE resellers ADD COLUMN last_login DATETIME DEFAULT NULL AFTER created_at;\n";
                        break;
                    // Add cases for other potentially missing columns
                    default:
                        echo "-- Column '$column' needs to be added manually with proper definition\n";
                }
            }
            echo "</pre>";
        } else {
            echo "<p style='color:green;'>✓ All expected columns exist!</p>";
        }
    } else {
        echo "<p style='color:red;'>✗ Resellers table does not exist!</p>";
        
        // Show SQL to create table
        echo "<h3>SQL to Create Resellers Table:</h3>";
        echo "<pre>";
        echo "CREATE TABLE resellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    payment_interval ENUM('weekly', 'monthly') NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'expired') DEFAULT 'pending',
    approval_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    approved_at DATETIME,
    approved_by INT,
    FOREIGN KEY (approved_by) REFERENCES admin(id)
);\n";
        echo "</pre>";
    }
    
    // Test sample query
    echo "<h2>Testing Sample Query:</h2>";
    $test_stmt = $conn->prepare("SELECT id, full_name, email FROM resellers LIMIT 1");
    if ($test_stmt) {
        echo "<p style='color:green;'>✓ Query preparation successful!</p>";
        
        $test_stmt->execute();
        $test_result = $test_stmt->get_result();
        echo "<p>Found {$test_result->num_rows} rows in resellers table</p>";
        
        if ($test_result->num_rows > 0) {
            $row = $test_result->fetch_assoc();
            echo "<p>Sample user: {$row['full_name']} ({$row['email']})</p>";
        }
        
        $test_stmt->close();
    } else {
        echo "<p style='color:red;'>✗ Query preparation failed: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?> 