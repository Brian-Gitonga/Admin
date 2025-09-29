-- Add this to your database.sql file if any of these tables are missing

-- Table for tracking payouts to resellers
-- This is referenced in the admin_payouts.php file
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

-- If you want to modify your existing transactions table to ensure proper tracking
-- You can add this index to improve query performance
ALTER TABLE `transactions` ADD INDEX `idx_timestamp` (`timestamp`);
ALTER TABLE `transactions` ADD INDEX `idx_hotspot_id` (`hotspot_id`);

-- Add stored procedure to automatically set payouts to overdue
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `update_overdue_payouts`()
BEGIN
  UPDATE payouts 
  SET status = 'overdue' 
  WHERE status = 'pending' 
  AND due_date < CURDATE();
END //
DELIMITER ;

-- Event to run the stored procedure daily
DELIMITER //
CREATE EVENT IF NOT EXISTS `daily_overdue_payout_check`
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE
DO
BEGIN
  CALL update_overdue_payouts();
END //
DELIMITER ;

-- Sample trigger to update payouts when a new transaction is added
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `after_transaction_insert`
AFTER INSERT ON `transactions`
FOR EACH ROW
BEGIN
  -- Optional: Add logic here to update current payout amounts
  -- This would be more complex and depend on your specific business logic
END //
DELIMITER ; 