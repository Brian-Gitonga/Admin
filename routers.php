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

// Get selected router ID from URL parameter
$selectedRouterId = isset($_GET['router_id']) ? intval($_GET['router_id']) : 0;

// Include database connection
require_once 'vouchers_script/db_connection.php';

// Placeholder data - this will be replaced with actual data from the database
$routerCount = 0;
$voucherCount = 0;
$packageCount = 0;
$activeRouters = 0;

// Get actual router count
$routerQuery = "SELECT COUNT(*) as count FROM hotspots WHERE reseller_id = ?";
$stmt = $conn->prepare($routerQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $routerCount = $row['count'];
    }
}

// Get active router count
$activeRouterQuery = "SELECT COUNT(*) as count FROM hotspots WHERE reseller_id = ? AND status = 'online'";
$stmt = $conn->prepare($activeRouterQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $activeRouters = $row['count'];
    }
}

// Get voucher count (active/unused)
$voucherQuery = "SELECT COUNT(*) as count FROM vouchers WHERE reseller_id = ? AND status = 'active'";
$stmt = $conn->prepare($voucherQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $voucherCount = $row['count'];
    }
}

// Get package count
$packageQuery = "SELECT COUNT(*) as count FROM packages WHERE reseller_id = ?";
$stmt = $conn->prepare($packageQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $packageCount = $row['count'];
    }
}

// Placeholder for current router info
$currentRouter = [
    'id' => 0,
    'name' => 'Select Router',
    'ip' => '-',
    'status' => 'unknown'
];

// Get the current router (selected or latest)
if ($selectedRouterId > 0) {
    // Get specific selected router
    $routerQuery = "SELECT id, name, router_ip, status, last_checked FROM hotspots
                    WHERE reseller_id = ? AND id = ? LIMIT 1";
    $stmt = $conn->prepare($routerQuery);
    if ($stmt) {
        $stmt->bind_param("ii", $resellerId, $selectedRouterId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $currentRouter = [
                'id' => $row['id'],
                'name' => $row['name'],
                'ip' => $row['router_ip'],
                'status' => $row['status'],
                'last_checked' => $row['last_checked']
            ];
        }
    }
} else {
    // Get the latest router (most recently added) as default
    $latestRouterQuery = "SELECT id, name, router_ip, status, last_checked FROM hotspots
                          WHERE reseller_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($latestRouterQuery);
    if ($stmt) {
        $stmt->bind_param("i", $resellerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $currentRouter = [
                'id' => $row['id'],
                'name' => $row['name'],
                'ip' => $row['router_ip'],
                'status' => $row['status'],
                'last_checked' => $row['last_checked']
            ];
        }
    }
}

// Get all routers for the dropdown
$routers = [];
$routersQuery = "SELECT id, name, router_ip, status FROM hotspots WHERE reseller_id = ? ORDER BY name";
$stmt = $conn->prepare($routersQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $routers[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'ip' => $row['router_ip'],
            'status' => $row['status']
        ];
    }
}

// Get voucher counts per package, now including router information
$packageVouchers = [];
$packageVouchersQuery = "SELECT 
                         p.id, 
                         p.name, 
                         p.price, 
                         COUNT(v.id) as voucher_count,
                         COALESCE(h.name, 'Unassigned') as router_name,
                         COALESCE(h.id, 0) as router_id,
                         COUNT(CASE WHEN v.router_id IS NOT NULL THEN v.id END) as router_assigned_count
                         FROM packages p 
                         LEFT JOIN vouchers v ON p.id = v.package_id AND v.status = 'active'
                         LEFT JOIN hotspots h ON v.router_id = h.id
                         WHERE p.reseller_id = ?
                         GROUP BY p.id, h.id
                         ORDER BY p.name ASC, router_name ASC";
