<?php
// Set content type to JSON
header('Content-Type: application/json');

// Allow CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Include database connection
require_once '../connection_dp.php';

// Function to log API requests
function logApiRequest($conn, $resellerId, $endpoint, $method, $requestData, $responseData, $statusCode) {
    $logQuery = "INSERT INTO api_logs (reseller_id, endpoint, method, request_data, response_data, status_code, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($logQuery);
    if ($stmt) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param("isssssss", $resellerId, $endpoint, $method, $requestData, $responseData, $statusCode, $ipAddress, $userAgent);
        $stmt->execute();
    }
}

// Function to authenticate API key
function authenticateApiKey($conn, $apiKey) {
    $query = "SELECT id, business_name, status FROM resellers WHERE api_key = ? AND status = 'active'";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("s", $apiKey);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    
    return false;
}

// Function to validate router belongs to reseller
function validateRouter($conn, $resellerId, $routerName) {
    // Router ID in API request is the router name, we need to find the database ID
    // Check if is_active column exists first
    $checkColumnQuery = "SHOW COLUMNS FROM hotspots LIKE 'is_active'";
    $columnResult = $conn->query($checkColumnQuery);

    if ($columnResult && $columnResult->num_rows > 0) {
        // is_active column exists, use it in the query
        $query = "SELECT id, name FROM hotspots WHERE reseller_id = ? AND name = ? AND is_active = 1";
    } else {
        // is_active column doesn't exist, query without it
        $query = "SELECT id, name FROM hotspots WHERE reseller_id = ? AND name = ?";
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("is", $resellerId, $routerName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row;
    }

    return false;
}

// Function to find package by validity
function findPackageByValidity($conn, $resellerId, $validity) {
    // Convert validity to duration format that matches the actual database format
    // Based on the sample data from packages_table.sql and demo packages
    $durationMap = [
        '1h' => '1 Hour',
        '2h' => '2 Hours',
        '3h' => '3 Hours',
        '6h' => '6 Hours',
        '12h' => '12 Hours',
        '1d' => '1 Day',
        '2d' => '2 Days',
        '3d' => '3 Days',
        '5d' => '5 Days',
        '7d' => '7 Days',
        '30d' => '30 Days'
    ];

    $duration = $durationMap[$validity] ?? $validity;

    // Also try alternative formats that might exist in the database
    $alternativeDurations = [];
    if (isset($durationMap[$validity])) {
        $alternativeDurations[] = $durationMap[$validity];

        // Add variations that might exist
        $baseDuration = $durationMap[$validity];
        $alternativeDurations[] = strtolower($baseDuration);
        $alternativeDurations[] = ucfirst(strtolower($baseDuration));

        // Handle plural/singular variations
        if (strpos($baseDuration, ' Hours') !== false) {
            $alternativeDurations[] = str_replace(' Hours', ' Hour', $baseDuration);
        } elseif (strpos($baseDuration, ' Hour') !== false) {
            $alternativeDurations[] = str_replace(' Hour', ' Hours', $baseDuration);
        }

        if (strpos($baseDuration, ' Days') !== false) {
            $alternativeDurations[] = str_replace(' Days', ' Day', $baseDuration);
        } elseif (strpos($baseDuration, ' Day') !== false) {
            $alternativeDurations[] = str_replace(' Day', ' Days', $baseDuration);
        }
    }

    // Remove duplicates
    $alternativeDurations = array_unique($alternativeDurations);

    // Check if is_active column exists in packages table
    $checkColumnQuery = "SHOW COLUMNS FROM packages LIKE 'is_active'";
    $columnResult = $conn->query($checkColumnQuery);
    $hasIsActiveColumn = ($columnResult && $columnResult->num_rows > 0);

    // Try to find package with exact duration match first
    foreach ($alternativeDurations as $testDuration) {
        if ($hasIsActiveColumn) {
            $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration = ? AND is_active = 1 LIMIT 1";
        } else {
            $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration = ? LIMIT 1";
        }

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("is", $resellerId, $testDuration);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                return $row;
            }
        }
    }

    // If no exact match found, try partial matching (LIKE search)
    foreach ($alternativeDurations as $testDuration) {
        if ($hasIsActiveColumn) {
            $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration LIKE ? AND is_active = 1 LIMIT 1";
        } else {
            $query = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND duration LIKE ? LIMIT 1";
        }

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $likeDuration = '%' . $testDuration . '%';
            $stmt->bind_param("is", $resellerId, $likeDuration);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                return $row;
            }
        }
    }

    // If still no match, try to find any active package for this reseller as fallback
    if ($hasIsActiveColumn) {
        $fallbackQuery = "SELECT id, name, duration FROM packages WHERE reseller_id = ? AND is_active = 1 ORDER BY id ASC LIMIT 1";
    } else {
        $fallbackQuery = "SELECT id, name, duration FROM packages WHERE reseller_id = ? ORDER BY id ASC LIMIT 1";
    }

    $fallbackStmt = $conn->prepare($fallbackQuery);
    if ($fallbackStmt) {
        $fallbackStmt->bind_param("i", $resellerId);
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->get_result();

        if ($fallbackRow = $fallbackResult->fetch_assoc()) {
            return $fallbackRow;
        }
    }

    return false;
}

