<?php
// Include the portal database connection
require_once 'portal_connection.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $resellerId = isset($_POST['reseller_id']) ? intval($_POST['reseller_id']) : 0;
    $voucherCode = isset($_POST['voucher_code']) ? $_POST['voucher_code'] : '';
    
    // Validate inputs
    if (empty($voucherCode)) {
        echo json_encode(['success' => false, 'message' => 'Voucher code is required']);
        exit;
    }
    
    // TODO: Validate the voucher code against your database or API
    // This is where you would check if the voucher is valid and not expired
    
    // For demo purposes, we'll simulate a valid voucher
    $isValidVoucher = true;
    
    if ($isValidVoucher) {
        // TODO: Connect the user to the hotspot using the router API
        // This is where you would make the API calls to authenticate the user
        
        // Return success response
        echo json_encode([
            'success' => true, 
            'message' => 'Successfully connected with voucher code. You are now online!',
            'redirect_url' => 'https://www.google.com' // Or your landing page
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired voucher code']);
    }
    
    exit;
} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?> 