<?php
// Setup Test Data for Paystack Workflow
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Setting Up Test Data for Paystack Workflow</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "billing_system";

// Create connection
$conn = new mysqli($servername, $username, $password);

echo "<div class='section'>";
echo "<h2>1. Database Connection and Setup</h2>";

// Check connection
if ($conn->connect_error) {
    echo "<p class='error'>❌ Connection failed: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p class='success'>✅ Connected to MySQL server</p>";
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Database '$dbname' created or already exists</p>";
} else {
    echo "<p class='error'>❌ Error creating database: " . $conn->error . "</p>";
}

// Select the database
$conn->select_db($dbname);
echo "<p class='success'>✅ Selected database '$dbname'</p>";
echo "</div>";

// Create tables
echo "<div class='section'>";
echo "<h2>2. Creating Required Tables</h2>";

// Create resellers table
$sql = "CREATE TABLE IF NOT EXISTS resellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    api_key VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Resellers table created</p>";
} else {
    echo "<p class='error'>❌ Error creating resellers table: " . $conn->error . "</p>";
}

// Create packages table
$sql = "CREATE TABLE IF NOT EXISTS packages (
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
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Packages table created</p>";
} else {
    echo "<p class='error'>❌ Error creating packages table: " . $conn->error . "</p>";
}

// Create hotspots table
$sql = "CREATE TABLE IF NOT EXISTS hotspots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    router_ip VARCHAR(50) NOT NULL,
    router_username VARCHAR(50) NOT NULL,
    router_password VARCHAR(255) NOT NULL,
    api_port INT DEFAULT 80,
    location VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    status ENUM('online', 'offline') DEFAULT 'offline',
    last_checked TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Hotspots table created</p>";
} else {
    echo "<p class='error'>❌ Error creating hotspots table: " . $conn->error . "</p>";
}

// Create vouchers table
$sql = "CREATE TABLE IF NOT EXISTS vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    username VARCHAR(50) DEFAULT NULL,
    password VARCHAR(50) DEFAULT NULL,
    package_id INT NOT NULL,
    reseller_id INT NOT NULL,
    router_id INT DEFAULT NULL,
    customer_phone VARCHAR(20) DEFAULT NULL,
    status ENUM('active','used','expired') NOT NULL DEFAULT 'active',
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (router_id) REFERENCES hotspots(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Vouchers table created</p>";
} else {
    echo "<p class='error'>❌ Error creating vouchers table: " . $conn->error . "</p>";
}

// Create payment_transactions table
$sql = "CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    package_id INT NOT NULL,
    package_name VARCHAR(255) NOT NULL,
    reseller_id INT NOT NULL,
    router_id INT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_gateway VARCHAR(50) NOT NULL DEFAULT 'paystack',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE,
    FOREIGN KEY (router_id) REFERENCES hotspots(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Payment transactions table created</p>";
} else {
    echo "<p class='error'>❌ Error creating payment_transactions table: " . $conn->error . "</p>";
}

// Create mpesa_transactions table for compatibility
$sql = "CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(255) UNIQUE,
    merchant_request_id VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    result_code INT DEFAULT NULL,
    result_desc TEXT,
    transaction_id VARCHAR(255),
    reseller_id INT NOT NULL,
    package_id INT DEFAULT NULL,
    package_name VARCHAR(255),
    router_id INT DEFAULT NULL,
    voucher_id INT DEFAULT NULL,
    voucher_code VARCHAR(20),
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ M-Pesa transactions table created</p>";
} else {
    echo "<p class='error'>❌ Error creating mpesa_transactions table: " . $conn->error . "</p>";
}

// Create resellers_mpesa_settings table
$sql = "CREATE TABLE IF NOT EXISTS resellers_mpesa_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    payment_gateway ENUM('phone', 'paybill', 'till', 'paystack') NOT NULL DEFAULT 'phone',
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
    
    -- Paystack settings
    paystack_secret_key VARCHAR(255),
    paystack_public_key VARCHAR(255),
    paystack_email VARCHAR(100),
    
    callback_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Resellers M-Pesa settings table created</p>";
} else {
    echo "<p class='error'>❌ Error creating resellers_mpesa_settings table: " . $conn->error . "</p>";
}

// Create sms_settings table
$sql = "CREATE TABLE IF NOT EXISTS sms_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reseller_id INT NOT NULL,
    sms_provider ENUM('africas-talking', 'textsms', 'hostpinnacle') NOT NULL DEFAULT 'textsms',
    enable_sms TINYINT(1) NOT NULL DEFAULT 1,
    
    -- TextSMS Credentials
    textsms_api_key VARCHAR(255) DEFAULT NULL,
    textsms_partner_id VARCHAR(50) DEFAULT NULL,
    textsms_sender_id VARCHAR(50) DEFAULT NULL,
    textsms_use_get TINYINT(1) DEFAULT 1,
    
    -- Africa's Talking Credentials
    at_username VARCHAR(100) DEFAULT NULL,
    at_api_key VARCHAR(255) DEFAULT NULL,
    at_shortcode VARCHAR(50) DEFAULT NULL,
    
    -- Hostpinnacle Credentials
    hostpinnacle_userid VARCHAR(100) DEFAULT NULL,
    hostpinnacle_password VARCHAR(255) DEFAULT NULL,
    hostpinnacle_sender VARCHAR(50) DEFAULT NULL,

    -- SMS Templates
    payment_template TEXT DEFAULT NULL,
    voucher_template TEXT DEFAULT NULL,
    account_creation_template TEXT DEFAULT NULL,
    password_reset_template TEXT DEFAULT NULL,
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reseller_id) REFERENCES resellers(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ SMS settings table created</p>";
} else {
    echo "<p class='error'>❌ Error creating sms_settings table: " . $conn->error . "</p>";
}

