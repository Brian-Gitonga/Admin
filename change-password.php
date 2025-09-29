<?php
// Include session check
require_once 'session_check.php';

// Include notification system
require_once 'notifications.php';

// Initialize variables
$user_id = $_SESSION['user_id'];

// Handle password change if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password)) {
        set_error_notification("Current password is required");
    } elseif (empty($new_password)) {
        set_error_notification("New password is required");
    } elseif (strlen($new_password) < 8) {
        set_error_notification("New password must be at least 8 characters long");
    } elseif ($new_password !== $confirm_password) {
        set_error_notification("New passwords do not match");
    } else {
        try {
            require_once 'connection_dp.php';
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM resellers WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($current_password, $user['password'])) {
                    // Current password is correct, update to new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $update_stmt = $conn->prepare("UPDATE resellers SET password = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        set_success_notification("Password updated successfully");
                    } else {
                        set_error_notification("Failed to update password: " . $conn->error);
                    }
                    
                    $update_stmt->close();
                } else {
                    set_error_notification("Current password is incorrect");
                }
            } else {
                set_error_notification("User not found");
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            set_error_notification("Error changing password: " . $e->getMessage());
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Qtro ISP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Password change specific styles */
        .password-container {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--bg-accent);
            background-color: var(--bg-accent);
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(var(--accent-blue-rgb), 0.2);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--accent-blue);
            color: white;
        }
        
        .btn-secondary {
            background-color: var(--bg-accent);
            color: var(--text-primary);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .password-requirements {
            margin-top: 1rem;
        }
        
        .password-requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }
        
        .requirement-icon {
            font-size: 0.75rem;
        }
        
        .requirement-unmet {
            color: var(--text-secondary);
        }
        
        .requirement-met {
            color: var(--accent-green);
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
                    Change Password
                    <i class="fas fa-info-circle info-icon" title="Update your account password"></i>
                </h1>
                <p class="page-subtitle">For security reasons, choose a strong and unique password</p>
            </div>
            <div class="header-actions">
                <?php include 'header-common.php'; ?>
            </div>
        </div>
        
        <?php display_notification(); ?>
        
        <div class="password-container">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <div class="password-input-container">
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                        <span class="password-toggle" data-target="current_password">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="new_password" name="new_password" class="form-input" required>
                        <span class="password-toggle" data-target="new_password">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    
                    <div class="password-requirements">
                        <div class="password-requirement" id="req-length">
                            <i class="fas fa-circle requirement-icon requirement-unmet"></i>
                            At least 8 characters
                        </div>
                        <div class="password-requirement" id="req-uppercase">
                            <i class="fas fa-circle requirement-icon requirement-unmet"></i>
                            At least 1 uppercase letter
                        </div>
                        <div class="password-requirement" id="req-lowercase">
                            <i class="fas fa-circle requirement-icon requirement-unmet"></i>
                            At least 1 lowercase letter
                        </div>
                        <div class="password-requirement" id="req-number">
                            <i class="fas fa-circle requirement-icon requirement-unmet"></i>
                            At least 1 number
                        </div>
                        <div class="password-requirement" id="req-special">
                            <i class="fas fa-circle requirement-icon requirement-unmet"></i>
                            At least 1 special character
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="password-input-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        <span class="password-toggle" data-target="confirm_password">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key"></i>
                        Update Password
                    </button>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const toggleButtons = document.querySelectorAll('.password-toggle');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
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
            });
            
            // Password requirements validation
            const newPasswordInput = document.getElementById('new_password');
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Check length requirement
                const reqLength = document.getElementById('req-length');
                updateRequirement(reqLength, password.length >= 8);
                
                // Check uppercase requirement
                const reqUppercase = document.getElementById('req-uppercase');
                updateRequirement(reqUppercase, /[A-Z]/.test(password));
                
                // Check lowercase requirement
                const reqLowercase = document.getElementById('req-lowercase');
                updateRequirement(reqLowercase, /[a-z]/.test(password));
                
                // Check number requirement
                const reqNumber = document.getElementById('req-number');
                updateRequirement(reqNumber, /[0-9]/.test(password));
                
                // Check special character requirement
                const reqSpecial = document.getElementById('req-special');
                updateRequirement(reqSpecial, /[^A-Za-z0-9]/.test(password));
            });
            
            // Confirm password match validation
            const confirmPasswordInput = document.getElementById('confirm_password');
            confirmPasswordInput.addEventListener('input', function() {
                const password = newPasswordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.setCustomValidity("Passwords do not match");
                } else {
                    this.setCustomValidity("");
                }
            });
            
            function updateRequirement(element, isMet) {
                const icon = element.querySelector('.requirement-icon');
                
                if (isMet) {
                    icon.classList.remove('requirement-unmet');
                    icon.classList.add('requirement-met');
                    icon.classList.remove('fa-circle');
                    icon.classList.add('fa-check-circle');
                } else {
                    icon.classList.remove('requirement-met');
                    icon.classList.add('requirement-unmet');
                    icon.classList.remove('fa-check-circle');
                    icon.classList.add('fa-circle');
                }
            }
        });
    </script>
</body>
</html> 