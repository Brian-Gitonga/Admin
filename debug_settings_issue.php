<?php
// Debug script to track down settings save issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'connection_dp.php';

// Check if the database connection is established
if (!is_db_connected()) {
    echo "Database connection failed!";
    exit();
}

// Get database tables
echo "<h1>Database Tables</h1>";
$tables = $conn->query("SHOW TABLES");

if ($tables && $tables->num_rows > 0) {
    echo "<ul>";
    while ($row = $tables->fetch_row()) {
        echo "<li>{$row[0]}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No tables found or error occurred.</p>";
}

// Check resellers_mpesa_settings table
echo "<h2>resellers_mpesa_settings Table Structure</h2>";
$tableCheck = $conn->query("SHOW CREATE TABLE resellers_mpesa_settings");

if ($tableCheck && $tableCheck->num_rows > 0) {
    $row = $tableCheck->fetch_assoc();
    echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
} else {
    echo "<p>Error getting table structure: " . $conn->error . "</p>";
}

// Count parameters in SQL statements
echo "<h2>Parameter Count Analysis</h2>";

// UPDATE statement
$updateSQL = "UPDATE resellers_mpesa_settings SET 
                payment_gateway = ?,
                environment = ?,
                is_active = ?,
                mpesa_phone = ?,
                paybill_number = ?,
                paybill_shortcode = ?,
                paybill_passkey = ?,
                paybill_consumer_key = ?,
                paybill_consumer_secret = ?,
                till_number = ?,
                till_shortcode = ?,
                till_passkey = ?,
                till_consumer_key = ?,
                till_consumer_secret = ?,
                paystack_secret_key = ?,
                paystack_public_key = ?,
                paystack_email = ?,
                callback_url = ?
                WHERE id = ?";

// INSERT statement
$insertSQL = "INSERT INTO resellers_mpesa_settings (
                reseller_id,
                payment_gateway,
                environment,
                is_active,
                mpesa_phone,
                paybill_number,
                paybill_shortcode,
                paybill_passkey,
                paybill_consumer_key,
                paybill_consumer_secret,
                till_number,
                till_shortcode,
                till_passkey,
                till_consumer_key,
                till_consumer_secret,
                paystack_secret_key,
                paystack_public_key,
                paystack_email,
                callback_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

// Count question marks in update and insert statements
$updateParams = substr_count($updateSQL, '?');
$insertParams = substr_count($insertSQL, '?');

echo "<p>UPDATE statement has <strong>{$updateParams}</strong> parameters</p>";
echo "<p>UPDATE bind_param format: <strong>\"ssisssssssssssssssi\"</strong> (" . strlen("ssisssssssssssssssi") . " types)</p>";

echo "<p>INSERT statement has <strong>{$insertParams}</strong> parameters</p>";
echo "<p>INSERT bind_param format: <strong>\"issiissssssssssssss\"</strong> (" . strlen("issiissssssssssssss") . " types)</p>";

// Check if the counts match
if ($updateParams === strlen("ssisssssssssssssssi")) {
    echo "<p style='color:green;'>UPDATE parameter count matches!</p>";
} else {
    echo "<p style='color:red;'>UPDATE parameter count mismatch! SQL has {$updateParams} but binding has " . strlen("ssisssssssssssssssi") . " types</p>";
}

if ($insertParams === strlen("issiissssssssssssss")) {
    echo "<p style='color:green;'>INSERT parameter count matches!</p>";
} else {
    echo "<p style='color:red;'>INSERT parameter count mismatch! SQL has {$insertParams} but binding has " . strlen("issiissssssssssssss") . " types</p>";
}









