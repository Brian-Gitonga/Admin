<?php
/**
 * Fix Foreign Key Constraint Issue - Remove the problematic constraint
 */

require_once 'portal_connection.php';

echo "<h1>🔧 Foreign Key Constraint Fix</h1>";

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>⚠️ Current Issue:</h3>";
echo "<p>The <code>payment_transactions</code> table has a foreign key constraint on <code>user_id</code> that's causing insertion failures.</p>";
echo "<p><strong>Error:</strong> <code>CONSTRAINT payment_transactions_ibfk_1 FOREIGN KEY (user_id) REFERENCES resellers (id)</code></p>";
echo "</div>";

// Step 1: Check current constraints
echo "<h2>Step 1: Current Foreign Key Constraints</h2>";

$constraintQuery = "
    SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE 
        TABLE_SCHEMA = 'billing_system' 
        AND TABLE_NAME = 'payment_transactions' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
";

$result = $portal_conn->query($constraintQuery);
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'>";
    echo "<th>Constraint Name</th><th>Column</th><th>References Table</th><th>References Column</th>";
    echo "</tr>";
    
    $constraints = [];
    while ($row = $result->fetch_assoc()) {
        $constraints[] = $row;
        echo "<tr>";
        echo "<td>" . $row['CONSTRAINT_NAME'] . "</td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No foreign key constraints found.</p>";
    $constraints = [];
}

// Step 2: Provide fix options
echo "<h2>Step 2: Fix Options</h2>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>✅ Option 1: Remove Foreign Key Constraint (Recommended)</h3>";
echo "<p>This will remove the constraint that's causing the error, allowing payments to work normally.</p>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='remove_constraint'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>🗑️ Remove Foreign Key Constraint</button>";
echo "</form>";
echo "</div>";

echo "<div style='background: #cce5ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h3>🔧 Option 2: Make user_id Nullable</h3>";
echo "<p>This will allow user_id to be NULL, so we don't have to provide it in every INSERT.</p>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='action' value='make_nullable'>";
echo "<button type='submit' style='background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;'>🔧 Make user_id Nullable</button>";
echo "</form>";
echo "</div>";

// Handle form submissions
if ($_POST['action'] ?? '') {
    echo "<h2>Step 3: Executing Fix</h2>";
    
    if ($_POST['action'] === 'remove_constraint') {
        echo "<h3>Removing Foreign Key Constraint...</h3>";
        
        // Drop the foreign key constraint
        $dropConstraintSQL = "ALTER TABLE payment_transactions DROP FOREIGN KEY payment_transactions_ibfk_1";
        
        if ($portal_conn->query($dropConstraintSQL) === TRUE) {
            echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> Foreign key constraint removed successfully.</p>";
            
            // Also drop the user_id column if it's not needed
            echo "<h4>Optional: Remove user_id Column</h4>";
            echo "<form method='post' style='margin: 10px 0;'>";
            echo "<input type='hidden' name='action' value='drop_column'>";
            echo "<button type='submit' style='background: #dc3545; color: white; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer;'>🗑️ Also Remove user_id Column</button>";
            echo "</form>";
            
        } else {
            echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . $portal_conn->error . "</p>";
        }
        
    } elseif ($_POST['action'] === 'make_nullable') {
        echo "<h3>Making user_id Nullable...</h3>";
        
        // First drop the constraint
        $dropConstraintSQL = "ALTER TABLE payment_transactions DROP FOREIGN KEY payment_transactions_ibfk_1";
        $portal_conn->query($dropConstraintSQL); // Ignore errors if constraint doesn't exist
        
        // Modify column to allow NULL
        $modifyColumnSQL = "ALTER TABLE payment_transactions MODIFY COLUMN user_id INT NULL";
        
        if ($portal_conn->query($modifyColumnSQL) === TRUE) {
            echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> user_id column is now nullable.</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . $portal_conn->error . "</p>";
        }
        
    } elseif ($_POST['action'] === 'drop_column') {
        echo "<h3>Removing user_id Column...</h3>";
        
        $dropColumnSQL = "ALTER TABLE payment_transactions DROP COLUMN user_id";
        
        if ($portal_conn->query($dropColumnSQL) === TRUE) {
            echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> user_id column removed completely.</p>";
            echo "<p style='color: blue;'>ℹ️ You can now run your payment tests without any user_id issues!</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . $portal_conn->error . "</p>";
        }
    }
    
    // Refresh constraint info after changes
    echo "<h3>Updated Table Structure:</h3>";
    $descResult = $portal_conn->query("DESCRIBE payment_transactions");
    if ($descResult) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f2f2f2;'>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th>";
        echo "</tr>";
        
        while ($row = $descResult->fetch_assoc()) {
            $nullColor = $row['Null'] === 'NO' ? 'red' : 'green';
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td style='color: $nullColor;'>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

echo "<h2>Step 4: Test After Fix</h2>";
echo "<p>After applying the fix, test your payment workflow:</p>";
echo "<ul>";
echo "<li><a href='test_paystack_callback.php' target='_blank'>🧪 Test Paystack Callback</a></li>";
echo "<li><a href='test_payment_flow.php' target='_blank'>🧪 Test Payment Flow</a></li>";
echo "<li><a href='check_recent_payments.php' target='_blank'>📊 Check Recent Payments</a></li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
h1, h2, h3 { color: #333; }
table { width: 100%; margin: 10px 0; }
th { background-color: #f8f9fa; }
button:hover { opacity: 0.9; }
</style>
