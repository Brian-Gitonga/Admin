<?php
/**
 * SMS Settings Operations
 * Functions to manage SMS settings in the database
 */

/**
 * Get SMS settings for a reseller
 * 
 * @param mysqli $conn Database connection
 * @param int $reseller_id Reseller ID
 * @return array|null SMS settings or null if not found
 */
function getSmsSettings($conn, $reseller_id) {
    // Check if the sms_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'sms_settings'");
    if ($table_check->num_rows == 0) {
        // Table doesn't exist, create it
        createSmsSettingsTable($conn);
        return getDefaultSmsSettings();
    }
    
    $query = "SELECT * FROM sms_settings WHERE reseller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return getDefaultSmsSettings();
}

/**
 * Get default SMS settings
 * 
 * @return array Default SMS settings
 */
function getDefaultSmsSettings() {
    return [
        'sms_provider' => 'textsms',
        'enable_sms' => 1,
        'textsms_api_key' => '1DL4pRCKKmg238fsCU6i7ZYEStP9fL9o4q',
        'textsms_partner_id' => '13361',
        'textsms_sender_id' => 'TextSMS',
        'textsms_use_get' => 1,
        'at_username' => '',
        'at_api_key' => '',
        'at_shortcode' => '',
        'hostpinnacle_userid' => 'qtro',
        'hostpinnacle_password' => '',
        'hostpinnacle_sender' => 'SENDER',
        'payment_template' => 'Thank you for your Purchasing {package} KSH {amount}. Your login credentials are as follows Username: {username} and Password: {password}. voucher code: {voucher}',
        'voucher_template' => 'Your voucher code is: {voucher}. Valid for {duration}.',
        'account_creation_template' => 'Welcome to our service! Your account has been created. Username: {username}, Password: {password}',
        'password_reset_template' => 'Your password has been reset. New password: {password}'
    ];
}

/**
 * Create the sms_settings table if it doesn't exist
 * 
 * @param mysqli $conn Database connection
 * @return bool True if successful, false otherwise
 */
function createSmsSettingsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `sms_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `reseller_id` int(11) NOT NULL,
        `sms_provider` enum('africas-talking', 'textsms', 'hostpinnacle') NOT NULL DEFAULT 'textsms',
        `enable_sms` tinyint(1) NOT NULL DEFAULT 1,
        
        /* TextSMS Credentials */
        `textsms_api_key` varchar(255) DEFAULT NULL,
        `textsms_partner_id` varchar(50) DEFAULT NULL,
        `textsms_sender_id` varchar(50) DEFAULT NULL,
        `textsms_use_get` tinyint(1) DEFAULT 1,
        
        /* Africa's Talking Credentials */
        `at_username` varchar(100) DEFAULT NULL,
        `at_api_key` varchar(255) DEFAULT NULL,
        `at_shortcode` varchar(50) DEFAULT NULL,
        
        /* Hostpinnacle Credentials */
        `hostpinnacle_userid` varchar(100) DEFAULT NULL,
        `hostpinnacle_password` varchar(255) DEFAULT NULL,
        `hostpinnacle_sender` varchar(50) DEFAULT NULL,

        /* SMS Templates */
        `payment_template` text DEFAULT NULL,
        `voucher_template` text DEFAULT NULL,
        `account_creation_template` text DEFAULT NULL,
        `password_reset_template` text DEFAULT NULL,
        
        /* General Settings */
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `reseller_id_UNIQUE` (`reseller_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    return $conn->query($sql);
}

/**
 * Save SMS settings
 * 
 * @param mysqli $conn Database connection
 * @param int $reseller_id Reseller ID
 * @param array $settings SMS settings
 * @return bool True if successful, false otherwise
 */
function saveSmsSettings($conn, $reseller_id, $settings) {
    // Check if the sms_settings table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'sms_settings'");
    if ($table_check->num_rows == 0) {
        // Table doesn't exist, create it
        createSmsSettingsTable($conn);
    }
    
    // Check if settings exist for this reseller
    $query = "SELECT id FROM sms_settings WHERE reseller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing settings
        $query = "UPDATE sms_settings SET 
            sms_provider = ?,
            enable_sms = ?,
            textsms_api_key = ?,
            textsms_partner_id = ?,
            textsms_sender_id = ?,
            textsms_use_get = ?,
            at_username = ?,
            at_api_key = ?,
            at_shortcode = ?,
            hostpinnacle_userid = ?,
            hostpinnacle_password = ?,
            hostpinnacle_sender = ?,
            payment_template = ?,
            voucher_template = ?,
            account_creation_template = ?,
            password_reset_template = ?
            WHERE reseller_id = ?";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "sisssisssssssssi",
            $settings['sms_provider'],
            $settings['enable_sms'],
            $settings['textsms_api_key'],
            $settings['textsms_partner_id'],
            $settings['textsms_sender_id'],
            $settings['textsms_use_get'],
            $settings['at_username'],
            $settings['at_api_key'],
            $settings['at_shortcode'],
            $settings['hostpinnacle_userid'],
            $settings['hostpinnacle_password'],
            $settings['hostpinnacle_sender'],
            $settings['payment_template'],
            $settings['voucher_template'],
            $settings['account_creation_template'],
            $settings['password_reset_template'],
            $reseller_id
        );
    } else {
        // Insert new settings
        $query = "INSERT INTO sms_settings (
            reseller_id,
            sms_provider,
            enable_sms,
            textsms_api_key,
            textsms_partner_id,
            textsms_sender_id,
            textsms_use_get,
            at_username,
            at_api_key,
            at_shortcode,
            hostpinnacle_userid,
            hostpinnacle_password,
            hostpinnacle_sender,
            payment_template,
            voucher_template,
            account_creation_template,
            password_reset_template
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            "isisssissssssssss",
            $reseller_id,
            $settings['sms_provider'],
            $settings['enable_sms'],
            $settings['textsms_api_key'],
            $settings['textsms_partner_id'],
            $settings['textsms_sender_id'],
            $settings['textsms_use_get'],
            $settings['at_username'],
            $settings['at_api_key'],
            $settings['at_shortcode'],
            $settings['hostpinnacle_userid'],
            $settings['hostpinnacle_password'],
            $settings['hostpinnacle_sender'],
            $settings['payment_template'],
            $settings['voucher_template'],
            $settings['account_creation_template'],
            $settings['password_reset_template']
        );
    }
    
    return $stmt->execute();
} 