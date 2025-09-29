<?php
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
    header("Location: login.php");
    exit;
}

// Get current user ID
$user_id = getCurrentUserId();

// Check if database connection is available
if (!isset($conn) || $conn === null) {
    // Handle the database connection error
    echo "<div class='error-message'>Database connection error. Please try again later.</div>";
    exit;
}

// Get package counts for tabs
$counts = getPackageCounts($user_id);

// Get packages for all tabs
$all_packages = getAllPackages($user_id);
$hotspot_packages = getPackagesByType($user_id, 'hotspot');
$pppoe_packages = getPackagesByType($user_id, 'pppoe');
$data_plan_packages = getPackagesByType($user_id, 'data-plan');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Plans & Packages</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="other-css/packages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Additional inline styles to ensure modal works */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.show {
            display: flex !important;
        }

        /* Add these styles to fix modal visibility */
        #create-package-modal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
            z-index: 9999 !important;
            display: none;
            justify-content: center !important;
            align-items: center !important;
        }

        #create-package-modal .modal {
            background-color: var(--bg-secondary) !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            width: 95% !important;
            max-width: 600px !important;
            position: relative !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            display: block !important;
            margin: 0 auto !important;
            transform: none !important;
            opacity: 1 !important;
        }
        
        /* Ensure modal content is properly displayed */
        #create-package-modal .modal-header,
        #create-package-modal .modal-body,
        #create-package-modal .modal-footer {
            display: block !important;
            opacity: 1 !important;
        }
        
        /* Free trial badge styling */
        .free-trial-badge {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">
                    Packages
                    <i class="fas fa-info-circle info-icon" title="Manage your internet packages"></i>
                </h1>
                <p class="page-subtitle">All packages available to clients including hotspot, PPPoE and Data Plan packages</p>
            </div>
            <div class="header-actions">
                <button class="create-btn" id="create-package-btn">
                    <i class="fas fa-plus"></i>
                    <span>Create Package</span>
                </button>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="all">
                <i class="fas fa-box"></i>
                <span>All</span>
                <span class="tab-count"><?php echo $counts['all']; ?></span>
            </div>
            <div class="tab" data-tab="hotspot">
                <i class="fas fa-wifi"></i>
                <span>Hotspot</span>
                <span class="tab-count"><?php echo $counts['hotspot']; ?></span>
            </div>
            <div class="tab" data-tab="pppoe">
                <i class="fas fa-network-wired"></i>
                <span>PPPOE</span>
                <span class="tab-count"><?php echo $counts['pppoe']; ?></span>
            </div>
            <div class="tab" data-tab="data-plans">
                <i class="fas fa-database"></i>
                <span>Data Plans</span>
                <span class="tab-count"><?php echo $counts['data-plan']; ?></span>
            </div>
        </div>
        
        <!-- All Packages Tab Content -->
        <div class="tab-content active" id="all-tab">
            <div class="packages-table-container">
                <div class="packages-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search" id="all-search">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="packages-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all"></div>
                                </th>
                                <th>Name <i class="fas fa-sort"></i></th>
                                <th>Price <i class="fas fa-sort"></i></th>
                                <th>Speed <i class="fas fa-sort"></i></th>
                                <th>Time <i class="fas fa-sort"></i></th>
                                <th>Type <i class="fas fa-sort"></i></th>
                                <th>Devices <i class="fas fa-sort"></i></th>
                                <th>Enabled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_packages)): ?>
                                <tr>
                                    <td colspan="9" class="no-data">No packages found. Create a new package to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_packages as $package): ?>
                                    <tr data-id="<?php echo $package['id']; ?>">
                                        <td class="checkbox-cell">
                                            <div class="custom-checkbox"></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($package['name']); ?></td>
                                        <td>
                                            <?php if ((float)$package['price'] == 0): ?>
                                                <span class="free-trial-badge">Free Trial</span>
                                            <?php else: ?>
                                                Ksh <?php echo number_format($package['price'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $package['upload_speed']; ?>M/<?php echo $package['download_speed']; ?>M</td>
                                        <td><?php echo htmlspecialchars($package['duration']); ?></td>
                                        <td><span class="type-badge"><?php echo ucfirst($package['type']); ?></span></td>
                                        <td><?php echo $package['device_limit']; ?></td>
                                        <td><span class="enabled-status"><?php echo $package['is_enabled'] ? 'Yes' : 'No'; ?></span></td>
                                        <td class="actions-cell">
                                            <div class="action-menu">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Showing <?php echo count($all_packages); ?> of <?php echo $counts['all']; ?> results</div>
                    <div class="per-page">
                        <span>Per page</span>
                        <select>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hotspot Tab Content -->
        <div class="tab-content" id="hotspot-tab">
            <div class="packages-table-container">
                <div class="packages-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search" id="hotspot-search">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="packages-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all-hotspot"></div>
                                </th>
                                <th>Name <i class="fas fa-sort"></i></th>
                                <th>Price <i class="fas fa-sort"></i></th>
                                <th>Speed <i class="fas fa-sort"></i></th>
                                <th>Time <i class="fas fa-sort"></i></th>
                                <th>Type <i class="fas fa-sort"></i></th>
                                <th>Devices <i class="fas fa-sort"></i></th>
                                <th>Enabled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hotspot_packages)): ?>
                                <tr>
                                    <td colspan="9" class="no-data">No hotspot packages found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($hotspot_packages as $package): ?>
                                    <tr data-id="<?php echo $package['id']; ?>">
                                        <td class="checkbox-cell">
                                            <div class="custom-checkbox"></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($package['name']); ?></td>
                                        <td>
                                            <?php if ((float)$package['price'] == 0): ?>
                                                <span class="free-trial-badge">Free Trial</span>
                                            <?php else: ?>
                                                Ksh <?php echo number_format($package['price'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $package['upload_speed']; ?>M/<?php echo $package['download_speed']; ?>M</td>
                                        <td><?php echo htmlspecialchars($package['duration']); ?></td>
                                        <td><span class="type-badge"><?php echo ucfirst($package['type']); ?></span></td>
                                        <td><?php echo $package['device_limit']; ?></td>
                                        <td><span class="enabled-status"><?php echo $package['is_enabled'] ? 'Yes' : 'No'; ?></span></td>
                                        <td class="actions-cell">
                                            <div class="action-menu">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Showing <?php echo count($hotspot_packages); ?> of <?php echo $counts['hotspot']; ?> results</div>
                    <div class="per-page">
                        <span>Per page</span>
                        <select>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- PPPOE Tab Content -->
        <div class="tab-content" id="pppoe-tab">
            <div class="packages-table-container">
                <div class="packages-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search" id="pppoe-search">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="packages-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all-pppoe"></div>
                                </th>
                                <th>Name <i class="fas fa-sort"></i></th>
                                <th>Price <i class="fas fa-sort"></i></th>
                                <th>Speed <i class="fas fa-sort"></i></th>
                                <th>Time <i class="fas fa-sort"></i></th>
                                <th>Type <i class="fas fa-sort"></i></th>
                                <th>Devices <i class="fas fa-sort"></i></th>
                                <th>Enabled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pppoe_packages)): ?>
                                <tr>
                                    <td colspan="9" class="no-data">No PPPOE packages found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pppoe_packages as $package): ?>
                                    <tr data-id="<?php echo $package['id']; ?>">
                                        <td class="checkbox-cell">
                                            <div class="custom-checkbox"></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($package['name']); ?></td>
                                        <td>
                                            <?php if ((float)$package['price'] == 0): ?>
                                                <span class="free-trial-badge">Free Trial</span>
                                            <?php else: ?>
                                                Ksh <?php echo number_format($package['price'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $package['upload_speed']; ?>M/<?php echo $package['download_speed']; ?>M</td>
                                        <td><?php echo htmlspecialchars($package['duration']); ?></td>
                                        <td><span class="type-badge"><?php echo ucfirst($package['type']); ?></span></td>
                                        <td><?php echo $package['device_limit']; ?></td>
                                        <td><span class="enabled-status"><?php echo $package['is_enabled'] ? 'Yes' : 'No'; ?></span></td>
                                        <td class="actions-cell">
                                            <div class="action-menu">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Showing <?php echo count($pppoe_packages); ?> of <?php echo $counts['pppoe']; ?> results</div>
                    <div class="per-page">
                        <span>Per page</span>
                        <select>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Plans Tab Content -->
        <div class="tab-content" id="data-plans-tab">
            <div class="packages-table-container">
                <div class="packages-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search" id="data-plan-search">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="packages-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all-data"></div>
                                </th>
                                <th>Name <i class="fas fa-sort"></i></th>
                                <th>Price <i class="fas fa-sort"></i></th>
                                <th>Speed <i class="fas fa-sort"></i></th>
                                <th>Time <i class="fas fa-sort"></i></th>
                                <th>Type <i class="fas fa-sort"></i></th>
                                <th>Devices <i class="fas fa-sort"></i></th>
                                <th>Enabled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data_plan_packages)): ?>
                                <tr>
                                    <td colspan="9" class="no-data">No data plan packages found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($data_plan_packages as $package): ?>
                                    <tr data-id="<?php echo $package['id']; ?>">
                                        <td class="checkbox-cell">
                                            <div class="custom-checkbox"></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($package['name']); ?></td>
                                        <td>
                                            <?php if ((float)$package['price'] == 0): ?>
                                                <span class="free-trial-badge">Free Trial</span>
                                            <?php else: ?>
                                                Ksh <?php echo number_format($package['price'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $package['upload_speed']; ?>M/<?php echo $package['download_speed']; ?>M</td>
                                        <td><?php echo htmlspecialchars($package['duration']); ?></td>
                                        <td><span class="type-badge"><?php echo ucfirst($package['type']); ?></span></td>
                                        <td><?php echo $package['device_limit']; ?></td>
                                        <td><span class="enabled-status"><?php echo $package['is_enabled'] ? 'Yes' : 'No'; ?></span></td>
                                        <td class="actions-cell">
                                            <div class="action-menu">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Showing <?php echo count($data_plan_packages); ?> of <?php echo $counts['data-plan']; ?> results</div>
                    <div class="per-page">
                        <span>Per page</span>
                        <select>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
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
    
    <!-- Create Package Modal -->
    <div class="modal-overlay" id="create-package-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Create New Package</h3>
                <button class="modal-close" id="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="package-form">
                    <div class="form-group">
                        <label for="package-type" class="form-label">Package Type</label>
                        <select id="package-type" name="package_type" class="form-select" required>
                            <option value="" disabled selected>Select package type</option>
                            <option value="hotspot">Hotspot</option>
                            <option value="pppoe">PPPOE</option>
                            <option value="data-plan">Data Plan</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="package-name" class="form-label">Package Name</label>
                        <input type="text" id="package-name" name="package_name" class="form-input" placeholder="Enter package name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="package-duration" class="form-label">Duration</label>
                        <select id="package-duration" name="package_duration" class="form-select" required>
                            <option value="" disabled selected>Select duration</option>
                            <option value="1-hour">1 Hour</option>
                            <option value="2-hours">2 Hours</option>
                            <option value="6-hours">6 Hours</option>
                            <option value="12-hours">12 Hours</option>
                            <option value="1-day">1 Day</option>
                            <option value="5-day">5 Days</option>
                            <option value="7-days">7 Days</option>
                            <option value="30-days">30 Days</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="upload-speed" class="form-label">Upload Speed (Mbps)</label>
                                <input type="number" id="upload-speed" name="upload_speed" class="form-input" placeholder="Enter upload speed" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="download-speed" class="form-label">Download Speed (Mbps)</label>
                                <input type="number" id="download-speed" name="download_speed" class="form-input" placeholder="Enter download speed" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="package-price" class="form-label">Price (KSH)</label>
                                <input type="number" id="package-price" name="package_price" class="form-input" placeholder="Enter price" required>
                                <small class="form-help">Set to 0 for a free trial package</small>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="device-limit" class="form-label">Device Limit</label>
                                <input type="number" id="device-limit" name="device_limit" class="form-input" placeholder="Enter device limit" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="free-trial-limit-field" style="display: none;">
                        <label for="free-trial-limit" class="form-label">Free Trial Usage Limit</label>
                        <select id="free-trial-limit" name="free_trial_limit" class="form-select">
                            <option value="1">1 time only</option>
                            <option value="2">2 times</option>
                            <option value="3">3 times</option>
                        </select>
                        <small class="form-help">How many times a user can access this free trial (tracked by phone number)</small>
                    </div>
                    
                    <div class="form-group" id="data-limit-field" style="display: none;">
                        <label for="data-limit" class="form-label">Data Limit (MB)</label>
                        <input type="number" id="data-limit" name="data_limit" class="form-input" placeholder="Enter data limit in MB">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancel-btn">Cancel</button>
                <button class="btn btn-primary" id="save-package-btn">
                    <i class="fas fa-save"></i>
                    <span>Save Package</span>
                </button>
            </div>
        </div>
    </div>

   <script src="other-js/packages.js"></script>
   <script src="script.js"></script>
   <script>
      // Completely override modal functionality to fix display issues
      document.addEventListener('DOMContentLoaded', function() {
          console.log('DOM loaded in inline script');
          
          // Elements
          const createBtn = document.getElementById('create-package-btn');
          const modal = document.getElementById('create-package-modal');
          const closeBtn = document.getElementById('modal-close');
          const cancelBtn = document.getElementById('cancel-btn');
          
          // Debug element presence
          console.log('Create button exists:', !!createBtn);
          console.log('Modal exists:', !!modal);
          console.log('Close button exists:', !!closeBtn);
          console.log('Cancel button exists:', !!cancelBtn);
          
          // Create button handler - force display the modal
          if (createBtn) {
              // Remove any existing event listeners by cloning and replacing
              const newCreateBtn = createBtn.cloneNode(true);
              createBtn.parentNode.replaceChild(newCreateBtn, createBtn);
              
              newCreateBtn.addEventListener('click', function(e) {
                  e.preventDefault();
                  e.stopPropagation();
                  console.log('Create package button clicked');
                  
                  // Force display the modal with inline style
                  if (modal) {
                      modal.setAttribute('style', 'display: flex !important');
                      console.log('Modal display style set to:', modal.style.display);
                  }
                  
                  return false;
              });
          }
          
          // Close button handler
          if (closeBtn) {
              // Remove any existing event listeners
              const newCloseBtn = closeBtn.cloneNode(true);
              closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
              
              newCloseBtn.addEventListener('click', function(e) {
                  e.preventDefault();
                  e.stopPropagation();
                  console.log('Close button clicked');
                  
                  if (modal) {
                      modal.setAttribute('style', 'display: none !important');
                  }
                  
                  return false;
              });
          }
          
          // Cancel button handler
          if (cancelBtn) {
              // Remove any existing event listeners
              const newCancelBtn = cancelBtn.cloneNode(true);
              cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
              
              newCancelBtn.addEventListener('click', function(e) {
                  e.preventDefault();
                  e.stopPropagation();
                  console.log('Cancel button clicked');
                  
                  if (modal) {
                      modal.setAttribute('style', 'display: none !important');
                  }
                  
                  return false;
              });
          }
          
          // Click outside to close
          if (modal) {
              modal.addEventListener('click', function(e) {
                  // Only close if clicking directly on the modal background, not its children
                  if (e.target === modal) {
                      console.log('Clicked outside modal content');
                      modal.setAttribute('style', 'display: none !important');
                  }
              });
          }
          
          // Test the modal's display property
          if (modal) {
              console.log('Initial modal display property:', window.getComputedStyle(modal).display);
              
              // Add a test button to show modal (for debugging)
              console.log('Adding test function to window object');
              window.testShowModal = function() {
                  console.log('Test function called');
                  modal.setAttribute('style', 'display: flex !important');
                  return 'Modal style set to: ' + modal.getAttribute('style');
              };
          }
      });
      
      // Add event listener for price field to show/hide free trial limit field
      document.addEventListener('DOMContentLoaded', function() {
          const priceField = document.getElementById('package-price');
          const freeTrialLimitField = document.getElementById('free-trial-limit-field');
          
          if (priceField && freeTrialLimitField) {
              priceField.addEventListener('input', function() {
                  if (this.value === '0') {
                      freeTrialLimitField.style.display = 'block';
                  } else {
                      freeTrialLimitField.style.display = 'none';
                  }
              });
              
              // Check initial value
              if (priceField.value === '0') {
                  freeTrialLimitField.style.display = 'block';
              }
          }
      });
   </script>
</body>
</html>