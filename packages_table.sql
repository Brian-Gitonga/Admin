-- Create packages table with reseller_id associations
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL, -- Reseller/owner of this package
    name VARCHAR(100) NOT NULL,
    type ENUM('hotspot', 'pppoe', 'data-plan') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    upload_speed INT NOT NULL, -- in Mbps
    download_speed INT NOT NULL, -- in Mbps
    duration VARCHAR(50) NOT NULL, -- e.g., "1 Hour", "30 Days"
    duration_in_minutes INT NOT NULL, -- Duration in minutes for calculations
    device_limit INT NOT NULL DEFAULT 1,
    data_limit INT NULL, -- in MB, NULL for unlimited
    is_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
);

-- Insert sample data (optional, you can remove this in production)
INSERT INTO packages (reseller_id, name, type, price, upload_speed, download_speed, duration, duration_in_minutes, device_limit, data_limit, is_enabled)
VALUES 
(1, '1Hr internet', 'hotspot', 10.00, 2, 2, '1 Hour 30 Minutes', 90, 1, NULL, TRUE),
(1, '3Hr internet', 'hotspot', 25.00, 2, 2, '3 Hours', 180, 1, NULL, TRUE),
(1, 'A Half day internet', 'hotspot', 50.00, 2, 2, '12 Hours', 720, 1, NULL, TRUE),
(1, '2Hr internet Mbogi', 'hotspot', 30.00, 2, 3, '2 Hours', 120, 2, NULL, TRUE),
(1, 'Home Basic', 'pppoe', 1500.00, 5, 5, '30 Days', 43200, 3, NULL, TRUE),
(1, 'Home Plus', 'pppoe', 2500.00, 10, 10, '30 Days', 43200, 5, NULL, TRUE),
(1, '5GB Data Plan', 'data-plan', 500.00, 3, 3, '30 Days', 43200, 1, 5120, TRUE),
(1, '10GB Data Plan', 'data-plan', 800.00, 5, 5, '30 Days', 43200, 1, 10240, TRUE); 