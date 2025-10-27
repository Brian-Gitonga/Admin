<?php
// Include session check
require_once 'session_check.php';

// Include database connection and functions
require_once 'connection_dp.php';
require_once 'mpesa_settings_operations.php';

// Get the reseller ID from the session
$reseller_id = $_SESSION['user_id'];

// Load M-Pesa settings for the current reseller
// This will automatically use system defaults if reseller hasn't configured yet
$mpesaSettings = getMpesaSettings($conn, $reseller_id);

// Check if this is using system defaults (for display purposes)
$usingSystemDefaults = false;
if (empty($mpesaSettings['paybill_consumer_key']) || empty($mpesaSettings['paybill_consumer_secret'])) {
    $usingSystemDefaults = true;
}

// Get system defaults to show in UI
$systemDefaults = getSystemMpesaApiCredentials();

// Load Hotspot settings for the current reseller
$hotspotSettings = array(
    'portal_name' => 'Qtro Hotspot',
    'redirect_url' => 'https://www.youtube.com/',
    'portal_theme' => 'dark',
    'enable_free_trial' => false,
    'free_trial_package' => '',
    'free_trial_limit' => 1
);

$query = "SELECT * FROM hotspot_settings WHERE reseller_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reseller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $hotspotSettings = $result->fetch_assoc();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Settings</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="other-css/settings.css">
    <link rel="stylesheet" href="style.css">

</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">
                    Settings
                    <i class="fas fa-info-circle info-icon" title="Configure your system settings"></i>
                </h1>
                <p class="page-subtitle">Configure your ISP billing system settings</p>
            </div>
            <button class="save-btn" id="save-settings-btn">
                <i class="fas fa-save"></i>
                <span>Save Changes</span>
            </button>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="general">
                <i class="fas fa-sliders-h"></i>
                <span>General</span>
            </div>
            <div class="tab" data-tab="payment">
                <i class="fas fa-credit-card"></i>
                <span>Payment</span>
            </div>
            <div class="tab" data-tab="hotspot">
                <i class="fas fa-wifi"></i>
                <span>Hotspot</span>
            </div>
            <div class="tab" data-tab="sms">
                <i class="fas fa-sms"></i>
                <span>SMS Gateway</span>
            </div>
        </div>
        
        <!-- General Settings Tab Content -->
        <div class="tab-content active" id="general-tab">
            <div class="settings-container">
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-building"></i>
                        Company Information
                    </h3>
                    <div class="form-group">
                        <label for="company-name" class="form-label">Company Name</label>
                        <input type="text" id="company-name" class="form-input" placeholder="Enter company name" value="Qtro ISP">
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="support-phone" class="form-label">Customer Support Number</label>
                                <input type="tel" id="support-phone" class="form-input" placeholder="Enter support phone number" value="+254 700 123 456">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="whatsapp-number" class="form-label">WhatsApp Support Number</label>
                                <input type="tel" id="whatsapp-number" class="form-input" placeholder="Enter WhatsApp number" value="+254 700 123 456">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="company-email" class="form-label">Company Email</label>
                        <input type="email" id="company-email" class="form-input" placeholder="Enter company email" value="support@qtroisp.com">
                    </div>
                    <div class="form-group">
                        <label for="company-address" class="form-label">Company Address</label>
                        <textarea id="company-address" class="form-textarea" placeholder="Enter company address">123 Main Street, Nairobi, Kenya</textarea>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-file-contract"></i>
                        Terms & Conditions
                    </h3>
                    <div class="form-group">
                        <label for="terms-conditions" class="form-label">Terms & Conditions for Captive Portal</label>
                        <textarea id="terms-conditions" class="form-textarea" placeholder="Enter terms and conditions">1. By using our service, you agree to abide by all applicable laws and regulations.
