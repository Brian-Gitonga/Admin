<?php
// Check Voucher Availability API
header('Content-Type: application/json');

// Include database connection
require_once 'connection_dp.php';

// Function to check voucher availability
function checkVoucherAvailability($conn, $packageId, $routerId) {
    try {
        // Check if we have a valid database connection
        if (!$conn || $conn->connect_error) {
            return [
                'success' => false,
                'message' => 'Database connection error',
                'available' => false,
                'count' => 0
            ];
        }
        
        // Query to count available vouchers for the package and router
        $query = "SELECT COUNT(*) as count FROM vouchers 
                  WHERE package_id = ? 
                  AND router_id = ? 
                  AND status = 'active'";
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            return [
                'success' => false,
                'message' => 'Database query error: ' . $conn->error,
                'available' => false,
                'count' => 0
            ];
        }
        
        $stmt->bind_param("ii", $packageId, $routerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            
            return [
                'success' => true,
                'message' => $count > 0 ? "Vouchers available" : "No vouchers available",
                'available' => $count > 0,
                'count' => $count
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to execute query',
                'available' => false,
                'count' => 0
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error checking voucher availability: ' . $e->getMessage(),
            'available' => false,
            'count' => 0
        ];
    }
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Try to get from POST data
        $packageId = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
        $routerId = isset($_POST['router_id']) ? (int)$_POST['router_id'] : 0;
    } else {
        $packageId = isset($input['package_id']) ? (int)$input['package_id'] : 0;
        $routerId = isset($input['router_id']) ? (int)$input['router_id'] : 0;
    }
    
    // Validate input
    if ($packageId <= 0 || $routerId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid package ID or router ID',
            'available' => false,
            'count' => 0
        ]);
        exit;
    }
    
    // Check voucher availability
    $result = checkVoucherAvailability($conn, $packageId, $routerId);
    
    // Return JSON response
    echo json_encode($result);
    exit;
}

// Handle GET request for testing
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $packageId = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
    $routerId = isset($_GET['router_id']) ? (int)$_GET['router_id'] : 0;
    
    if ($packageId <= 0 || $routerId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide package_id and router_id parameters',
            'available' => false,
            'count' => 0
        ]);
        exit;
    }
    
    // Check voucher availability
    $result = checkVoucherAvailability($conn, $packageId, $routerId);
    
    // Return JSON response
    echo json_encode($result);
    exit;
}

// Invalid request method
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed. Use POST or GET.',
    'available' => false,
    'count' => 0
]);
?>
