<?php
// This file contains functions to handle M-Pesa settings operations

/**
 * Get M-Pesa settings for a reseller
 * 
 * @param mysqli $conn - Database connection
 * @param int $reseller_id - Reseller ID
 * @return array|false - Settings array or false if not found
 */
function getMpesaSettings($conn, $reseller_id) {
    $stmt = $conn->prepare("SELECT * FROM resellers_mpesa_settings WHERE reseller_id = ? LIMIT 1");
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        
        // Check if the settings have valid API credentials
        // If consumer_key or consumer_secret is empty, fall back to system defaults
        if (empty($settings['paybill_consumer_key']) || empty($settings['paybill_consumer_secret'])) {
            error_log("M-Pesa: Reseller $reseller_id has incomplete credentials, using system defaults for API calls");
            
            // Get system defaults
            $systemCreds = getSystemMpesaApiCredentials();
            
            // Merge: keep reseller's payment_gateway and phone settings, but use system API credentials
            $settings['paybill_consumer_key'] = $systemCreds['consumer_key'];
            $settings['paybill_consumer_secret'] = $systemCreds['consumer_secret'];
            $settings['paybill_shortcode'] = $systemCreds['shortcode'];
            $settings['paybill_passkey'] = $systemCreds['passkey'];
            
            // Also set till credentials to system defaults if empty
            if (empty($settings['till_consumer_key']) || empty($settings['till_consumer_secret'])) {
                $settings['till_consumer_key'] = $systemCreds['consumer_key'];
                $settings['till_consumer_secret'] = $systemCreds['consumer_secret'];
                $settings['till_shortcode'] = $systemCreds['shortcode'];
                $settings['till_passkey'] = $systemCreds['passkey'];
            }
            
            // Use system callback URL if reseller's is invalid
            if (empty($settings['callback_url']) ||
                strpos($settings['callback_url'], 'mydomain.com') !== false ||
                strpos($settings['callback_url'], 'localhost') !== false) {
                $settings['callback_url'] = $systemCreds['callback_url'];
            }
        }
        
        return $settings;
    }
    
    // If no settings found at all, return system defaults for testing
    error_log("M-Pesa: No settings found for reseller $reseller_id, using system defaults");
    return getDefaultMpesaSettings();
}

/**
 * Get blank M-Pesa settings for new resellers
 * Each user should start with blank fields, not test values
 * 
 * @return array - Blank settings
 */
function getBlankMpesaSettings() {
    return [
        'payment_gateway' => 'phone',
        'environment' => 'sandbox',
        'is_active' => true,
        'mpesa_phone' => '',
        'paybill_number' => '',
        'paybill_shortcode' => '',
        'paybill_passkey' => '',
        'paybill_consumer_key' => '',
        'paybill_consumer_secret' => '',
        'till_number' => '',
        'till_shortcode' => '',
        'till_passkey' => '',
        'till_consumer_key' => '',
        'till_consumer_secret' => '',
        'callback_url' => ''
    ];
}

/**
 * Get default test M-Pesa settings
 * ONLY used for internal testing purposes, not for production
 * 
 * @return array - Default test settings
 */
function getDefaultMpesaSettings() {
    return [
        'payment_gateway' => 'phone',
        'environment' => 'sandbox',
        'is_active' => true,
        'mpesa_phone' => '0700000000',
        'paybill_number' => '174379',
        'paybill_shortcode' => '174379',
        'paybill_passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'paybill_consumer_key' => 'bAoiO0bYMLsAHDgzGSGVMnpSAxSUuCMEfWkrrAOK1MZJNAcA',
        'paybill_consumer_secret' => '2idZFLPp26Du8JdF9SB3nLpKrOJO67qDIkvICkkVl7OhADTQCb0Oga5wNgzu1xQx',
        'till_number' => '',
        'till_shortcode' => '',
        'till_passkey' => '',
        'till_consumer_key' => '',
        'till_consumer_secret' => '',
        'callback_url' => 'https://7d2fcfdeb690.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php'
    ];
}

/**
 * Save M-Pesa settings for a reseller
 * 
 * @param mysqli $conn - Database connection
 * @param int $reseller_id - Reseller ID
 * @param array $settings - Settings data
 * @return bool - Success status
 */
