<?php
// Start session to access user info
session_start();

// Database connection
$db_host = 'localhost';
$db_user = 'root'; // Change if different
$db_pass = ''; // Change if different
$db_name = 'billing_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in, redirect if not
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize message variables
$success_message = '';
$error_message = '';

// Process remote access request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_remote_access'])) {
    $router_id = intval($_POST['router_id']);
    $reseller_id = $_SESSION['user_id'];

    // Check if request already exists
    $check_stmt = $conn->prepare("SELECT id, request_status FROM remote_access_requests WHERE reseller_id = ? AND router_id = ?");
    $check_stmt->bind_param("ii", $reseller_id, $router_id);
    $check_stmt->execute();
    $existing_request = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($existing_request) {
        if ($existing_request['request_status'] == 'ordered') {
            $error_message = "Remote access request already submitted and pending approval.";
        } elseif ($existing_request['request_status'] == 'approved') {
            $success_message = "Remote access already approved for this router.";
        } else {
            $error_message = "Previous remote access request was rejected. Please contact support.";
        }
    } else {
        // Create new remote access request
        $stmt = $conn->prepare("INSERT INTO remote_access_requests (reseller_id, router_id, request_status) VALUES (?, ?, 'ordered')");
        $stmt->bind_param("ii", $reseller_id, $router_id);

        if ($stmt->execute()) {
            $success_message = "Remote access request submitted successfully! You will be notified once approved.";
        } else {
            $error_message = "Error submitting request: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_router'])) {
    // Get form data
    $router_name = $_POST['router_name'];
    
    // Combine IP address segments
    $ip_segment_1 = $_POST['ip_segment_1'];
    $ip_segment_2 = $_POST['ip_segment_2'];
    $ip_segment_3 = $_POST['ip_segment_3'];
    $ip_segment_4 = $_POST['ip_segment_4'];
    $router_ip = $ip_segment_1 . '.' . $ip_segment_2 . '.' . $ip_segment_3 . '.' . $ip_segment_4;
    
    $router_username = $_POST['router_username'];
    $router_password = $_POST['router_password'];
    $api_port = $_POST['api_port'];
    
    // Get reseller_id from session
    $reseller_id = $_SESSION['user_id'];
    
    // Initial status
    $is_active = 1;
    $status = 'offline';
    
    // Prepare and execute SQL query
    $stmt = $conn->prepare("INSERT INTO hotspots (reseller_id, name, router_ip, router_username, router_password, api_port, is_active, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("issssiis", $reseller_id, $router_name, $router_ip, $router_username, $router_password, $api_port, $is_active, $status);
    
    if ($stmt->execute()) {
        $success_message = "Router configuration saved successfully!";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

// Process router status toggle
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $router_id = intval($_GET['id']);
    $reseller_id = $_SESSION['user_id'];

    // First, get the current status
    $check_stmt = $conn->prepare("SELECT status FROM hotspots WHERE id = ? AND reseller_id = ?");
    $check_stmt->bind_param("ii", $router_id, $reseller_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $router = $result->fetch_assoc();
        $current_status = $router['status'];

        // Toggle the status
        $new_status = ($current_status == 'online') ? 'offline' : 'online';

        // Update the status
        $update_stmt = $conn->prepare("UPDATE hotspots SET status = ? WHERE id = ? AND reseller_id = ?");
        $update_stmt->bind_param("sii", $new_status, $router_id, $reseller_id);

        if ($update_stmt->execute()) {
            $success_message = "Router status updated to " . ucfirst($new_status) . " successfully!";
        } else {
            $error_message = "Error updating router status: " . $update_stmt->error;
        }

        $update_stmt->close();
    } else {
        $error_message = "Router not found or you don't have permission to modify it.";
    }

    $check_stmt->close();

    // Redirect to avoid resubmission
    header("Location: linkrouter.php");
    exit();
}

// Process router deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $router_id = intval($_GET['id']);
    $reseller_id = $_SESSION['user_id'];

    // Prepare and execute delete query with security check for reseller_id
    $stmt = $conn->prepare("DELETE FROM hotspots WHERE id = ? AND reseller_id = ?");
    $stmt->bind_param("ii", $router_id, $reseller_id);

    if ($stmt->execute()) {
        $success_message = "Router deleted successfully!";
    } else {
        $error_message = "Error deleting router: " . $stmt->error;
    }

    $stmt->close();

    // Redirect to avoid resubmission
    header("Location: linkrouter.php");
    exit();
}

// Fetch existing routers
$routers = [];

// Get reseller ID from session
$reseller_id = $_SESSION['user_id'];

// Initialize base query - filter by reseller_id
$query_params = [];
$where_clauses = ["reseller_id = ?"];
$query_params[] = $reseller_id;
$param_types = "i"; // Integer for reseller_id

// Add status filter if provided
if (isset($_GET['status']) && in_array($_GET['status'], ['online', 'offline'])) {
    $status = $_GET['status'];
    $where_clauses[] = "status = ?";
    $query_params[] = $status;
    $param_types .= "s"; // String for status
}

// Build the final query
$query = "SELECT * FROM hotspots";
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

// Execute the query with prepared statement
$stmt = $conn->prepare($query);

if ($stmt) {
    // Bind parameters dynamically
    if (!empty($query_params)) {
        $stmt->bind_param($param_types, ...$query_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $routers[] = $row;
        }
    }
    
    $stmt->close();
} else {
    $error_message = "Error preparing query: " . $conn->error;
}

// Fetch remote access requests for this reseller
$remote_access_requests = [];
$access_query = "SELECT r.*, h.name as router_name, h.router_ip
                 FROM remote_access_requests r
                 JOIN hotspots h ON r.router_id = h.id
                 WHERE r.reseller_id = ?
                 ORDER BY r.created_at DESC";
$access_stmt = $conn->prepare($access_query);
if ($access_stmt) {
    $access_stmt->bind_param("i", $reseller_id);
    $access_stmt->execute();
    $access_result = $access_stmt->get_result();
    while ($row = $access_result->fetch_assoc()) {
        $remote_access_requests[] = $row;
    }
    $access_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>link router</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="other-css/linkrouter.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="main-content" id="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-network-wired"></i>
                Router Configuration
            </h1>
        </div>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="router-grid">
            <div class="router-visual">
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="error-content">
                        <div class="error-title">Remote Configuration Now Available</div>
                        <div class="error-description">
                            Full remote configuration access is currently available. Please contact 
                            <span class="contact-number">0750059353</span> for assistance with router setup.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="router-form">
                <h2 class="form-title">
                    <i class="fas fa-network-wired"></i>
                    Router Details
                </h2>
                
                <form id="router-form" method="POST" action="">
                    <div class="form-group">
                        <label for="router-name" class="form-label">
                            <i class="fas fa-tag"></i>
                            Router Name
                        </label>
                        <input type="text" id="router-name" name="router_name" class="form-input" placeholder="e.g., Elsa Estate Net" required>
                        <div class="form-hint">Give your router a descriptive name for easy identification</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="router-ip" class="form-label">
                            <i class="fas fa-globe"></i>
                            Router IP Address
                        </label>
                        <div class="ip-segments">
                            <input type="text" id="ip-segment-1" name="ip_segment_1" class="form-input ip-segment" placeholder="192" maxlength="3" required>
                            <span class="ip-dot">.</span>
                            <input type="text" id="ip-segment-2" name="ip_segment_2" class="form-input ip-segment" placeholder="168" maxlength="3" required>
                            <span class="ip-dot">.</span>
                            <input type="text" id="ip-segment-3" name="ip_segment_3" class="form-input ip-segment" placeholder="1" maxlength="3" required>
                            <span class="ip-dot">.</span>
                            <input type="text" id="ip-segment-4" name="ip_segment_4" class="form-input ip-segment" placeholder="1" maxlength="3" required>
                        </div>
                        <div class="form-hint">Enter the IP address of your router (usually 192.168.1.1 or 192.168.0.1)</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="router-username" class="form-label">
                            <i class="fas fa-user"></i>
                            Username
                        </label>
                        <input type="text" id="router-username" name="router_username" class="form-input" placeholder="admin" required>
                        <div class="form-hint">Default username is often 'admin' please make sure you change your username after you login</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="router-password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="input-icon" style="position: relative;">
                            <input type="password" id="router-password" name="router_password" class="form-input" placeholder="Enter router password" required>
                            <span class="password-toggle" id="password-toggle">
                                <i class="far fa-eye"></i>
                            </span>
                        </div>
                        <div class="form-hint">Default password is often 'admin' or 'password' please make sure you change your password after you login</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="api-port" class="form-label">
                            <i class="fas fa-plug"></i>
                            Management Port
                            <div class="tooltip">
                                <i class="fas fa-info-circle"></i>
                                <span class="tooltip-text">The port used for router management. Common ports are 80 (HTTP) or 443 (HTTPS).</span>
                            </div>
                        </label>
                        <input type="number" id="api-port" name="api_port" class="form-input port-input" placeholder="80" min="1" max="65535" required>
                        <div class="form-hint">Standard ports: 80 (HTTP) or 443 (HTTPS)</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="contact-support" class="btn btn-secondary">
                            <i class="fas fa-phone-alt"></i>
                            Contact Support
                        </button>
                        <button type="submit" name="save_router" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Router
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php
        // Count online and offline routers
        $online_count = 0;
        $offline_count = 0;
        foreach ($routers as $router) {
            if ($router['status'] == 'online') {
                $online_count++;
            } else {
                $offline_count++;
            }
        }
        ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <h3 class="filter-title">Filter Routers</h3>
            </div>
            <div class="filter-buttons">
                <a href="linkrouter.php" class="filter-btn <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">
                    All
                </a>
                <a href="linkrouter.php?status=online" class="filter-btn filter-online <?php echo (isset($_GET['status']) && $_GET['status'] == 'online') ? 'active' : ''; ?>">
                    <span class="filter-dot online"></span> Online
                </a>
                <a href="linkrouter.php?status=offline" class="filter-btn filter-offline <?php echo (isset($_GET['status']) && $_GET['status'] == 'offline') ? 'active' : ''; ?>">
                    <span class="filter-dot offline"></span> Offline
                </a>
            </div>
        </div>

        <!-- Router Summary Cards -->
        <div class="router-summary-grid">
            <div class="summary-card">
                <div class="summary-label">TOTAL ROUTERS</div>
                <div class="summary-number"><?php echo count($routers); ?></div>
            </div>
            <div class="summary-card summary-online">
                <div class="summary-label">ONLINE</div>
                <div class="summary-number"><?php echo $online_count; ?></div>
            </div>
            <div class="summary-card summary-offline">
                <div class="summary-label">OFFLINE</div>
                <div class="summary-number"><?php echo $offline_count; ?></div>
            </div>
        </div>

        <div class="router-cards">
            
            <?php if (empty($routers)): ?>
            <p class="no-routers-message">No routers have been configured yet. Add your first router above.</p>
            <?php else: ?>
                <?php foreach ($routers as $router): ?>
                <div class="router-card">
                    <div class="router-card-header">
                        <div class="router-name">
                            <i class="fas fa-wifi router-wifi-icon"></i>
                            <?php echo htmlspecialchars($router['name']); ?>
                        </div>
                    </div>

                    <div class="router-card-body">
                        <div class="router-info-row">
                            <div class="info-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">IP:</div>
                                <div class="info-value"><?php echo htmlspecialchars($router['router_ip']); ?></div>
                            </div>
                        </div>

                        <div class="router-info-row">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Username:</div>
                                <div class="info-value"><?php echo htmlspecialchars($router['router_username']); ?></div>
                            </div>
                        </div>

                        <div class="router-info-row">
                            <div class="info-icon">
                                <i class="fas fa-plug"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Management Port:</div>
                                <div class="info-value"><?php echo htmlspecialchars($router['api_port']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="router-card-footer">
                        <div class="router-status-badge <?php echo ($router['status'] == 'online') ? 'status-online' : 'status-offline'; ?>">
                            <span class="status-indicator"></span>
                            <?php echo ucfirst($router['status']); ?>
                        </div>
                        <div class="router-actions">
                            <a href="edit_router.php?id=<?php echo $router['id']; ?>" class="action-btn action-edit" title="Edit Router">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?action=delete&id=<?php echo $router['id']; ?>" class="action-btn action-delete"
                               onclick="return confirm('Are you sure you want to delete this router?');" title="Delete Router">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!isset($routers) || count($routers) < 3): ?>
            
            <?php endif; ?>
        </div>

        <!-- Remote Access Ordering Section -->
        <div class="remote-access-section">
            <h2 class="section-title">
                <i class="fas fa-cloud"></i>
                Remote Access Management
            </h2>

            <?php if (!empty($routers)): ?>
                <?php if (!empty($remote_access_requests)): ?>
                    <div class="requests-list">
                        <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Your Remote Access Requests</h3>
                        <?php foreach ($remote_access_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="request-router">
                                        <i class="fas fa-router"></i>
                                        <?php echo htmlspecialchars($request['router_name']); ?> (<?php echo htmlspecialchars($request['router_ip']); ?>)
                                    </div>
                                    <span class="status-badge status-<?php echo $request['request_status']; ?>">
                                        <?php echo ucfirst($request['request_status']); ?>
                                    </span>
                                </div>

                                <div class="request-details">
                                    <p><strong>Requested:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></p>

                                    <?php if ($request['request_status'] == 'approved'): ?>
                                        <div class="credentials">
                                            <h4 style="margin-bottom: 0.75rem; color: var(--text-primary);">Remote Access Credentials</h4>
                                            <?php if ($request['dns_name']): ?>
                                                <div class="credential-item">
                                                    <span class="credential-label">DNS Name:</span>
                                                    <span class="credential-value"><?php echo htmlspecialchars($request['dns_name']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="credential-item">
                                                <span class="credential-label">Username:</span>
                                                <span class="credential-value"><?php echo htmlspecialchars($request['remote_username']); ?></span>
                                            </div>
                                            <div class="credential-item">
                                                <span class="credential-label">Password:</span>
                                                <span class="credential-value"><?php echo htmlspecialchars($request['remote_password']); ?></span>
                                            </div>
                                            <div class="credential-item">
                                                <span class="credential-label">Port:</span>
                                                <span class="credential-value"><?php echo $request['remote_port']; ?></span>
                                            </div>
                                            <?php if ($request['admin_comments']): ?>
                                                <div style="margin-top: 1rem; padding: 0.75rem; background: #f0f9ff; border-radius: 0.5rem; border-left: 4px solid #0ea5e9;">
                                                    <strong>Admin Notes:</strong> <?php echo htmlspecialchars($request['admin_comments']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($request['request_status'] == 'rejected' && $request['admin_comments']): ?>
                                        <div style="margin-top: 1rem; padding: 0.75rem; background: #fef2f2; border-radius: 0.5rem; border-left: 4px solid #ef4444;">
                                            <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($request['admin_comments']); ?>
                                        </div>
                                    <?php elseif ($request['request_status'] == 'ordered'): ?>
                                        <div style="margin-top: 1rem; padding: 0.75rem; background: #fffbeb; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                                            <strong>Status:</strong> Your request is pending admin approval. You will be notified once processed.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Request Remote Access Form -->
                <div style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem; color: var(--text-primary);">Request Remote Access</h3>
                    <form method="POST" action="" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                            <label for="router_id" class="form-label">
                                <i class="fas fa-router"></i>
                                Select Router
                            </label>
                            <select name="router_id" id="router_id" class="form-input" required>
                                <option value="">Choose a router...</option>
                                <?php foreach ($routers as $router): ?>
                                    <option value="<?php echo $router['id']; ?>">
                                        <?php echo htmlspecialchars($router['name']); ?> (<?php echo htmlspecialchars($router['router_ip']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="request_remote_access" class="btn btn-secondary">
                            <i class="fas fa-paper-plane"></i>
                            Request Access
                        </button>
                    </form>
                    <p class="form-hint" style="margin-top: 0.5rem;">
                        Remote access allows our support team to help configure your router remotely.
                        Your request will be reviewed and you'll receive credentials once approved.
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; color: var(--accent-blue);"></i>
                    <p>Add a router configuration first to request remote access.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-footer">
            Need help configuring your router? <a href="#">View our documentation</a> or <a href="https://wa.me/0750059353">contact support</a>.
        </div>
        
        
    </div>

    <script src="script.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('password-toggle').addEventListener('click', function() {
            var passwordInput = document.getElementById('router-password');
            var eyeIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        });
        
        // Router connection testing functionality has been removed
        
        // IP address validation
        const ipInputs = document.querySelectorAll('.ip-segment');
        ipInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Move to next input when current input is filled
                if (this.value.length === 3) {
                    const nextInput = this.nextElementSibling?.nextElementSibling;
                    if (nextInput && nextInput.tagName === 'INPUT') {
                        nextInput.focus();
                    }
                }
                
                // Validate IP segment (0-255)
                if (parseInt(this.value) > 255) {
                    this.value = '255';
                }
            });
        });
        
        // Form validation
        document.getElementById('router-form').addEventListener('submit', function(e) {
            let valid = true;
            
            // Validate router name
            const routerName = document.getElementById('router-name').value.trim();
            if (routerName.length < 3) {
                alert('Router name must be at least 3 characters long');
                e.preventDefault();
                return false;
            }
            
            // Validate IP address
            for (let i = 0; i < ipInputs.length; i++) {
                if (ipInputs[i].value.trim() === '') {
                    alert('Please complete the IP address');
                    e.preventDefault();
                    return false;
                }
            }
            
            return valid;
        });
        
        // Support button
        document.getElementById('contact-support').addEventListener('click', function() {
            const phone = document.querySelector('.contact-number').textContent;
            alert('Please contact our support team at ' + phone + ' for assistance with router configuration.');
        });

        // Make sure router cards are visible
        document.addEventListener('DOMContentLoaded', function() {
            const routerCards = document.querySelectorAll('.router-card');
            routerCards.forEach(card => {
                card.style.display = 'block';
            });
        });
    </script>
</body>
</html>