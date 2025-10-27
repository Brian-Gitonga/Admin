-- Create the database
CREATE DATABASE IF NOT EXISTS billing_system;
USE billing_system;

-- 1. Admin table (you, the system owner)
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    profile_photo VARCHAR(255),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Resellers (hotspot owners)
CREATE TABLE resellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    payment_interval ENUM('weekly', 'monthly') NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'expired') DEFAULT 'pending',
    approval_required BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    approved_at DATETIME,
    approved_by INT,
    FOREIGN KEY (approved_by) REFERENCES admin(id)
);

-- 3. Subscription plans (for resellers)
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Reseller subscriptions (payments to you)
CREATE TABLE reseller_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    plan_id INT NOT NULL,
    start_date DATETIME NOT NULL,
    expiry_date DATETIME NOT NULL,
    status ENUM('active', 'pending', 'expired', 'unpaid') DEFAULT 'pending',
    last_payment_date DATETIME,
    amount_paid DECIMAL(10,2),
    payment_method ENUM('mpesa', 'bank'),
    transaction_id VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
);

-- 4.2 resellers_mpesa_settings
CREATE TABLE resellers_mpesa_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    payment_gateway ENUM('phone', 'paybill', 'till') NOT NULL DEFAULT 'phone',
    environment ENUM('sandbox', 'live') NOT NULL DEFAULT 'sandbox',
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Phone number payment settings
    mpesa_phone VARCHAR(20),
    
    -- Paybill payment settings
    paybill_number VARCHAR(20),
    paybill_shortcode VARCHAR(20),
    paybill_passkey VARCHAR(255),
    paybill_consumer_key VARCHAR(255),
    paybill_consumer_secret VARCHAR(255),
    
    -- Till payment settings
    till_number VARCHAR(20),
    till_shortcode VARCHAR(20),
    till_passkey VARCHAR(255),
    till_consumer_key VARCHAR(255),
    till_consumer_secret VARCHAR(255),
    
    -- Common settings
    callback_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- Insert default M-Pesa settings for testing
INSERT INTO resellers_mpesa_settings (
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
    callback_url
) VALUES (
    1, -- This will need to be adjusted to an actual reseller ID when available
    'phone',
    'sandbox',
    TRUE,
    '0700000000',
    '174379',
    '174379',
    'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    'bAoiO0bYMLsAHDgzGSGVMnpSAxSUuCMEfWkrrAOK1MZJNAcA',
    '2idZFLPp26Du8JdF9SB3nLpKrOJO67qDIkvICkkVl7OhADTQCb0Oga5wNgzu1xQx',
    'https://mydomain.com/mpesa_callback.php'
);

-- 4.5 packages (packages that resellers offer to their customers)
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    price DECIMAL(10,2) NOT NULL,
    duration VARCHAR(50) NOT NULL,
    type ENUM('daily', 'weekly', 'monthly') NOT NULL,
    data_limit VARCHAR(50),
    speed VARCHAR(50),
    device_limit INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- 5. Hotspots (reseller's routers)
CREATE TABLE hotspots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    router_ip VARCHAR(50) NOT NULL,
    router_username VARCHAR(50) NOT NULL,
    router_password VARCHAR(255) NOT NULL,
    api_port INT DEFAULT 80,
    location VARCHAR(255),
    is_active BOOLEAN DEFAULT FALSE,
    status ENUM('online', 'offline') DEFAULT 'offline',
    last_checked TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- 6. End users (WiFi customers)
CREATE TABLE end_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotspot_id INT NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    current_plan VARCHAR(50),
    expiry_date DATETIME,
    data_used_mb INT DEFAULT 0,
    status ENUM('active', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotspot_id) REFERENCES hotspots(id) ON DELETE CASCADE
);

-- 7. Transactions (M-Pesa payments from end users)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    end_user_id INT,
    hotspot_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    mpesa_code VARCHAR(50) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (end_user_id) REFERENCES end_users(id) ON DELETE SET NULL,
    FOREIGN KEY (hotspot_id) REFERENCES hotspots(id) ON DELETE CASCADE
);

-- 8. Payouts (to resellers)
-- Table for tracking payouts to resellers
CREATE TABLE IF NOT EXISTS `payouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reseller_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','overdue','paid') DEFAULT 'pending',
  `payment_method` enum('mpesa','bank') DEFAULT 'mpesa',
  `transaction_id` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reseller_id` (`reseller_id`),
  KEY `paid_by` (`paid_by`),
  CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payouts_ibfk_2` FOREIGN KEY (`paid_by`) REFERENCES `admin` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. M-Pesa settings (Daraja API config)
CREATE TABLE mpesa_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_shortcode VARCHAR(20) NOT NULL,
    passkey VARCHAR(255) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL,
    callback_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 10. System notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    recipient_type ENUM('admin', 'reseller') NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Create a table to store M-Pesa transactions
CREATE TABLE IF NOT EXISTS `mpesa_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checkout_request_id` varchar(50) NOT NULL,
  `merchant_request_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `package_id` int(11) NOT NULL,
  `package_name` varchar(100) NOT NULL,
  `reseller_id` int(11) NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `mpesa_receipt` varchar(50) DEFAULT NULL,
  `transaction_date` varchar(50) DEFAULT NULL,
  `result_code` int(11) DEFAULT NULL,
  `result_description` varchar(255) DEFAULT NULL,
  `voucher_code` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkout_request_id` (`checkout_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Vouchers
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `package_id` int(11) NOT NULL,
  `reseller_id` int(11) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `status` enum('active','used','expired') NOT NULL DEFAULT 'active',
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 

-- Insert initial admin (you)
INSERT INTO admin (username, email, password) 
VALUES ('admin', 'your@email.com', '$2y$10$hashedpassword');


-- 13. SMS Settings
CREATE TABLE `sms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reseller_id` int(11) NOT NULL,
  `sms_provider` enum('africas-talking', 'textsms', 'hostpinnacle') NOT NULL DEFAULT 'textsms',
  
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
  
  /* General Settings */
  `enable_sms` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reseller_id_UNIQUE` (`reseller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--14 subscription request
CREATE TABLE IF NOT EXISTS subscription_requests (
    id INT(11) NOT NULL AUTO_INCREMENT,
    reseller_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    requested_at DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT,
    processed_at DATETIME,
    processed_by INT(11),
    PRIMARY KEY (id),
    FOREIGN KEY (reseller_id) REFERENCES resellers(id)
)