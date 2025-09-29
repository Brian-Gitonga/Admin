-- API Migration Script for Batch Voucher API
-- This script adds API functionality to the existing WiFi billing system
-- It works with the existing vouchers table structure from database.sql

-- Add API key column to resellers table (if not exists)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'resellers'
     AND column_name = 'api_key'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE resellers ADD COLUMN api_key VARCHAR(255) UNIQUE NULL AFTER password',
    'SELECT "api_key column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for faster API key lookups (if not exists)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_name = 'resellers'
     AND index_name = 'idx_api_key'
     AND table_schema = DATABASE()) = 0,
    'CREATE INDEX idx_api_key ON resellers(api_key)',
    'SELECT "idx_api_key index already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create API logs table for tracking API usage
CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    endpoint VARCHAR(100) NOT NULL,
    method VARCHAR(10) NOT NULL,
    request_data TEXT,
    response_data TEXT,
    status_code INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    INDEX idx_reseller_date (reseller_id, created_at),
    INDEX idx_endpoint (endpoint),
    INDEX idx_status (status_code)
);

-- Add router_id column to vouchers table if it doesn't exist
-- This links vouchers to specific hotspots/routers
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'router_id'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN router_id INT NULL AFTER reseller_id',
    'SELECT "router_id column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add API-specific columns to vouchers table for batch API functionality
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'profile'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN profile VARCHAR(100) NULL AFTER password',
    'SELECT "profile column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'validity'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN validity VARCHAR(50) NULL AFTER profile',
    'SELECT "validity column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'comment'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN comment VARCHAR(255) NULL AFTER validity',
    'SELECT "comment column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'metadata'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN metadata JSON NULL AFTER comment',
    'SELECT "metadata column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_name = 'vouchers'
     AND column_name = 'api_created'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD COLUMN api_created BOOLEAN DEFAULT FALSE AFTER metadata',
    'SELECT "api_created column already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Clean up invalid router_id values before adding foreign key constraint
-- Set router_id to NULL for any vouchers that reference non-existent hotspots
UPDATE vouchers
SET router_id = NULL
WHERE router_id IS NOT NULL
AND router_id NOT IN (SELECT id FROM hotspots);

-- Add foreign key constraint for router_id to link vouchers to hotspots
-- This ensures vouchers are properly linked to routers that belong to the reseller
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
     WHERE table_name = 'vouchers'
     AND column_name = 'router_id'
     AND referenced_table_name = 'hotspots'
     AND table_schema = DATABASE()) = 0,
    'ALTER TABLE vouchers ADD FOREIGN KEY (router_id) REFERENCES hotspots(id) ON DELETE SET NULL',
    'SELECT "router_id foreign key already exists" as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create a view for API statistics (helps with dashboard display)
CREATE OR REPLACE VIEW api_stats AS
SELECT
    r.id as reseller_id,
    COALESCE(r.business_name, r.full_name) as reseller_name,
    COUNT(al.id) as total_requests,
    COUNT(CASE WHEN al.status_code = 200 THEN 1 END) as successful_requests,
    COUNT(CASE WHEN al.status_code != 200 THEN 1 END) as failed_requests,
    COUNT(CASE WHEN DATE(al.created_at) = CURDATE() THEN 1 END) as requests_today,
    COUNT(CASE WHEN al.endpoint = '/api/vouchers' THEN 1 END) as voucher_requests,
    MAX(al.created_at) as last_request_at
FROM resellers r
LEFT JOIN api_logs al ON r.id = al.reseller_id
WHERE r.api_key IS NOT NULL
GROUP BY r.id, COALESCE(r.business_name, r.full_name);

SELECT 'API migration completed successfully! You can now use the batch voucher API.' as message;
