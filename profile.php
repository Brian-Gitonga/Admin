<?php
// Include session check
require_once 'session_check.php';

// Include notification system
require_once 'notifications.php';

// Initialize variables
$user_id = $_SESSION['user_id'];
$user_data = [];
$form_action = htmlspecialchars($_SERVER["PHP_SELF"]);

// Load user profile data
try {
    // Include database connection
    require_once 'connection_dp.php';
    
    // Check if $conn is properly initialized
    if (!isset($conn) || is_null($conn)) {
        throw new Exception("Database connection not established");
    }
    
    $stmt = $conn->prepare("SELECT id, full_name, email, phone, status, payment_interval, created_at, last_login FROM resellers WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
        } else {
            set_error_notification("User profile not found");
        }
        $stmt->close();
    } else {
        set_error_notification("Database error: " . $conn->error);
    }
} catch (Exception $e) {
    set_error_notification("Error loading profile: " . $e->getMessage());
    error_log("Profile error: " . $e->getMessage());
}

// Handle profile update if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $new_phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    
    // Validate inputs
    if (empty($new_full_name)) {
        set_error_notification("Name cannot be empty");
    } elseif (empty($new_phone)) {
        set_error_notification("Phone number cannot be empty");
    } else {
        try {
            // Check if database connection exists
            if (!isset($conn) || is_null($conn)) {
                // Reconnect to database if needed
                require_once 'connection_dp.php';
                
                if (!isset($conn) || is_null($conn)) {
                    throw new Exception("Database connection not established");
                }
            }
            
            // Check if phone is unique (if changed)
            if ($new_phone != $user_data['phone']) {
                $check_stmt = $conn->prepare("SELECT id FROM resellers WHERE phone = ? AND id != ?");
                if (!$check_stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $check_stmt->bind_param("si", $new_phone, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    set_error_notification("This phone number is already in use by another account");
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Update profile
                    $update_stmt = $conn->prepare("UPDATE resellers SET full_name = ?, phone = ? WHERE id = ?");
                    if (!$update_stmt) {
                        throw new Exception("Database error: " . $conn->error);
                    }
                    
                    $update_stmt->bind_param("ssi", $new_full_name, $new_phone, $user_id);
                    
                    if ($update_stmt->execute()) {
                        set_success_notification("Profile updated successfully");
                        
                        // Update session and local data
                        $_SESSION['user_name'] = $new_full_name;
                        $user_data['full_name'] = $new_full_name;
                        $user_data['phone'] = $new_phone;
                    } else {
                        set_error_notification("Failed to update profile: " . $conn->error);
                    }
                    
                    $update_stmt->close();
                }
            } else {
                // Only name changed
                $update_stmt = $conn->prepare("UPDATE resellers SET full_name = ? WHERE id = ?");
                if (!$update_stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $update_stmt->bind_param("si", $new_full_name, $user_id);
                
                if ($update_stmt->execute()) {
                    set_success_notification("Profile updated successfully");
                    
                    // Update session and local data
                    $_SESSION['user_name'] = $new_full_name;
                    $user_data['full_name'] = $new_full_name;
                    
                    // Reload the page to show updated data
                    header("Location: " . $form_action);
                    exit();
                } else {
                    set_error_notification("Failed to update profile: " . $conn->error);
                }
                
                $update_stmt->close();
            }
        } catch (Exception $e) {
            set_error_notification("Error updating profile: " . $e->getMessage());
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

// Format account status for display
function formatStatus($status) {
    switch ($status) {
        case 'active':
            return '<span class="status-badge status-active">Active</span>';
        case 'pending':
            return '<span class="status-badge status-pending">Pending Approval</span>';
        case 'suspended':
            return '<span class="status-badge status-suspended">Suspended</span>';
        case 'expired':
            return '<span class="status-badge status-expired">Expired</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Qtro ISP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Profile specific styles */
        .profile-container {
            background-color: var(--bg-secondary);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1.5rem;
        }
        
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--bg-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--text-primary);
        }
        
        .profile-title h2 {
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        
        .profile-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .profile-fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .profile-field {
            margin-bottom: 1.5rem;
        }
        
        .field-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .field-value {
            font-size: 1.1rem;
            padding: 0.75rem;
            background-color: var(--bg-accent);
            border-radius: 0.5rem;
        }
        
        .profile-actions {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(16, 185, 129, 0.2);
            color: var(--accent-green);
        }
        
        .status-pending {
            background-color: rgba(249, 115, 22, 0.2);
            color: var(--accent-orange);
        }
        
        .status-suspended {
            background-color: rgba(239, 68, 68, 0.2);
            color: var(--accent-red);
        }
        
        .status-expired {
            background-color: rgba(107, 114, 128, 0.2);
            color: var(--text-secondary);
        }
        
        /* Form styles */
        .edit-profile-form {
            margin-top: 2rem;
            display: none; /* Hidden by default */
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
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(var(--accent-blue-rgb), 0.2);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
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
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="page-title-container">
                <h1 class="page-title">
                    My Profile
                    <i class="fas fa-info-circle info-icon" title="View and manage your profile information"></i>
                </h1>
                <p class="page-subtitle">View and manage your personal information and account settings</p>
            </div>
            <div class="header-actions">
                <?php include 'header-common.php'; ?>
            </div>
        </div>
        
        <?php display_notification(); ?>
        
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-title">
                    <h2><?php echo htmlspecialchars($user_data['full_name'] ?? 'User Name'); ?></h2>
                    <div class="profile-subtitle">
                        <span><?php echo htmlspecialchars($user_data['email'] ?? 'email@example.com'); ?></span>
                        &nbsp;•&nbsp;
                        <?php echo isset($user_data['status']) ? formatStatus($user_data['status']) : ''; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-fields">
                <div class="profile-field">
                    <div class="field-label">Full Name</div>
                    <div class="field-value"><?php echo htmlspecialchars($user_data['full_name'] ?? 'Not available'); ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Email Address</div>
                    <div class="field-value"><?php echo htmlspecialchars($user_data['email'] ?? 'Not available'); ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Phone Number</div>
                    <div class="field-value"><?php echo htmlspecialchars($user_data['phone'] ?? 'Not available'); ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Account Status</div>
                    <div class="field-value"><?php echo isset($user_data['status']) ? formatStatus($user_data['status']) : 'Not available'; ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Payment Interval</div>
                    <div class="field-value"><?php echo ucfirst(htmlspecialchars($user_data['payment_interval'] ?? 'Not available')); ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Account Created</div>
                    <div class="field-value"><?php echo isset($user_data['created_at']) ? date('F j, Y', strtotime($user_data['created_at'])) : 'Not available'; ?></div>
                </div>
                
                <div class="profile-field">
                    <div class="field-label">Last Login</div>
                    <div class="field-value"><?php echo isset($user_data['last_login']) ? date('F j, Y g:i A', strtotime($user_data['last_login'])) : 'Not available'; ?></div>
                </div>
            </div>
            
            <div class="profile-actions">
                <button id="edit-profile-btn" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Profile
                </button>
                <a href="change-password.php" class="btn btn-secondary">
                    <i class="fas fa-key"></i>
                    Change Password
                </a>
            </div>
            
            <!-- Edit Profile Form -->
            <div id="edit-profile-form" class="edit-profile-form">
                <h3>Edit Profile Information</h3>
                <form id="profileForm" method="POST" action="<?php echo $form_action; ?>">
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                        <button type="button" id="cancel-edit-btn" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide edit form
            const editProfileBtn = document.getElementById('edit-profile-btn');
            const cancelEditBtn = document.getElementById('cancel-edit-btn');
            const editProfileForm = document.getElementById('edit-profile-form');
            
            if (editProfileBtn && cancelEditBtn && editProfileForm) {
                editProfileBtn.addEventListener('click', function() {
                    editProfileForm.style.display = 'block';
                    editProfileBtn.style.display = 'none';
                });
                
                cancelEditBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent any default button behavior
                    editProfileForm.style.display = 'none';
                    editProfileBtn.style.display = 'inline-flex';
                });
            }
            
            // Form submission
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    // Validation can be added here if needed
                    const fullName = document.getElementById('full_name').value.trim();
                    const phone = document.getElementById('phone').value.trim();
                    
                    if (!fullName) {
                        e.preventDefault();
                        alert('Please enter your full name');
                        return false;
                    }
                    
                    if (!phone) {
                        e.preventDefault();
                        alert('Please enter your phone number');
                        return false;
                    }
                    
                    // If we reach here, form is valid and can be submitted
                    return true;
                });
            }
        });
    </script>
</body>
</html> 