<?php
/**
 * SMS Voucher Delivery System
 * Handles voucher assignment and SMS delivery after successful M-Pesa payment
 */

require_once 'portal_connection.php';

// SMS Gateway Configuration
class SmsGatewayManager {
    private $activeGateway;
    private $gateways;
    
    public function __construct() {
        // Define available SMS gateways
        $this->gateways = [
            'textsms' => [
                'class' => 'TextSmsApi',
                'file' => 'sms api/textsms_api.php',
                'config' => [
                    'api_key' => '7624c6d424ae11de80b2d6611e69704a',
                    'partner_id' => '13361',
                    'sender_id' => 'UMS_SMS'
                ]
            ],
            'host_pinacle' => [
                'class' => 'HostPinacleApi',
                'file' => 'sms api/host_pinacle.php',
                'config' => []
            ],
            'umeskia' => [
                'class' => 'UmeskiaApi',
                'file' => 'sms api/umeskia_api.php',
                'config' => [
                    'api_key' => '7c973941a96b28fd910e19db909e7fda',
                    'app_id' => 'UMSC631939',
                    'sender_id' => 'UMS_SMS'
                ]
            ]
        ];
        
        // Set default active gateway to Umeskia
        $this->activeGateway = 'umeskia';
    }
    
    public function setActiveGateway($gateway) {
        if (isset($this->gateways[$gateway])) {
            $this->activeGateway = $gateway;
            return true;
        }
        return false;
    }
    
    public function sendVoucherSms($phoneNumber, $voucherCode, $username, $password, $packageName, $duration = '') {
        $gateway = $this->gateways[$this->activeGateway];
        
        // Load the gateway class
        if (!file_exists($gateway['file'])) {
            return [
                'success' => false,
                'message' => "SMS gateway file not found: {$gateway['file']}"
            ];
        }
        
        require_once $gateway['file'];
        
        // Create SMS message
        $message = $this->createVoucherMessage($voucherCode, $username, $password, $packageName, $duration);
        
        // Send SMS based on active gateway
        switch ($this->activeGateway) {
            case 'textsms':
                return $this->sendViaTextSms($phoneNumber, $message, $gateway['config']);
            
            case 'host_pinacle':
                return $this->sendViaHostPinacle($phoneNumber, $message, $gateway['config']);
                
            case 'umeskia':
                return $this->sendViaUmeskia($phoneNumber, $message, $gateway['config']);
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown SMS gateway: ' . $this->activeGateway
                ];
        }
    }
    
    private function createVoucherMessage($voucherCode, $username, $password, $packageName, $duration) {
        $message = "🎉 Payment Successful!\n\n";
        $message .= "Your WiFi Voucher Details:\n";
        $message .= "📱 Code: $voucherCode\n";
        $message .= "👤 Username: $username\n";
        $message .= "🔐 Password: $password\n";
        $message .= "📦 Package: $packageName\n";
        
        if (!empty($duration)) {
            $message .= "⏰ Duration: $duration\n";
        }
        
        $message .= "\nConnect to WiFi and use these details to access the internet.";
        $message .= "\n\nThank you for your payment!";
        
        return $message;
    }
    
    private function sendViaTextSms($phoneNumber, $message, $config) {
        try {
            $sms = new TextSmsApi(
                $config['api_key'],
                $config['partner_id'],
                $config['sender_id']
            );
            
            $success = $sms->sendSms($phoneNumber, $message);
            
            if ($success) {
                $response = $sms->getResponseData();
                $messageId = isset($response['responses'][0]['messageid']) ? $response['responses'][0]['messageid'] : null;
                
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully via TextSMS',
                    'message_id' => $messageId,
                    'gateway' => 'textsms'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'TextSMS Error: ' . $sms->getError(),
                    'gateway' => 'textsms'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'TextSMS Exception: ' . $e->getMessage(),
                'gateway' => 'textsms'
            ];
        }
    }
    
    private function sendViaHostPinacle($phoneNumber, $message, $config) {
        // Implement Host Pinacle SMS sending
        return [
            'success' => false,
            'message' => 'Host Pinacle SMS gateway not yet implemented',
            'gateway' => 'host_pinacle'
        ];
    }
    
    private function sendViaUmeskia($phoneNumber, $message, $config) {
        try {
            // Format phone number for Umeskia (they expect 07xxxxxxxx format)
            if (substr($phoneNumber, 0, 3) === '254') {
                $phoneNumber = '0' . substr($phoneNumber, 3);
            }

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => "https://comms.umeskiasoftwares.com/api/v1/sms/send",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    "api_key" => $config['api_key'],
                    "app_id" => $config['app_id'],
                    "sender_id" => $config['sender_id'],
                    "message" => $message,
                    "phone" => $phoneNumber
                ]
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($err) {
                return [
                    'success' => false,
                    'message' => 'Umeskia cURL Error: ' . $err,
                    'gateway' => 'umeskia'
                ];
            }

            // Parse the response
            $responseData = json_decode($response, true);

            if ($httpCode === 200 && $responseData) {
                // Check if Umeskia returned success
                if (isset($responseData['status']) && $responseData['status'] === 'success') {
                    return [
                        'success' => true,
                        'message' => 'SMS sent successfully via Umeskia',
                        'message_id' => $responseData['message_id'] ?? null,
                        'gateway' => 'umeskia',
                        'response_data' => $responseData
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Umeskia Error: ' . ($responseData['message'] ?? 'Unknown error'),
                        'gateway' => 'umeskia',
                        'response_data' => $responseData
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Umeskia HTTP Error: ' . $httpCode . ' - ' . $response,
                    'gateway' => 'umeskia'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Umeskia Exception: ' . $e->getMessage(),
                'gateway' => 'umeskia'
            ];
        }
    }
}

