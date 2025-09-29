<?php
// Debug file to check database connection and packages table structure
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection settings
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "billing_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h1>Database Connection Successful</h1>";

// Check if 'packages' table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'packages'");
if ($tableCheck->num_rows == 0) {
    die("<h2>Error: 'packages' table does not exist</h2>");
}

echo "<h2>'packages' table exists</h2>";

// Get table structure
$tableStructure = $conn->query("DESCRIBE packages");
echo "<h3>Packages Table Structure:</h3>";
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $tableStructure->fetch_assoc()) {
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

// Get sample packages data
echo "<h3>Sample Packages Data:</h3>";
$sampleQuery = "SELECT * FROM packages LIMIT 5";
$sampleResult = $conn->query($sampleQuery);

if ($sampleResult->num_rows == 0) {
    echo "<p>No packages found in the database</p>";
} else {
    echo "<table border='1'><tr>";
    
    // Create table headers from column names
    $fields = $sampleResult->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>" . $field->name . "</th>";
    }
    echo "</tr>";
    
    // Reset result pointer
    $sampleResult->data_seek(0);
    
    // Output data rows
    while ($row = $sampleResult->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
}

// Check for reseller data
echo "<h3>Reseller Information:</h3>";
$resellersQuery = "SELECT id, full_name, business_name FROM resellers LIMIT 5";
$resellersResult = $conn->query($resellersQuery);

if ($resellersResult === false) {
    echo "<p>Error querying resellers table: " . $conn->error . "</p>";
} else if ($resellersResult->num_rows == 0) {
    echo "<p>No resellers found in the database</p>";
} else {
    echo "<table border='1'><tr><th>ID</th><th>Full Name</th><th>Business Name</th></tr>";
    while ($row = $resellersResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['full_name'] . "</td>";
        echo "<td>" . $row['business_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Close connection
$conn->close();
?> 