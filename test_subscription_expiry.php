<?php
/**
 * Test script for subscription expiry functionality
 * This script simulates an expired subscription to test the system
 */

// Include database connection
require_once 'connection_dp.php';

// Function to create a test user with an expired subscription
function createTestUserWithExpiredSubscription($conn) {
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Create a test reseller
        $stmt = $conn->prepare("
            INSERT INTO resellers 
            (business_name, full_name, email, phone, password, payment_interval, status, approval_required) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $business_name = "Test Business " . time();
        $full_name = "Test User";
        $email = "test_" . time() . "@example.com";
        $phone = "1234567890";
        $password = password_hash("testpassword", PASSWORD_DEFAULT);
        $payment_interval = "monthly";
        $status = "active";
        $approval_required = 0; // No approval needed for this test
        
        $stmt->bind_param("sssssssi", $business_name, $full_name, $email, $phone, $password, $payment_interval, $status, $approval_required);
        $stmt->execute();
        $reseller_id = $conn->insert_id;
        $stmt->close();
        
        // Create a subscription plan if none exists
        $plan_id = 0;
        $check_plan = $conn->query("SELECT id FROM subscription_plans LIMIT 1");
        if ($check_plan->num_rows > 0) {
            $plan_row = $check_plan->fetch_assoc();
            $plan_id = $plan_row['id'];
        } else {
            $plan_stmt = $conn->prepare("
                INSERT INTO subscription_plans 
                (name, duration_days, price, description, is_active) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $plan_name = "Test Plan";
            $duration = 30;
            $price = 1000;
            $description = "Test plan for subscription expiry testing";
            $is_active = 1;
            
            $plan_stmt->bind_param("sidsi", $plan_name, $duration, $price, $description, $is_active);
            $plan_stmt->execute();
            $plan_id = $conn->insert_id;
            $plan_stmt->close();
        }
        
        // Create an expired subscription
        $sub_stmt = $conn->prepare("
            INSERT INTO reseller_subscriptions 
            (reseller_id, plan_id, start_date, expiry_date, status, last_payment_date, amount_paid) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        // Set dates to be already expired
        $start_date = date('Y-m-d H:i:s', strtotime('-2 months'));
        $expiry_date = date('Y-m-d H:i:s', strtotime('-1 day')); // Expired yesterday
        $status = "active"; // Set as active even though it's expired
        $last_payment_date = date('Y-m-d H:i:s', strtotime('-2 months'));
        $amount_paid = 1000;
        
        $sub_stmt->bind_param("iissssd", $reseller_id, $plan_id, $start_date, $expiry_date, $status, $last_payment_date, $amount_paid);
        $sub_stmt->execute();
        $sub_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'reseller_id' => $reseller_id,
            'email' => $email,
            'password' => 'testpassword'
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to test if the expiry check works
function testExpiryCheck($conn, $reseller_id) {
    try {
        // First check the current status
        $stmt = $conn->prepare("SELECT status FROM resellers WHERE id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $initial_status = null;
        
        if ($row = $result->fetch_assoc()) {
            $initial_status = $row['status'];
        }
        $stmt->close();
        
        // Now run the expiry check script
        require_once 'check_subscription_expiry.php';
        $check_result = checkAndUpdateExpiredSubscriptions();
        
        // Check the status again
        $stmt = $conn->prepare("SELECT status FROM resellers WHERE id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $final_status = null;
        
        if ($row = $result->fetch_assoc()) {
            $final_status = $row['status'];
        }
        $stmt->close();
        
        return [
            'success' => true,
            'initial_status' => $initial_status,
            'final_status' => $final_status,
            'check_result' => $check_result
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Function to clean up test data
function cleanupTestData($conn, $reseller_id) {
    try {
        // Delete the test subscription
        $conn->query("DELETE FROM reseller_subscriptions WHERE reseller_id = $reseller_id");
        
        // Delete the test reseller
        $conn->query("DELETE FROM resellers WHERE id = $reseller_id");
        
        return ['success' => true];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Main test execution
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    // Only proceed if database connection exists
    if (isset($conn) && is_object($conn)) {
        echo "<h1>Subscription Expiry Test</h1>";
        
        echo "<h2>Creating test user with expired subscription...</h2>";
        $create_result = createTestUserWithExpiredSubscription($conn);
        
        if ($create_result['success']) {
            echo "<p>Test user created with email: {$create_result['email']}</p>";
            
            echo "<h2>Testing expiry check...</h2>";
            $test_result = testExpiryCheck($conn, $create_result['reseller_id']);
            
            if ($test_result['success']) {
                echo "<p>Initial status: {$test_result['initial_status']}</p>";
                echo "<p>Final status: {$test_result['final_status']}</p>";
                
                if ($test_result['final_status'] === 'expired') {
                    echo "<p style='color:green;font-weight:bold;'>TEST PASSED: User status correctly changed to expired!</p>";
                } else {
                    echo "<p style='color:red;font-weight:bold;'>TEST FAILED: User status not changed to expired.</p>";
                }
                
                echo "<p>Updated count: {$test_result['check_result']['updated_count']}</p>";
                
                if (!empty($test_result['check_result']['error_message'])) {
                    echo "<p>Error: {$test_result['check_result']['error_message']}</p>";
                }
            } else {
                echo "<p style='color:red;'>Error testing expiry check: {$test_result['error']}</p>";
            }
            
            echo "<h2>Cleaning up test data...</h2>";
            $cleanup_result = cleanupTestData($conn, $create_result['reseller_id']);
            
            if ($cleanup_result['success']) {
                echo "<p>Test data cleaned up successfully.</p>";
            } else {
                echo "<p style='color:red;'>Error cleaning up: {$cleanup_result['error']}</p>";
            }
            
        } else {
            echo "<p style='color:red;'>Error creating test user: {$create_result['error']}</p>";
        }
        
    } else {
        echo "<p style='color:red;'>Error: Database connection not available.</p>";
    }
}

