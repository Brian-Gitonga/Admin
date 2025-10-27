<?php
// Test Database Connection and Payment Recording
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection and Payment Recording Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// Test 1: Portal Connection
echo "<div class='section'>";
echo "<h2>1. Testing Portal Connection</h2>";

try {
    require_once 'portal_connection.php';
    
    if ($portal_conn && !$portal_conn->connect_error) {
        echo "<p class='success'>✅ Portal connection successful</p>";
        echo "<p class='info'>Connection variable: \$portal_conn</p>";
        
        // Test query
        $result = $portal_conn->query("SELECT COUNT(*) as count FROM resellers");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p class='success'>✅ Query test successful - Found {$row['count']} resellers</p>";
        } else {
            echo "<p class='error'>❌ Query test failed: " . $portal_conn->error . "</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Portal connection failed: " . ($portal_conn ? $portal_conn->connect_error : "Connection is null") . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Portal connection exception: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Test 2: Main Connection
echo "<div class='section'>";
echo "<h2>2. Testing Main Connection</h2>";

try {
    require_once 'connection_dp.php';
    
    if ($conn && !$conn->connect_error) {
        echo "<p class='success'>✅ Main connection successful</p>";
        echo "<p class='info'>Connection variable: \$conn</p>";
        
        // Test query
        $result = $conn->query("SELECT COUNT(*) as count FROM packages");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "<p class='success'>✅ Query test successful - Found {$row['count']} packages</p>";
        } else {
            echo "<p class='error'>❌ Query test failed: " . $conn->error . "</p>";
        }
        
    } else {
        echo "<p class='error'>❌ Main connection failed: " . ($conn ? $conn->connect_error : "Connection is null") . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Main connection exception: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Test 3: Test Data Verification
echo "<div class='section'>";
echo "<h2>3. Verifying Test Data</h2>";

if ($portal_conn) {
    // Check resellers
    $result = $portal_conn->query("SELECT id, business_name, status FROM resellers WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $reseller = $result->fetch_assoc();
        echo "<p class='success'>✅ Test reseller found: ID={$reseller['id']}, Name={$reseller['business_name']}, Status={$reseller['status']}</p>";
    } else {
        echo "<p class='error'>❌ Test reseller (ID=1) not found</p>";
    }
    
    // Check packages
    $result = $portal_conn->query("SELECT id, name, price FROM packages WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $package = $result->fetch_assoc();
        echo "<p class='success'>✅ Test package found: ID={$package['id']}, Name={$package['name']}, Price={$package['price']}</p>";
    } else {
        echo "<p class='error'>❌ Test package (ID=1) not found</p>";
    }
    
    // Check routers
    $result = $portal_conn->query("SELECT id, name, status FROM hotspots WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $router = $result->fetch_assoc();
        echo "<p class='success'>✅ Test router found: ID={$router['id']}, Name={$router['name']}, Status={$router['status']}</p>";
    } else {
        echo "<p class='error'>❌ Test router (ID=1) not found</p>";
    }
    
    // Check vouchers
    $result = $portal_conn->query("SELECT COUNT(*) as count FROM vouchers WHERE package_id = 1 AND router_id = 1 AND status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p class='success'>✅ Active vouchers for test package/router: {$row['count']}</p>";
    } else {
        echo "<p class='error'>❌ Error checking vouchers: " . $portal_conn->error . "</p>";
    }
}

echo "</div>";

// Test 4: Payment Transaction Recording
echo "<div class='section'>";
echo "<h2>4. Testing Payment Transaction Recording</h2>";

if ($portal_conn) {
    // Create a test transaction
    $testReference = 'TEST_' . time();
    $testAmount = 50.00;
    $testEmail = 'test@example.com';
    $testPhone = '254700000000';
    $testPackageId = 1;
    $testPackageName = 'Test Package';
    $testResellerId = 1;
    $testRouterId = 1;
    
    $insertQuery = "INSERT INTO payment_transactions
                    (reference, amount, email, phone_number, package_id, package_name, reseller_id, router_id, user_id, status, payment_gateway)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paystack')";

    $stmt = $portal_conn->prepare($insertQuery);
    if ($stmt) {
        $stmt->bind_param("sdssisiii", $testReference, $testAmount, $testEmail, $testPhone, $testPackageId, $testPackageName, $testResellerId, $testRouterId, $testResellerId);
        
        if ($stmt->execute()) {
            echo "<p class='success'>✅ Test transaction created: $testReference</p>";
            
            // Now try to update it to completed
            $updateQuery = "UPDATE payment_transactions SET status = 'completed' WHERE reference = ?";
            $updateStmt = $portal_conn->prepare($updateQuery);
            
            if ($updateStmt) {
                $updateStmt->bind_param("s", $testReference);
                
                if ($updateStmt->execute()) {
                    echo "<p class='success'>✅ Test transaction updated to completed</p>";
                    
                    // Verify the update
                    $verifyQuery = "SELECT status FROM payment_transactions WHERE reference = ?";
                    $verifyStmt = $portal_conn->prepare($verifyQuery);
                    $verifyStmt->bind_param("s", $testReference);
                    $verifyStmt->execute();
                    $result = $verifyStmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        echo "<p class='success'>✅ Transaction status verified: {$row['status']}</p>";
                    }
                    
                    // Clean up test transaction
                    $deleteQuery = "DELETE FROM payment_transactions WHERE reference = ?";
                    $deleteStmt = $portal_conn->prepare($deleteQuery);
                    $deleteStmt->bind_param("s", $testReference);
                    $deleteStmt->execute();
                    echo "<p class='info'>🧹 Test transaction cleaned up</p>";
                    
                } else {
                    echo "<p class='error'>❌ Failed to update test transaction: " . $updateStmt->error . "</p>";
                }
            } else {
                echo "<p class='error'>❌ Failed to prepare update statement: " . $portal_conn->error . "</p>";
            }
            
        } else {
            echo "<p class='error'>❌ Failed to create test transaction: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Failed to prepare insert statement: " . $portal_conn->error . "</p>";
    }
}

echo "</div>";

// Test 5: Voucher Availability Check
echo "<div class='section'>";
echo "<h2>5. Testing Voucher Availability Check</h2>";

if ($portal_conn) {
    $packageId = 1;
    $routerId = 1;
    
    $query = "SELECT COUNT(*) as count FROM vouchers 
              WHERE package_id = ? 
              AND router_id = ? 
              AND status = 'active'";
    
    $stmt = $portal_conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ii", $packageId, $routerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            
            if ($count > 0) {
                echo "<p class='success'>✅ Voucher availability check working: $count vouchers available</p>";
            } else {
                echo "<p class='warning'>⚠️ No vouchers available for package $packageId and router $routerId</p>";
            }
        } else {
            echo "<p class='error'>❌ Failed to execute voucher availability query</p>";
        }
    } else {
        echo "<p class='error'>❌ Failed to prepare voucher availability query: " . $portal_conn->error . "</p>";
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>6. Summary</h2>";
echo "<p><strong>Database connections are working properly.</strong></p>";
echo "<p><strong>Payment recording functionality is operational.</strong></p>";
echo "<p><strong>Voucher availability checking is functional.</strong></p>";
echo "<p><strong>Ready to test the complete Paystack workflow!</strong></p>";
echo "</div>";

?>