$stmt = $conn->prepare($packageVouchersQuery);
if ($stmt) {
    $stmt->bind_param("i", $resellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Group results by package
    $tempPackages = [];
    while ($row = $result->fetch_assoc()) {
        $packageId = $row['id'];
        if (!isset($tempPackages[$packageId])) {
            $tempPackages[$packageId] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'price' => $row['price'],
                'total_voucher_count' => 0,
                'routers' => []
            ];
        }
        
        // Add to total voucher count
        $tempPackages[$packageId]['total_voucher_count'] += $row['voucher_count'];
        
        // Add router info
        if ($row['voucher_count'] > 0) {
            $tempPackages[$packageId]['routers'][] = [
                'router_id' => $row['router_id'],
                'router_name' => $row['router_name'],
                'voucher_count' => $row['voucher_count']
            ];
        }
    }
    
    // Convert to array format
    foreach ($tempPackages as $package) {
        $packageVouchers[] = $package;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qtro ISP - Router Management</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="other-css/hotspot_list.css">
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
                <h1 class="page-title">Router Management</h1>
            </div>
            <div class="header-buttons">
                <a href="linkrouter.php" class="upload-btn">
                    <i class="fas fa-plus"></i>
                    <span>Add New Router</span>
                </a>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-title">
                    <i class="fas fa-wifi"></i>
                    Total Routers
                </div>
                <div class="stat-value"><?php echo $routerCount; ?></div>
                <div class="stat-comparison">
                    <span>Configured routers</span>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-title">
                    <i class="fas fa-check-circle"></i>
                    Active Routers
                </div>
                <div class="stat-value"><?php echo $activeRouters; ?></div>
                <div class="stat-comparison">
                    <span>Currently online</span>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-title">
                    <i class="fas fa-ticket-alt"></i>
                    Available Vouchers
                </div>
                <div class="stat-value"><?php echo $voucherCount; ?></div>
                <div class="stat-comparison">
                    <span>Ready to use</span>
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-title">
                    <i class="fas fa-cube"></i>
                    Total Packages
                </div>
                <div class="stat-value"><?php echo $packageCount; ?></div>
                <div class="stat-comparison">
                    <span>Configured packages</span>
                </div>
            </div>
        </div>

        <!-- API Management Section -->
        <div class="api-management-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    API Management
                </h2>
                <div class="api-actions">
                    <button id="generate-api-key" class="api-btn primary">
                        <i class="fas fa-plus"></i>
                        <span>Generate API Key</span>
                    </button>
                    <button id="view-api-docs" class="api-btn secondary">
                        <i class="fas fa-book"></i>
                        <span>API Documentation</span>
                    </button>
                </div>
            </div>

            <div class="api-content">
                <div class="api-key-section">
                    <div class="api-key-card">
                        <div class="api-key-header">
                            <h3>Your API Key</h3>
                            <div class="api-key-status">
                                <span class="status-indicator active"></span>
                                <span>Active</span>
                            </div>
                        </div>
                        <div class="api-key-display">
                            <div class="api-key-value" id="api-key-value">
                                <?php
                                // Get current API key for the user
                                $apiKeyQuery = "SELECT api_key FROM resellers WHERE id = ?";
                                $stmt = $conn->prepare($apiKeyQuery);
                                if ($stmt) {
                                    $stmt->bind_param("i", $resellerId);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($row = $result->fetch_assoc()) {
                                        $apiKey = $row['api_key'];
                                        if ($apiKey) {
                                            echo '<span class="key-text">' . htmlspecialchars($apiKey) . '</span>';
                                        } else {
                                            echo '<span class="no-key">No API key generated yet</span>';
                                        }
                                    } else {
                                        echo '<span class="no-key">No API key generated yet</span>';
                                    }
                                } else {
                                    echo '<span class="no-key">Error loading API key</span>';
                                }
                                ?>
                            </div>
                            <div class="api-key-actions">
                                <button id="copy-api-key" class="action-btn" title="Copy API Key">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button id="regenerate-api-key" class="action-btn" title="Regenerate API Key">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="api-key-info">
                            <p><i class="fas fa-info-circle"></i> Use this API key to authenticate requests to the batch voucher API</p>
                        </div>
                    </div>
                </div>

                <div class="api-stats-section">
                    <div class="api-stats-grid">
                        <div class="api-stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">0</div>
                                <div class="stat-label">API Requests Today</div>
                            </div>
                        </div>
                        <div class="api-stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">0</div>
                                <div class="stat-label">Vouchers via API</div>
                            </div>
                        </div>
                        <div class="api-stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value">100%</div>
                                <div class="stat-label">Success Rate</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="api-endpoints-section">
                    <h3>Available Endpoints</h3>
                    <div class="endpoint-list">
                        <div class="endpoint-item">
                            <div class="endpoint-method post">POST</div>
                            <div class="endpoint-path">/api/vouchers</div>
                            <div class="endpoint-description">Batch create vouchers (up to 100 per request)</div>
                        </div>
                    </div>
                </div>

                <div class="router-mapping-section">
                    <h3>Router Mapping</h3>
                    <div class="router-mapping-info">
                        <p>Your routers and their API identifiers:</p>
                        <div class="router-mapping-list">
                            <?php foreach ($routers as $router): ?>
                            <div class="router-mapping-item">
                                <div class="router-info">
                                    <span class="router-name"><?php echo htmlspecialchars($router['name']); ?></span>
                                    <span class="router-ip"><?php echo htmlspecialchars($router['ip']); ?></span>
                                </div>
                                <div class="router-id">
                                    <span class="id-label">API ID:</span>
                                    <code class="router-api-id"><?php echo htmlspecialchars($router['name']); ?></code>
                                    <button class="copy-router-id" data-router-id="<?php echo htmlspecialchars($router['name']); ?>" title="Copy Router ID">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($routers)): ?>
                            <div class="no-routers">
                                <i class="fas fa-wifi"></i>
                                <p>No routers configured yet. <a href="linkrouter.php">Add a router</a> to start using the API.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Router Selection -->
        <div class="router-selection">
            <div class="router-header">
                <div class="router-details">
                    <div class="router-title"><?php echo htmlspecialchars($currentRouter['name']); ?></div>
                    <div class="router-address"><?php echo htmlspecialchars($currentRouter['ip']); ?></div>
                </div>
                
                <button id="refresh-router" class="router-refresh" title="Refresh Router Status">
                    <i class="fas fa-sync-alt"></i>
                </button>
                
                <div class="router-status <?php echo $currentRouter['status']; ?>">
                    <i class="fas fa-<?php echo $currentRouter['status'] === 'online' ? 'check-circle' : ($currentRouter['status'] === 'offline' ? 'times-circle' : 'question-circle'); ?>"></i>
                    <span><?php echo ucfirst($currentRouter['status']); ?></span>
                </div>
                
                <select id="router-selector" class="router-selector">
                    <option value="">Select Router</option>
                    <?php foreach ($routers as $router): ?>
                    <option value="<?php echo $router['id']; ?>" <?php echo ($router['id'] == $currentRouter['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($router['name']); ?> (<?php echo $router['status']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="router-actions">
                <button class="router-btn primary">
                    <i class="fas fa-sync-alt"></i>
                    <span>Sync Vouchers</span>
                </button>
                <button class="router-btn secondary" id="download-captive-portal-btn">
                    <i class="fas fa-download"></i>
                    <span>Download Captive Portal</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Package List -->
            <div class="package-list">
                <div class="section-title">
                    <i class="fas fa-cube"></i>
                    <span>Package Voucher Status</span>
                </div>
                
                <?php if (empty($packageVouchers)): ?>
                <div class="empty-state">
                    <i class="fas fa-cube"></i>
                    <h3>No Packages Found</h3>
                    <p>You don't have any packages configured yet. Create packages to see their voucher status here.</p>
                </div>
                <?php else: ?>
                <table class="package-table">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Price</th>
                            <th>Vouchers</th>
                            <th>Routers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packageVouchers as $package): 
                            $voucherClass = 'low';
                            if ($package['total_voucher_count'] >= 20) {
                                $voucherClass = 'good';
                            } else if ($package['total_voucher_count'] >= 10) {
                                $voucherClass = 'medium';
                            }
                        ?>
                        <tr data-package-id="<?php echo $package['id']; ?>">
                            <td><?php echo htmlspecialchars($package['name']); ?></td>
                            <td><?php echo htmlspecialchars($package['price']); ?></td>
                            <td>
                                <span class="voucher-count <?php echo $voucherClass; ?>">
                                    <?php echo $package['total_voucher_count']; ?>
                                    <?php if ($package['total_voucher_count'] < 10): ?>
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($package['routers'])): ?>
                                <div class="router-distribution">
                                    <?php foreach ($package['routers'] as $router): ?>
                                    <div class="router-tag" data-router-id="<?php echo $router['router_id']; ?>">
                                        <span class="router-name"><?php echo htmlspecialchars($router['router_name']); ?></span>
                                        <span class="router-voucher-count"><?php echo $router['voucher_count']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <span class="no-routers">No vouchers</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
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

    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // API Management functionality
            const generateApiKeyBtn = document.getElementById('generate-api-key');
            const copyApiKeyBtn = document.getElementById('copy-api-key');
            const regenerateApiKeyBtn = document.getElementById('regenerate-api-key');
            const viewApiDocsBtn = document.getElementById('view-api-docs');

            // Generate/Regenerate API Key
            if (generateApiKeyBtn) {
                generateApiKeyBtn.addEventListener('click', function() {
                    generateApiKey();
                });
            }

            if (regenerateApiKeyBtn) {
                regenerateApiKeyBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to regenerate your API key? This will invalidate the current key.')) {
                        generateApiKey();
                    }
                });
            }

            // Copy API Key
            if (copyApiKeyBtn) {
                copyApiKeyBtn.addEventListener('click', function() {
                    const keyText = document.querySelector('.key-text');
                    if (keyText) {
                        navigator.clipboard.writeText(keyText.textContent).then(function() {
                            showNotification('API key copied to clipboard!', 'success');
                        }).catch(function() {
                            // Fallback for older browsers
                            const textArea = document.createElement('textarea');
                            textArea.value = keyText.textContent;
                            document.body.appendChild(textArea);
                            textArea.select();
                            document.execCommand('copy');
                            document.body.removeChild(textArea);
                            showNotification('API key copied to clipboard!', 'success');
                        });
                    } else {
                        showNotification('No API key to copy', 'warning');
                    }
                });
            }

            // Copy Router ID buttons
            document.addEventListener('click', function(e) {
                if (e.target.closest('.copy-router-id')) {
                    const button = e.target.closest('.copy-router-id');
                    const routerId = button.dataset.routerId;

                    navigator.clipboard.writeText(routerId).then(function() {
                        showNotification('Router ID copied to clipboard!', 'success');
                    }).catch(function() {
                        // Fallback for older browsers
                        const textArea = document.createElement('textarea');
                        textArea.value = routerId;
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        showNotification('Router ID copied to clipboard!', 'success');
                    });
                }
            });

            // View API Documentation
            if (viewApiDocsBtn) {
                viewApiDocsBtn.addEventListener('click', function() {
                    window.open('api_docs.php', '_blank');
                });
            }

            // Function to generate API key
            function generateApiKey() {
                const button = generateApiKeyBtn || regenerateApiKeyBtn;
                const originalText = button.querySelector('span').textContent;
                const icon = button.querySelector('i');

                // Show loading state
                icon.classList.add('refresh-spin');
                button.querySelector('span').textContent = 'Generating...';
                button.disabled = true;

                fetch('api/generate_api_key.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    icon.classList.remove('refresh-spin');
                    button.querySelector('span').textContent = originalText;
                    button.disabled = false;

                    if (data.success) {
                        // Update the API key display
                        const apiKeyValue = document.getElementById('api-key-value');
                        apiKeyValue.innerHTML = '<span class="key-text">' + data.api_key + '</span>';

                        showNotification('API key generated successfully!', 'success');
                    } else {
                        showNotification('Failed to generate API key: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    icon.classList.remove('refresh-spin');
                    button.querySelector('span').textContent = originalText;
                    button.disabled = false;
                    showNotification('Error: ' + error.message, 'error');
                });
            }

            // Function to show API documentation
            function showApiDocumentation() {
                const modal = createModal('API Documentation');
                const modalContent = modal.querySelector('.modal-content');

                modalContent.innerHTML = `
                    <div class="api-documentation">
                        <div class="doc-section">
                            <h3>Batch Voucher API</h3>
                            <p>Create up to 100 vouchers per request using our batch API endpoint.</p>
                        </div>

                        <div class="doc-section">
                            <h4>Authentication</h4>
                            <p>Include your API key in the Authorization header:</p>
                            <pre><code>Authorization: Bearer YOUR_API_KEY</code></pre>
                        </div>

                        <div class="doc-section">
                            <h4>Endpoint</h4>
                            <div class="endpoint-doc">
                                <span class="method post">POST</span>
                                <code>/api/vouchers</code>
                            </div>
                        </div>

                        <div class="doc-section">
                            <h4>Request Body</h4>
                            <pre><code>{
    "router_id": "Nairobi_CBD_Router",
    "vouchers": [
        {
            "voucher_code": "ABC123",
            "profile": "2Mbps",
            "validity": "1d",
            "created_at": "2025-08-25 14:30:00",
            "comment": "batch-001",
            "metadata": {
                "mikhmon_version": "3.0",
                "generated_by": "admin",
                "password": "ABC123",
                "time_limit": "1d",
                "data_limit": "1073741824",
                "user_mode": "vc"
            }
        }
    ]
}</code></pre>
                        </div>

                        <div class="doc-section">
                            <h4>Response</h4>
                            <pre><code>{
    "success": true,
    "message": "Batch processed",
    "data": {
        "total": 100,
        "stored": 98,
        "failed": 2,
        "results": [
            {"voucher_code": "ABC123", "status": "stored"},
            {"voucher_code": "DEF456", "status": "stored"},
            {"voucher_code": "XYZ789", "status": "duplicate"},
            {"voucher_code": "JKL111", "status": "invalid"}
        ]
    }
}</code></pre>
                        </div>

                        <div class="doc-section">
                            <h4>Error Codes</h4>
                            <ul>
                                <li><strong>400:</strong> Bad Request - Invalid JSON or missing required fields</li>
                                <li><strong>401:</strong> Unauthorized - Invalid or missing API key</li>
                                <li><strong>403:</strong> Forbidden - Router doesn't belong to your account</li>
                                <li><strong>413:</strong> Payload Too Large - More than 100 vouchers in request</li>
                                <li><strong>500:</strong> Internal Server Error - Database or server error</li>
                            </ul>
                        </div>

                        <div class="doc-section">
                            <h4>Rate Limits</h4>
                            <p>Maximum 100 vouchers per request. No rate limiting on number of requests.</p>
                        </div>

                        <div class="modal-actions">
                            <button class="modal-button close">Close</button>
                        </div>
                    </div>
                `;

                // Add event listener to close button
                const closeButton = modalContent.querySelector('.modal-button.close');
                closeButton.addEventListener('click', function() {
                    modal.remove();
                });
            }

            // Router selector change event
            const routerSelector = document.getElementById('router-selector');
            if (routerSelector) {
                // Ensure the correct router is selected on page load
                const currentRouterId = '<?php echo $currentRouter['id']; ?>';
                if (currentRouterId && currentRouterId !== '0') {
                    routerSelector.value = currentRouterId;
                }

                routerSelector.addEventListener('change', function() {
                    const routerId = this.value;
                    if (routerId) {
                        // Load the selected router data via page reload
                        window.location.href = 'routers.php?router_id=' + routerId;
                    }
                });
            }
            
            // Refresh router status
            const refreshButton = document.getElementById('refresh-router');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    const routerId = document.getElementById('router-selector').value;
                    
                    if (!routerId) {
                        alert('Please select a router first');
                        return;
                    }
                    
                    icon.classList.add('refresh-spin');
                    
                    // Make AJAX call to refresh router status
                    const formData = new FormData();
                    formData.append('router_id', routerId);
                    
                    fetch('refresh_router_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        icon.classList.remove('refresh-spin');
                        
                        if (data.success) {
                            // Update the UI with the new status
                            const statusElement = document.querySelector('.router-status');
                            statusElement.className = 'router-status ' + data.router.status;
                            statusElement.innerHTML = `
                                <i class="fas fa-${data.router.status === 'online' ? 'check-circle' : (data.router.status === 'offline' ? 'times-circle' : 'question-circle')}"></i>
                                <span>${data.router.status.charAt(0).toUpperCase() + data.router.status.slice(1)}</span>
                            `;
                            
                            // Show success message
                            showNotification('Router status refreshed successfully!', 'success');
                        } else {
                            // Show error message
                            showNotification('Failed to refresh router status: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        icon.classList.remove('refresh-spin');
                        showNotification('Error: ' + error.message, 'error');
                    });
                });
            }
            
            // Sync vouchers button
            const syncButton = document.querySelector('.router-btn.primary');
            if (syncButton) {
                syncButton.addEventListener('click', function() {
                    const routerId = document.getElementById('router-selector').value;
                    
                    if (!routerId) {
                        showNotification('Please select a router first', 'warning');
                        return;
                    }
                    
                    // Add loading state
                    const icon = this.querySelector('i');
                    const originalText = this.querySelector('span').textContent;
                    icon.classList.add('refresh-spin');
                    this.querySelector('span').textContent = 'Syncing...';
                    this.disabled = true;
                    
                    // Make AJAX call to sync vouchers
                    const formData = new FormData();
                    formData.append('router_id', routerId);
                    
                    // Sync vouchers functionality removed - router integration disabled
                    setTimeout(() => {
                        showNotification('Sync vouchers functionality has been disabled', 'info');
                        // Reset button state
                        icon.classList.remove('refresh-spin');
                        this.querySelector('span').textContent = originalText;
                        this.disabled = false;
                    }, 1000);
                });
            }

            // Download Captive Portal functionality
            const downloadCaptivePortalBtn = document.getElementById('download-captive-portal-btn');
            if (downloadCaptivePortalBtn) {
                downloadCaptivePortalBtn.addEventListener('click', function() {
                    let routerId = document.getElementById('router-selector').value;

                    // If no router selected in dropdown, use the current router ID from PHP
                    if (!routerId) {
                        routerId = '<?php echo $currentRouter['id']; ?>';
                    }

                    if (!routerId || routerId === '0') {
                        showNotification('Please select a router first', 'warning');
                        return;
                    }

                    // Add loading state
                    const icon = this.querySelector('i');
                    const originalText = this.querySelector('span').textContent;
                    icon.classList.add('refresh-spin');
                    this.querySelector('span').textContent = 'Generating...';
                    this.disabled = true;

                    // Make AJAX call to generate captive portal
                    fetch('generate_captive_portal.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            router_id: routerId,
                            reseller_id: <?php echo $resellerId; ?>
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.blob();
                    })
                    .then(blob => {
                        // Create download link
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;

                        // Get router name for filename
                        const selectedRouter = document.getElementById('router-selector');
                        const routerName = selectedRouter.options[selectedRouter.selectedIndex].text.split(' (')[0];
                        const safeRouterName = routerName.replace(/[^a-zA-Z0-9_-]/g, '_');
                        a.download = 'captive_portal_' + safeRouterName + '.html';

                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);

                        showNotification('Captive portal downloaded successfully!', 'success');

                        // Reset button state
                        icon.classList.remove('refresh-spin');
                        this.querySelector('span').textContent = originalText;
                        this.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Error generating captive portal. Please try again.', 'error');

                        // Reset button state
                        icon.classList.remove('refresh-spin');
                        this.querySelector('span').textContent = originalText;
                        this.disabled = false;
                    });
                });
            }

            // Make package rows clickable for more details
            const packageRows = document.querySelectorAll('.package-table tbody tr');
            packageRows.forEach(row => {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on a router tag
                    if (e.target.closest('.router-tag')) {
                        return;
                    }
                    
                    const packageId = this.dataset.packageId;
                    const packageName = this.cells[0].textContent;
                    
                    if (!packageId) {
                        showNotification('Package ID not found', 'error');
                        return;
                    }
                    
                    // Create modal for package details
                    const modal = createModal('Package Details: ' + packageName);
                    
                    // Add loading spinner
                    const modalContent = modal.querySelector('.modal-content');
                    modalContent.innerHTML = `
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading package details...</p>
                        </div>
                    `;
                    
                    // Make AJAX call to get package details
                    const formData = new FormData();
                    formData.append('package_id', packageId);
                    
                    fetch('package_details.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Build the package details HTML
                            const packageData = data.package;
                            const voucherCounts = data.voucher_counts;
                            const recentVouchers = data.recent_vouchers;
                            const routerVouchers = data.router_vouchers || [];
                            
                            modalContent.innerHTML = `
                                <div class="package-details">
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">Name</div>
                                            <div class="detail-value">${packageData.name}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Price</div>
                                            <div class="detail-value">${packageData.price}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Duration</div>
                                            <div class="detail-value">${packageData.duration} (${packageData.type})</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Data Limit</div>
                                            <div class="detail-value">${packageData.data_limit || 'Unlimited'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Speed</div>
                                            <div class="detail-value">${packageData.speed || 'Unlimited'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Device Limit</div>
                                            <div class="detail-value">${packageData.device_limit || 1}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">${packageData.is_active == 1 ? 'Active' : 'Inactive'}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="voucher-stats">
                                        <h3>Voucher Statistics</h3>
                                        <div class="stats-grid">
                                            <div class="stat-item active">
                                                <div class="stat-value">${voucherCounts.active_count}</div>
                                                <div class="stat-label">Active</div>
                                            </div>
                                            <div class="stat-item used">
                                                <div class="stat-value">${voucherCounts.used_count}</div>
                                                <div class="stat-label">Used</div>
                                            </div>
                                            <div class="stat-item expired">
                                                <div class="stat-value">${voucherCounts.expired_count}</div>
                                                <div class="stat-label">Expired</div>
                                            </div>
                                            <div class="stat-item total">
                                                <div class="stat-value">${voucherCounts.total_count}</div>
                                                <div class="stat-label">Total</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="router-voucher-stats">
                                        <h3>Vouchers by Router</h3>
                                        ${routerVouchers.length > 0 ? `
                                            <div class="router-voucher-grid">
                                                ${routerVouchers.map(router => `
                                                    <div class="router-voucher-card" data-router-id="${router.router_id || 0}">
                                                        <div class="router-voucher-header">
                                                            <div class="router-voucher-name">${router.router_name}</div>
                                                            <div class="router-voucher-count">${router.total_count}</div>
                                                        </div>
                                                        <div class="router-voucher-stats">
                                                            <div class="mini-stat active">
                                                                <span class="mini-value">${router.active_count}</span>
                                                                <span class="mini-label">Active</span>
                                                            </div>
                                                            <div class="mini-stat used">
                                                                <span class="mini-value">${router.used_count}</span>
                                                                <span class="mini-label">Used</span>
                                                            </div>
                                                            <div class="mini-stat expired">
                                                                <span class="mini-value">${router.expired_count}</span>
                                                                <span class="mini-label">Expired</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        ` : '<p>No vouchers found for this package</p>'}
                                    </div>
                                    
                                    <div class="recent-vouchers">
                                        <h3>Recent Vouchers</h3>
                                        ${recentVouchers.length > 0 ? `
                                            <table class="voucher-table">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Router</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${recentVouchers.map(voucher => `
                                                        <tr>
                                                            <td>${voucher.code}</td>
                                                            <td><span class="voucher-router">${voucher.router_name}</span></td>
                                                            <td><span class="voucher-status ${voucher.status}">${voucher.status}</span></td>
                                                            <td>${formatDate(voucher.created_at)}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        ` : '<p>No vouchers found for this package</p>'}
                                    </div>
                                    
                                    <div class="modal-actions">
                                        <a href="packages.php?id=${packageData.id}" class="modal-button primary">Edit Package</a>
                                        <a href="upload_voucher.php?package=${packageData.id}" class="modal-button secondary">Upload Vouchers</a>
                                        <button class="modal-button close">Close</button>
                                    </div>
                                </div>
                            `;
                            
                            // Add event listener to close button
                            const closeButton = modalContent.querySelector('.modal-button.close');
                            closeButton.addEventListener('click', function() {
                                modal.remove();
                            });
                        } else {
                            modalContent.innerHTML = `
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>${data.message}</p>
                                </div>
                                <div class="modal-actions">
                                    <button class="modal-button close">Close</button>
                                </div>
                            `;
                            
                            // Add event listener to close button
                            const closeButton = modalContent.querySelector('.modal-button.close');
                            closeButton.addEventListener('click', function() {
                                modal.remove();
                            });
                        }
                    })
                    .catch(error => {
                        modalContent.innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <p>Error: ${error.message}</p>
                            </div>
                            <div class="modal-actions">
                                <button class="modal-button close">Close</button>
                            </div>
                        `;
                        
                        // Add event listener to close button
                        const closeButton = modalContent.querySelector('.modal-button.close');
                        closeButton.addEventListener('click', function() {
                            modal.remove();
                        });
                    });
                });
            });
            
            // Make router tags clickable
            document.addEventListener('click', function(e) {
                const routerTag = e.target.closest('.router-tag');
                if (routerTag) {
                    const routerId = routerTag.dataset.routerId;
                    const routerName = routerTag.querySelector('.router-name').textContent;
                    const packageId = routerTag.closest('tr').dataset.packageId;
                    const packageName = routerTag.closest('tr').cells[0].textContent;
                    
                    if (routerId && packageId) {
                        // Create modal for router-specific vouchers
                        const modal = createModal(`Vouchers for ${packageName} on ${routerName}`);
                        
                        // Add loading spinner
                        const modalContent = modal.querySelector('.modal-content');
                        modalContent.innerHTML = `
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading voucher details...</p>
                            </div>
                        `;
                        
                        // Make AJAX call to get voucher details for this router and package
                        const formData = new FormData();
                        formData.append('package_id', packageId);
                        formData.append('router_id', routerId);
                        
                        fetch('get_router_vouchers.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Build the voucher list HTML
                                modalContent.innerHTML = `
                                    <div class="router-voucher-details">
                                        <div class="router-voucher-summary">
                                            <div class="summary-item">
                                                <div class="summary-label">Router</div>
                                                <div class="summary-value">${data.router.name}</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-label">Package</div>
                                                <div class="summary-value">${data.package.name}</div>
                                            </div>
                                            <div class="summary-item">
                                                <div class="summary-label">Total Vouchers</div>
                                                <div class="summary-value">${data.vouchers.length}</div>
                                            </div>
                                        </div>
                                        
                                        ${data.vouchers.length > 0 ? `
                                            <table class="voucher-table">
                                                <thead>
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Username</th>
                                                        <th>Status</th>
                                                        <th>Created</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${data.vouchers.map(voucher => `
                                                        <tr>
                                                            <td>${voucher.code}</td>
                                                            <td>${voucher.username}</td>
                                                            <td><span class="voucher-status ${voucher.status}">${voucher.status}</span></td>
                                                            <td>${formatDate(voucher.created_at)}</td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        ` : '<p class="no-vouchers">No vouchers found for this router and package</p>'}
                                        
                                        <div class="modal-actions">
                                            <a href="upload_voucher.php?package=${packageId}&router=${routerId}" class="modal-button primary">Upload More Vouchers</a>
                                            <button class="modal-button close">Close</button>
                                        </div>
                                    </div>
                                `;
                                
                                // Add event listener to close button
                                const closeButton = modalContent.querySelector('.modal-button.close');
                                closeButton.addEventListener('click', function() {
                                    modal.remove();
                                });
                            } else {
                                modalContent.innerHTML = `
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <p>${data.message}</p>
                                    </div>
                                    <div class="modal-actions">
                                        <button class="modal-button close">Close</button>
                                    </div>
                                `;
                                
                                // Add event listener to close button
                                const closeButton = modalContent.querySelector('.modal-button.close');
                                closeButton.addEventListener('click', function() {
                                    modal.remove();
                                });
                            }
                        })
                        .catch(error => {
                            modalContent.innerHTML = `
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <p>Error: ${error.message}</p>
                                </div>
                                <div class="modal-actions">
                                    <button class="modal-button close">Close</button>
                                </div>
                            `;
                            
                            // Add event listener to close button
                            const closeButton = modalContent.querySelector('.modal-button.close');
                            closeButton.addEventListener('click', function() {
                                modal.remove();
                            });
                        });
                    }
                }
            });
            
            // Helper function to create a modal
            function createModal(title) {
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.innerHTML = `
                    <div class="modal-overlay"></div>
                    <div class="modal-container">
                        <div class="modal-header">
                            <h2>${title}</h2>
                            <button class="modal-close"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-content"></div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Add event listeners for closing the modal
                const overlay = modal.querySelector('.modal-overlay');
                const closeButton = modal.querySelector('.modal-close');
                
                overlay.addEventListener('click', function() {
                    modal.remove();
                });
                
                closeButton.addEventListener('click', function() {
                    modal.remove();
                });
                
                return modal;
            }
            
            // Helper function to format dates
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            }
            
            // Helper function to show notifications
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="notification-icon">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : (type === 'warning' ? 'exclamation-triangle' : 'info-circle'))}"></i>
                    </div>
                    <div class="notification-message">${message}</div>
                    <button class="notification-close"><i class="fas fa-times"></i></button>
                `;
                
                document.body.appendChild(notification);
                
                // Add event listener for close button
                const closeButton = notification.querySelector('.notification-close');
                closeButton.addEventListener('click', function() {
                    notification.classList.add('notification-hiding');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                });
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    notification.classList.add('notification-hiding');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 5000);
                
                // Animate in
                setTimeout(() => {
                    notification.classList.add('notification-visible');
                }, 10);
            }
        });
    </script>
    
    <style>
        /* API Management Styles */
        .api-management-section {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .api-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .api-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .api-btn.primary {
            background-color: var(--accent-blue);
            color: white;
        }

        .api-btn.primary:hover {
            background-color: #2563eb;
        }

        .api-btn.secondary {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }

        .api-btn.secondary:hover {
            background-color: #4b5563;
        }

        .api-content {
            margin-top: 1.5rem;
        }

        .api-key-section {
            margin-bottom: 2rem;
        }

        .api-key-card {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            padding: 1.5rem;
            border: 1px solid var(--bg-accent);
        }

        .api-key-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .api-key-header h3 {
            margin: 0;
            font-size: 1.1rem;
        }

        .api-key-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-indicator.active {
            background-color: var(--accent-green);
        }

        .api-key-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .api-key-value {
            flex: 1;
            background-color: var(--bg-secondary);
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border: 1px solid var(--bg-accent);
        }

        .key-text {
            color: var(--text-primary);
            word-break: break-all;
        }

        .no-key {
            color: var(--text-secondary);
            font-style: italic;
        }

        .api-key-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background-color: var(--bg-accent);
            border: none;
            color: var(--text-secondary);
            width: 36px;
            height: 36px;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background-color: var(--accent-blue);
            color: white;
        }

        .api-key-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .api-stats-section {
            margin-bottom: 2rem;
        }

        .api-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .api-stat-card {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid var(--bg-accent);
        }

        .api-stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .api-stat-card .stat-content {
            flex: 1;
        }

        .api-stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .api-stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .api-endpoints-section,
        .router-mapping-section {
            margin-bottom: 2rem;
        }

        .api-endpoints-section h3,
        .router-mapping-section h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--bg-accent);
            padding-bottom: 0.5rem;
        }

        .endpoint-list {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            border: 1px solid var(--bg-accent);
        }

        .endpoint-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
        }

        .endpoint-method {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .endpoint-method.post {
            background-color: rgba(34, 197, 94, 0.2);
            color: var(--accent-green);
        }

        .endpoint-path {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .endpoint-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .router-mapping-info p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .router-mapping-list {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            border: 1px solid var(--bg-accent);
        }

        .router-mapping-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--bg-accent);
        }

        .router-mapping-item:last-child {
            border-bottom: none;
        }

        .router-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .router-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .router-ip {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .router-id {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .id-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .router-api-id {
            background-color: var(--bg-secondary);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: var(--text-primary);
        }

        .copy-router-id {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .copy-router-id:hover {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }

        .no-routers {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .no-routers i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-routers a {
            color: var(--accent-blue);
            text-decoration: none;
        }

        .no-routers a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .api-key-display {
                flex-direction: column;
                align-items: stretch;
            }

            .api-stats-grid {
                grid-template-columns: 1fr;
            }

            .router-mapping-item {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .endpoint-item {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-container {
            position: relative;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            background-color: var(--bg-secondary);
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1001;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--bg-accent);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }
        
        .modal-content {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(90vh - 130px);
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .loading-spinner i {
            font-size: 2rem;
            color: var(--accent-blue);
            margin-bottom: 1rem;
        }
        
        .error-message {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--accent-red);
        }
        
        .error-message i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .package-details .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .detail-item {
            padding: 1rem;
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .voucher-stats h3,
        .recent-vouchers h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--bg-accent);
            padding-bottom: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-item {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-item .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .stat-item .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .stat-item.active .stat-value {
            color: var(--accent-green);
        }
        
        .stat-item.used .stat-value {
            color: var(--accent-blue);
        }
        
        .stat-item.expired .stat-value {
            color: var(--accent-red);
        }
        
        .voucher-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        
        .voucher-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--bg-accent);
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .voucher-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--bg-accent);
        }
        
        .voucher-status {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.5rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .voucher-status.active {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
        }
        
        .voucher-status.used {
            background-color: rgba(59, 130, 246, 0.2);
            color: var(--accent-blue);
        }
        
        .voucher-status.expired {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--accent-red);
        }
        
        .modal-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
            justify-content: flex-end;
        }
        
        .modal-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
            border: none;
        }
        
        .modal-button.primary {
            background-color: var(--accent-blue);
            color: white;
        }
        
        .modal-button.primary:hover {
            background-color: #2563eb;
        }
        
        .modal-button.secondary {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }
        
        .modal-button.secondary:hover {
            background-color: #4b5563;
        }
        
        .modal-button.close {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--bg-accent);
        }
        
        .modal-button.close:hover {
            background-color: var(--bg-accent);
        }
        
        /* Notification Styles */
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: var(--bg-secondary);
            border-radius: 0.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            padding: 1rem;
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
            max-width: 450px;
        }
        
        .notification-visible {
            transform: translateY(0);
            opacity: 1;
        }

        /* API Documentation Styles */
        .api-documentation {
            max-width: none;
        }

        .doc-section {
            margin-bottom: 2rem;
        }

        .doc-section h3 {
            color: var(--accent-blue);
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .doc-section h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .doc-section p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .doc-section pre {
            background-color: var(--bg-primary);
            border: 1px solid var(--bg-accent);
            border-radius: 0.5rem;
            padding: 1rem;
            overflow-x: auto;
            margin: 1rem 0;
        }

        .doc-section code {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .endpoint-doc {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1rem 0;
        }

        .endpoint-doc .method {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .endpoint-doc code {
            background-color: var(--bg-primary);
            padding: 0.5rem;
            border-radius: 0.25rem;
            border: 1px solid var(--bg-accent);
        }

        .doc-section ul {
            color: var(--text-secondary);
            padding-left: 1.5rem;
        }

        .doc-section li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .doc-section li strong {
            color: var(--text-primary);
        }
        
        .notification-hiding {
            transform: translateY(100px);
            opacity: 0;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .notification.success .notification-icon {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
        }
        
        .notification.error .notification-icon {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--accent-red);
        }
        
        .notification.warning .notification-icon {
            background-color: rgba(245, 158, 11, 0.2);
            color: var(--accent-orange);
        }
        
        .notification.info .notification-icon {
            background-color: rgba(59, 130, 246, 0.2);
            color: var(--accent-blue);
        }
        
        .notification-message {
            flex: 1;
            font-size: 0.95rem;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-close:hover {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }
    </style>
</body>
</html>
