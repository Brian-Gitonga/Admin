<?php
// save_sms_settings.php

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['reseller_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Include database connection and functions
require_once 'connection_dp.php';
require_once 'sms_settings_operations.php';

// Get the reseller ID from the session
$reseller_id = $_SESSION['reseller_id'];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get form data
$sms_provider = $_POST['sms_provider'] ?? 'textsms';
$enable_sms = isset($_POST['enable_sms']) ? 1 : 0;

// Initialize settings array
$settings = [
    'sms_provider' => $sms_provider,
    'enable_sms' => $enable_sms,
    'textsms_api_key' => '',
    'textsms_partner_id' => '',
    'textsms_sender_id' => 'TextSMS',
    'textsms_use_get' => 0,
    'at_username' => '',
    'at_api_key' => '',
    'at_shortcode' => '',
    'hostpinnacle_userid' => 'qtro',
    'hostpinnacle_password' => '',
    'hostpinnacle_sender' => 'SENDER',
    'payment_template' => $_POST['payment_template'] ?? '',
    'voucher_template' => $_POST['voucher_template'] ?? '',
    'account_creation_template' => $_POST['account_creation_template'] ?? '',
    'password_reset_template' => $_POST['password_reset_template'] ?? ''
];

// Get provider-specific settings
if ($sms_provider === 'textsms') {
    $settings['textsms_api_key'] = $_POST['textsms_api_key'] ?? '';
    $settings['textsms_partner_id'] = $_POST['textsms_partner_id'] ?? '';
    $settings['textsms_sender_id'] = $_POST['textsms_sender_id'] ?? 'TextSMS';
    $settings['textsms_use_get'] = isset($_POST['textsms_use_get']) ? 1 : 0;
} elseif ($sms_provider === 'africas-talking') {
    $settings['at_username'] = $_POST['at_username'] ?? '';
    $settings['at_api_key'] = $_POST['at_api_key'] ?? '';
    $settings['at_shortcode'] = $_POST['at_shortcode'] ?? '';
} elseif ($sms_provider === 'hostpinnacle') {
    $settings['hostpinnacle_userid'] = $_POST['hostpinnacle_userid'] ?? 'qtro';
    $settings['hostpinnacle_password'] = $_POST['hostpinnacle_password'] ?? '';
    $settings['hostpinnacle_sender'] = $_POST['hostpinnacle_sender'] ?? 'SENDER';
}

// Save settings
try {
    $success = saveSmsSettings($conn, $reseller_id, $settings);
    
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'SMS settings saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save SMS settings']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} 