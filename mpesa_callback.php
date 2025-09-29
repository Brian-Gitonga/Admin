<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the portal database connection
require_once 'portal_connection.php';
// MikroTik integration removed - vouchers will be generated without router communication

// Initialize debug log
$log_file = 'mpesa_callback.log';
function log_callback($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

log_callback("======= M-PESA CALLBACK RECEIVED =======");
log_callback("Remote IP: " . $_SERVER['REMOTE_ADDR']);
log_callback("Request Method: " . $_SERVER['REQUEST_METHOD']);
log_callback("Request URI: " . $_SERVER['REQUEST_URI']);
log_callback("Full URL: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

// Log all headers for debugging
$headers = getallheaders();
log_callback("Request Headers: " . print_r($headers, true));

// Get the callback data
$callbackJSONData = file_get_contents('php://input');
log_callback("Callback raw data: " . $callbackJSONData);

// Check if the data is empty
if (empty($callbackJSONData)) {
    log_callback("WARNING: Empty request body received");
    // Respond with success to prevent retries
    header("Content-Type: application/json");
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
    exit;
}

// Try to sanitize the JSON if it looks like it might be malformed
$sanitized_data = trim($callbackJSONData);
if (substr($sanitized_data, 0, 1) !== '{' && substr($sanitized_data, -1) !== '}') {
    log_callback("Attempting to sanitize non-JSON data");
    // If it's not JSON, try to handle simple key-value responses
    if (strpos($sanitized_data, 'ResultCode') !== false) {
        $simpleData = new stdClass();
        if (strpos($sanitized_data, 'ResultCode=0') !== false) {
            $simpleData->ResultCode = 0;
            $simpleData->ResultDesc = "Success";
        } else {
            $simpleData->ResultCode = 1;
            $simpleData->ResultDesc = "Failed";
        }
        $callbackData = $simpleData;
        log_callback("Created simple object from non-JSON data: " . print_r($callbackData, true));
    } else {
        $callbackData = null;
    }
} else {
    // Parse the JSON data with error handling
    $callbackData = json_decode($sanitized_data);
}

// Check for JSON parsing errors
if (json_last_error() !== JSON_ERROR_NONE) {
    log_callback("Failed to decode JSON data: " . json_last_error_msg() . ". Attempting alternative parsing...");
    
    // Try an alternative approach for simple JSON responses
    $simple_data = [];
    if (preg_match('/ResultCode["\s:=]+(\d+)/', $sanitized_data, $matches)) {
        $simple_data['ResultCode'] = intval($matches[1]);
    }
    if (preg_match('/ResultDesc["\s:=]+([^,"}\n]+)/', $sanitized_data, $matches)) {
        $simple_data['ResultDesc'] = trim($matches[1]);
    }
    
    // If we found at least the ResultCode
    if (!empty($simple_data)) {
        $callbackData = (object)$simple_data;
        log_callback("Successfully parsed simple data: " . print_r($callbackData, true));
    }
}

if ($callbackData) {
    // Log the callback data in a structured format
    log_callback("Callback decoded: " . print_r($callbackData, true));
    
    // Handle simple success response
    if (isset($callbackData->ResultCode) && !isset($callbackData->Body)) {
        log_callback("Simple result response received: ResultCode=" . $callbackData->ResultCode);
        header("Content-Type: application/json");
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
        exit;
    }
    
    // Extract the necessary information
    if (isset($callbackData->Body) && isset($callbackData->Body->stkCallback)) {
        $stkCallback = $callbackData->Body->stkCallback;
        $merchantRequestID = $stkCallback->MerchantRequestID;
        $checkoutRequestID = $stkCallback->CheckoutRequestID;
        $resultCode = $stkCallback->ResultCode;
        $resultDesc = $stkCallback->ResultDesc;
        
        log_callback("Processing STK callback: CheckoutRequestID=$checkoutRequestID, ResultCode=$resultCode, ResultDesc=$resultDesc");
        
        // Check if the transaction was successful (ResultCode 0 means success)
        $success = ($resultCode == 0);
        
        // Check if transaction exists in database
        $checkStmt = $conn->prepare("SELECT id FROM mpesa_transactions WHERE checkout_request_id = ?");
        $checkStmt->bind_param("s", $checkoutRequestID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            log_callback("ERROR: No transaction found with CheckoutRequestID: $checkoutRequestID");
            // Respond to M-Pesa with success to prevent retries
            header("Content-Type: application/json");
            echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
            exit;
        }
        
        if ($success) {
            // Extract payment details for successful transaction
            $callbackMetadata = $stkCallback->CallbackMetadata;
            $amount = null;
            $mpesaReceiptNumber = null;
            $transactionDate = null;
            $phoneNumber = null;
            
            if (isset($callbackMetadata->Item)) {
                foreach ($callbackMetadata->Item as $item) {
                    if ($item->Name == "Amount") $amount = $item->Value;
                    if ($item->Name == "MpesaReceiptNumber") $mpesaReceiptNumber = $item->Value;
                    if ($item->Name == "TransactionDate") $transactionDate = $item->Value;
                    if ($item->Name == "PhoneNumber") $phoneNumber = $item->Value;
                }
            }
            
            log_callback("Payment successful: Amount=$amount, ReceiptNumber=$mpesaReceiptNumber, PhoneNumber=$phoneNumber");
            
            // Update the transaction in the database
            try {
                $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
                    status = 'completed', 
                    mpesa_receipt = ?, 
                    transaction_date = ?, 
                    result_code = ?,
                    result_description = ?,
                    updated_at = NOW() 
                    WHERE checkout_request_id = ?");
                    
                $stmt->bind_param("ssiss", 
                    $mpesaReceiptNumber, 
                    $transactionDate,
                    $resultCode,
                    $resultDesc,
                    $checkoutRequestID
                );
                $result = $stmt->execute();
                
                if ($result) {
                    log_callback("Transaction updated successfully in database. Affected rows: " . $stmt->affected_rows);
                } else {
                    log_callback("ERROR: Failed to update transaction in database: " . $conn->error);
                }
                
                if ($stmt->affected_rows > 0) {
                    log_callback("Transaction updated successfully in database");
                    
                    // Get the package details from the transaction
                    $stmt = $conn->prepare("SELECT package_id, reseller_id, phone_number, router_id FROM mpesa_transactions WHERE checkout_request_id = ?");
                    $stmt->bind_param("s", $checkoutRequestID);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        $packageId = $row['package_id'];
                        $resellerId = $row['reseller_id'];
                        $customerPhone = $row['phone_number'];
                        $routerId = isset($row['router_id']) ? $row['router_id'] : 0;
                        
                        log_callback("Need to generate WiFi access for package ID $packageId, reseller ID $resellerId, customer phone $customerPhone");
                        
                        // STEP 1: Try to fetch an existing voucher from the database
                        $voucherFetched = false;
                        $voucherCode = '';
                        
                        // Include the voucher fetching system if it exists
                        if (file_exists('fetch_voucher.php')) {
                            require_once 'fetch_voucher.php';
                            log_callback("Attempting to fetch existing voucher from database");
                            
                            // Try to get an existing voucher
                            $voucher = getVoucherForPayment($packageId, $routerId, $customerPhone, $checkoutRequestID);
                            
                            if ($voucher) {
                                // Successfully fetched an existing voucher
                                $voucherCode = $voucher['code'];
                                $voucherFetched = true;
                                log_callback("Fetched existing voucher from database: $voucherCode");
                            } else {
                                log_callback("ERROR: No vouchers available for package ID: $packageId. Please upload vouchers for this package.");
                                
                                // Update transaction with error message
                                $errorMsg = "No vouchers available for this package. Contact support.";
                                $errorUpdateStmt = $conn->prepare("UPDATE mpesa_transactions SET 
                                    notes = ? 
                                    WHERE checkout_request_id = ?");
                                $errorUpdateStmt->bind_param("ss", $errorMsg, $checkoutRequestID);
                                $errorUpdateStmt->execute();
                            }
                        } else {
                            log_callback("ERROR: fetch_voucher.php not found. Cannot fetch vouchers from database.");
                        }
                        
                        // STEP 2: Only proceed if a voucher was successfully fetched
                        if ($voucherFetched) {
                                // Add voucher to MikroTik
                                $mikrotikResult = addVoucherToMikrotik($voucherCode, $packageId, $resellerId, $customerPhone, $conn);
                                if ($mikrotikResult === true) {
                                    log_callback("Voucher added to MikroTik: $voucherCode");
                                } else {
                                    log_callback("Failed to add voucher to MikroTik: $mikrotikResult");
                                }
                                
                                // Get package name/details for SMS
                                $packageName = "WiFi Package"; // Default name
                                $packageDuration = "";
                                
                                // Try to get package details
                                $packageQuery = $conn->prepare("SELECT name, description, duration FROM packages WHERE id = ?");
                                if ($packageQuery) {
                                    $packageQuery->bind_param("i", $packageId);
                                    $packageQuery->execute();
                                    $packageResult = $packageQuery->get_result();
                                    
                                    if ($packageResult && $packageResult->num_rows > 0) {
                                        $packageData = $packageResult->fetch_assoc();
                                        $packageName = $packageData['name'];
                                        $packageDuration = $packageData['duration'] ?? "";
                                    }
                                    $packageQuery->close();
                                }
                                
                                // Send SMS to the customer using the same mechanism as free trial
                                log_callback("Sending voucher SMS to customer: $customerPhone");
                                
                                // Prepare parameters for SMS sending
                                $smsData = [
                                    'phone_number' => $customerPhone,
                                    'voucher_code' => $voucherCode,
                                    'username' => $voucherCode, // Use voucher code as username
                                    'password' => $voucherCode, // Use voucher code as password
                                    'package_name' => $packageName,
                                    'duration' => $packageDuration
                                ];
                                
                                // Send SMS using cURL
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, 'send_free_trial_sms.php');
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($smsData));
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                
                                $smsResponse = curl_exec($ch);
                                $smsError = curl_error($ch);
                                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                curl_close($ch);
                                
                                if ($smsError) {
                                    log_callback("SMS sending error: $smsError");
                        } else {
                                    log_callback("SMS sending response (HTTP $httpCode): $smsResponse");
                                    
                                    // Try to parse the JSON response
                                    $smsResult = json_decode($smsResponse, true);
                                    if ($smsResult && isset($smsResult['success']) && $smsResult['success']) {
                                        log_callback("SMS sent successfully to: $customerPhone");
                                    } else {
                                        $errorMsg = isset($smsResult['message']) ? $smsResult['message'] : 'Unknown error';
                                        log_callback("SMS sending failed: $errorMsg");
                                    }
                                }
                            } else {
                            log_callback("No action taken: No voucher available to assign to customer");
                        }
                    } else {
                        log_callback("Could not find transaction details in database");
                    }
                } else {
                    log_callback("No transaction updated - possibly already processed? CheckoutRequestID: $checkoutRequestID");
                }
            } catch (Exception $e) {
                log_callback("Database error: " . $e->getMessage());
            }
        } else {
            // Transaction failed
            log_callback("Payment failed: ResultCode=$resultCode, ResultDesc=$resultDesc");
            
            // Update the transaction status to failed
            try {
                $stmt = $conn->prepare("UPDATE mpesa_transactions SET 
                    status = 'failed', 
                    result_code = ?, 
                    result_description = ?, 
                    updated_at = NOW() 
                    WHERE checkout_request_id = ?");
                    
                $stmt->bind_param("iss", 
                    $resultCode, 
                    $resultDesc, 
                    $checkoutRequestID
                );
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    log_callback("Transaction status updated to failed in database");
                } else {
                    log_callback("No transaction found with CheckoutRequestID: $checkoutRequestID");
                }
            } catch (Exception $e) {
                log_callback("Database error: " . $e->getMessage());
            }
        }
    } else {
        log_callback("Invalid callback format: missing Body->stkCallback");
    }
} else {
    log_callback("Failed to decode JSON data: " . json_last_error_msg());
}

// Function to generate a random voucher code
function generateVoucherCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Always respond with a success message to M-Pesa
header("Content-Type: application/json");
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
?> 