<?php
/**
 * SMS Service Interface
 * 
 * This file provides a unified interface for sending SMS messages
 * through different providers.
 */

require_once 'africatalking.php';

class SMSService {
    private $provider;
    private $defaultCountryCode;
    private $debug;
    
    /**
     * Initialize the SMS service
     * 
     * @param string $provider The SMS provider to use ('africatalking', 'twilio', etc.)
     * @param array $config Configuration for the selected provider
     * @param string $defaultCountryCode Default country code for phone numbers
     * @param bool $debug Whether to enable debug mode
     */
    public function __construct($provider = 'africatalking', $config = [], $defaultCountryCode = '254', $debug = false) {
        $this->provider = $provider;
        $this->defaultCountryCode = $defaultCountryCode;
        $this->debug = $debug;
        
        // Initialize the selected provider
        switch ($this->provider) {
            case 'africatalking':
                $this->providerInstance = new AfricaTalkingSMS(
                    $config['username'] ?? null,
                    $config['apiKey'] ?? null,
                    $config['environment'] ?? 'production',
                    $config['sender'] ?? null,
                    $this->debug
                );
                break;
                
            // Add more providers here as needed
            // case 'twilio':
            //     $this->providerInstance = new TwilioSMS(...);
            //     break;
                
            default:
                throw new Exception("Unsupported SMS provider: {$this->provider}");
        }
    }
    
    /**
     * Send an SMS message
     * 
     * @param string|array $recipients Phone number(s) to send to
     * @param string $message The message to send
     * @return array Response with success status and details
     */
    public function sendSMS($recipients, $message) {
        // Format phone numbers
        if (is_array($recipients)) {
            $formattedRecipients = array_map(function($number) {
                return $this->formatPhoneNumber($number);
            }, $recipients);
        } else {
            $formattedRecipients = $this->formatPhoneNumber($recipients);
        }
        
        // Log the sending attempt
        if ($this->debug) {
            error_log("Sending SMS via {$this->provider} to: " . (is_array($formattedRecipients) ? implode(',', $formattedRecipients) : $formattedRecipients));
            error_log("Message content: {$message}");
        }
        
        // Send the message using the selected provider
        return $this->providerInstance->sendSMS($formattedRecipients, $message);
    }
    
    /**
     * Send a voucher code via SMS
     * 
     * @param string $phoneNumber Recipient's phone number
     * @param string $voucherCode The voucher code to send
     * @param array $packageDetails Details about the voucher package
     * @return array Response with success status and details
     */
    public function sendVoucherSMS($phoneNumber, $voucherCode, $packageDetails = []) {
        // Build a message with the voucher details
        $message = "Your WiFi voucher code is: {$voucherCode}\n";
        
        if (!empty($packageDetails)) {
            if (isset($packageDetails['name'])) {
                $message .= "Package: {$packageDetails['name']}\n";
            }
            
            if (isset($packageDetails['duration'])) {
                $message .= "Duration: {$packageDetails['duration']}\n";
            }
            
            if (isset($packageDetails['price'])) {
                $message .= "Price: {$packageDetails['price']}\n";
            }
        }
        
        $message .= "Thank you for choosing our service!";
        
        // Send the SMS
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Format a phone number according to the requirements
     * 
     * @param string $phoneNumber The phone number to format
     * @return string Formatted phone number
     */
    public function formatPhoneNumber($phoneNumber) {
        switch ($this->provider) {
            case 'africatalking':
                return AfricaTalkingSMS::formatPhoneNumber($phoneNumber, $this->defaultCountryCode);
                
            // Add more providers as needed
            
            default:
                // Default formatting
                $phoneNumber = preg_replace('/\D/', '', $phoneNumber);
                
                // Add country code if not present
                if (strlen($phoneNumber) <= 10) {
                    $phoneNumber = $this->defaultCountryCode . $phoneNumber;
                }
                
                return '+' . $phoneNumber;
        }
    }
}
?> 