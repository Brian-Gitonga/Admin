<?php
/**
 * Test file for Captive Portal Download Feature
 * This file can be used to test the captive portal generation functionality
 */

// Include database connection
require_once 'portal_connection.php';

// Test data
$test_data = [
    'router_id' => 1,
    'business_name' => 'Test WiFi Business',
    'reseller_id' => 1
];

echo "<h1>Captive Portal Download Feature Test</h1>";

// Test 1: Check if generate_captive_portal.php exists
echo "<h2>Test 1: File Existence</h2>";
if (file_exists('generate_captive_portal.php')) {
    echo "✅ generate_captive_portal.php exists<br>";
} else {
    echo "❌ generate_captive_portal.php not found<br>";
}

// Test 2: Check database connection
echo "<h2>Test 2: Database Connection</h2>";
if ($conn && $conn->ping()) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed<br>";
}

// Test 3: Check if required tables exist
echo "<h2>Test 3: Database Tables</h2>";
$required_tables = ['hotspots', 'resellers', 'packages'];
foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table' exists<br>";
    } else {
        echo "❌ Table '$table' not found<br>";
    }
}

// Test 4: Check if sample data exists
echo "<h2>Test 4: Sample Data</h2>";

// Check for resellers
$result = $conn->query("SELECT COUNT(*) as count FROM resellers");
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Resellers in database: " . $row['count'] . "<br>";
}

// Check for hotspots
$result = $conn->query("SELECT COUNT(*) as count FROM hotspots");
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Hotspots in database: " . $row['count'] . "<br>";
}

// Check for packages
$result = $conn->query("SELECT COUNT(*) as count FROM packages");
if ($result) {
    $row = $result->fetch_assoc();
    echo "📊 Packages in database: " . $row['count'] . "<br>";
}

// Test 5: Test portal.php integration
echo "<h2>Test 5: Portal Integration</h2>";
if (file_exists('portal.php')) {
    $portal_content = file_get_contents('portal.php');
    
    if (strpos($portal_content, 'download-portal-btn') !== false) {
        echo "✅ Download button found in portal.php<br>";
    } else {
        echo "❌ Download button not found in portal.php<br>";
    }
    
    if (strpos($portal_content, 'generate_captive_portal.php') !== false) {
        echo "✅ JavaScript integration found in portal.php<br>";
    } else {
        echo "❌ JavaScript integration not found in portal.php<br>";
    }
} else {
    echo "❌ portal.php not found<br>";
}

// Test 6: Generate a test captive portal (simulation)
echo "<h2>Test 6: Captive Portal Generation Simulation</h2>";

try {
    // Simulate the generation process
    $router_id = 1;
    $business_name = 'Test Business';
    $reseller_id = 1;
    
    // Get router information (if exists)
    $routerQuery = "SELECT * FROM hotspots WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($routerQuery);
    $stmt->bind_param("i", $router_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $routerInfo = $result->fetch_assoc();
        echo "✅ Test router found: " . htmlspecialchars($routerInfo['name']) . "<br>";
        
        // Generate safe filename
        $safeRouterName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routerInfo['name']);
        $filename = 'captive_portal_' . $safeRouterName . '.html';
        echo "✅ Generated filename: " . htmlspecialchars($filename) . "<br>";
        
    } else {
        echo "⚠️ No test router found in database<br>";
    }
    
    // Get reseller information (if exists)
    $resellerQuery = "SELECT * FROM resellers WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($resellerQuery);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $resellerInfo = $result->fetch_assoc();
        echo "✅ Test reseller found: " . htmlspecialchars($resellerInfo['business_name']) . "<br>";
    } else {
        echo "⚠️ No test reseller found in database<br>";
    }
    
    echo "✅ Captive portal generation simulation successful<br>";
    
} catch (Exception $e) {
    echo "❌ Error in captive portal generation: " . $e->getMessage() . "<br>";
}

// Test 7: Check CSS and JavaScript integration
echo "<h2>Test 7: Frontend Integration</h2>";

if (file_exists('portal.php')) {
    $portal_content = file_get_contents('portal.php');
    
    // Check for CSS classes
    $css_classes = ['download-portal-section', 'download-portal-btn', 'download-title'];
    foreach ($css_classes as $class) {
        if (strpos($portal_content, $class) !== false) {
            echo "✅ CSS class '$class' found<br>";
        } else {
            echo "❌ CSS class '$class' not found<br>";
        }
    }
    
    // Check for JavaScript functionality
    if (strpos($portal_content, 'fetch(\'generate_captive_portal.php\'') !== false) {
        echo "✅ AJAX call to generate_captive_portal.php found<br>";
    } else {
        echo "❌ AJAX call not found<br>";
    }
}

echo "<h2>Test Summary</h2>";
echo "<p>✅ = Pass | ❌ = Fail | ⚠️ = Warning | 📊 = Info</p>";

echo "<h2>Manual Testing Instructions</h2>";
echo "<ol>";
echo "<li>Navigate to portal.php with a router ID: <code>portal.php?router_id=1&business=TestBusiness</code></li>";
echo "<li>Scroll to the footer section</li>";
echo "<li>Look for the 'Download Captive Portal' button (only shows if router is selected)</li>";
echo "<li>Click the button to test the download functionality</li>";
echo "<li>Verify the downloaded HTML file contains proper branding and functionality</li>";
echo "</ol>";

echo "<h2>Next Steps</h2>";
echo "<ul>";
echo "<li>Test the downloaded HTML file in a browser</li>";
echo "<li>Upload to a test router and verify functionality</li>";
echo "<li>Test the complete payment flow</li>";
echo "<li>Verify mobile responsiveness</li>";
echo "</ul>";

?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

h1, h2 {
    color: #333;
    border-bottom: 2px solid #f59e0b;
    padding-bottom: 10px;
}

code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

ol, ul {
    margin-left: 20px;
}

li {
    margin-bottom: 5px;
}
</style>