2. We reserve the right to terminate service for any violation of these terms.
3. Bandwidth usage is subject to fair usage policy.
4. Payment is required in advance for continued service.
5. We are not responsible for any content accessed through our service.
6. Service availability may vary and is not guaranteed 100% of the time.
7. Customer information will be handled according to our privacy policy.</textarea>
                        <p class="form-help">These terms will be displayed on the captive portal login page.</p>
                    </div>
                </div>
                
                
            </div>
        </div>
        
        <!-- Payment Settings Tab Content -->
        <div class="tab-content" id="payment-tab">
            <div class="settings-container">
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-credit-card"></i>
                        Payment Gateway
                    </h3>
                    <div class="form-group">
                        <label for="payment-gateway" class="form-label">Payment Gateway Setup</label>
                        <select id="payment-gateway" class="form-select">
                            <option value="phone" <?php echo $mpesaSettings['payment_gateway'] == 'phone' ? 'selected' : ''; ?>>M-Pesa Phone Number</option>
                            <option value="paybill" <?php echo $mpesaSettings['payment_gateway'] == 'paybill' ? 'selected' : ''; ?>>M-Pesa Paybill Number</option>
                            <option value="till" <?php echo $mpesaSettings['payment_gateway'] == 'till' ? 'selected' : ''; ?>>M-Pesa Till Number</option>
                            <option value="paystack" <?php echo $mpesaSettings['payment_gateway'] == 'paystack' ? 'selected' : ''; ?>>Paystack</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-environment" class="form-label">Payment Environment</label>
                        <select id="payment-environment" class="form-select">
                            <option value="sandbox" <?php echo $mpesaSettings['environment'] == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                            <option value="live" <?php echo $mpesaSettings['environment'] == 'live' ? 'selected' : ''; ?>>Live Production</option>
                        </select>
                    </div>
                    
                    <!-- M-Pesa Phone Settings -->
                    <div id="mpesa-settings" style="<?php echo $mpesaSettings['payment_gateway'] == 'phone' ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="mpesa-phone" class="form-label">M-Pesa Number</label>
                                    <input type="text" id="mpesa-phone" class="form-input" placeholder="Enter M-Pesa number" value="<?php echo htmlspecialchars($mpesaSettings['mpesa_phone']); ?>">
                                    <p class="form-help">This is the phone number that will receive customer payments</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Interval - For Phone Only -->
                        <div class="form-group">
                            <label for="phone-billing-cycle" class="form-label">Default Billing Cycle</label>
                            <select id="phone-billing-cycle" class="form-select">
                                <?php 
                                    // Get current payment interval from resellers table
                                    $query = "SELECT payment_interval FROM resellers WHERE id = ?";
                                    $stmt = $conn->prepare($query);
                                    $stmt->bind_param("i", $reseller_id);
                                    $stmt->execute();
                                    $intervalResult = $stmt->get_result();
                                    $paymentInterval = 'monthly'; // Default
                                    
                                    if ($intervalResult->num_rows > 0) {
                                        $intervalRow = $intervalResult->fetch_assoc();
                                        $paymentInterval = $intervalRow['payment_interval'];
                                    }
                                ?>
                                <option value="monthly" <?php echo ($paymentInterval == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                <option value="weekly" <?php echo ($paymentInterval == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Paybill Settings - All fields editable -->
                    <div id="bank-settings" style="<?php echo $mpesaSettings['payment_gateway'] == 'paybill' ? 'display: block;' : 'display: none;'; ?>">
                        <?php if ($usingSystemDefaults): ?>
                        <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #2196f3; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                            <i class="fas fa-info-circle" style="color: #2196f3;"></i>
                            <strong>Using System Default Credentials</strong><br>
                            These are test credentials. You can edit and save your own M-Pesa API credentials below.
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="paybill-number" class="form-label">Paybill Number</label>
                            <input type="text" id="paybill-number" class="form-input" placeholder="Enter paybill number" value="<?php echo htmlspecialchars($mpesaSettings['paybill_number']); ?>">
                            <p class="form-help">Your M-Pesa paybill business number</p>
                        </div>
                        <div class="form-group">
                            <label for="paybill-shortcode" class="form-label">
                                M-Pesa Shortcode (Business Number)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Currently: <?php echo $systemDefaults['shortcode']; ?>)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="paybill-shortcode" class="form-input" placeholder="Enter mpesa shortcode" value="<?php echo htmlspecialchars($mpesaSettings['paybill_shortcode']); ?>">
                            <p class="form-help">Your M-Pesa business shortcode for API calls</p>
                        </div>
                        <div class="form-group">
                            <label for="paybill-passkey" class="form-label">
                                M-Pesa Passkey
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="paybill-passkey" class="form-input" placeholder="Enter mpesa passkey" value="<?php echo htmlspecialchars($mpesaSettings['paybill_passkey']); ?>">
                            <p class="form-help">Your M-Pesa Lipa Na M-Pesa Online Passkey from Daraja</p>
                        </div>
                        <div class="form-group">
                            <label for="paybill-consumer-key" class="form-label">
                                Consumer Key (App Key)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="paybill-consumer-key" class="form-input" placeholder="Enter consumer key" value="<?php echo htmlspecialchars($mpesaSettings['paybill_consumer_key']); ?>">
                            <p class="form-help">Your M-Pesa Daraja API Consumer Key</p>
                        </div>
                        <div class="form-group">
                            <label for="paybill-consumer-secret" class="form-label">
                                Consumer Secret (App Secret)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="paybill-consumer-secret" class="form-input" placeholder="Enter consumer secret" value="<?php echo htmlspecialchars($mpesaSettings['paybill_consumer_secret']); ?>">
                            <p class="form-help">Your M-Pesa Daraja API Consumer Secret</p>
                        </div>
                        
                        <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 8px; margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle" style="color: #856404;"></i>
                            <strong>Note:</strong> The callback URL is automatically configured by the system. You don't need to set it.
                        </div>
                    </div>
                    
                    <!-- Till Settings - All fields editable -->
                    <div id="till-settings" style="<?php echo $mpesaSettings['payment_gateway'] == 'till' ? 'display: block;' : 'display: none;'; ?>">
                        <?php if ($usingSystemDefaults): ?>
                        <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #2196f3; padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                            <i class="fas fa-info-circle" style="color: #2196f3;"></i>
                            <strong>Using System Default Credentials</strong><br>
                            These are test credentials. You can edit and save your own M-Pesa API credentials below.
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="till-number" class="form-label">Till Number</label>
                            <input type="text" id="till-number" class="form-input" placeholder="Enter till number" value="<?php echo htmlspecialchars($mpesaSettings['till_number']); ?>">
                            <p class="form-help">Your M-Pesa till business number</p>
                        </div>
                        <div class="form-group">
                            <label for="till-shortcode" class="form-label">
                                M-Pesa Shortcode (Business Number)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Currently: <?php echo $systemDefaults['shortcode']; ?>)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="till-shortcode" class="form-input" placeholder="Enter mpesa shortcode" value="<?php echo htmlspecialchars($mpesaSettings['till_shortcode']); ?>">
                            <p class="form-help">Your M-Pesa business shortcode for API calls</p>
                        </div>
                        <div class="form-group">
                            <label for="till-passkey" class="form-label">
                                M-Pesa Passkey
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="till-passkey" class="form-input" placeholder="Enter mpesa passkey" value="<?php echo htmlspecialchars($mpesaSettings['till_passkey']); ?>">
                            <p class="form-help">Your M-Pesa Lipa Na M-Pesa Online Passkey from Daraja</p>
                        </div>
                        <div class="form-group">
                            <label for="till-consumer-key" class="form-label">
                                Consumer Key (App Key)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="till-consumer-key" class="form-input" placeholder="Enter Consumer Key" value="<?php echo htmlspecialchars($mpesaSettings['till_consumer_key']); ?>">
                            <p class="form-help">Your M-Pesa Daraja API Consumer Key</p>
                        </div>
                        <div class="form-group">
                            <label for="till-consumer-secret" class="form-label">
                                Consumer Secret (App Secret)
                                <?php if ($usingSystemDefaults): ?>
                                <span style="color: #2196f3; font-size: 12px;">(Using system default)</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" id="till-consumer-secret" class="form-input" placeholder="Enter Consumer Secret" value="<?php echo htmlspecialchars($mpesaSettings['till_consumer_secret']); ?>">
                            <p class="form-help">Your M-Pesa Daraja API Consumer Secret</p>
                        </div>
                        
                        <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; border-radius: 8px; margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle" style="color: #856404;"></i>
                            <strong>Note:</strong> The callback URL is automatically configured by the system. You don't need to set it.
                        </div>
                    </div>

                    <!-- Paystack Settings - All fields editable -->
                    <div id="paystack-settings" style="<?php echo $mpesaSettings['payment_gateway'] == 'paystack' ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-group">
                            <label for="paystack-secret-key" class="form-label">Secret Key</label>
                            <input type="password" id="paystack-secret-key" class="form-input" placeholder="Enter Paystack Secret Key" value="<?php echo htmlspecialchars($mpesaSettings['paystack_secret_key'] ?? ''); ?>">
                            <p class="form-help">Your Paystack Secret Key (sk_live_*)</p>
                        </div>
                        <div class="form-group">
                            <label for="paystack-public-key" class="form-label">Public Key</label>
                            <input type="text" id="paystack-public-key" class="form-input" placeholder="Enter Paystack Public Key" value="<?php echo htmlspecialchars($mpesaSettings['paystack_public_key'] ?? ''); ?>">
                            <p class="form-help">Your Paystack Public Key (pk_live_*)</p>
                        </div>
                        <div class="form-group">
                            <label for="paystack-email" class="form-label">Merchant Email</label>
                            <input type="email" id="paystack-email" class="form-input" placeholder="Enter Merchant Email" value="<?php echo htmlspecialchars($mpesaSettings['paystack_email'] ?? ''); ?>">
                            <p class="form-help">Email address registered with your Paystack account</p>
                        </div>
                        <div class="form-group">
                            <a href="https://paystack.com/docs" target="_blank" class="form-link">
                                <i class="fas fa-external-link-alt"></i>
                                Paystack API Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hotspot Settings Tab Content -->
        <div class="tab-content" id="hotspot-tab">
            <div class="settings-container">
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-wifi"></i>
                        Captive Portal Settings
                    </h3>
                    <div class="form-group">
                        <label for="portal-name" class="form-label">Portal Name</label>
                        <input type="text" id="portal-name" class="form-input" placeholder="Enter portal name" value="<?php echo htmlspecialchars($hotspotSettings['portal_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="redirect-url" class="form-label">Redirection URL</label>
                        <input type="url" id="redirect-url" class="form-input" placeholder="Enter redirection URL" value="<?php echo htmlspecialchars($hotspotSettings['redirect_url']); ?>">
                        <p class="form-help">Users will be redirected to this URL after successful login.</p>
                    </div>
                    <div class="form-group">
                        <label for="portal-logo" class="form-label">Portal Logo</label>
                        <input type="file" id="portal-logo" accept="image/*" style="display: none;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <button type="button" onclick="document.getElementById('portal-logo').click()" style="padding: 0.5rem 1rem; background-color: var(--bg-accent); border: none; border-radius: 0.5rem; color: var(--text-primary); cursor: pointer;">
                                Choose File
                            </button>
                            <span id="logo-file-name">No file chosen</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="portal-theme" class="form-label">Portal Theme</label>
                        <select id="portal-theme" class="form-select">
                            <option value="dark" <?php echo $hotspotSettings['portal_theme'] == 'dark' ? 'selected' : ''; ?>>Dark Theme</option>
                            <option value="light" <?php echo $hotspotSettings['portal_theme'] == 'light' ? 'selected' : ''; ?>>Light Theme</option>
                            <option value="blue" <?php echo $hotspotSettings['portal_theme'] == 'blue' ? 'selected' : ''; ?>>Blue Theme</option>
                            <option value="green" <?php echo $hotspotSettings['portal_theme'] == 'green' ? 'selected' : ''; ?>>Green Theme</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-sign-in-alt"></i>
                        Login Settings
                    </h3>
                    <div class="form-group">
                        <label class="form-switch">
                            <input type="checkbox" checked>
                            <span class="switch-slider"></span>
                            <span class="switch-label">Allow voucher login</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-switch">
                            <input type="checkbox" checked>
                            <span class="switch-slider"></span>
                            <span class="switch-label">Allow username/password login</span>
                        </label>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-gift"></i>
                        Free Trial Settings
                    </h3>
                    <div class="form-group">
                        <label class="form-switch">
                            <input type="checkbox" id="enable-free-trial" <?php echo $hotspotSettings['enable_free_trial'] ? 'checked' : ''; ?>>
                            <span class="switch-slider"></span>
                            <span class="switch-label">Enable Free Trial</span>
                        </label>
                        <p class="form-help">Allow new users to access a free trial package</p>
                    </div>
                    
                    <div id="free-trial-settings" style="<?php echo $hotspotSettings['enable_free_trial'] ? 'display: block;' : 'display: none;'; ?>">
                        <div class="form-group">
                            <label for="free-trial-package" class="form-label">Free Trial Package</label>
                            <select id="free-trial-package" class="form-select">
                                <option value="">-- Select Package --</option>
                                <?php
                                // Make sure we have package_operations.php included
                                if (!function_exists('getAllPackages')) {
                                    require_once 'package_operations.php';
                                }

                                // Get packages using the function from package_operations.php if available
                                if (function_exists('getAllPackages')) {
                                    $packages = getAllPackages($reseller_id);
                                } else {
                                    // Fallback to direct database query
                                    $packages = [];
                                    try {
                                        $query = "SELECT * FROM packages WHERE reseller_id = ? AND is_enabled = TRUE ORDER BY name ASC";
                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("i", $reseller_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        
                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $packages[] = $row;
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("Error fetching packages: " . $e->getMessage());
                                    }
                                }

                                // Display packages in dropdown
                                if (!empty($packages)) {
                                    foreach ($packages as $package) {
                                        $selected = ($package['id'] == $hotspotSettings['free_trial_package']) ? 'selected' : '';
                                        $packageType = ucfirst($package['type']); // Capitalize the first letter of the type
                                        
                                        // Format speed if available
                                        $speedInfo = '';
                                        if (isset($package['upload_speed']) && isset($package['download_speed'])) {
                                            $speedInfo = " - {$package['upload_speed']}M/{$package['download_speed']}M";
                                        }
                                        
                                        echo "<option value='" . $package['id'] . "' $selected>" . 
                                             htmlspecialchars($package['name']) . 
                                             " (" . $packageType . 
                                             " - " . htmlspecialchars($package['duration']) . 
                                             $speedInfo . ")</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled>No packages available</option>";
                                }
                                ?>
                            </select>
                            <p class="form-help">Select which package will be offered as a free trial</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="free-trial-limit" class="form-label">Maximum Free Trial Uses</label>
                            <select id="free-trial-limit" class="form-select">
                                <option value="1" <?php echo $hotspotSettings['free_trial_limit'] == 1 ? 'selected' : ''; ?>>1 time only</option>
                                <option value="2" <?php echo $hotspotSettings['free_trial_limit'] == 2 ? 'selected' : ''; ?>>2 times</option>
                                <option value="3" <?php echo $hotspotSettings['free_trial_limit'] == 3 ? 'selected' : ''; ?>>3 times</option>
                            </select>
                            <p class="form-help">How many times a user can access the free trial (tracked by device/MAC address)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SMS Gateway Tab Content -->
        <div class="tab-content" id="sms-tab">
            <div class="settings-container">
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-sms"></i>
                        SMS Gateway Settings
                    </h3>


                    <div class="form-group">
                        <label for="sms-provider" class="form-label">SMS Provider</label>
                        <select id="sms-provider" class="form-select">
                            <option value="africas-talking" selected>Africa's Talking</option>
                            <option value="twilio">Twilio</option>
                            <option value="infobip">Infobip</option>
                            <option value="nexmo">Nexmo (Vonage)</option>
                        </select>
                    </div>
                    
                    <!-- Africa's Talking Settings 
                    <div id="africas-talking-settings">
                        <div class="form-group">
                            <label for="at-username" class="form-label">Africa's Talking Username</label>
                            <input type="text" id="at-username" class="form-input" placeholder="Enter username" value="qtro_isp">
                        </div>
                        <div class="form-group">
                            <label for="at-api-key" class="form-label">Africa's Talking API Key</label>
                            <input type="password" id="at-api-key" class="form-input" placeholder="Enter API key" value="••••••••••••••••••••••••">
                        </div>
                        <div class="form-group">
                            <label for="at-shortcode" class="form-label">Africa's Talking Shortcode</label>
                            <input type="text" id="at-shortcode" class="form-input" placeholder="Enter shortcode" value="12345">
                            <p class="form-help">Optional: Leave blank if not using a shortcode.</p>
                        </div>
                    </div>
                    -->
                    <!-- Twilio Settings (hidden by default) 
                    <div id="twilio-settings" style="display: none;">
                        <div class="form-group">
                            <label for="twilio-sid" class="form-label">Twilio Account SID</label>
                            <input type="text" id="twilio-sid" class="form-input" placeholder="Enter Twilio Account SID">
                        </div>
                        <div class="form-group">
                            <label for="twilio-token" class="form-label">Twilio Auth Token</label>
                            <input type="password" id="twilio-token" class="form-input" placeholder="Enter Twilio Auth Token">
                        </div>
                        <div class="form-group">
                            <label for="twilio-number" class="form-label">Twilio Phone Number</label>
                            <input type="text" id="twilio-number" class="form-input" placeholder="Enter Twilio Phone Number">
                        </div>
                    </div>
                    -->
                    
                    <!-- Other providers' settings would be here -->
                </div>
                
                <div class="settings-section">
                    <h3 class="settings-section-title">
                        <i class="fas fa-envelope"></i>
                        SMS Templates
                    </h3>
                    <div class="form-group">
                        <label for="welcome-template" class="form-label">Default Message</label>
                        <textarea id="welcome-template" class="form-textarea" placeholder="Enter welcome message template">Your account have sucessfully bought {package} plan. Your username is: {username}, Password: {password} and vocucher is: {voucher}.</textarea>
                        <p class="form-help">Use {username}, {password}, {package}, {expiry} as placeholders.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="page-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Whatsapp Channel</a>
                <a href="#" class="footer-link">Privacy & Terms</a>
            </div>
            <div class="copyright">© 2025 Qtro ISP. All Rights Reserved.</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Tab switching functionality
            $('.tab').click(function() {
                const tabId = $(this).data('tab');
                
                // Remove active class from all tabs and add to clicked tab
                $('.tab').removeClass('active');
                $(this).addClass('active');
                
                // Hide all tab contents and show the selected one
                $('.tab-content').removeClass('active');
                $(`#${tabId}-tab`).addClass('active');
            });
            
            // Free trial toggle functionality
            $('#enable-free-trial').change(function() {
                if($(this).is(':checked')) {
                    $('#free-trial-settings').slideDown();
                } else {
                    $('#free-trial-settings').slideUp();
                }
            });
            
            // Payment gateway toggling
            $('#payment-gateway').change(function() {
                const gateway = $(this).val();
                
                // Hide all gateway settings
                $('#mpesa-settings, #bank-settings, #till-settings, #paystack-settings').hide();
                
                // Show the selected gateway settings
                if (gateway === 'phone') {
                    $('#mpesa-settings').show();
                    console.log('Switched to phone payment method - System API credentials will be used');
                } else if (gateway === 'paybill') {
                    $('#bank-settings').show();
                } else if (gateway === 'till') {
                    $('#till-settings').show();
                } else if (gateway === 'paystack') {
                    $('#paystack-settings').show();
                    console.log('Switched to Paystack payment method');
                }
            });
            
            // Save settings button
            $('#save-settings-btn').click(function() {
                // Show loading state
                const originalText = $(this).html();
                $(this).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
                $(this).prop('disabled', true);
                
                // Determine which tab is active
                const activeTab = $('.tab.active').data('tab');
                
                // Save settings based on active tab
                if (activeTab === 'payment') {
                    savePaymentSettings();
                } else if (activeTab === 'hotspot') {
                    saveHotspotSettings();
                } else {
                    // Restore button
                    $(this).html(originalText);
                    $(this).prop('disabled', false);
                    
                    // Show notification
                    alert('Settings functionality for this tab is not yet implemented');
                }
            });
            
            // Function to save payment settings
            function savePaymentSettings() {
                const gateway = $('#payment-gateway').val();
                const environment = $('#payment-environment').val();
                
                let formData = {
                    payment_gateway: gateway,
                    environment: environment
                };
                
                // Add appropriate fields based on gateway type
                if (gateway === 'phone') {
                    formData.mpesa_phone = $('#mpesa-phone').val();
                    formData.billing_cycle = $('#phone-billing-cycle').val();
                } else if (gateway === 'paybill') {
                    formData.paybill_number = $('#paybill-number').val();
                    formData.paybill_shortcode = $('#paybill-shortcode').val();
                    formData.paybill_passkey = $('#paybill-passkey').val();
                    formData.paybill_consumer_key = $('#paybill-consumer-key').val();
                    formData.paybill_consumer_secret = $('#paybill-consumer-secret').val();
                } else if (gateway === 'till') {
                    formData.till_number = $('#till-number').val();
                    formData.till_shortcode = $('#till-shortcode').val();
                    formData.till_passkey = $('#till-passkey').val();
                    formData.till_consumer_key = $('#till-consumer-key').val();
                    formData.till_consumer_secret = $('#till-consumer-secret').val();
                } else if (gateway === 'paystack') {
                    formData.paystack_secret_key = $('#paystack-secret-key').val();
                    formData.paystack_public_key = $('#paystack-public-key').val();
                    formData.paystack_email = $('#paystack-email').val();
                }
                
                // Debug log the form data being sent
                console.log('Saving settings with data:', formData);
                
                // Save settings via AJAX
                $.ajax({
                    url: 'save_mpesa_settings.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    timeout: 30000, // 30 second timeout
                    success: function(response) {
                        // Restore button
                        $('#save-settings-btn').html('<i class="fas fa-save"></i> <span>Save Changes</span>');
                        $('#save-settings-btn').prop('disabled', false);
                        
                        // Show response message
                        if (response.success) {
                            alert('Settings saved successfully');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Restore button
                        $('#save-settings-btn').html('<i class="fas fa-save"></i> <span>Save Changes</span>');
                        $('#save-settings-btn').prop('disabled', false);
                        
                        // Try to get more detailed error info
                        let errorMessage = 'An error occurred while saving settings.';
                        
                        if (xhr.responseText) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.message) {
                                    errorMessage = response.message;
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                // If response isn't JSON, might contain HTML error
                                if (xhr.responseText.length < 200) {
                                    errorMessage += ' Server said: ' + xhr.responseText;
                                }
                            }
                        }
                        
                        // Show error message
                        alert(errorMessage);
                        
                        // Log detailed error info
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            response: xhr.responseText
                        });
                    }
                });
            }
            
            // Function to save hotspot settings
            function saveHotspotSettings() {
                let formData = {
                    portal_name: $('#portal-name').val(),
                    redirect_url: $('#redirect-url').val(),
                    portal_theme: $('#portal-theme').val(),
                    enable_free_trial: $('#enable-free-trial').is(':checked') ? 1 : 0
                };
                
                // Add free trial settings if enabled
                if ($('#enable-free-trial').is(':checked')) {
                    formData.free_trial_package = $('#free-trial-package').val();
                    formData.free_trial_limit = $('#free-trial-limit').val();
                }
                
                // Save settings via AJAX
                $.ajax({
                    url: 'save_hotspot_settings.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        // Restore button
                        $('#save-settings-btn').html('<i class="fas fa-save"></i> <span>Save Changes</span>');
                        $('#save-settings-btn').prop('disabled', false);
                        
                        // Show response message
                        if (response.success) {
                            alert('Hotspot settings saved successfully');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Restore button
                        $('#save-settings-btn').html('<i class="fas fa-save"></i> <span>Save Changes</span>');
                        $('#save-settings-btn').prop('disabled', false);
                        
                        // Show error message
                        alert('An error occurred while saving hotspot settings. Please try again.');
                        console.error('AJAX Error:', error);
                    }
                });
            }
        });
    </script>
</body>
</html>