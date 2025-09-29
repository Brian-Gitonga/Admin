<?php
/**
 * Database connection file
 * This file establishes a connection to the MySQL database
 */

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "billing_system";

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }
    
    // Set error reporting mode
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
} catch (Exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    
    // For diagnostics, let's create a variable that can be checked
    $conn_error = $e->getMessage();
    
    // Only show error message if we're in development mode
    if (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1')) {
        echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; background: #ffeeee;'>";
        echo "<h3>Database Connection Error</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check your database configuration in connection_dp.php</p>";
        echo "</div>";
    } else {
        // In production, show a more generic message
        echo "<div style='color: red; padding: 20px; margin: 20px; border: 1px solid red; background: #ffeeee;'>";
        echo "<h3>System Temporarily Unavailable</h3>";
        echo "<p>We're experiencing technical difficulties. Please try again later.</p>";
        echo "</div>";
    }
    
    // Initialize $conn as null so we can check for it
    $conn = null;
}

// Function to check database connection status
function is_db_connected() {
    global $conn;
    return ($conn instanceof mysqli) && !$conn->connect_error;
}
?>