try {
    // Get Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid Authorization header. Use: Authorization: Bearer YOUR_API_KEY'
        ]);
        exit;
    }
    
    $apiKey = $matches[1];
    
    // Authenticate API key
    $reseller = authenticateApiKey($conn, $apiKey);
    if (!$reseller) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid API key or inactive account'
        ]);
        exit;
    }
    
    $resellerId = $reseller['id'];
    
    // Get request body
    $input = file_get_contents('php://input');
    $requestData = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        $response = [
            'success' => false,
            'message' => 'Invalid JSON in request body'
        ];
        echo json_encode($response);
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 400);
        exit;
    }
    
    // Validate required fields
    if (!isset($requestData['router_id']) || !isset($requestData['vouchers'])) {
        http_response_code(400);
        $response = [
            'success' => false,
            'message' => 'Missing required fields: router_id and vouchers'
        ];
        echo json_encode($response);
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 400);
        exit;
    }
    
    $routerId = $requestData['router_id'];
    $vouchers = $requestData['vouchers'];
    
    // Validate vouchers is an array
    if (!is_array($vouchers)) {
        http_response_code(400);
        $response = [
            'success' => false,
            'message' => 'vouchers must be an array'
        ];
        echo json_encode($response);
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 400);
        exit;
    }
    
    // Check voucher count limit
    if (count($vouchers) > 100) {
        http_response_code(413);
        $response = [
            'success' => false,
            'message' => 'Max 100 vouchers per request'
        ];
        echo json_encode($response);
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 413);
        exit;
    }
    
    // Validate router belongs to reseller (router_id in API is the router name)
    $router = validateRouter($conn, $resellerId, $routerId);
    if (!$router) {
        http_response_code(403);
        $response = [
            'success' => false,
            'message' => 'Router "' . $routerId . '" not found or does not belong to your account'
        ];
        echo json_encode($response);
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 403);
        exit;
    }

    $routerDbId = $router['id'];
    
    // Process vouchers
    $results = [];
    $stored = 0;
    $failed = 0;
    
    foreach ($vouchers as $voucher) {
        $voucherCode = $voucher['voucher_code'] ?? '';
        $profile = $voucher['profile'] ?? '';
        $validity = $voucher['validity'] ?? '';
        $createdAt = $voucher['created_at'] ?? date('Y-m-d H:i:s');
        $comment = $voucher['comment'] ?? '';
        $metadata = $voucher['metadata'] ?? [];
        
        // Validate required voucher fields
        if (empty($voucherCode) || empty($validity)) {
            $results[] = [
                'voucher_code' => $voucherCode,
                'status' => 'invalid',
                'message' => 'Missing required fields: voucher_code or validity'
            ];
            $failed++;
            continue;
        }
        
        // Check for duplicate voucher code (global check, not just per reseller)
        $duplicateQuery = "SELECT id FROM vouchers WHERE code = ?";
        $duplicateStmt = $conn->prepare($duplicateQuery);
        if ($duplicateStmt) {
            $duplicateStmt->bind_param("s", $voucherCode);
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();

            if ($duplicateResult->num_rows > 0) {
                $results[] = [
                    'voucher_code' => $voucherCode,
                    'status' => 'duplicate'
                ];
                $failed++;
                continue;
            }
        }
        
        // Find package by validity
        $package = findPackageByValidity($conn, $resellerId, $validity);
        if (!$package) {
            $results[] = [
                'voucher_code' => $voucherCode,
                'status' => 'invalid',
                'message' => 'No package found for validity: ' . $validity . ' (reseller_id: ' . $resellerId . ')'
            ];
            $failed++;
            continue;
        }
        
        // Insert voucher using the existing vouchers table structure
        // Based on database.sql: code, username, password, package_id, reseller_id, customer_phone, status, used_at, created_at, expires_at
        // Plus the new API columns: router_id, profile, validity, comment, metadata, api_created
        $insertQuery = "INSERT INTO vouchers (code, username, password, package_id, reseller_id, router_id, profile, validity, comment, metadata, api_created, customer_phone, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'api', 'active', ?)";
        $insertStmt = $conn->prepare($insertQuery);

        if ($insertStmt) {
            $username = $voucherCode; // Use voucher code as username (standard practice)
            $password = $metadata['password'] ?? $voucherCode; // Use password from metadata or voucher code
            $metadataJson = json_encode($metadata);

            $insertStmt->bind_param("sssiiisssss",
                $voucherCode,
                $username,
                $password,
                $package['id'],
                $resellerId,
                $routerDbId,
                $profile,
                $validity,
                $comment,
                $metadataJson,
                $createdAt
            );

            if ($insertStmt->execute()) {
                $results[] = [
                    'voucher_code' => $voucherCode,
                    'status' => 'stored',
                    'package_id' => $package['id'],
                    'package_name' => $package['name']
                ];
                $stored++;
            } else {
                $results[] = [
                    'voucher_code' => $voucherCode,
                    'status' => 'failed',
                    'message' => 'Database insert failed: ' . $insertStmt->error
                ];
                $failed++;
            }
        } else {
            $results[] = [
                'voucher_code' => $voucherCode,
                'status' => 'failed',
                'message' => 'Database prepare failed: ' . $conn->error
            ];
            $failed++;
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Batch processed',
        'data' => [
            'total' => count($vouchers),
            'stored' => $stored,
            'failed' => $failed,
            'results' => $results
        ]
    ];
    
    http_response_code(200);
    echo json_encode($response);
    logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input, json_encode($response), 200);
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ];
    echo json_encode($response);
    
    if (isset($resellerId)) {
        logApiRequest($conn, $resellerId, '/api/vouchers', 'POST', $input ?? '', json_encode($response), 500);
    }
}
?>
