<?php
// Database connection
require_once 'portal_connection.php';

// Check table structure
function checkTableStructure($conn, $tableName) {
    echo "<h2>Table Structure for: $tableName</h2>";
    
    $result = $conn->query("SHOW CREATE TABLE $tableName");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    } else {
        echo "<p>Error getting table structure: " . $conn->error . "</p>";
    }
    
    echo "<hr>";
    
    // List columns
    echo "<h3>Columns in $tableName:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM $tableName");
    
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Structure Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background-color: #f4f4f4; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 8px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Database Structure Check</h1>
    
    <?php
    if ($portal_conn) {
        // Check payment_transactions table
        checkTableStructure($portal_conn, 'payment_transactions');

        // Check mpesa_credentials table
        checkTableStructure($portal_conn, 'mpesa_credentials');

        // Check recent transactions
        echo "<h2>Recent Payment Transactions:</h2>";
        $result = $portal_conn->query("SELECT * FROM payment_transactions ORDER BY created_at DESC LIMIT 3");
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            $first = true;
            while ($row = $result->fetch_assoc()) {
                if ($first) {
                    echo "<tr>";
                    foreach (array_keys($row) as $key) {
                        echo "<th>$key</th>";
                    }
                    echo "</tr>";
                    $first = false;
                }
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No transactions found or error: " . $portal_conn->error . "</p>";
        }
    } else {
        echo "<p>Error: Database connection failed.</p>";
    }
    ?>
    
</body>
</html>









