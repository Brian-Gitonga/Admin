<?php
// Include database connection
require_once 'vouchers_script/db_connection.php';

// Check if router_id column already exists in vouchers table
$checkColumnQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'vouchers' 
                    AND COLUMN_NAME = 'router_id'";
$result = $conn->query($checkColumnQuery);

if ($result->num_rows === 0) {
    // Column doesn't exist, let's add it
    $alterTableQuery = "ALTER TABLE vouchers ADD COLUMN router_id INT(11) AFTER package_id";
    
    if ($conn->query($alterTableQuery) === TRUE) {
        echo "Successfully added router_id column to vouchers table.<br>";
        
        // Add foreign key constraint to relate to hotspots table
        $addForeignKeyQuery = "ALTER TABLE vouchers ADD CONSTRAINT fk_voucher_router 
                              FOREIGN KEY (router_id) REFERENCES hotspots(id) 
                              ON DELETE SET NULL";
        
        if ($conn->query($addForeignKeyQuery) === TRUE) {
            echo "Successfully added foreign key constraint for router_id.<br>";
        } else {
            echo "Error adding foreign key constraint: " . $conn->error . "<br>";
        }
        
        // Update existing vouchers to associate with default router if available
        $defaultRouterQuery = "UPDATE vouchers v 
                              JOIN (SELECT id, reseller_id FROM hotspots GROUP BY reseller_id) h 
                              ON v.reseller_id = h.reseller_id 
                              SET v.router_id = h.id 
                              WHERE v.router_id IS NULL";
        
        if ($conn->query($defaultRouterQuery) === TRUE) {
            echo "Updated existing vouchers with default router where possible.<br>";
        } else {
            echo "Error updating existing vouchers: " . $conn->error . "<br>";
        }
        
    } else {
        echo "Error adding router_id column: " . $conn->error . "<br>";
    }
} else {
    echo "router_id column already exists in vouchers table.<br>";
}

echo "Done!";
?> 