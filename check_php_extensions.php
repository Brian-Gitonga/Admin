<?php
// Check PHP Extensions and Configuration
echo "<h1>PHP Extensions and Configuration Check</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    .info { color: blue; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

echo "<div class='section'>";
echo "<h2>PHP Version and Basic Info</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server API:</strong> " . php_sapi_name() . "</p>";
echo "<p><strong>Operating System:</strong> " . PHP_OS . "</p>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>Database Extensions</h2>";
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th><th>Notes</th></tr>";

// Check MySQLi
if (extension_loaded('mysqli')) {
    echo "<tr><td>MySQLi</td><td class='success'>✅ Loaded</td><td>MySQL Improved extension</td></tr>";
} else {
    echo "<tr><td>MySQLi</td><td class='error'>❌ Not Loaded</td><td>Required for database connections</td></tr>";
}

// Check MySQL (deprecated)
if (extension_loaded('mysql')) {
    echo "<tr><td>MySQL (deprecated)</td><td class='warning'>⚠️ Loaded</td><td>Old MySQL extension</td></tr>";
} else {
    echo "<tr><td>MySQL (deprecated)</td><td class='info'>Not Loaded</td><td>Good - deprecated extension</td></tr>";
}

// Check PDO
if (extension_loaded('pdo')) {
    echo "<tr><td>PDO</td><td class='success'>✅ Loaded</td><td>PHP Data Objects</td></tr>";
} else {
    echo "<tr><td>PDO</td><td class='error'>❌ Not Loaded</td><td>Alternative database interface</td></tr>";
}

// Check PDO MySQL
if (extension_loaded('pdo_mysql')) {
    echo "<tr><td>PDO MySQL</td><td class='success'>✅ Loaded</td><td>PDO MySQL driver</td></tr>";
} else {
    echo "<tr><td>PDO MySQL</td><td class='error'>❌ Not Loaded</td><td>PDO MySQL driver</td></tr>";
}

echo "</table>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>Other Important Extensions</h2>";
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th><th>Purpose</th></tr>";

$extensions = [
    'curl' => 'HTTP requests (for Paystack API)',
    'json' => 'JSON encoding/decoding',
    'mbstring' => 'Multi-byte string functions',
    'openssl' => 'SSL/TLS support',
    'session' => 'Session management',
    'hash' => 'Hashing functions',
    'filter' => 'Data filtering',
    'pcre' => 'Regular expressions'
];

foreach ($extensions as $ext => $purpose) {
    if (extension_loaded($ext)) {
        echo "<tr><td>$ext</td><td class='success'>✅ Loaded</td><td>$purpose</td></tr>";
    } else {
        echo "<tr><td>$ext</td><td class='error'>❌ Not Loaded</td><td>$purpose</td></tr>";
    }
}

echo "</table>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>PHP Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th></tr>";

$settings = [
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'session.auto_start' => ini_get('session.auto_start'),
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'On' : 'Off',
    'allow_url_include' => ini_get('allow_url_include') ? 'On' : 'Off'
];

foreach ($settings as $setting => $value) {
    echo "<tr><td>$setting</td><td>$value</td></tr>";
}

echo "</table>";
echo "</div>";

// Test database connection with PDO if MySQLi is not available
if (!extension_loaded('mysqli') && extension_loaded('pdo_mysql')) {
    echo "<div class='section'>";
    echo "<h2>Testing PDO MySQL Connection</h2>";
    
    try {
        $dsn = "mysql:host=localhost;dbname=billing_system";
        $username = "root";
        $password = "";
        
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<p class='success'>✅ PDO MySQL connection successful</p>";
        
        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'billing_system'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p class='success'>✅ Database 'billing_system' has {$result['count']} tables</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ PDO MySQL connection failed: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>Recommendations</h2>";

if (!extension_loaded('mysqli')) {
    echo "<div class='error'>";
    echo "<h3>❌ Critical Issue: MySQLi Extension Missing</h3>";
    echo "<p>The MySQLi extension is required for the application to work. To fix this:</p>";
    echo "<ol>";
    echo "<li>Open your php.ini file</li>";
    echo "<li>Find the line <code>;extension=mysqli</code></li>";
    echo "<li>Remove the semicolon to uncomment it: <code>extension=mysqli</code></li>";
    echo "<li>Restart your web server (Apache/Nginx)</li>";
    echo "</ol>";
    echo "<p><strong>Alternative:</strong> If you're using XAMPP, make sure you're using the correct PHP version and that MySQLi is enabled in the XAMPP control panel.</p>";
    echo "</div>";
} else {
    echo "<p class='success'>✅ MySQLi extension is available - database connections should work</p>";
}

if (!extension_loaded('curl')) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Warning: cURL Extension Missing</h3>";
    echo "<p>The cURL extension is needed for Paystack API calls. Enable it in php.ini by uncommenting <code>extension=curl</code></p>";
    echo "</div>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>Next Steps</h2>";
echo "<p>1. Enable the MySQLi extension in PHP</p>";
echo "<p>2. Restart your web server</p>";
echo "<p>3. Run the database connection test again</p>";
echo "<p>4. Test the complete Paystack payment workflow</p>";
echo "</div>";

?>
