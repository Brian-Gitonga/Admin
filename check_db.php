<?php
// Database connection
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = ''; 
$db_name = 'billing_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Check</h1>";

// Check tables in database
$result = $conn->query("SHOW TABLES");
echo "<h2>Tables in database:</h2>";
echo "<ul>";
while ($row = $result->fetch_row()) {
    echo "<li>{$row[0]}</li>";
}
echo "</ul>";

// Check if hotspots table exists
$result = $conn->query("SHOW TABLES LIKE 'hotspots'");
if ($result->num_rows > 0) {
    echo "<h2>✅ Hotspots table exists</h2>";
    
    // Check structure
    $result = $conn->query("DESCRIBE hotspots");
    echo "<h3>Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
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
    
    // Count rows
    $result = $conn->query("SELECT COUNT(*) as count FROM hotspots");
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    echo "<h3>Total records: $count</h3>";
    
    // Check data
    if ($count > 0) {
        $result = $conn->query("SELECT * FROM hotspots LIMIT 10");
        echo "<h3>Sample Data:</h3>";
        echo "<table border='1' cellpadding='5'>";
        
        // Get field names
        $fields = $result->fetch_fields();
        echo "<tr>";
        foreach ($fields as $field) {
            echo "<th>{$field->name}</th>";
        }
        echo "</tr>";
        
        // Reset result pointer
        $result->data_seek(0);
        
        // Display data rows
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<h3>⚠️ No data in hotspots table! Let's insert sample data</h3>";
        
        // First check if we have a reseller
        $result = $conn->query("SELECT id FROM resellers LIMIT 1");
        $reseller_id = 1; // Default
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $reseller_id = $row['id'];
            echo "Found existing reseller with ID: $reseller_id<br>";
        } else {
            // Create a sample reseller
            $sql = "INSERT INTO resellers (business_name, full_name, email, phone, password, payment_interval, status) 
                    VALUES ('Sample Business', 'Test User', 'test@example.com', '0712345678', 
                    '".password_hash('password123', PASSWORD_DEFAULT)."', 'monthly', 'active')";
            
            if ($conn->query($sql)) {
                $reseller_id = $conn->insert_id;
                echo "Created sample reseller with ID: $reseller_id<br>";
            } else {
                echo "Error creating reseller: " . $conn->error . "<br>";
                // Skip foreign key checks if we can't create a reseller
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            }
        }
        
        // Insert sample data
        $sql = "INSERT INTO hotspots (reseller_id, name, router_ip, router_username, router_password, api_port, is_active, status) VALUES
                ($reseller_id, 'Office Router', '192.168.1.1', 'admin', 'password123', 80, 1, 'online'),
                ($reseller_id, 'Home Router', '192.168.0.1', 'admin', 'admin123', 80, 1, 'offline'),
                ($reseller_id, 'Shop Router', '10.0.0.1', 'mikrotik', 'router123', 8728, 1, 'online')";
        
        if ($conn->query($sql)) {
            echo "✅ Sample data inserted successfully!<br>";
            echo "<strong>Please refresh this page to see the data.</strong>";
        } else {
            echo "❌ Error inserting sample data: " . $conn->error . "<br>";
        }
        
        // Re-enable foreign key checks if we disabled them
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
} else {
    echo "<h2>❌ Hotspots table does not exist!</h2>";
    
    // Create the table
    $sql = "CREATE TABLE hotspots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reseller_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        router_ip VARCHAR(50) NOT NULL,
        router_username VARCHAR(50) NOT NULL,
        router_password VARCHAR(255) NOT NULL,
        api_port INT DEFAULT 80,
        location VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        status ENUM('online', 'offline') DEFAULT 'offline',
        last_checked TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql)) {
        echo "✅ Hotspots table created successfully!<br>";
        echo "<strong>Please refresh this page to continue...</strong>";
    } else {
        echo "❌ Error creating hotspots table: " . $conn->error;
    }
}

$conn->close();
?> 