<?php
// Include the portal database connection
require_once 'portal_connection.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $resellerId = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 0;
    $mobileNumber = isset($_POST['mobile_number']) ? $_POST['mobile_number'] : '';
    $mobilePin = isset($_POST['mobile_pin']) ? $_POST['mobile_pin'] : '';
    
    // Validate inputs
    if (empty($mobileNumber) || empty($mobilePin)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number and PIN are required']);
        exit;
    }
    
    // Format phone number (remove spaces, ensure it starts with 254)
    $mobileNumber = preg_replace('/\s+/', '', $mobileNumber);
    
    // If number starts with 0, replace with 254
    if (substr($mobileNumber, 0, 1) === '0') {
        $mobileNumber = '254' . substr($mobileNumber, 1);
    }
    
    // TODO: Validate the mobile credentials against your database
    // Check if the user has a valid subscription
    
    // For demo purposes, we'll simulate a valid user
    $sql = "SELECT * FROM end_users WHERE phone = ? AND status = 'active' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobileNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // TODO: Verify PIN (in a real app, you would hash the PIN)
        // For demo, we'll assume the PIN is valid
        
        // TODO: Connect the user to the hotspot using the router API
        // This is where you would make the API calls to authenticate the user
        
        // Return success response
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully authenticated. You are now online!',
            'redirect_url' => 'https://www.google.com' // Or your landing page
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials or expired subscription']);
    }
    
    exit;
} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?> 