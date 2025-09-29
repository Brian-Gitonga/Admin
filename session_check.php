<?php
/**
 * Session management file - include at the top of all protected pages
 * This file checks if the user is logged in and redirects to login page if not
 */
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include notification system
require_once 'notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // User is not logged in, redirect to login page
    set_warning_notification("Please log in to access this page");
    header("Location: login.php");
    exit();
}

// If the session is older than 30 minutes, refresh it or log out
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Last activity was more than 30 minutes ago
    session_unset();     // unset $_SESSION variables
    session_destroy();   // destroy session data
    
    // Start a new session for the notification
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    set_warning_notification("Your session has expired. Please log in again.");
    header("Location: login.php");
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Check if user's IP has changed during the session (basic security measure)
if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
    // IP address has changed - possible session hijacking attempt
    session_unset();
    session_destroy();
    
    // Start a new session for the notification
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    set_error_notification("A security issue was detected. Please log in again.");
    header("Location: login.php");
    exit();
}

// Store user IP on first check
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
}

// Check if account is still active (in case status changed while user was logged in)
require_once 'session_functions.php';
require_once 'connection_dp.php'; // Add this line to ensure database connection is available

$user_id = $_SESSION['user_id'];
$account_status = checkAccountStatus($user_id);

if ($account_status !== 'active') {
    // Account is no longer active, log out user
    session_unset();
    session_destroy();
    
    // Start a new session for the notification
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set appropriate message based on status
    if ($account_status === 'pending') {
        set_warning_notification("Your account is now pending approval. Please contact admin for activation.");
    } elseif ($account_status === 'suspended') {
        set_error_notification("Your account has been suspended. Please contact support.");
    } elseif ($account_status === 'expired') {
        set_warning_notification("Your account has expired. Please renew your subscription.");
    } else {
        set_error_notification("Your account is no longer active. Please contact support.");
    }
    
    header("Location: login.php");
    exit();
}
?> 