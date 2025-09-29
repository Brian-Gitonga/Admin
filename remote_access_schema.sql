-- Remote Access Ordering System Database Schema
-- This file contains the SQL structure for the remote access ordering feature

-- Create remote_access_requests table
CREATE TABLE IF NOT EXISTS `remote_access_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reseller_id` int(11) NOT NULL,
  `router_id` int(11) NOT NULL,
  `request_status` enum('ordered','rejected','approved') NOT NULL DEFAULT 'ordered',
  `admin_comments` text DEFAULT NULL,
  `remote_username` varchar(100) DEFAULT NULL,
  `remote_password` varchar(255) DEFAULT NULL,
  `dns_name` varchar(255) DEFAULT NULL,
  `remote_port` int(11) DEFAULT 8291,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reseller_id` (`reseller_id`),
  KEY `idx_router_id` (`router_id`),
  KEY `idx_request_status` (`request_status`),
  CONSTRAINT `fk_remote_access_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_remote_access_router` FOREIGN KEY (`router_id`) REFERENCES `hotspots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add DNS name field to hotspots table if it doesn't exist
ALTER TABLE `hotspots` 
ADD COLUMN IF NOT EXISTS `dns_name` varchar(255) DEFAULT NULL AFTER `api_port`,
ADD COLUMN IF NOT EXISTS `remote_access_enabled` tinyint(1) DEFAULT 0 AFTER `dns_name`;

-- Create admin_users table for tracking who approved requests (if not exists)
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123 - should be changed in production)
INSERT IGNORE INTO `admin_users` (`username`, `email`, `password_hash`, `role`) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Create remote_access_logs table for tracking access attempts
CREATE TABLE IF NOT EXISTS `remote_access_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `reseller_id` int(11) NOT NULL,
  `router_id` int(11) NOT NULL,
  `access_type` enum('ssh','api','web') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_reseller_id` (`reseller_id`),
  KEY `idx_router_id` (`router_id`),
  KEY `idx_accessed_at` (`accessed_at`),
  CONSTRAINT `fk_access_logs_request` FOREIGN KEY (`request_id`) REFERENCES `remote_access_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_logs_reseller` FOREIGN KEY (`reseller_id`) REFERENCES `resellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_access_logs_router` FOREIGN KEY (`router_id`) REFERENCES `hotspots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_remote_access_created_at` ON `remote_access_requests` (`created_at`);
CREATE INDEX IF NOT EXISTS `idx_remote_access_updated_at` ON `remote_access_requests` (`updated_at`);
CREATE INDEX IF NOT EXISTS `idx_hotspots_dns_name` ON `hotspots` (`dns_name`);

-- Sample data for testing (optional - remove in production)
-- INSERT INTO `remote_access_requests` (`reseller_id`, `router_id`, `request_status`, `admin_comments`) 
-- VALUES (1, 1, 'ordered', 'Initial test request');

-- Create view for easy access to request details
CREATE OR REPLACE VIEW `remote_access_requests_view` AS
SELECT 
    r.id,
    r.reseller_id,
    r.router_id,
    r.request_status,
    r.admin_comments,
    r.remote_username,
    r.remote_password,
    r.dns_name,
    r.remote_port,
    r.created_at,
    r.updated_at,
    r.approved_at,
    r.approved_by,
    res.business_name as reseller_business_name,
    res.email as reseller_email,
    res.phone as reseller_phone,
    h.name as router_name,
    h.router_ip,
    h.status as router_status,
    a.username as approved_by_username
FROM remote_access_requests r
LEFT JOIN resellers res ON r.reseller_id = res.id
LEFT JOIN hotspots h ON r.router_id = h.id
LEFT JOIN admin_users a ON r.approved_by = a.id;

-- Grant necessary permissions (adjust as needed for your setup)
-- GRANT SELECT, INSERT, UPDATE ON remote_access_requests TO 'your_app_user'@'localhost';
-- GRANT SELECT ON remote_access_requests_view TO 'your_app_user'@'localhost';
-- GRANT SELECT, INSERT ON remote_access_logs TO 'your_app_user'@'localhost';

-- Comments for documentation
-- remote_access_requests.request_status:
--   - 'ordered': Initial state when user requests remote access
--   - 'rejected': Admin has rejected the request (admin_comments should explain why)
--   - 'approved': Admin has approved and provided credentials

-- remote_access_requests.remote_username/remote_password:
--   - Only populated when status is 'approved'
--   - These are the credentials the user will use for remote access

-- remote_access_requests.dns_name:
--   - Optional DNS name for easier access (e.g., router1.company.com)
--   - Can be used instead of IP address for remote connections

-- hotspots.remote_access_enabled:
--   - Flag to indicate if remote access is currently enabled for this router
--   - Can be used to temporarily disable access without deleting the request
