<?php
/**
 * Simple Payment Test - Bypass complex logic and test core functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'portal_connection.php';

// Set JSON header
header('Content-Type: application/json');

// Log function
function log_simple($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('simple_payment_test.log', "[$timestamp] $message\n", FILE_APPEND);
}

log_simple("=== SIMPLE PAYMENT TEST STARTED ===");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_simple("POST request received");
    log_simple("POST data: " . print_r($_POST, true));
    
    // Get form data with defaults
    $resellerId = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 6;
    $packageName = isset($_POST['package_name']) ? $_POST['package_name'] : 'Test Package';
    $packagePrice = isset($_POST['package_price']) ? $_POST['package_price'] : '100';
    $mpesaNumber = isset($_POST['mpesa_number']) ? $_POST['mpesa_number'] : '';
    $packageId = isset($_POST['package_id']) ? intval($_POST['package_id']) : 15;
    $routerId = isset($_POST['router_id']) ? intval($_POST['router_id']) : 0;
    
    log_simple("Parsed data: reseller=$resellerId, package=$packageName, price=$packagePrice, phone=$mpesaNumber, packageId=$packageId");
    
    // Basic validation
    if (empty($mpesaNumber)) {
        log_simple("ERROR: Missing phone number");
        echo json_encode([
            'success' => false,
            'message' => 'Phone number is required'
        ]);
        exit;
    }
    
    if (empty($packageId)) {
        log_simple("ERROR: Missing package ID");
        echo json_encode([
            'success' => false,
            'message' => 'Package ID is required'
        ]);
        exit;
    }
    
    // Format phone number
    $phone = preg_replace('/[^0-9]/', '', $mpesaNumber);
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    log_simple("Formatted phone: $phone");
    
    // Generate fake checkout request ID for testing
    $checkoutRequestId = 'ws_CO_' . date('dmYHis') . rand(100000, 999999);
    $merchantRequestId = 'mr_' . date('dmYHis') . rand(100000, 999999);
    
    log_simple("Generated IDs: checkout=$checkoutRequestId, merchant=$merchantRequestId");
    
    try {
        // Insert transaction record
        $stmt = $conn->prepare("INSERT INTO mpesa_transactions (checkout_request_id, merchant_request_id, amount, phone_number, package_id, package_name, reseller_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ssdsiis", $checkoutRequestId, $merchantRequestId, $packagePrice, $phone, $packageId, $packageName, $resellerId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        log_simple("Transaction inserted successfully");
        
        // Simulate successful M-Pesa response
        echo json_encode([
            'success' => true,
            'message' => 'Payment initiated successfully. Please complete payment on your phone.',
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'phone_number' => $phone,
            'amount' => $packagePrice
        ]);
        
        log_simple("Success response sent");
        
    } catch (Exception $e) {
        log_simple("ERROR: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
} else {
    // GET request - show test form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Simple Payment Test</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
            .form-group { margin: 15px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input, select { width: 300px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
            button:hover { opacity: 0.9; }
            .result { margin-top: 20px; padding: 15px; border-radius: 5px; }
            .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
            .error { background: #fef2f2; border: 1px solid #ef4444; color: #dc2626; }
        </style>
    </head>
    <body>
        <h1>🧪 Simple Payment Test</h1>
        <p>This is a simplified payment test that bypasses complex M-Pesa API calls and focuses on core functionality.</p>
        
        <form id="test-form">
            <div class="form-group">
                <label for="reseller_id">Reseller ID:</label>
                <input type="number" id="reseller_id" name="reseller_id" value="6" required>
            </div>
            
            <div class="form-group">
                <label for="package_name">Package Name:</label>
                <input type="text" id="package_name" name="package_name" value="Test Package" required>
            </div>
            
            <div class="form-group">
                <label for="package_price">Package Price:</label>
                <input type="number" id="package_price" name="package_price" value="100" required>
            </div>
            
            <div class="form-group">
                <label for="mpesa_number">Phone Number:</label>
                <input type="tel" id="mpesa_number" name="mpesa_number" placeholder="0712345678" required>
            </div>
            
            <div class="form-group">
                <label for="package_id">Package ID:</label>
                <select id="package_id" name="package_id" required>
                    <?php
                    $packages = $conn->query("SELECT id, name, price FROM packages ORDER BY id");
                    if ($packages) {
                        while ($pkg = $packages->fetch_assoc()) {
                            echo "<option value='{$pkg['id']}'>{$pkg['name']} - KES {$pkg['price']}</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="router_id">Router ID:</label>
                <input type="number" id="router_id" name="router_id" value="0">
            </div>
            
            <button type="submit">🚀 Test Payment Submission</button>
        </form>
        
        <div id="result"></div>
        
        <script>
        document.getElementById('test-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="result">🔄 Testing payment submission...</div>';
            
            const formData = new FormData(this);
            
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            
            fetch('simple_payment_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Parsed response:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="result success">
                            <h3>✅ Payment Submission Successful!</h3>
                            <p><strong>Message:</strong> ${data.message}</p>
                            <p><strong>Checkout Request ID:</strong> ${data.checkout_request_id}</p>
                            <p><strong>Phone Number:</strong> ${data.phone_number}</p>
                            <p><strong>Amount:</strong> KES ${data.amount}</p>
                            
                            <h4>Next Steps:</h4>
                            <ol>
                                <li>The transaction has been saved to the database</li>
                                <li>In real scenario, M-Pesa would send STK push to your phone</li>
                                <li>After payment, M-Pesa would call the callback to update status</li>
                                <li>You can manually update the status to 'completed' to test voucher fetching</li>
                            </ol>
                            
                            <button onclick="updateToCompleted('${data.checkout_request_id}')" style="margin-top: 10px;">
                                🔄 Simulate Payment Completion
                            </button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>❌ Payment Submission Failed</h3>
                            <p><strong>Error:</strong> ${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h3>🚨 Request Failed</h3>
                        <p><strong>Error:</strong> ${error.message}</p>
                    </div>
                `;
            });
        });
        
        function updateToCompleted(checkoutRequestId) {
            fetch('simulate_payment_completion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'checkout_request_id=' + encodeURIComponent(checkoutRequestId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Payment status updated to completed! You can now test the voucher fetching.');
                    location.reload();
                } else {
                    alert('❌ Failed to update payment status: ' + data.message);
                }
            })
            .catch(error => {
                alert('🚨 Error: ' + error.message);
            });
        }
        </script>
    </body>
    </html>
    <?php
}
?>
