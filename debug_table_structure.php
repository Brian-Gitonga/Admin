<?php
/**
 * Debug M-Pesa Table Structure and Fix Issues
 */

require_once 'portal_connection.php';

echo "<h1>🔧 M-Pesa Table Structure Debug</h1>";

// Check mpesa_transactions table structure
echo "<h2>Current mpesa_transactions Table Structure:</h2>";

$structure = $conn->query("DESCRIBE mpesa_transactions");
if ($structure) {
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
        echo "<p style='color: red;'>❌ <strong>router_id</strong> column is MISSING from mpesa_transactions table!</p>";
        echo "<p>This is causing the error: <code>Unknown column 'router_id' in 'field list'</code></p>";
        
        echo "<h3>Fix Options:</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Option 1: Add router_id Column</h4>";
        echo "<button onclick='addRouterIdColumn()' style='background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>Add router_id Column</button>";
        echo "<p><em>This will add the missing router_id column to the table.</em></p>";
        echo "</div>";
        
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Option 2: Remove router_id from Queries</h4>";
        echo "<button onclick='fixQueries()' style='background: #ffc107; color: black; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>Fix Code to Remove router_id</button>";
        echo "<p><em>This will modify the code to not use router_id column.</em></p>";
        echo "</div>";
        
        echo "<div id='fix-result' style='margin-top: 15px;'></div>";
    } else {
        echo "<p style='color: green;'>✅ <strong>router_id</strong> column exists in the table.</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ Could not retrieve table structure: " . $conn->error . "</p>";
}

// Check recent transactions
echo "<h2>Recent M-Pesa Transactions:</h2>";
$data = $conn->query("SELECT * FROM mpesa_transactions ORDER BY created_at DESC LIMIT 5");
if ($data && $data->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>ID</th><th>Checkout Request ID</th><th>Phone</th><th>Amount</th><th>Status</th><th>Created</th>";
    echo "</tr>";
    
    while ($row = $data->fetch_assoc()) {
        $statusColor = $row['status'] === 'completed' ? 'green' : ($row['status'] === 'pending' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td style='font-size: 11px;'>" . substr($row['checkout_request_id'], 0, 20) . "...</td>";
        echo "<td>" . $row['phone_number'] . "</td>";
        echo "<td>KES " . $row['amount'] . "</td>";
        echo "<td style='color: $statusColor; font-weight: bold;'>" . $row['status'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No transactions found.</p>";
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'add_router_id') {
        $alterSQL = "ALTER TABLE mpesa_transactions ADD COLUMN router_id INT(11) DEFAULT NULL AFTER reseller_id";
        if ($conn->query($alterSQL) === TRUE) {
            echo json_encode(['success' => true, 'message' => 'router_id column added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding column: ' . $conn->error]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'fix_queries') {
        // This will be handled by modifying the PHP files
        echo json_encode(['success' => true, 'message' => 'Code will be updated to remove router_id references.']);
        exit;
    }
}

?>

<script>
function addRouterIdColumn() {
    document.getElementById('fix-result').innerHTML = '<p>Adding router_id column...</p>';
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=add_router_id'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('fix-result').innerHTML = 
                '<p style="color: green;">✅ ' + data.message + '</p>' +
                '<p><a href="?" style="color: blue;">Refresh page to verify</a></p>';
        } else {
            document.getElementById('fix-result').innerHTML = 
                '<p style="color: red;">❌ ' + data.message + '</p>';
        }
    })
    .catch(error => {
        document.getElementById('fix-result').innerHTML = 
            '<p style="color: red;">❌ Error: ' + error + '</p>';
    });
}

function fixQueries() {
    document.getElementById('fix-result').innerHTML = '<p>This will require code modifications. Please use the second approach.</p>';
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; }
</style>
