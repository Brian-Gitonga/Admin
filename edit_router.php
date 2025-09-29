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

// Initialize variables
$router = null;
$success_message = '';
$error_message = '';

// Check if router ID is provided
if (!isset($_GET['id'])) {
    header("Location: linkrouter.php");
    exit();
}

$router_id = intval($_GET['id']);

// Fetch router details
$stmt = $conn->prepare("SELECT * FROM hotspots WHERE id = ?");
$stmt->bind_param("i", $router_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: linkrouter.php");
    exit();
}

$router = $result->fetch_assoc();
$stmt->close();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_router'])) {
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
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Prepare and execute SQL query
    $stmt = $conn->prepare("UPDATE hotspots SET name = ?, router_ip = ?, router_username = ?, 
                           router_password = ?, api_port = ?, is_active = ? WHERE id = ?");
    
    $stmt->bind_param("ssssiis", $router_name, $router_ip, $router_username, $router_password, $api_port, $is_active, $router_id);
    
    if ($stmt->execute()) {
        $success_message = "Router updated successfully!";
        
        // Refresh router data
        $stmt = $conn->prepare("SELECT * FROM hotspots WHERE id = ?");
        $stmt->bind_param("i", $router_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $router = $result->fetch_assoc();
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

// Extract IP segments
$ip_segments = explode('.', $router['router_ip']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Router - <?php echo htmlspecialchars($router['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="other-css/linkrouter.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <div class="container main-content">
        <div class="page-header">
            <h1 class="page-title">Edit Router</h1>
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
        
        <div class="router-form">
            <h2 class="form-title">
                <i class="fas fa-network-wired"></i>
                Edit Router: <?php echo htmlspecialchars($router['name']); ?>
            </h2>
            
            <form id="router-form" method="POST" action="">
                <div class="form-group">
                    <label for="router-name" class="form-label">
                        <i class="fas fa-tag"></i>
                        Router Name
                    </label>
                    <input type="text" id="router-name" name="router_name" class="form-input" 
                           value="<?php echo htmlspecialchars($router['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="router-ip" class="form-label">
                        <i class="fas fa-globe"></i>
                        Router IP Address
                    </label>
                    <div class="ip-segments">
                        <input type="text" id="ip-segment-1" name="ip_segment_1" class="form-input ip-segment" 
                               value="<?php echo isset($ip_segments[0]) ? htmlspecialchars($ip_segments[0]) : ''; ?>" maxlength="3" required>
                        <span class="ip-dot">.</span>
                        <input type="text" id="ip-segment-2" name="ip_segment_2" class="form-input ip-segment" 
                               value="<?php echo isset($ip_segments[1]) ? htmlspecialchars($ip_segments[1]) : ''; ?>" maxlength="3" required>
                        <span class="ip-dot">.</span>
                        <input type="text" id="ip-segment-3" name="ip_segment_3" class="form-input ip-segment" 
                               value="<?php echo isset($ip_segments[2]) ? htmlspecialchars($ip_segments[2]) : ''; ?>" maxlength="3" required>
                        <span class="ip-dot">.</span>
                        <input type="text" id="ip-segment-4" name="ip_segment_4" class="form-input ip-segment" 
                               value="<?php echo isset($ip_segments[3]) ? htmlspecialchars($ip_segments[3]) : ''; ?>" maxlength="3" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="router-username" class="form-label">
                        <i class="fas fa-user"></i>
                        Username
                    </label>
                    <input type="text" id="router-username" name="router_username" class="form-input" 
                           value="<?php echo htmlspecialchars($router['router_username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="router-password" class="form-label">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <div class="input-icon" style="position: relative;">
                        <input type="password" id="router-password" name="router_password" class="form-input" 
                               value="<?php echo htmlspecialchars($router['router_password']); ?>" required>
                        <span class="password-toggle" id="password-toggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="api-port" class="form-label">
                        <i class="fas fa-plug"></i>
                        API Port
                    </label>
                    <input type="number" id="api-port" name="api_port" class="form-input port-input" 
                           value="<?php echo htmlspecialchars($router['api_port']); ?>" min="1" max="65535" required>
                </div>
                
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="is_active" <?php echo $router['is_active'] ? 'checked' : ''; ?>>
                        <span>Active</span>
                    </label>
                </div>
                
                <div class="form-actions">
                    <a href="linkrouter.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>
                    <button type="submit" name="update_router" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Router
                    </button>
                </div>
            </form>
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
    </script>
</body>
</html> 