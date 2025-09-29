-- Create the hotspot_settings table
CREATE TABLE IF NOT EXISTS hotspot_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    portal_name VARCHAR(100) NOT NULL,
    redirect_url VARCHAR(255) NOT NULL DEFAULT 'https://www.google.com',
    portal_theme ENUM('dark', 'light', 'blue', 'green') NOT NULL DEFAULT 'dark',
    enable_free_trial BOOLEAN DEFAULT FALSE,
    free_trial_package VARCHAR(50) DEFAULT NULL,
    free_trial_limit INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- Create the free_trial_usage table to track users who have used free trials
CREATE TABLE IF NOT EXISTS free_trial_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    mac_address VARCHAR(17) NULL, -- Device MAC address (optional now)
    ip_address VARCHAR(45) NOT NULL, -- IPv4 or IPv6 address
    phone_number VARCHAR(20) NOT NULL, -- User's phone number for SMS voucher delivery
    voucher_code VARCHAR(50) NULL, -- Voucher code sent to the user
    usage_count INT DEFAULT 1,
    first_usage_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_usage_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    INDEX mac_address_idx (mac_address),
    UNIQUE INDEX phone_number_idx (reseller_id, phone_number)
);

-- Insert default settings for the first reseller if not exists
INSERT INTO hotspot_settings (reseller_id, portal_name, redirect_url, portal_theme, enable_free_trial)
SELECT 1, 'Qtro Hotspot', 'https://www.youtube.com/', 'dark', FALSE
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM hotspot_settings WHERE reseller_id = 1); 