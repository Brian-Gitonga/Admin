<?php
// Include session check
require_once 'session_check.php';

// Load user profile data from database
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User'; // Default fallback
$user_role = 'Reseller'; // Default role

// Get fresh data from database if needed
try {
    // Include database connection
    require_once 'connection_dp.php';
    
    // Check if $conn is properly initialized
    if (!isset($conn) || is_null($conn)) {
        throw new Exception("Database connection not established");
    }
    
    $stmt = $conn->prepare("SELECT full_name, email, status, payment_interval FROM resellers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            $user_name = $user_data['full_name'];
            
            // Set user role based on payment_interval (optional)
            //if ($user_data['payment_interval'] === 'monthly') {
                //$user_role = 'Monthly Subscriber';
            //} else if ($user_data['payment_interval'] === 'weekly') {
                //$user_role = 'Weekly Subscriber';
            //}
            
            // You could also update the session with fresh data
            $_SESSION['user_name'] = $user_name;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Just log the error but don't disrupt the page
    error_log("Error loading user profile: " . $e->getMessage());
    // Use session data as fallback
    $user_name = $_SESSION['user_name'] ?? 'User';
}
?>

<?php
// Get current page filename to set active class
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Theme initialization script - Must be at the top before rendering any content -->
<script>
    // Check for saved theme preference and apply it immediately
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    });
    
    // Also apply theme immediately to prevent flash of wrong theme
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    })();
</script>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo-container">
        <div class="logo">
            <i class="#"></i>
            <span>Qtro</span>
        </div>
        <button class="toggle-btn" id="toggle-sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="nav-section">
        <div class="nav-title">Main</div>
        <ul class="nav-items">
            <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-item <?php echo ($current_page == 'survey.php') ? 'active' : ''; ?>">
                <a href="survey.php" class="nav-link">
                    <i class="fas fa-file-alt"></i>
                    <span class="nav-text">Survey</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="nav-section">
        <div class="nav-title">Management</div>
        <ul class="nav-items">
            <li class="nav-item <?php echo ($current_page == 'packages.php') ? 'active' : ''; ?>">
                <a href="packages.php" class="nav-link">
                    <i class="fas fa-wifi"></i>
                    <span class="nav-text">Plans & Packages</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current_page == 'transations.php') ? 'active' : ''; ?>">
                <a href="transations.php" class="nav-link">
                    <i class="fas fa-money-bill"></i>
                    <span class="nav-text">Transactions</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current_page == 'voucher.php') ? 'active' : ''; ?>">
                <a href="voucher.php" class="nav-link">
                    <i class="fas fa-ticket-alt"></i>
                    <span class="nav-text">Vouchers</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current_page == 'routers.php') ? 'active' : ''; ?>">
                <a href="routers.php" class="nav-link">
                    <i class="fas fa-server"></i>
                    <span class="nav-text">Routers</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current_page == 'linkrouter.php') ? 'active' : ''; ?>">
                <a href="linkrouter.php" class="nav-link">
                    <i class="fas fa-network-wired"></i>
                    <span class="nav-text">Remote Access</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="nav-section">
        <div class="nav-title">Settings</div>
        <ul class="nav-items">
            <li class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            <li class="nav-item <?php echo ($current_page == 'payment.php') ? 'active' : ''; ?>">
                <a href="payment.php" class="nav-link">
                    <i class="fas fa-phone"></i>
                    <span class="nav-text">Payment</span>
                </a>
            </li>

            <li class="nav-item <?php echo ($current_page == 'hotspot_list.php') ? 'active' : ''; ?>">
                <a href="hotspot_list.php" class="nav-link">
                    <i class="fas fa-wifi"></i>
                    <span class="nav-text">Hotspot Portal</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <div class="user-profile">
        <div class="user-avatar"><i class="fas fa-user"></i></div>
        <div class="user-info">
        <a href="profile.php">
            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
            </a>
            <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
        </div>
    </div>
</div>

<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobile-menu-btn">
    <i class="fas fa-bars"></i>
</button>

<!-- Display notifications -->
<?php display_notification(); ?>