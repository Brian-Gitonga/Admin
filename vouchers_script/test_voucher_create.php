<?php
// Test script to debug voucher creation
require_once 'db_connection.php';
require_once 'generate_after_payment.php';

// Set up the test parameters
$packageId = 999; // Using demo package
$resellerId = 1; // Default reseller ID
$count = 1; // Just create one voucher

// Test the voucher creation directly
echo "<h1>Testing Direct Voucher Creation</h1>";

try {
    $voucherCode = createVoucher($conn, $packageId, $resellerId);
    if ($voucherCode) {
        echo "<p>Success! Created voucher: $voucherCode</p>";
    } else {
        echo "<p>Failed to create voucher</p>";
    }
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Test bulk voucher creation
echo "<h1>Testing Bulk Voucher Creation</h1>";

try {
    $result = generateMultipleVouchers($packageId, $resellerId, $count);
    echo "<pre>" . print_r($result, true) . "</pre>";
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Test the database structure
echo "<h1>Database Structure</h1>";

try {
    $tables = [];
    $query = $conn->query("SHOW TABLES");
    while ($row = $query->fetch_array()) {
        $tables[] = $row[0];
    }
    
    echo "<h2>Tables in Database:</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check vouchers table structure
    echo "<h2>Vouchers Table Structure:</h2>";
    $query = $conn->query("DESCRIBE vouchers");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p>Error checking database: " . $e->getMessage() . "</p>";
}
?> 