/**
 * Process voucher assignment and SMS delivery after successful payment
 */
function processVoucherDelivery($checkoutRequestId, $packageId, $resellerId, $customerPhone, $mpesaReceipt) {
    global $conn;

    $logFile = 'voucher_delivery.log';

    // Helper function for logging
    $logVoucherDelivery = function($message) use ($logFile) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    };
    
    $logVoucherDelivery("=== VOUCHER DELIVERY STARTED ===");
    $logVoucherDelivery("Checkout ID: $checkoutRequestId");
    $logVoucherDelivery("Package ID: $packageId, Reseller ID: $resellerId");
    $logVoucherDelivery("Customer Phone: $customerPhone");
    
    try {
        // Step 1: Check if voucher already assigned to this transaction
        $existingVoucherCheck = $conn->prepare("SELECT voucher_code FROM mpesa_transactions WHERE checkout_request_id = ? AND voucher_code IS NOT NULL");
        if ($existingVoucherCheck) {
            $existingVoucherCheck->bind_param("s", $checkoutRequestId);
            $existingVoucherCheck->execute();
            $existingResult = $existingVoucherCheck->get_result();
            
            if ($existingResult->num_rows > 0) {
                $existingVoucher = $existingResult->fetch_assoc();
                $logVoucherDelivery("Voucher already assigned: " . $existingVoucher['voucher_code']);
                
                // Re-send SMS for existing voucher
                return resendVoucherSms($existingVoucher['voucher_code'], $customerPhone, $packageId);
            }
        }
        
        // Step 2: Find an active voucher for this package and reseller
        $voucherQuery = $conn->prepare("SELECT id, code, username, password FROM vouchers WHERE package_id = ? AND reseller_id = ? AND status = 'active' ORDER BY created_at ASC LIMIT 1");
        
        if (!$voucherQuery) {
            $logVoucherDelivery("ERROR: Failed to prepare voucher query: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Database error: Failed to prepare voucher query'
            ];
        }
        
        $voucherQuery->bind_param("ii", $packageId, $resellerId);
        $voucherQuery->execute();
        $voucherResult = $voucherQuery->get_result();
        
        if ($voucherResult->num_rows === 0) {
            $logVoucherDelivery("ERROR: No active vouchers available for package $packageId, reseller $resellerId");
            return [
                'success' => false,
                'message' => 'No vouchers available for this package. Please contact support.'
            ];
        }
        
        // Step 3: Get the voucher and mark it as used
        $voucher = $voucherResult->fetch_assoc();
        $voucherId = $voucher['id'];
        $voucherCode = $voucher['code'];
        $username = $voucher['username'] ?: $voucherCode;
        $password = $voucher['password'] ?: $voucherCode;
        
        $logVoucherDelivery("Found voucher: ID=$voucherId, Code=$voucherCode");

        // Step 4: Mark voucher as used
        $updateVoucherQuery = $conn->prepare("UPDATE vouchers SET status = 'used', customer_phone = ?, used_at = NOW() WHERE id = ?");
        if (!$updateVoucherQuery) {
            $logVoucherDelivery("ERROR: Failed to prepare voucher update query: " . $conn->error);
            return [
                'success' => false,
                'message' => 'Database error: Failed to prepare voucher update'
            ];
        }
        
        $updateVoucherQuery->bind_param("si", $customerPhone, $voucherId);
        if (!$updateVoucherQuery->execute()) {
            $logVoucherDelivery("ERROR: Failed to update voucher status: " . $updateVoucherQuery->error);
            return [
                'success' => false,
                'message' => 'Failed to assign voucher to customer'
            ];
        }
        
        $logVoucherDelivery("Voucher marked as used successfully");

        // Step 5: Update transaction with voucher details
        $updateTransactionQuery = $conn->prepare("UPDATE mpesa_transactions SET voucher_code = ?, voucher_id = ? WHERE checkout_request_id = ?");
        if ($updateTransactionQuery) {
            $updateTransactionQuery->bind_param("sis", $voucherCode, $voucherId, $checkoutRequestId);
            $updateTransactionQuery->execute();
            $logVoucherDelivery("Transaction updated with voucher details");
        }
        
        // Step 6: Get package details for SMS
        $packageName = "WiFi Package";
        $packageDuration = "";
        
        $packageQuery = $conn->prepare("SELECT name, duration FROM packages WHERE id = ?");
        if ($packageQuery) {
            $packageQuery->bind_param("i", $packageId);
            $packageQuery->execute();
            $packageResult = $packageQuery->get_result();
            
            if ($packageResult->num_rows > 0) {
                $packageData = $packageResult->fetch_assoc();
                $packageName = $packageData['name'];
                $packageDuration = $packageData['duration'] ?: "";
            }
        }
        
        // Step 7: Send SMS with voucher details
        $logVoucherDelivery("Sending SMS to customer: $customerPhone");
        
        $smsManager = new SmsGatewayManager();
        $smsResult = $smsManager->sendVoucherSms($customerPhone, $voucherCode, $username, $password, $packageName, $packageDuration);
        
        if ($smsResult['success']) {
            $logVoucherDelivery("SMS sent successfully: " . $smsResult['message']);
            
            // Log SMS delivery in database
            $smsLogQuery = $conn->prepare("INSERT INTO sms_logs (phone_number, message_type, voucher_code, status, gateway, message_id, created_at) VALUES (?, 'voucher_delivery', ?, 'sent', ?, ?, NOW())");
            if ($smsLogQuery) {
                $messageId = $smsResult['message_id'] ?? null;
                $gateway = $smsResult['gateway'] ?? 'unknown';
                $smsLogQuery->bind_param("ssss", $customerPhone, $voucherCode, $gateway, $messageId);
                $smsLogQuery->execute();
            }
            
            return [
                'success' => true,
                'message' => 'Voucher assigned and SMS sent successfully',
                'voucher_code' => $voucherCode,
                'username' => $username,
                'password' => $password,
                'package_name' => $packageName,
                'sms_result' => $smsResult
            ];
        } else {
            $logVoucherDelivery("SMS sending failed: " . $smsResult['message']);
            
            // Log SMS failure
            $smsLogQuery = $conn->prepare("INSERT INTO sms_logs (phone_number, message_type, voucher_code, status, gateway, error_message, created_at) VALUES (?, 'voucher_delivery', ?, 'failed', ?, ?, NOW())");
            if ($smsLogQuery) {
                $gateway = $smsResult['gateway'] ?? 'unknown';
                $smsLogQuery->bind_param("ssss", $customerPhone, $voucherCode, $gateway, $smsResult['message']);
                $smsLogQuery->execute();
            }
            
            return [
                'success' => false,
                'message' => 'Voucher assigned but SMS sending failed: ' . $smsResult['message'],
                'voucher_code' => $voucherCode,
                'username' => $username,
                'password' => $password,
                'package_name' => $packageName
            ];
        }
        
    } catch (Exception $e) {
        $logVoucherDelivery("EXCEPTION: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'System error: ' . $e->getMessage()
        ];
    }
}

