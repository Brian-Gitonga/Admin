<?php 
// Start session if not started - THIS MUST BE AT THE VERY TOP OF THE FILE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the navigation helper (assuming this may contain session_check.php)
require_once 'vouchers_script/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'login.php';</script>";
    exit;
}


// Get the reseller ID from the session
$resellerId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Upload Vouchers</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Upload Voucher Page Styles */
        .upload-container {
            background-color: var(--bg-secondary);
            border-radius: 0.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-file {
            display: none;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--bg-accent);
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.9rem;
            transition: border-color 0.2s ease;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--accent-blue);
        }
        
        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
        }
        
        .file-upload-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 2rem 1rem;
            border-radius: 0.5rem;
            border: 2px dashed var(--bg-accent);
            background-color: var(--bg-primary);
            color: var(--text-secondary);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .file-upload-btn:hover {
            border-color: var(--accent-blue);
            color: var(--text-primary);
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .form-hint {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .submit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 0.5rem;
            background-color: var(--accent-blue);
            color: white;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 1rem;
        }
        
        .submit-btn:hover {
            background-color: #2563eb;
        }
        
        .submit-btn:disabled {
            background-color: #93c5fd;
            cursor: not-allowed;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: var(--text-primary);
        }
        
        .instructions {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .instructions h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .instructions ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .instructions li {
            margin-bottom: 0.5rem;
        }
        
        #upload-status {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
            display: none;
        }
        
        #upload-status.success {
            display: block;
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
        }
        
        #upload-status.error {
            display: block;
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--accent-red);
        }
        
        .loading-spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .flex-row {
            display: flex;
            gap: 1rem;
        }
        
        .flex-col {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .flex-row {
                flex-direction: column;
            }
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
                <h1 class="page-title">Upload Vouchers</h1>
            </div>
        </div>
        
        <a href="voucher.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Vouchers</span>
        </a>
        
        <div class="upload-container">
            <form id="upload-form" enctype="multipart/form-data">
                <div class="flex-row">
                    <div class="flex-col">
                        <div class="form-group">
                            <label for="package-id" class="form-label">Select Package</label>
                            <select id="package-id" name="package_id" class="form-select" required>
                                <option value="" disabled selected>Select package</option>
                                <!-- Packages will be loaded via Ajax -->
                            </select>
                            <p class="form-hint">
                                The package these vouchers will be associated with
                            </p>
                        </div>
                    </div>
                    <div class="flex-col">
                        <div class="form-group">
                            <label for="router-id" class="form-label">Select Router</label>
                            <select id="router-id" name="router_id" class="form-select" required>
                                <option value="" disabled selected>Select router</option>
                                <!-- Routers will be loaded via Ajax -->
                            </select>
                            <p class="form-hint">
                                The router these vouchers belong to
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="voucher-file" class="form-label">Select Voucher File</label>
                    <input type="file" id="voucher-file" name="file" class="form-file" accept=".csv, .xlsx, .xls, .pdf" required>
                    <label for="voucher-file" class="file-upload-btn" id="file-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click to select file or drag and drop</span>
                    </label>
                    <p class="form-hint">
                        Accepted formats: CSV, Excel (.xlsx, .xls), PDF
                    </p>
                </div>
                
                <div id="upload-status"></div>
                
                <div class="form-group" style="margin-top: 2rem;">
                    <button type="submit" class="submit-btn" id="upload-btn">
                        <i class="fas fa-upload"></i>
                        <span>Upload Vouchers</span>
                    </button>
                </div>
            </form>
            
            <div class="instructions">
                <h3><i class="fas fa-info-circle"></i> Instructions</h3>
                <ul>
                    <li>Generate vouchers from your MikroTik User Manager first</li>
                    <li>Export the vouchers as CSV, Excel or PDF</li>
                    <li>Select the appropriate package and router from the dropdowns</li>
                    <li>Upload the file using the form above</li>
                    <li>For CSV/Excel files: ensure there's either a <strong>username</strong> column (preferred) or <strong>code</strong> column with voucher codes</li>
                    <li>For PDF files: the system will attempt to extract voucher codes automatically</li>
                    <li>Each voucher code must be unique in the system</li>
                    <li>Maximum file size: 5MB</li>
                </ul>
            </div>
        </div>
        
        <div class="page-footer">
            <div class="footer-links">
                <a href="#" class="footer-link">Whatsapp Channel</a>
                <a href="#" class="footer-link">Privacy & Terms</a>
            </div>
            <div class="copyright">© 2025 Centipid Billing. All Rights Reserved.</div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('voucher-file');
            const fileLabel = document.getElementById('file-label');
            const uploadForm = document.getElementById('upload-form');
            const uploadStatus = document.getElementById('upload-status');
            const uploadBtn = document.getElementById('upload-btn');
            const packageSelect = document.getElementById('package-id');
            const routerSelect = document.getElementById('router-id');
            
            // Check for URL parameters for pre-selection
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedPackageId = urlParams.get('package');
            const preselectedRouterId = urlParams.get('router');
            
            // Fetch packages for the dropdown
            fetchPackages();
            
            // Fetch routers for the dropdown
            fetchRouters();
            
            // File selection change
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileLabel.innerHTML = `<i class="fas fa-file"></i><span>${this.files[0].name}</span>`;
                } else {
                    fileLabel.innerHTML = `<i class="fas fa-cloud-upload-alt"></i><span>Click to select file or drag and drop</span>`;
                }
                validateForm();
            });
            
            // Packages and routers change - validate form
            packageSelect.addEventListener('change', validateForm);
            routerSelect.addEventListener('change', validateForm);
            
            // Validate form function
            function validateForm() {
                const isPackageSelected = packageSelect.value !== '';
                const isRouterSelected = routerSelect.value !== '';
                const isFileSelected = fileInput.files.length > 0;
                
                uploadBtn.disabled = !(isPackageSelected && isRouterSelected && isFileSelected);
            }
            
            // Initialize form validation
            validateForm();
            
            // Drag and drop functionality
            fileLabel.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--accent-blue)';
                this.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            });
            
            fileLabel.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            });
            
            fileLabel.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.backgroundColor = '';
                
                if (e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    if (fileInput.files.length > 0) {
                        fileLabel.innerHTML = `<i class="fas fa-file"></i><span>${fileInput.files[0].name}</span>`;
                    }
                    validateForm();
                }
            });
            
            // Form submission
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!fileInput.files.length) {
                    showStatus('Please select a file to upload', 'error');
                    return;
                }
                
                if (!packageSelect.value) {
                    showStatus('Please select a package', 'error');
                    return;
                }
                
                if (!routerSelect.value) {
                    showStatus('Please select a router', 'error');
                    return;
                }
                
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('package_id', packageSelect.value);
                formData.append('router_id', routerSelect.value);
                
                // Disable button and show loading
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Uploading...</span>';
                
                // Send request to server
                fetch('vouchers_script/upload_vouchers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showStatus(data.message, 'success');
                        // Reset form after successful upload
                        uploadForm.reset();
                        fileLabel.innerHTML = `<i class="fas fa-cloud-upload-alt"></i><span>Click to select file or drag and drop</span>`;
                        
                        // Redirect to vouchers page after 2 seconds
                        setTimeout(() => {
                            window.location.href = 'voucher.php';
                        }, 2000);
                    } else {
                        showStatus(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatus('An error occurred during upload. Please try again.', 'error');
                })
                .finally(() => {
                    // Re-enable button
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-upload"></i><span>Upload Vouchers</span>';
                    validateForm();
                });
            });
            
            // Function to show status message
            function showStatus(message, type) {
                uploadStatus.textContent = message;
                uploadStatus.className = ''; // Reset classes
                uploadStatus.classList.add(type);
                uploadStatus.style.display = 'block';
                
                // Scroll to status message
                uploadStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Function to fetch packages
            function fetchPackages() {
                // Add loading option
                packageSelect.innerHTML = '<option value="" disabled selected>Loading packages...</option>';
                
                fetch('vouchers_script/get_packages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.packages && data.packages.length > 0) {
                        // Clear dropdown
                        packageSelect.innerHTML = '<option value="" disabled selected>Select package</option>';
                        
                        // Add packages to dropdown
                        data.packages.forEach(pkg => {
                            const option = document.createElement('option');
                            option.value = pkg.id;
                            
                            // Format the display text with more details from the updated package structure
                            let packageDetails = `${pkg.name} - ${pkg.price}`;
                            
                            // Add speed if available
                            if (pkg.speed) {
                                packageDetails += ` (${pkg.speed})`;
                            }
                            
                            // Add duration if available
                            if (pkg.duration) {
                                packageDetails += ` - ${pkg.duration}`;
                            }
                            
                            option.textContent = packageDetails;
                            
                            // Store additional data that might be needed later
                            option.dataset.type = pkg.type;
                            option.dataset.duration = pkg.duration;
                            option.dataset.dataLimit = pkg.data_limit;
                            
                            packageSelect.appendChild(option);
                        });
                        
                        // Pre-select package if provided in URL
                        if (preselectedPackageId) {
                            packageSelect.value = preselectedPackageId;
                        }
                    } else {
                        packageSelect.innerHTML = '<option value="" disabled selected>No packages available</option>';
                    }
                    validateForm();
                })
                .catch(error => {
                    console.error('Error fetching packages:', error);
                    packageSelect.innerHTML = '<option value="" disabled selected>Error loading packages</option>';
                    validateForm();
                });
            }
            
            // Function to fetch routers
            function fetchRouters() {
                // Add loading option
                routerSelect.innerHTML = '<option value="" disabled selected>Loading routers...</option>';
                
                fetch('vouchers_script/get_routers.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.routers && data.routers.length > 0) {
                        // Clear dropdown
                        routerSelect.innerHTML = '<option value="" disabled selected>Select router</option>';
                        
                        // Add routers to dropdown
                        data.routers.forEach(router => {
                            const option = document.createElement('option');
                            option.value = router.id;
                            option.textContent = router.name;
                            routerSelect.appendChild(option);
                        });
                        
                        // Pre-select router if provided in URL
                        if (preselectedRouterId) {
                            routerSelect.value = preselectedRouterId;
                        }
                    } else {
                        routerSelect.innerHTML = '<option value="" disabled selected>No routers available</option>';
                    }
                    validateForm();
                })
                .catch(error => {
                    console.error('Error fetching routers:', error);
                    routerSelect.innerHTML = '<option value="" disabled selected>Error loading routers</option>';
                    validateForm();
                });
            }
        });
    </script>
</body>
</html> 