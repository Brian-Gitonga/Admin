<?php
require_once 'portal_connection.php';

echo "<h1>Vouchers Table Structure</h1>";

// Check if vouchers table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'vouchers'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "<p>✅ Vouchers table exists</p>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE vouchers");
    if ($structure) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>";
        
        $columns = [];
        while ($row = $structure->fetch_assoc()) {
            $columns[] = $row['Field'];
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Available Columns:</h3>";
        echo "<p>" . implode(', ', $columns) . "</p>";
        
        // Check if router_id column exists
        if (!in_array('router_id', $columns)) {
            echo "<p style='color: red;'>❌ <strong>router_id</strong> column is MISSING from vouchers table!</p>";
        } else {
            echo "<p style='color: green;'>✅ <strong>router_id</strong> column exists in vouchers table.</p>";
        }
    }
    
    // Show sample vouchers
    $sampleData = $conn->query("SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 5");
    if ($sampleData && $sampleData->num_rows > 0) {
        echo "<h3>Sample Vouchers:</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>ID</th><th>Code</th><th>Package ID</th><th>Status</th><th>Customer Phone</th><th>Created</th>";
        echo "</tr>";
        
        while ($row = $sampleData->fetch_assoc()) {
            $statusColor = $row['status'] === 'active' ? 'green' : ($row['status'] === 'used' ? 'orange' : 'red');
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['code'] . "</td>";
            echo "<td>" . $row['package_id'] . "</td>";
            echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
            echo "<td>" . ($row['customer_phone'] ?: 'N/A') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No vouchers found in the table.</p>";
    }
    
} else {
    echo "<p>❌ Vouchers table does NOT exist</p>";
}

// Check packages table
echo "<h2>Packages Table</h2>";
$packagesData = $conn->query("SELECT id, name, price, duration FROM packages ORDER BY id");
if ($packagesData && $packagesData->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Name</th><th>Price</th><th>Duration</th>";
    echo "</tr>";
    
    while ($row = $packagesData->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>KES " . $row['price'] . "</td>";
        echo "<td>" . $row['duration'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No packages found.</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
</style>
