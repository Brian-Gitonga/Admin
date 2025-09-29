<?php
/**
 * Notifications system for managing flash messages across pages 
 * This file handles setting, retrieving, and displaying notification messages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Set a notification message to be displayed on the next page load
 * 
 * @param string $message The message to display
 * @param string $type The type of message (success, error, warning, info)
 * @return void
 */
function set_notification($message, $type = 'info') {
    // Validate type
    $allowed_types = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowed_types)) {
        $type = 'info';  // Default to info if invalid type
    }
    
    // Create the notification
    $_SESSION['notification'] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Check if there's a notification to display
 * 
 * @return bool True if a notification exists, false otherwise
 */
function has_notification() {
    return isset($_SESSION['notification']) && !empty($_SESSION['notification']['message']);
}

/**
 * Get the current notification and clear it
 * 
 * @return array|null The notification data or null if none exists
 */
function get_notification() {
    if (!has_notification()) {
        return null;
    }
    
    $notification = $_SESSION['notification'];
    
    // Clear the notification so it's shown only once
    unset($_SESSION['notification']);
    
    return $notification;
}

/**
 * Display the notification if one exists
 * 
 * @return void
 */
function display_notification() {
    if (!has_notification()) {
        return;
    }
    
    $notification = get_notification();
    $type_class = $notification['type'];
    
    // Map types to icons
    $icon_map = [
        'success' => 'check-circle',
        'error' => 'times-circle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    
    $icon = isset($icon_map[$notification['type']]) ? $icon_map[$notification['type']] : 'info-circle';
    
    echo '<div class="notification notification-' . htmlspecialchars($type_class) . '" id="notification">';
    echo '    <div class="notification-icon"><i class="fas fa-' . $icon . '"></i></div>';
    echo '    <div class="notification-message">' . htmlspecialchars($notification['message']) . '</div>';
    echo '    <button class="notification-close" onclick="closeNotification()"><i class="fas fa-times"></i></button>';
    echo '</div>';
    
    // Add inline script for auto-dismissal
    echo '<script>
        // Auto-dismiss notification after 5 seconds
        setTimeout(function() {
            closeNotification();
        }, 5000);
        
        function closeNotification() {
            const notification = document.getElementById("notification");
            if (notification) {
                notification.classList.add("notification-hiding");
                setTimeout(function() {
                    notification.remove();
                }, 500);
            }
        }
    </script>';
}

/**
 * Set a success notification
 * 
 * @param string $message The success message to display
 * @return void
 */
function set_success_notification($message) {
    set_notification($message, 'success');
}

/**
 * Set an error notification
 * 
 * @param string $message The error message to display
 * @return void
 */
function set_error_notification($message) {
    set_notification($message, 'error');
}

/**
 * Set a warning notification
 * 
 * @param string $message The warning message to display
 * @return void
 */
function set_warning_notification($message) {
    set_notification($message, 'warning');
}

/**
 * Set an info notification
 * 
 * @param string $message The info message to display
 * @return void
 */
function set_info_notification($message) {
    set_notification($message, 'info');
} 