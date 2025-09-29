<?php
// Start session
session_start();

// Include notifications system
require_once 'notifications.php';

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$email = "";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Include database connection
    require_once 'connection_dp.php';
    
    // Get form data and sanitize
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // No sanitizing as we'll verify with password_verify
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_error_notification("Please enter a valid email address");
    } else {
        try {
            // Check if the resellers table exists
            $check_table = $conn->query("SHOW TABLES LIKE 'resellers'");
            if ($check_table->num_rows == 0) {
                throw new Exception("Database structure error: resellers table not found");
            }
            
            // Prepare query to check if user exists with proper error handling
            $stmt = $conn->prepare("SELECT id, full_name, email, password, status FROM resellers WHERE email = ?");
            
            if ($stmt === false) {
                throw new Exception("Database query error: " . $conn->error);
            }
            
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                // User found, verify password
                $user = $result->fetch_assoc();
                
                // Check account status first
                if ($user['status'] === 'pending') {
                    set_warning_notification("Your account is pending approval. Please contact admin for activation.");
                } elseif ($user['status'] === 'suspended') {
                    set_error_notification("Your account has been suspended. Please contact support.");
                } elseif ($user['status'] === 'expired') {
                    set_warning_notification("Your account has expired. Please renew your subscription.");
                } elseif ($user['status'] === 'active') {
                    // Only proceed if status is active
                    // Verify password (assuming password is hashed with password_hash)
                    if (password_verify($password, $user['password'])) {
                        // Password is correct, create session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                        
                        // Try to update last login time - check if column exists first
                        try {
                            $check_column = $conn->query("SHOW COLUMNS FROM resellers LIKE 'last_login'");
                            if ($check_column->num_rows > 0) {
                                $update_stmt = $conn->prepare("UPDATE resellers SET last_login = NOW() WHERE id = ?");
                                if ($update_stmt) {
                                    $update_stmt->bind_param("i", $user['id']);
                                    $update_stmt->execute();
                                }
                            } else {
                                // Log warning but continue - this is not a critical error
                                error_log("Warning: last_login column not found in resellers table");
                            }
                        } catch (Exception $e) {
                            // Just log this error but don't prevent login
                            error_log("Warning: Failed to update last_login: " . $e->getMessage());
                        }
                        
                        // Set a welcome notification
                        set_success_notification("Welcome back, " . $user['full_name'] . "!");
                        
                        // Redirect to index
                        header("Location: index.php");
                        exit();
                    } else {
                        set_error_notification("Invalid email or password");
                    }
                } else {
                    // Unknown status
                    set_error_notification("Account has an unknown status. Please contact support.");
                }
            } else {
                // User not found
                set_error_notification("Invalid email or password");
            }
            
            if ($stmt) {
                $stmt->close();
            }
            
        } catch (Exception $e) {
            set_error_notification("Login error: " . $e->getMessage());
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check for session expiry message
if (isset($_GET['session_expired']) && $_GET['session_expired'] == 1) {
    set_warning_notification("Your session has expired. Please log in again.");
}

// Check for security error
if (isset($_GET['security_error']) && $_GET['security_error'] == 1) {
    set_error_notification("A security issue was detected. Please log in again.");
}

// Check for logout message
if (isset($_GET['logged_out']) && $_GET['logged_out'] == 1) {
    set_success_notification("You have been successfully logged out.");
}

// Check for account status changes during active session
if (isset($_GET['account_status'])) {
    $status = $_GET['account_status'];
    
    switch ($status) {
        case 'pending':
            set_warning_notification("Your account is now pending approval. Please contact admin for activation.");
            break;
        case 'suspended':
            set_error_notification("Your account has been suspended. Please contact support.");
            break;
        case 'expired':
            set_warning_notification("Your account has expired. Please renew your subscription.");
            break;
        default:
            set_error_notification("Your account is no longer active. Please contact support.");
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Qtro ISP - Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="other-css/login.css">
<link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        
        <div class="form-card">
            <div class="form-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account to continue</p>
            </div>
            
            <?php display_notification(); ?>
            
            <form id="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-icon">
                        <i class="far fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon" style="position: relative;">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                        <span class="password-toggle" id="password-toggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember-me" name="remember_me">
                        <label for="remember-me">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            <!-- future implementation
            <div class="form-divider">Or continue with</div>
            
            <div class="social-login">
                <button type="button" class="social-btn google-btn">
                    <i class="fab fa-google"></i>
                    Google
                </button>
                <button type="button" class="social-btn microsoft-btn">
                    <i class="fab fa-microsoft"></i>
                    Microsoft
                </button>
            </div>
            -->
            <div class="form-footer">
                Don't have an account? <a href="registration.php">Sign up</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle visibility
            document.getElementById('password-toggle').addEventListener('click', function() {
                const passwordInput = document.getElementById('password');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
            
            // Client-side form validation
            document.getElementById('login-form').addEventListener('submit', function(e) {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                let isValid = true;
                
                if (!email) {
                    alert('Please enter your email address');
                    isValid = false;
                }
                
                if (!password) {
                    alert('Please enter your password');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>