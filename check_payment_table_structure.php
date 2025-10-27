<?php
require_once 'portal_connection.php';

echo "<h2>Payment Transactions Table Structure</h2>";

// Check table structure
$result = $portal_conn->query("DESCRIBE payment_transactions");
if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $nullColor = $row['Null'] === 'NO' ? 'red' : 'green';
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td style='color: $nullColor; font-weight: bold;'>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error: " . $portal_conn->error . "</p>";
}

// Check foreign key constraints
echo "<h2>Foreign Key Constraints</h2>";
$fkResult = $portal_conn->query("
    SELECT 
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE 
        TABLE_SCHEMA = 'billing_system' 
        AND TABLE_NAME = 'payment_transactions' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fkResult && $fkResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Column</th><th>Constraint Name</th><th>References Table</th><th>References Column</th>";
    echo "</tr>";
    
    while ($row = $fkResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['COLUMN_NAME'] . "</strong></td>";
        echo "<td>" . $row['CONSTRAINT_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No foreign key constraints found or error: " . $portal_conn->error . "</p>";
}

// Check available resellers
echo "<h2>Available Resellers</h2>";
$resellerResult = $portal_conn->query("SELECT id, business_name, email, status FROM resellers LIMIT 10");
if ($resellerResult && $resellerResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Business Name</th><th>Email</th><th>Status</th>";
    echo "</tr>";
    
    while ($row = $resellerResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['business_name'] ?: 'N/A') . "</td>";
        echo "<td>" . ($row['email'] ?: 'N/A') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No resellers found or error: " . $portal_conn->error . "</p>";
}

// Sample INSERT statement
echo "<h2>Correct INSERT Statement</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;'>";
echo "INSERT INTO payment_transactions (<br>";
echo "&nbsp;&nbsp;reference, phone_number, amount, package_id, package_name,<br>";
echo "&nbsp;&nbsp;router_id, reseller_id, <strong style='color: red;'>user_id</strong>, status, created_at<br>";
echo ") VALUES (?, ?, ?, ?, ?, ?, ?, <strong style='color: red;'>?</strong>, 'pending', NOW())";
echo "</div>";

echo "<p><strong>Note:</strong> The <code>user_id</code> field is required and must reference a valid reseller ID from the resellers table.</p>";
?>