function saveMpesaSettings($conn, $reseller_id, $settings) {
    try {
        // We no longer need to check if the reseller exists since this is handled in save_mpesa_settings.php
        
        // Check if settings already exist for this reseller
        $stmt = $conn->prepare("SELECT id, payment_gateway FROM resellers_mpesa_settings WHERE reseller_id = ?");
        $stmt->bind_param("i", $reseller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Convert boolean to integer (1 or 0) for database
        $is_active = $settings['is_active'] ? 1 : 0;
        
        // Log if we're switching to or from phone payment
        $was_phone_payment = false;
        
        if ($result->num_rows > 0) {
            // Update existing settings
            $row = $result->fetch_assoc();
            $settings_id = $row['id'];
            $was_phone_payment = ($row['payment_gateway'] === 'phone');
            $is_switching_to_phone = (!$was_phone_payment && $settings['payment_gateway'] === 'phone');
            
            if ($is_switching_to_phone) {
                error_log("Switching from " . $row['payment_gateway'] . " to phone payment for reseller $reseller_id - ensuring API credentials are updated");
            }
            
            $stmt = $conn->prepare("UPDATE resellers_mpesa_settings SET 
                payment_gateway = ?,
                environment = ?,
                is_active = ?,
                mpesa_phone = ?,
                paybill_number = ?,
                paybill_shortcode = ?,
                paybill_passkey = ?,
                paybill_consumer_key = ?,
                paybill_consumer_secret = ?,
                till_number = ?,
                till_shortcode = ?,
                till_passkey = ?,
                till_consumer_key = ?,
                till_consumer_secret = ?,
                callback_url = ?
                WHERE id = ?");
            
            $stmt->bind_param("ssissssssssssssi", 
                $settings['payment_gateway'],
                $settings['environment'],
                $is_active,
                $settings['mpesa_phone'],
                $settings['paybill_number'],
                $settings['paybill_shortcode'],
                $settings['paybill_passkey'],
                $settings['paybill_consumer_key'],
                $settings['paybill_consumer_secret'],
                $settings['till_number'],
                $settings['till_shortcode'],
                $settings['till_passkey'],
                $settings['till_consumer_key'],
                $settings['till_consumer_secret'],
                $settings['callback_url'],
                $settings_id
            );
        } else {
            // Insert new settings
            error_log("Creating new M-Pesa settings for reseller $reseller_id with payment_gateway: " . $settings['payment_gateway']);
            
            $stmt = $conn->prepare("INSERT INTO resellers_mpesa_settings (
                reseller_id,
                payment_gateway,
                environment,
                is_active,
                mpesa_phone,
                paybill_number,
                paybill_shortcode,
                paybill_passkey,
                paybill_consumer_key,
                paybill_consumer_secret,
                till_number,
                till_shortcode,
                till_passkey,
                till_consumer_key,
                till_consumer_secret,
                callback_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("issisissssssssss", 
                $reseller_id,
                $settings['payment_gateway'],
                $settings['environment'],
                $is_active,
                $settings['mpesa_phone'],
                $settings['paybill_number'],
                $settings['paybill_shortcode'],
                $settings['paybill_passkey'],
                $settings['paybill_consumer_key'],
                $settings['paybill_consumer_secret'],
                $settings['till_number'],
                $settings['till_shortcode'],
                $settings['till_passkey'],
                $settings['till_consumer_key'],
                $settings['till_consumer_secret'],
                $settings['callback_url']
            );
        }
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Error executing statement: " . $stmt->error);
        }
        
        return $result;
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Error saving M-Pesa settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the system's predefined M-Pesa API credentials
 * These are used for processing payments on behalf of users 
 * Users cannot modify these values
 * 
 * @return array - System's M-Pesa API credentials
 */
function getSystemMpesaApiCredentials() {
    return [
        'shortcode' => '174379', // Replace with your actual shortcode
        'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919', // Replace with your actual passkey
        'consumer_key' => 'bAoiO0bYMLsAHDgzGSGVMnpSAxSUuCMEfWkrrAOK1MZJNAcA', // Replace with your actual consumer key
        'consumer_secret' => '2idZFLPp26Du8JdF9SB3nLpKrOJO67qDIkvICkkVl7OhADTQCb0Oga5wNgzu1xQx', // Replace with your actual consumer secret
        'callback_url' => 'https://7d2fcfdeb690.ngrok-free.app/SAAS/Wifi%20Billiling%20system/Admin/mpesa_callback.php' // Replace with your actual callback URL
    ];
}

/**
 * Get M-Pesa credentials based on the gateway type and environment
 * Used by payment processing scripts
 * 
 * @param mysqli $conn - Database connection
 * @param int $reseller_id - Reseller ID
 * @return array - Credentials for payment processing
 */
function getMpesaCredentials($conn, $reseller_id) {
    $settings = getMpesaSettings($conn, $reseller_id);
    
    $credentials = [
        'payment_gateway' => $settings['payment_gateway'],
        'environment' => $settings['environment'],
        'callback_url' => $settings['callback_url']
    ];
    
    // Add appropriate credentials based on payment gateway type
    switch ($settings['payment_gateway']) {
        case 'phone':
            $credentials['phone_number'] = $settings['mpesa_phone'];
            $credentials['consumer_key'] = $settings['paybill_consumer_key']; // Use paybill credentials for API
            $credentials['consumer_secret'] = $settings['paybill_consumer_secret'];
            $credentials['business_shortcode'] = $settings['paybill_shortcode'];
            $credentials['passkey'] = $settings['paybill_passkey'];
            break;

        case 'paybill':
            $credentials['paybill_number'] = $settings['paybill_number'];
            $credentials['consumer_key'] = $settings['paybill_consumer_key'];
            $credentials['consumer_secret'] = $settings['paybill_consumer_secret'];
            $credentials['business_shortcode'] = $settings['paybill_shortcode'];
            $credentials['passkey'] = $settings['paybill_passkey'];
            break;

        case 'till':
            $credentials['till_number'] = $settings['till_number'];
            $credentials['consumer_key'] = $settings['till_consumer_key'];
            $credentials['consumer_secret'] = $settings['till_consumer_secret'];
            $credentials['business_shortcode'] = $settings['till_shortcode'];
            $credentials['passkey'] = $settings['till_passkey'];
            break;

        case 'paystack':
            $credentials['secret_key'] = $settings['paystack_secret_key'];
            $credentials['public_key'] = $settings['paystack_public_key'];
            $credentials['paystack_email'] = $settings['paystack_email'];
            break;
    }
    
    return $credentials;
}
?> 