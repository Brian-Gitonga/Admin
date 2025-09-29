<?php
// Start session and check authentication
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Include database connection
require_once '../connection_dp.php';

// Get the reseller ID from session
$resellerId = $_SESSION['user_id'];

try {
    // Generate a secure API key
    $apiKey = 'qtro_' . bin2hex(random_bytes(32));
    
    // Update the reseller's API key in the database
    $updateQuery = "UPDATE resellers SET api_key = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("si", $apiKey, $resellerId);
    
    if ($stmt->execute()) {
        // Log the API key generation
        $logQuery = "INSERT INTO api_logs (reseller_id, endpoint, method, request_data, response_data, status_code, ip_address, user_agent) 
                     VALUES (?, '/api/generate_api_key', 'POST', '{}', ?, 200, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        if ($logStmt) {
            $responseData = json_encode(['success' => true, 'api_key_generated' => true]);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $logStmt->bind_param("isss", $resellerId, $responseData, $ipAddress, $userAgent);
            $logStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'API key generated successfully',
            'api_key' => $apiKey
        ]);
    } else {
        throw new Exception('Failed to update API key: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating API key: ' . $e->getMessage()
    ]);
}
?>
