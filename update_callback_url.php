<!DOCTYPE html>
<html>
<head>
    <title>Update M-Pesa Callback URL</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #4CAF50;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #45a049;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .current-url {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
            font-family: monospace;
            word-break: break-all;
        }
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 5px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Update M-Pesa Callback URL</h1>
        
        <div class="instructions">
            <strong>📋 Instructions:</strong>
            <ol>
                <li>Start ngrok: <code>ngrok http 80</code></li>
                <li>Copy the HTTPS forwarding URL (e.g., <code>https://abc123.ngrok-free.app</code>)</li>
                <li>Paste it in the field below (without the path)</li>
                <li>Click "Update Callback URL"</li>
            </ol>
        </div>
        
        <?php
        require_once 'portal_connection.php';
        
        // Display current callback URL
        $currentUrlQuery = "SELECT callback_url FROM mpesa_settings LIMIT 1";
        $currentUrlResult = $conn->query($currentUrlQuery);
        
        if ($currentUrlResult && $currentUrlResult->num_rows > 0) {
            $row = $currentUrlResult->fetch_assoc();
            echo "<div class='current-url'>";
            echo "<strong>Current Callback URL:</strong><br>";
            echo htmlspecialchars($row['callback_url']);
            echo "</div>";
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ngrok_url'])) {
            $ngrokUrl = trim($_POST['ngrok_url']);
            
            // Validate URL
            if (empty($ngrokUrl)) {
                echo "<div class='error'>❌ Please enter an ngrok URL.</div>";
            } elseif (!filter_var($ngrokUrl, FILTER_VALIDATE_URL)) {
                echo "<div class='error'>❌ Invalid URL format. Please enter a valid HTTPS URL.</div>";
            } elseif (strpos($ngrokUrl, 'https://') !== 0) {
                echo "<div class='error'>❌ URL must start with https://</div>";
            } else {
                // Remove trailing slash if present
                $ngrokUrl = rtrim($ngrokUrl, '/');
                
                // Build full callback URL
                $callbackPath = '/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php';
                $fullCallbackUrl = $ngrokUrl . $callbackPath;
                
                // Update database
                $updateQuery = "UPDATE mpesa_settings SET callback_url = ?";
                $stmt = $conn->prepare($updateQuery);
                
                if ($stmt) {
                    $stmt->bind_param("s", $fullCallbackUrl);
                    
                    if ($stmt->execute()) {
                        $affectedRows = $stmt->affected_rows;
                        
                        echo "<div class='success'>";
                        echo "✅ <strong>Success!</strong> Callback URL updated in database.<br>";
                        echo "<strong>Rows updated:</strong> $affectedRows<br>";
                        echo "<strong>New URL:</strong> " . htmlspecialchars($fullCallbackUrl);
                        echo "</div>";
                        
                        echo "<div class='info'>";
                        echo "<strong>⚠️ IMPORTANT:</strong> You must also update the callback URL in the code:<br><br>";
                        echo "<strong>File:</strong> <code>mpesa_settings_operations.php</code><br>";
                        echo "<strong>Lines:</strong> 73 and 218<br><br>";
                        echo "Change the <code>callback_url</code> value to:<br>";
                        echo "<code>" . htmlspecialchars($fullCallbackUrl) . "</code>";
                        echo "</div>";
                    } else {
                        echo "<div class='error'>❌ Failed to update database: " . $stmt->error . "</div>";
                    }
                    
                    $stmt->close();
                } else {
                    echo "<div class='error'>❌ Failed to prepare statement: " . $conn->error . "</div>";
                }
            }
        }
        ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="ngrok_url">ngrok HTTPS URL:</label>
                <input 
                    type="text" 
                    id="ngrok_url" 
                    name="ngrok_url" 
                    placeholder="https://abc123def456.ngrok-free.app"
                    required
                >
                <small style="color: #666; display: block; margin-top: 5px;">
                    Enter only the base URL (without the path). Example: https://abc123.ngrok-free.app
                </small>
            </div>
            
            <button type="submit">Update Callback URL</button>
        </form>
        
        <div class="info" style="margin-top: 30px;">
            <strong>🧪 Test Callback URL:</strong><br>
            After updating, test if the URL is accessible by visiting it in your browser.<br>
            You should see a blank page or an error (this is normal - it means the URL is reachable).
        </div>
        
        <div class="info">
            <strong>📝 Note:</strong><br>
            ngrok free tier URLs change every time you restart ngrok.<br>
            You'll need to update the callback URL each time you restart ngrok.<br>
            For production, use a permanent domain instead of ngrok.
        </div>
    </div>
</body>
</html>

