<?php
/**
 * TextSMS API Client
 * 
 * A PHP class for sending SMS messages using the TextSMS API service
 * Documentation: https://textsms.co.ke/bulk-sms-api/
 * 
 * @version 1.0
 */

class TextSmsApi {
    /**
     * @var string API key from TextSMS account
     */
    private $apiKey;
    
    /**
     * @var string Partner ID from TextSMS account
     */
    private $partnerId;
    
    /**
     * @var string Default sender ID / shortcode to use for messages
     */
    private $defaultSenderId;
    
    /**
     * @var int Timeout in seconds for API requests
     */
    private $timeout = 30;
    
    /**
     * @var array Last API response and details
     */
    private $lastResponse = null;
    
    /**
     * Constructor for TextSMS API client
     * 
     * @param string $apiKey Your TextSMS API key
     * @param string $partnerId Your TextSMS Partner ID
     * @param string $defaultSenderId Default sender ID to use
     */
    public function __construct($apiKey, $partnerId, $defaultSenderId = 'TextSMS') {
        $this->apiKey = $apiKey;
        $this->partnerId = $partnerId;
        $this->defaultSenderId = $defaultSenderId;
    }
    
    /**
     * Set a custom timeout for API requests
     * 
     * @param int $seconds Timeout in seconds
     * @return $this
     */
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
        return $this;
    }
    
    /**
     * Send an SMS message
     * 
     * @param string|array $phoneNumber Phone number(s) to send to (international format: 254XXXXXXXXX)
     * @param string $message Message content
     * @param string|null $senderId Sender ID/shortcode (will use default if null)
     * @param string|null $scheduleTime Schedule time in format YYYY-MM-DD HH:MM (send immediately if null)
     * @param bool $useGet Whether to use GET method instead of POST
     * @return bool Success status
     */
    public function sendSms($phoneNumber, $message, $senderId = null, $scheduleTime = null, $useGet = false) {
        // Format phone number(s)
        if (is_array($phoneNumber)) {
            $phoneNumber = implode(',', array_map([$this, 'formatPhoneNumber'], $phoneNumber));
        } else {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        }
        
        // Use default sender ID if none provided
        $senderId = $senderId ?: $this->defaultSenderId;
        
        if ($useGet) {
            return $this->sendSmsViaGet($phoneNumber, $message, $senderId, $scheduleTime);
        } else {
            return $this->sendSmsViaPost($phoneNumber, $message, $senderId, $scheduleTime);
        }
    }
    
    /**
     * Check account SMS balance
     * 
     * @param bool $useGet Whether to use GET method instead of POST
     * @return bool Success status
     */
    public function checkBalance($useGet = false) {
        if ($useGet) {
            $url = "https://sms.textsms.co.ke/api/services/getbalance/index.php?apikey=" . urlencode($this->apiKey) . 
                   "&partnerID=" . urlencode($this->partnerId);
            
            $this->lastResponse = $this->executeGetRequest($url);
        } else {
            $payload = [
                'apikey' => $this->apiKey,
                'partnerID' => $this->partnerId
            ];
            
            $this->lastResponse = $this->executePostRequest(
                "https://sms.textsms.co.ke/api/services/getbalance/",
                $payload
            );
        }
        
        return $this->isSuccessResponse();
    }
    
    /**
     * Get delivery report for a message
     * 
     * @param string $messageId The message ID to check status for
     * @param bool $useGet Whether to use GET method instead of POST
     * @return bool Success status
     */
    public function getDeliveryReport($messageId, $useGet = false) {
        if ($useGet) {
            $url = "https://sms.textsms.co.ke/api/services/getdlr/?apikey=" . urlencode($this->apiKey) . 
                   "&partnerID=" . urlencode($this->partnerId) . 
                   "&messageID=" . urlencode($messageId);
            
            $this->lastResponse = $this->executeGetRequest($url);
        } else {
            $payload = [
                'apikey' => $this->apiKey,
                'partnerID' => $this->partnerId,
                'messageID' => $messageId
            ];
            
            $this->lastResponse = $this->executePostRequest(
                "https://sms.textsms.co.ke/api/services/getdlr/",
                $payload
            );
        }
        
        return $this->isSuccessResponse();
    }
    
    /**
     * Get the last API response data
     * 
     * @return array|null Last API response or null if no request made
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
    
    /**
     * Get decoded JSON response data
     * 
     * @return array|null Decoded JSON data or null if invalid
     */
    public function getResponseData() {
        if (!$this->lastResponse || !isset($this->lastResponse['response'])) {
            return null;
        }
        
        $data = json_decode($this->lastResponse['response'], true);
        return $data;
    }
    
    /**
     * Get error message if any
     * 
     * @return string|null Error message or null if no error
     */
    public function getError() {
        if (!$this->lastResponse) {
            return 'No API request has been made';
        }
        
        if (!empty($this->lastResponse['error'])) {
            return $this->lastResponse['error'];
        }
        
        $data = $this->getResponseData();
        if (isset($data['responses'][0]['respose-code']) && $data['responses'][0]['respose-code'] != 200) {
            return $data['responses'][0]['response-description'] ?? 'Unknown API error';
        }
        
        return null;
    }
    
    /**
     * Format a phone number to international format
     * 
     * @param string $phoneNumber Phone number to format
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phoneNumber) {
        // Strip any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If it starts with 0, replace with 254
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        }
        
        // If it doesn't have country code and is Kenyan number (starts with 7 or 1), add 254
        if (strlen($phoneNumber) === 9 && (substr($phoneNumber, 0, 1) === '7' || substr($phoneNumber, 0, 1) === '1')) {
            $phoneNumber = '254' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Send SMS via GET method
     * 
     * @param string $phoneNumber Formatted phone number(s)
     * @param string $message Message content
     * @param string $senderId Sender ID/shortcode
     * @param string|null $scheduleTime Schedule time
     * @return bool Success status
     */
    private function sendSmsViaGet($phoneNumber, $message, $senderId, $scheduleTime = null) {
        $url = "https://sms.textsms.co.ke/api/services/sendsms/?" . 
               "apikey=" . urlencode($this->apiKey) . 
               "&partnerID=" . urlencode($this->partnerId) . 
               "&message=" . urlencode($message) . 
               "&shortcode=" . urlencode($senderId) . 
               "&mobile=" . urlencode($phoneNumber);
        
        if ($scheduleTime) {
            $url .= "&timeToSend=" . urlencode($scheduleTime);
        }
        
        $this->lastResponse = $this->executeGetRequest($url);
        return $this->isSuccessResponse();
    }
    
    /**
     * Send SMS via POST method
     * 
     * @param string $phoneNumber Formatted phone number(s)
     * @param string $message Message content
     * @param string $senderId Sender ID/shortcode
     * @param string|null $scheduleTime Schedule time
     * @return bool Success status
     */
    private function sendSmsViaPost($phoneNumber, $message, $senderId, $scheduleTime = null) {
        $payload = [
            'apikey' => $this->apiKey,
            'partnerID' => $this->partnerId,
            'message' => $message,
            'shortcode' => $senderId,
            'mobile' => $phoneNumber
        ];
        
        if ($scheduleTime) {
            $payload['timeToSend'] = $scheduleTime;
        }
        
        $this->lastResponse = $this->executePostRequest(
            "https://sms.textsms.co.ke/api/services/sendsms/",
            $payload
        );
        
        return $this->isSuccessResponse();
    }
    
    /**
     * Execute a GET request
     * 
     * @param string $url Full URL with parameters
     * @return array Response array
     */
    private function executeGetRequest($url) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        return [
            'response' => $response,
            'error' => $err,
            'info' => $info
        ];
    }
    
    /**
     * Execute a POST request
     * 
     * @param string $url API endpoint URL
     * @param array $payload Data to send
     * @return array Response array
     */
    private function executePostRequest($url, $payload) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        return [
            'response' => $response,
            'error' => $err,
            'info' => $info
        ];
    }
    
    /**
     * Check if the response indicates success
     * 
     * @return bool True if successful, false otherwise
     */
    private function isSuccessResponse() {
        if (empty($this->lastResponse) || !empty($this->lastResponse['error'])) {
            return false;
        }
        
        $data = $this->getResponseData();
        
        if (!$data) {
            return false;
        }
        
        if (isset($data['responses'][0]['respose-code'])) {
            return $data['responses'][0]['respose-code'] == 200;
        }
        
        return true;
    }
}

// Example usage (commented out for production):
/*
// Initialize with your credentials
$sms = new TextSmsApi('7624c6d424ae11de80b2d6611e69704a', '13361', 'TextSMS');

// Send a message
if ($sms->sendSms('254114669532', 'Hello! This is a test message.')) {
    echo "Message sent successfully!";
    
    // Get the message ID for tracking
    $response = $sms->getResponseData();
    if (isset($response['responses'][0]['messageid'])) {
        $messageId = $response['responses'][0]['messageid'];
        echo "Message ID: " . $messageId;
    }
} else {
    echo "Error: " . $sms->getError();
}

// Check balance
if ($sms->checkBalance()) {
    $balanceData = $sms->getResponseData();
    echo "Your balance: " . $balanceData['balance'] . " SMS credits";
} else {
    echo "Error checking balance: " . $sms->getError();
}
*/
