<?php
/**
 * Logout page
 * This page destroys the user's session and redirects to the login page
 */
session_start();

// Include notifications system
require_once 'notifications.php';

// Get the user's name before destroying the session
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, remove it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session just for the notification
session_start();

// Set a personalized logout notification
set_success_notification("$user_name, you have been successfully logged out.");

// Redirect to login page
header("Location: login.php");
exit();
?> 