-- Create a table to store M-Pesa transactions
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
  `voucher_id` varchar(20) NOT NULL,
  `voucher_code` varchar(20) NOT NULL,
  `result_description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkout_request_id` (`checkout_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a table for vouchers
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