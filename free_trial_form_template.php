<?php
// This is a template file for the free trial registration form
// It will be included in the captive portal login page when a free trial is available

// Check if free trial is enabled and the user is eligible
$free_trial_enabled = false;

if (isset($hotspotSettings) && $hotspotSettings['enable_free_trial']) {
    $free_trial_enabled = true;
    
    // Get package details
    $package_id = $hotspotSettings['free_trial_package'];
    $package_name = '';
    $package_duration = '';
    
    // Get the package details
    $query = "SELECT name, duration FROM packages WHERE id = ? AND reseller_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $package_id, $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $package = $result->fetch_assoc();
        $package_name = $package['name'];
        $package_duration = $package['duration'];
    }
}

// Only show the form if free trial is enabled
if ($free_trial_enabled):
?>
<div class="free-trial-section">
    <h3>Try Our Free Internet</h3>
    <p>Get a free <?php echo htmlspecialchars($package_name); ?> (<?php echo htmlspecialchars($package_duration); ?>) trial now!</p>
    
    <form id="free-trial-form" action="process_free_trial.php" method="post">
        <div class="form-group">
            <label for="phone-number">Your Phone Number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                <input type="tel" id="phone-number" name="phone_number" class="form-control" placeholder="Enter your phone number" required>
            </div>
            <small class="form-text">We'll send your access voucher to this number</small>
        </div>
        
        <input type="hidden" name="mac_address" value="<?php echo htmlspecialchars($mac_address ?? ''); ?>">
        <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($ip_address ?? ''); ?>">
        
        <div class="form-check">
            <input type="checkbox" id="terms-agree" name="terms_agree" class="form-check-input" required>
            <label for="terms-agree" class="form-check-label">I agree to the terms and conditions</label>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">Get Free Trial</button>
    </form>
</div>
<?php endif; ?> 