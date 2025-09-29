<?php
/**
 * Process Approval/Rejection Actions
 * Handles approve and reject requests from the super admin interface
 */

// Include database configuration
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['action']) || !isset($input['request_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$action = $input['action'];
$request_id = (int)$input['request_id'];

try {
    // Validate request exists and is in 'ordered' status
    $check_sql = "SELECT id, request_status FROM remote_access_requests WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $request_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Request not found');
    }
    
    $request = $result->fetch_assoc();
    if ($request['request_status'] !== 'ordered') {
        throw new Exception('Request has already been processed');
    }
    
    if ($action === 'approve') {
        // Validate approval data
        if (!isset($input['credentials'])) {
            throw new Exception('Credentials are required for approval');
        }
        
        $credentials = $input['credentials'];
        $required_fields = ['username', 'password'];
        
        foreach ($required_fields as $field) {
            if (empty($credentials[$field])) {
                throw new Exception("$field is required for approval");
            }
        }
        
        // Set default values for optional fields
        $dns_name = !empty($credentials['dns_name']) ? $credentials['dns_name'] : null;
        $port = !empty($credentials['port']) ? (int)$credentials['port'] : 8291;
        $admin_comments = !empty($input['admin_comments']) ? $input['admin_comments'] : 'Approved by super admin';
        
        // Update request with approval
        $success = updateRequestStatus($conn, $request_id, 'approved', $admin_comments, [
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'dns_name' => $dns_name,
            'port' => $port
        ]);
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Request approved successfully',
                'action' => 'approved'
            ]);
        } else {
            throw new Exception('Failed to approve request');
        }
        
    } elseif ($action === 'reject') {
        // Validate rejection reason
        if (empty($input['admin_comments'])) {
            throw new Exception('Rejection reason is required');
        }
        
        $admin_comments = $input['admin_comments'];
        
        // Update request with rejection
        $success = updateRequestStatus($conn, $request_id, 'rejected', $admin_comments);
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Request rejected successfully',
                'action' => 'rejected'
            ]);
        } else {
            throw new Exception('Failed to reject request');
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?>