echo "</div>";

// Insert test data
echo "<div class='section'>";
echo "<h2>3. Inserting Test Data</h2>";

// Insert test reseller
$sql = "INSERT IGNORE INTO resellers (id, business_name, email, password, phone, status, api_key) 
        VALUES (1, 'Test ISP', 'test@testisp.com', '\$2y\$10\$hashedpassword', '254700000000', 'active', 'test_api_key_123')";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Test reseller inserted</p>";
} else {
    echo "<p class='error'>❌ Error inserting test reseller: " . $conn->error . "</p>";
}

// Insert test package
$sql = "INSERT IGNORE INTO packages (id, reseller_id, name, description, price, duration, type, data_limit, speed, device_limit, is_active) 
        VALUES (1, 1, 'Test Package 1 Hour', '1 Hour WiFi Access', 50.00, '1 Hour', 'daily', '1GB', '10 Mbps', 1, TRUE)";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Test package inserted</p>";
} else {
    echo "<p class='error'>❌ Error inserting test package: " . $conn->error . "</p>";
}

// Insert test router
$sql = "INSERT IGNORE INTO hotspots (id, reseller_id, name, router_ip, router_username, router_password, location, is_active, status) 
        VALUES (1, 1, 'Test Router', '192.168.1.1', 'admin', 'password', 'Test Location', TRUE, 'online')";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Test router inserted</p>";
} else {
    echo "<p class='error'>❌ Error inserting test router: " . $conn->error . "</p>";
}

// Insert test vouchers
for ($i = 1; $i <= 10; $i++) {
    $voucherCode = 'TEST' . str_pad($i, 4, '0', STR_PAD_LEFT);
    $sql = "INSERT IGNORE INTO vouchers (code, username, password, package_id, reseller_id, router_id, status, created_at) 
            VALUES ('$voucherCode', '$voucherCode', '$voucherCode', 1, 1, 1, 'active', NOW())";
    
    if ($conn->query($sql) === TRUE) {
        echo "<p class='success'>✅ Test voucher $voucherCode inserted</p>";
    } else {
        echo "<p class='error'>❌ Error inserting test voucher $voucherCode: " . $conn->error . "</p>";
    }
}

// Insert Paystack settings for test reseller
$sql = "INSERT IGNORE INTO resellers_mpesa_settings (reseller_id, payment_gateway, environment, is_active, paystack_secret_key, paystack_public_key, paystack_email) 
        VALUES (1, 'paystack', 'sandbox', TRUE, 'sk_test_your_secret_key', 'pk_test_your_public_key', 'test@testisp.com')";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Test Paystack settings inserted</p>";
} else {
    echo "<p class='error'>❌ Error inserting test Paystack settings: " . $conn->error . "</p>";
}

// Insert SMS settings for test reseller
$sql = "INSERT IGNORE INTO sms_settings (reseller_id, sms_provider, enable_sms, textsms_api_key, textsms_partner_id, textsms_sender_id, payment_template) 
        VALUES (1, 'textsms', 1, '1DL4pRCKKmg238fsCU6i7ZYEStP9fL9o4q', '13361', 'TextSMS', 'Thank you for purchasing {package}. Your voucher code is: {voucher}. Username: {username}, Password: {password}')";

if ($conn->query($sql) === TRUE) {
    echo "<p class='success'>✅ Test SMS settings inserted</p>";
} else {
    echo "<p class='error'>❌ Error inserting test SMS settings: " . $conn->error . "</p>";
}

echo "</div>";

// Verify data
echo "<div class='section'>";
echo "<h2>4. Verifying Test Data</h2>";

// Check resellers
$result = $conn->query("SELECT COUNT(*) as count FROM resellers");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 Resellers: $count</p>";

// Check packages
$result = $conn->query("SELECT COUNT(*) as count FROM packages");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 Packages: $count</p>";

// Check hotspots
$result = $conn->query("SELECT COUNT(*) as count FROM hotspots");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 Hotspots: $count</p>";

// Check vouchers
$result = $conn->query("SELECT COUNT(*) as count FROM vouchers WHERE status = 'active'");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 Active Vouchers: $count</p>";

// Check payment settings
$result = $conn->query("SELECT COUNT(*) as count FROM resellers_mpesa_settings");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 Payment Settings: $count</p>";

// Check SMS settings
$result = $conn->query("SELECT COUNT(*) as count FROM sms_settings");
$count = $result->fetch_assoc()['count'];
echo "<p class='info'>📊 SMS Settings: $count</p>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>5. Test Data Summary</h2>";
echo "<p><strong>Test Reseller ID:</strong> 1</p>";
echo "<p><strong>Test Package ID:</strong> 1</p>";
echo "<p><strong>Test Router ID:</strong> 1</p>";
echo "<p><strong>Available Vouchers:</strong> TEST0001 to TEST0010</p>";
echo "<p><strong>Payment Gateway:</strong> Paystack (sandbox)</p>";
echo "<p><strong>SMS Provider:</strong> TextSMS</p>";
echo "</div>";

$conn->close();
echo "<p class='success'>✅ Test data setup completed successfully!</p>";
?>
