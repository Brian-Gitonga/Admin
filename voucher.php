<?php
// Start session at the very beginning of the file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}

// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];

// Include database connection for initial counts
require_once 'vouchers_script/db_connection.php';

// Get counts
$unusedCount = countVouchersByStatus($conn, 'active', $resellerId);
$usedCount = countVouchersByStatus($conn, 'used', $resellerId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Vouchers</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="other-css/voucher.css">
    <style>
        /* Custom styles for credential columns */
        .vouchers-table th:nth-child(4),  /* Username column */
        .vouchers-table th:nth-child(5) { /* Password column */
            min-width: 120px;
            max-width: 200px;
        }
        
        .vouchers-table td:nth-child(4),
        .vouchers-table td:nth-child(5) {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <?php 
    // Include the navigation
    include 'nav.php'; 
    ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">Vouchers</h1>
            </div>
            <div class="header-buttons">
                <a href="upload_voucher.php" class="upload-btn">
                    <i class="fas fa-upload"></i>
                    <span>Upload Vouchers</span>
                </a>
                <button class="create-btn" id="create-voucher-btn">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Create Voucher</span>
                </button>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="unused">
                <i class="fas fa-ticket-alt"></i>
                <span>Unused</span>
                <span class="tab-count"><?php echo $unusedCount; ?></span>
            </div>
            <div class="tab" data-tab="used">
                <i class="fas fa-check-circle"></i>
                <span>Used</span>
                <span class="tab-count"><?php echo $usedCount; ?></span>
            </div>
        </div>
        
        <!-- Unused Vouchers Tab Content -->
        <div class="tab-content active" id="unused-tab">
            <div class="vouchers-table-container">
                <div class="vouchers-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search">
                    </div>
                    <div class="filter-btn">
                        <i class="fas fa-filter"></i>
                        <span class="badge">0</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="vouchers-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all"></div>
                                </th>
                                <th>Voucher Code</th>
                                <th>Package <i class="fas fa-sort"></i></th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Used At <i class="fas fa-sort"></i></th>
                                <th>Generated At <i class="fas fa-sort"></i></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center" style="text-align: center; color: var(--text-secondary);">
                                    Loading vouchers...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Loading results...</div>
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
        
        <!-- Used Vouchers Tab Content -->
        <div class="tab-content" id="used-tab">
            <div class="vouchers-table-container">
                <div class="vouchers-table-header">
                    <div class="search-filter">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search">
                    </div>
                    <div class="filter-btn">
                        <i class="fas fa-filter"></i>
                        <span class="badge">0</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="vouchers-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <div class="custom-checkbox" id="select-all-used"></div>
                                </th>
                                <th>Voucher Code</th>
                                <th>Package <i class="fas fa-sort"></i></th>
                                <th>Username</th>
                                <th>Password</th>
                                <th>Used At <i class="fas fa-sort"></i></th>
                                <th>Generated At <i class="fas fa-sort"></i></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center" style="text-align: center; color: var(--text-secondary);">
                                    Loading vouchers...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="table-footer">
                    <div class="results-info">Loading results...</div>
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
            <div class="copyright">© 2025 Qtro ISP Billing. All Rights Reserved.</div>
        </div>
    </div>
    
    <!-- Create Voucher Modal -->
    <div class="modal-overlay" id="create-voucher-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Create Voucher</h3>
                <button class="modal-close" id="create-modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="voucher-form">
                    <div class="form-group">
                        <label for="package-type" class="form-label">Package</label>
                        <select id="package-type" class="form-select" required>
                            <option value="" disabled selected>Select package</option>
                            <!-- Packages will be loaded via Ajax -->
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="voucher-count" class="form-label">Number of Vouchers</label>
                        <input type="number" id="voucher-count" class="form-input" placeholder="Enter number of vouchers" min="1" max="100" value="1" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="create-cancel-btn">Cancel</button>
                <button class="btn btn-primary" id="save-voucher-btn">
                    <i class="fas fa-save"></i>
                    <span>Generate Vouchers</span>
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="vouchers_script/voucher.js"></script>
</body>
</html>