/**
 * Resend SMS for existing voucher
 */
function resendVoucherSms($voucherCode, $customerPhone, $packageId) {
    global $conn;
    
    // Get voucher details
    $voucherQuery = $conn->prepare("SELECT username, password FROM vouchers WHERE code = ?");
    if ($voucherQuery) {
        $voucherQuery->bind_param("s", $voucherCode);
        $voucherQuery->execute();
        $voucherResult = $voucherQuery->get_result();
        
        if ($voucherResult->num_rows > 0) {
            $voucher = $voucherResult->fetch_assoc();
            $username = $voucher['username'] ?: $voucherCode;
            $password = $voucher['password'] ?: $voucherCode;
            
            // Get package details
            $packageName = "WiFi Package";
            $packageDuration = "";
            
            $packageQuery = $conn->prepare("SELECT name, duration FROM packages WHERE id = ?");
            if ($packageQuery) {
                $packageQuery->bind_param("i", $packageId);
                $packageQuery->execute();
                $packageResult = $packageQuery->get_result();
                
                if ($packageResult->num_rows > 0) {
                    $packageData = $packageResult->fetch_assoc();
                    $packageName = $packageData['name'];
                    $packageDuration = $packageData['duration'] ?: "";
                }
            }
            
            // Send SMS
            $smsManager = new SmsGatewayManager();
            $smsResult = $smsManager->sendVoucherSms($customerPhone, $voucherCode, $username, $password, $packageName, $packageDuration);
            
            return [
                'success' => $smsResult['success'],
                'message' => 'Existing voucher SMS ' . ($smsResult['success'] ? 'sent successfully' : 'sending failed'),
                'voucher_code' => $voucherCode,
                'username' => $username,
                'password' => $password,
                'package_name' => $packageName,
                'sms_result' => $smsResult
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Voucher details not found'
    ];
}

// Create SMS logs table if it doesn't exist
function createSmsLogsTable() {
    global $conn;
    
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_number VARCHAR(20) NOT NULL,
        message_type VARCHAR(50) NOT NULL,
        voucher_code VARCHAR(50) DEFAULT NULL,
        status ENUM('sent', 'failed', 'pending') NOT NULL,
        gateway VARCHAR(50) DEFAULT NULL,
        message_id VARCHAR(100) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_phone (phone_number),
        INDEX idx_voucher (voucher_code),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $conn->query($createTableSql);
}

// Initialize SMS logs table
createSmsLogsTable();

?>
