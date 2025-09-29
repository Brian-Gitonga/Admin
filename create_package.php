<?php
/**
 * Package Creation Handler
 * Processes the form submission for creating new packages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'connection_dp.php';
require_once 'session_functions.php';
require_once 'package_operations.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Return JSON response with error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to create a package']);
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current user ID
    $user_id = getCurrentUserId();
    
    // Validate and sanitize input
    $name = trim(filter_input(INPUT_POST, 'package_name', FILTER_SANITIZE_STRING));
    $type = filter_input(INPUT_POST, 'package_type', FILTER_SANITIZE_STRING);
    $duration = filter_input(INPUT_POST, 'package_duration', FILTER_SANITIZE_STRING);
    $upload_speed = filter_input(INPUT_POST, 'upload_speed', FILTER_VALIDATE_FLOAT);
    $download_speed = filter_input(INPUT_POST, 'download_speed', FILTER_VALIDATE_FLOAT);
    $price = filter_input(INPUT_POST, 'package_price', FILTER_VALIDATE_FLOAT);
    $device_limit = filter_input(INPUT_POST, 'device_limit', FILTER_VALIDATE_INT);
    $data_limit = isset($_POST['data_limit']) ? filter_input(INPUT_POST, 'data_limit', FILTER_VALIDATE_INT) : 0;
    
    // Handle free trial packages
    $is_free_trial = ($price == 0);
    $free_trial_limit = 1; // Default to 1
    
    if ($is_free_trial && isset($_POST['free_trial_limit'])) {
        $free_trial_limit = filter_input(INPUT_POST, 'free_trial_limit', FILTER_VALIDATE_INT);
        // Ensure the limit is between 1 and 3
        $free_trial_limit = max(1, min(3, $free_trial_limit));
    }
    
    // Convert duration values
    $duration_map = [
        '1-hour' => '1 Hour',
        '2-hours' => '2 Hours',
        '6-hours' => '6 Hours',
        '12-hours' => '12 Hours',
        '1-day' => '1 Day',
        '5-day' => '5 Days',
        '7-days' => '7 Days',
        '30-days' => '30 Days'
    ];
    
    $display_duration = isset($duration_map[$duration]) ? $duration_map[$duration] : $duration;
    $duration_minutes = durationToMinutes($display_duration);
    
    // Validate required fields
    if (empty($name) || empty($type) || empty($duration) || empty($upload_speed) || 
        empty($download_speed) || !isset($price) || empty($device_limit)) {
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Check valid package type
    if (!in_array($type, ['hotspot', 'pppoe', 'data-plan'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid package type']);
        exit;
    }
    
    // Check if data-plan requires data limit
    if ($type === 'data-plan' && empty($data_limit)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Data limit is required for data plans']);
        exit;
    }
    
    // Prepare package data
    $package_data = [
        'reseller_id' => $user_id,
        'name' => $name,
        'type' => $type,
        'price' => $price,
        'upload_speed' => $upload_speed,
        'download_speed' => $download_speed,
        'duration' => $display_duration,
        'duration_in_minutes' => $duration_minutes,
        'device_limit' => $device_limit,
        'data_limit' => $data_limit,
        'is_enabled' => true,
        'is_free_trial' => $is_free_trial,
        'free_trial_limit' => $free_trial_limit
    ];
    
    // Create package in database
    $package_id = createPackage($package_data);
    
    if ($package_id) {
        // Success! Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Package created successfully', 'package_id' => $package_id]);
        exit;
    } else {
        // Error creating package
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to create package. Please try again.']);
        exit;
    }
} else {
    // Not a POST request, redirect to packages page
    header("Location: packages.php");
    exit;
}
?> 