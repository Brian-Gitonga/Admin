<?php
// This script generates a printable voucher
require_once 'db_connection.php';

// Check for session status or start a new one if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Error: Please log in to print vouchers";
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Check if voucher code is provided
if (!isset($_GET['code']) || empty($_GET['code'])) {
    echo "Error: No voucher code provided";
    exit;
}

$voucherCode = $_GET['code'];

// Get voucher details
$voucher = getVoucherByCode($conn, $voucherCode);

// Check if the voucher exists and belongs to this reseller
if (!$voucher || $voucher['reseller_id'] != $resellerId) {
    echo "Error: Voucher not found or does not belong to you";
    exit;
}

// Get reseller information for printing
$resellerInfo = getResellerInfo($conn, $resellerId);

if (!$resellerInfo) {
    $businessName = "WiFi Hotspot";
    $contactNumber = "";
} else {
    $businessName = $resellerInfo['business_name'] ?? $resellerInfo['business_display_name'] ?? "WiFi Hotspot";
    $contactNumber = $resellerInfo['phone'] ?? "";
}

// Format dates
$createdDate = new DateTime($voucher['created_at']);
$expiryDate = new DateTime($voucher['expires_at']);

// Generate the HTML for the printable voucher
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Voucher - <?php echo htmlspecialchars($voucherCode); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px 10px;
            background-color: #f8f9fa;
        }
        
        .voucher-container {
            border: 2px dashed #3b82f6;
            border-radius: 12px;
            padding: 20px;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .voucher-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .business-name {
            font-size: 22px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .wifi-text {
            font-size: 16px;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        
        .wifi-icon {
            font-size: 24px;
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .voucher-body {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .voucher-code {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1e293b;
            background-color: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .package-name {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        
        .package-details {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
        }
        
        .voucher-footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .expiry-date {
            font-weight: bold;
            color: #ef4444;
        }
        
        .instructions {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8fafc;
            border-radius: 8px;
            text-align: left;
        }
        
        .instructions h3 {
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        
        .instructions ol {
            padding-left: 20px;
            font-size: 12px;
            color: #64748b;
        }
        
        .instructions li {
            margin-bottom: 3px;
        }
        
        .contact-info {
            margin-top: 10px;
            font-size: 12px;
            color: #64748b;
        }
        
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(59, 130, 246, 0.05);
            z-index: 0;
            pointer-events: none;
        }
        
        .print-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .print-btn:hover {
            background-color: #2563eb;
        }
        
        @media print {
            body {
                padding: 0;
                background-color: white;
            }
            
            .voucher-container {
                border: 2px dashed #3b82f6;
                box-shadow: none;
            }
            
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <div class="watermark">WIFI</div>
        
        <div class="voucher-header">
            <div class="business-name"><?php echo htmlspecialchars($businessName); ?></div>
            <div class="wifi-text">WIFI ACCESS VOUCHER</div>
            <div class="wifi-icon">&#x1F4F6;</div>
        </div>
        
        <div class="voucher-body">
            <div class="package-name"><?php echo htmlspecialchars($voucher['package_name']); ?></div>
            <div class="package-details"><?php echo htmlspecialchars($voucher['package_duration'] ?? ''); ?></div>
            
            <div>Voucher Code:</div>
            <div class="voucher-code"><?php echo htmlspecialchars($voucherCode); ?></div>
        </div>
        
        <div class="instructions">
            <h3>How to Connect:</h3>
            <ol>
                <li>Connect to the WiFi network: <strong><?php echo htmlspecialchars($businessName); ?></strong></li>
                <li>Open your web browser, you will be redirected to login page</li>
                <li>Enter the voucher code above</li>
                <li>Click "Connect" to start browsing</li>
            </ol>
        </div>
        
        <div class="voucher-footer">
            <div>Generated: <?php echo $createdDate->format('M d, Y H:i'); ?></div>
            <div>Valid until: <span class="expiry-date"><?php echo $expiryDate->format('M d, Y H:i'); ?></span></div>
            
            <?php if (!empty($contactNumber)): ?>
            <div class="contact-info">
                For support call: <?php echo htmlspecialchars($contactNumber); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">Print Voucher</button>
    
    <script>
        // Auto print dialog
        window.onload = function() {
            // Wait a moment for the page to fully load before printing
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>

<?php
/**
 * Get reseller information from ID
 */
function getResellerInfo($conn, $resellerId) {
    $sql = "SELECT business_name, business_display_name, phone FROM resellers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log("Error preparing statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}
?> 