<?php
/**
 * Africa's Talking SMS Gateway Integration
 * 
 * This file handles sending SMS messages through Africa's Talking API
 */

class AfricaTalkingSMS {
    private $username;
    private $apiKey;
    private $environment;
    private $sender;
    private $debug;
    
    /**
     * Initialize the SMS client
     * 
     * @param string $username Africa's Talking username
     * @param string $apiKey Africa's Talking API key
     * @param string $environment Either 'production' or 'sandbox'
     * @param string $sender Optional sender ID
     * @param bool $debug Whether to enable debug logging
     */
    public function __construct($username = null, $apiKey = null, $environment = 'production', $sender = null, $debug = false) {
        // Use provided credentials or load from config
        $this->username = $username ?: 'qtroisp';
        $this->apiKey = $apiKey ?: 'atsk_d4694d9cac17304ae0413ea231b7ea814601af20d64b529e4f459c8e483ba16772fc22c3';
        $this->environment = $environment;
        $this->sender = $sender;
        $this->debug = $debug;
        
        if ($this->debug) {
            error_log("Africa's Talking SMS initialized for username: {$this->username}");
        }
    }
    
    /**
     * Send an SMS message
     * 
     * @param string|array $recipients Phone number(s) to send to (with country code)
     * @param string $message The message to send
     * @return array Response with success status and details
     */
    public function sendSMS($recipients, $message) {
        if ($this->debug) {
            error_log("Preparing to send SMS via Africa's Talking");
        }
        
        // Format phone numbers if an array is provided
        if (is_array($recipients)) {
            $recipients = implode(',', $recipients);
        }
        
        // Prepare the data
        $postData = [
            'username' => $this->username,
            'to' => $recipients,
            'message' => $message
        ];
        
        // Add sender ID if provided
        if ($this->sender) {
            $postData['from'] = $this->sender;
        }
        
        // Determine the API URL based on environment
        $apiUrl = $this->environment === 'sandbox' 
            ? 'https://api.sandbox.africastalking.com/version1/messaging' 
            : 'https://api.africastalking.com/version1/messaging';
        
        // Initialize cURL session
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $this->apiKey
        ]);
        
        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log for debugging
        if ($this->debug) {
            error_log("SMS API response (HTTP $httpCode): " . $response);
            if ($error) {
                error_log("cURL Error: " . $error);
            }
        }
        
        // Handle failed requests
        if ($httpCode !== 201 || $error) {
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . ($error ?: "HTTP error $httpCode"),
                'data' => $response ? json_decode($response, true) : null
            ];
        }
        
        // Parse and return the response
        $responseData = json_decode($response, true);
        
        // Check if the API returned a successful response
        if (isset($responseData['SMSMessageData']) && 
            isset($responseData['SMSMessageData']['Recipients']) && 
            count($responseData['SMSMessageData']['Recipients']) > 0) {
            
            $recipients = $responseData['SMSMessageData']['Recipients'];
            $successCount = 0;
            
            // Count successful deliveries
            foreach ($recipients as $recipient) {
                if (isset($recipient['status']) && ($recipient['status'] === 'Success' || $recipient['statusCode'] == 101)) {
                    $successCount++;
                }
            }
            
            return [
                'success' => $successCount > 0,
                'message' => $successCount . ' out of ' . count($recipients) . ' messages sent successfully',
                'data' => $responseData
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to parse API response',
            'data' => $responseData
        ];
    }
    
    /**
     * Format phone number to match Africa's Talking requirements
     * 
     * @param string $phoneNumber Phone number to format
     * @param string $defaultCountryCode Default country code if not provided (e.g., '254' for Kenya)
     * @return string Formatted phone number
     */
    public static function formatPhoneNumber($phoneNumber, $defaultCountryCode = '254') {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
        
        // Check if the number already has a country code
        if (substr($phoneNumber, 0, 1) === '+') {
            return $phoneNumber; // Already has a + prefix
        }
        
        // Handle numbers starting with a leading zero
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = $defaultCountryCode . substr($phoneNumber, 1);
        }
        
        // If no country code is present and number doesn't start with 0, add default
        if (strlen($phoneNumber) <= 10) {
            $phoneNumber = $defaultCountryCode . $phoneNumber;
        }
        
        // Add a plus sign if needed
        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = '+' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
}
?> 