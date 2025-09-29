<?php
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['reseller_id'])) {
    // Return error response
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Include database connection
require_once 'connection_dp.php';

// Get reseller ID from session
$reseller_id = $_SESSION['reseller_id'];

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$portal_name = isset($_POST['portal_name']) ? $_POST['portal_name'] : '';
$redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : '';
$portal_theme = isset($_POST['portal_theme']) ? $_POST['portal_theme'] : 'dark';
$enable_free_trial = isset($_POST['enable_free_trial']) ? (int)$_POST['enable_free_trial'] : 0;
$free_trial_package = isset($_POST['free_trial_package']) ? $_POST['free_trial_package'] : '';
$free_trial_limit = isset($_POST['free_trial_limit']) ? (int)$_POST['free_trial_limit'] : 1;

// Validate input
if (empty($portal_name)) {
    echo json_encode(['success' => false, 'message' => 'Portal name is required']);
    exit;
}

if ($enable_free_trial && empty($free_trial_package)) {
    echo json_encode(['success' => false, 'message' => 'Please select a free trial package']);
    exit;
}

try {
    // Check if hotspot settings already exist for this reseller
    $stmt = $conn->prepare("SELECT id FROM hotspot_settings WHERE reseller_id = ?");
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing settings
        $stmt = $conn->prepare("UPDATE hotspot_settings SET 
            portal_name = ?, redirect_url = ?, portal_theme = ?, 
            enable_free_trial = ?, free_trial_package = ?, free_trial_limit = ?
            WHERE reseller_id = ?");
        $stmt->bind_param("sssisii", 
            $portal_name, $redirect_url, $portal_theme, 
            $enable_free_trial, $free_trial_package, $free_trial_limit,
            $reseller_id);
    } else {
        // Insert new settings
        $stmt = $conn->prepare("INSERT INTO hotspot_settings 
            (reseller_id, portal_name, redirect_url, portal_theme, enable_free_trial, free_trial_package, free_trial_limit)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssiis", 
            $reseller_id, $portal_name, $redirect_url, $portal_theme, 
            $enable_free_trial, $free_trial_package, $free_trial_limit);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Hotspot settings saved successfully']);
    } else {
        throw new Exception("Error executing query: " . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error saving hotspot settings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving settings']);
}
?> 