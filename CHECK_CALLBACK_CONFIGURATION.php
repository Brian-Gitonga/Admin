<?php
/**
 * M-Pesa Callback Configuration Checker
 * 
 * This script helps you verify that your M-Pesa callback URL is properly configured
 * and provides recommendations for fixing any issues.
 */

// Start session
session_start();

// Include database connection
require_once 'portal_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in to use this tool.");
}

$reseller_id = $_SESSION['user_id'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Callback Configuration Checker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .status-box {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .status-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .status-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .status-error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .status-info {
            background-color: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .code-block {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .recommendation {
            background-color: #e7f3ff;
            padding: 15px;
            border-left: 4px solid #2196F3;
            margin: 15px 0;
        }
        .check-item {
            margin: 20px 0;
            padding: 15px;
            background-color: #fafafa;
            border-radius: 5px;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 M-Pesa Callback Configuration Checker</h1>
        
        <?php
        // Get M-Pesa settings from the database
        require_once 'mpesa_settings_operations.php';
        $settings = getMpesaSettings($conn, $reseller_id);
        
        $callback_url = $settings['callback_url'] ?? '';
        $environment = $settings['environment'] ?? 'sandbox';
        
        echo "<h2>Current Configuration</h2>";
        echo "<div class='check-item'>";
        echo "<strong>Reseller ID:</strong> " . htmlspecialchars($reseller_id) . "<br>";
        echo "<strong>Environment:</strong> " . htmlspecialchars($environment) . "<br>";
        echo "<strong>Callback URL:</strong> <code>" . htmlspecialchars($callback_url) . "</code>";
        echo "</div>";
        
        // Check 1: Callback URL exists in the codebase
        echo "<h2>Configuration Checks</h2>";
        
        if (empty($callback_url)) {
            echo "<div class='status-box status-error'>";
            echo "<span class='icon'>❌</span><strong>ERROR:</strong> No callback URL configured!";
            echo "</div>";
            echo "<div class='recommendation'>";
            echo "<strong>Recommendation:</strong> You need to configure a callback URL in your M-Pesa settings.";
            echo "</div>";
        } else {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Callback URL is configured";
            echo "</div>";
        }
        
        // Check 2: Localhost detection
        if (strpos($callback_url, 'localhost') !== false || strpos($callback_url, '127.0.0.1') !== false) {
            echo "<div class='status-box status-error'>";
            echo "<span class='icon'>❌</span><strong>ERROR:</strong> Callback URL contains localhost!";
            echo "</div>";
            echo "<div class='recommendation'>";
            echo "<strong>Problem:</strong> M-Pesa cannot reach localhost URLs.<br>";
            echo "<strong>Solution:</strong> Use ngrok for local development:<br>";
            echo "<div class='code-block'>";
            echo "# Install ngrok from https://ngrok.com<br>";
            echo "# Start ngrok tunnel<br>";
            echo "ngrok http 80<br><br>";
            echo "# Copy the HTTPS URL and update your callback URL<br>";
            echo "# Example: https://abc123.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php";
            echo "</div>";
            echo "</div>";
        } else {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Callback URL is not localhost";
            echo "</div>";
        }
        
        // Check 3: HTTPS
        if (strpos($callback_url, 'https://') === 0) {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Callback URL uses HTTPS";
            echo "</div>";
        } elseif (strpos($callback_url, 'http://') === 0) {
            echo "<div class='status-box status-warning'>";
            echo "<span class='icon'>⚠️</span><strong>WARNING:</strong> Callback URL uses HTTP (not HTTPS)";
            echo "</div>";
            echo "<div class='recommendation'>";
            echo "<strong>Recommendation:</strong> M-Pesa requires HTTPS for production. Use ngrok or deploy to a server with SSL.";
            echo "</div>";
        }
        
        // Check 4: Callback file exists
        $callback_file = __DIR__ . '/mpesa_callback.php';
        if (file_exists($callback_file)) {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Callback file exists (mpesa_callback.php)";
            echo "</div>";
        } else {
            echo "<div class='status-box status-error'>";
            echo "<span class='icon'>❌</span><strong>ERROR:</strong> Callback file not found!";
            echo "</div>";
        }
        
        // Check 5: Callback log file
        $log_file = __DIR__ . '/mpesa_callback.log';
        if (file_exists($log_file)) {
            $log_size = filesize($log_file);
            $log_modified = date('Y-m-d H:i:s', filemtime($log_file));
            
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Callback log file exists";
            echo "<br><small>Last modified: $log_modified | Size: " . number_format($log_size) . " bytes</small>";
            echo "</div>";
            
            // Show last few lines of log
            if ($log_size > 0) {
                echo "<div class='check-item'>";
                echo "<strong>Recent Callback Activity:</strong>";
                echo "<div class='code-block'>";
                $lines = file($log_file);
                $last_lines = array_slice($lines, -10);
                foreach ($last_lines as $line) {
                    echo htmlspecialchars($line) . "<br>";
                }
                echo "</div>";
                echo "</div>";
            }
        } else {
            echo "<div class='status-box status-info'>";
            echo "<span class='icon'>ℹ️</span><strong>INFO:</strong> No callback log file yet (will be created when first callback is received)";
            echo "</div>";
        }
        
        // Check 6: Auto process vouchers file. the file that is extracting voucher from DP to the user
        $auto_process_file = __DIR__ . '/auto_process_vouchers.php';
        if (file_exists($auto_process_file)) {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>✅</span><strong>PASS:</strong> Voucher processing file exists";
            echo "</div>";
        } else {
            echo "<div class='status-box status-error'>";
            echo "<span class='icon'>❌</span><strong>ERROR:</strong> Voucher processing file not found!";
            echo "</div>";
        }
        
        // Summary and recommendations
        echo "<h2>Summary</h2>";
        
        $issues = [];
        if (empty($callback_url)) $issues[] = "No callback URL configured";
        if (strpos($callback_url, 'localhost') !== false) $issues[] = "Using localhost URL";
        if (strpos($callback_url, 'http://') === 0) $issues[] = "Not using HTTPS";
        if (!file_exists($callback_file)) $issues[] = "Callback file missing";
        if (!file_exists($auto_process_file)) $issues[] = "Voucher processing file missing";
        
        if (empty($issues)) {
            echo "<div class='status-box status-success'>";
            echo "<span class='icon'>🎉</span><strong>All checks passed!</strong> Your callback configuration looks good.";
            echo "</div>";
            echo "<div class='recommendation'>";
            echo "<strong>Next Steps:</strong><br>";
            echo "1. Test with a real payment to verify callbacks are working<br>";
            echo "2. Monitor the callback log file for activity<br>";
            echo "3. Check transaction status updates automatically";
            echo "</div>";
        } else {
            echo "<div class='status-box status-warning'>";
            echo "<span class='icon'>⚠️</span><strong>Issues Found:</strong><br>";
            foreach ($issues as $issue) {
                echo "• " . htmlspecialchars($issue) . "<br>";
            }
            echo "</div>";
            echo "<div class='recommendation'>";
            echo "<strong>Action Required:</strong><br>";
            echo "1. Fix the issues listed above<br>";
            echo "2. Update your callback URL in Settings → M-Pesa Settings<br>";
            echo "3. Re-run this checker to verify the fixes";
            echo "</div>";
        }
        
        //Procedure on how to test the problem
        echo "<h2>How to Test Callbacks</h2>";
        echo "<div class='check-item'>";
        echo "<strong>Testing Steps:</strong><br>";
        echo "1. Make a test payment through your portal<br>";
        echo "2. Complete the payment on your phone<br>";
        echo "3. Wait 5-10 seconds<br>";
        echo "4. Check the transactions page - status should update automatically<br>";
        echo "5. Check the callback log file for activity<br>";
        echo "6. Verify voucher was generated and SMS was sent";
        echo "</div>";
        
        echo "<div class='check-item'>";
        echo "<strong>If Callbacks Don't Work:</strong><br>";
        echo "1. Verify your callback URL is publicly accessible<br>";
        echo "2. Check firewall settings<br>";
        echo "3. Ensure ngrok tunnel is active (if using ngrok)<br>";
        echo "4. Use the manual 'Check Status' button as a fallback<br>";
        echo "5. Review callback logs for errors";
        echo "</div>";
        ?>
        
        <a href="transations.php" class="btn">← Back to Transactions</a>
        <a href="settings.php" class="btn" style="background-color: #2196F3;">⚙️ Update Settings</a>
    </div>
</body>
</html>

