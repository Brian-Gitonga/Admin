<?php
// Start session
session_start();

// Initialize variables
$email = $company_name = $phone = $country = "";
$password = $confirm_password = "";
$error_message = "";
$current_step = 1;

// Include database connection
require_once 'connection_dp.php';

// Function to validate email existence
function emailExists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM resellers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to validate company name uniqueness
function companyNameExists($conn, $company_name) {
    $stmt = $conn->prepare("SELECT id FROM resellers WHERE full_name = ?");
    $stmt->bind_param("s", $company_name);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to validate phone uniqueness
function phoneExists($conn, $phone) {
    $stmt = $conn->prepare("SELECT id FROM resellers WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Email validation in step 1
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['step']) && $_POST['step'] == "email_check") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
        exit;
    }
    
    if (emailExists($conn, $email)) {
        echo json_encode(['success' => false, 'message' => 'This email already exists. Please use a different email or login.']);
        exit;
    }
    
    // Email is valid and doesn't exist in the system
    $_SESSION['registration_email'] = $email;
    echo json_encode(['success' => true]);
    exit;
}

// Business details validation in step 2
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['step']) && $_POST['step'] == "business_check") {
    $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING);
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $country = filter_input(INPUT_POST, 'country', FILTER_SANITIZE_STRING);
    
    // Validate company name
    if (empty($company_name)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your company name']);
        exit;
    }
    
    if (companyNameExists($conn, $company_name)) {
        echo json_encode(['success' => false, 'message' => 'This company name already exists. Please use a different name.']);
        exit;
    }
    
    // Validate full name
    if (empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your full name']);
        exit;
    }
    
    // Validate phone
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your phone number']);
        exit;
    }
    
    if (phoneExists($conn, $phone)) {
        echo json_encode(['success' => false, 'message' => 'This phone number already exists. Please use a different number.']);
        exit;
    }
    
    // Validate country
    if (empty($country)) {
        echo json_encode(['success' => false, 'message' => 'Please select your country']);
        exit;
    }
    
    // Save details to session
    $_SESSION['registration_company'] = $company_name;
    $_SESSION['registration_full_name'] = $full_name;
    $_SESSION['registration_phone'] = $phone;
    $_SESSION['registration_country'] = $country;
    
    echo json_encode(['success' => true]);
    exit;
}

// Final registration submission in step 3
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['step']) && $_POST['step'] == "register") {
    // Retrieve data from session
    $email = isset($_SESSION['registration_email']) ? $_SESSION['registration_email'] : '';
    $company_name = isset($_SESSION['registration_company']) ? $_SESSION['registration_company'] : '';
    $full_name = isset($_SESSION['registration_full_name']) ? $_SESSION['registration_full_name'] : '';
    $phone = isset($_SESSION['registration_phone']) ? $_SESSION['registration_phone'] : '';
    $country = isset($_SESSION['registration_country']) ? $_SESSION['registration_country'] : '';
    
    // Get password from form
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a password']);
        exit;
    }
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Insert new user with "pending" status
        $stmt = $conn->prepare("INSERT INTO resellers (business_name, full_name, email, phone, password, status, payment_interval, created_at) VALUES (?, ?, ?, ?, ?, 'pending', 'monthly', NOW())");
        $stmt->bind_param("sssss", $company_name, $full_name, $email, $phone, $hashed_password);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            
            // Clear registration session data
            unset($_SESSION['registration_email']);
            unset($_SESSION['registration_company']);
            unset($_SESSION['registration_full_name']);
            unset($_SESSION['registration_phone']);
            unset($_SESSION['registration_country']);
            
            // Set success flag for step 4
            $_SESSION['registration_complete'] = true;
            
            echo json_encode(['success' => true]);
        } else {
            // Rollback transaction
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to create account. Please try again.']);
        }
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        error_log("Registration error: " . $e->getMessage());
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Qtro ISP - Registration</title>
<link rel="icon" type="image/png" href="favicon.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="other-css/register.css">
<style>
    .error-message {
        color: #ef4444;
        font-size: 14px;
        margin-top: 5px;
        display: none;
    }
    .spinner {
        display: none;
        margin-left: 8px;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="trial-badge">
            <i class="far fa-clock"></i>
            14-day free trial
        </div>
        
        <div class="form-card">
            <div class="steps">
                <div class="step active" data-step="1">
                    1
                    <div class="step-label">Email</div>
                </div>
                <div class="step" data-step="2">
                    2
                    <div class="step-label">Business Details</div>
                </div>
                <div class="step" data-step="3">
                    3
                    <div class="step-label">Security</div>
                </div>
                <div class="step" data-step="4">
                    4
                    <div class="step-label">Check Email</div>
                </div>
            </div>
            
            <!-- Step 1: Email -->
            <div class="step-content active" id="step-1">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-icon">
                        <i class="far fa-envelope"></i>
                        <input type="email" id="email" class="form-input" placeholder="Enter your email address" required>
                    </div>
                    <div class="error-message" id="email-error"></div>
                </div>
                
                <div class="form-actions">
                    <div></div> <!-- Empty div for spacing -->
                    <button type="button" class="btn btn-primary" id="next-1">
                        Next
                        <i class="fas fa-arrow-right"></i>
                        <div class="spinner" id="spinner-step1"></div>
                    </button>
                </div>
                
                <div class="form-note">
                    Already have an account? <a href="login.php">Sign in</a>
                </div>
            </div>
            
            <!-- Step 2: Business Details -->
            <div class="step-content" id="step-2">
                <div class="form-group">
                    <label for="company-name" class="form-label">Company Name</label>
                    <div class="input-icon">
                        <i class="far fa-building"></i>
                        <input type="text" id="company-name" class="form-input" placeholder="Your ISP Business Name" required>
                    </div>
                    <div class="error-message" id="company-error"></div>
                </div>
                <div class="form-group">
                    <label for="full-name" class="form-label">Full Name</label>
                    <div class="input-icon">
                        <i class="far fa-user"></i>
                        <input type="text" id="full-name" class="form-input" placeholder="Your Full Name" required>
                    </div>
                    <div class="error-message" id="full-name-error"></div>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="phone" class="form-input" placeholder="+1 (555) 000-0000" required>
                    </div>
                    <div class="error-message" id="phone-error"></div>
                </div>
                
                <div class="form-group">
                    <label for="country" class="form-label">Country</label>
                    <div class="input-icon">
                        <i class="fas fa-globe"></i>
                        <select id="country" class="form-select" required>
                            <option value="" selected disabled>Select a country</option>
                            <option value="ke">Kenya</option>
                            <option value="tz">Tanzania</option>
                            <option value="ug">Uganda</option>
                            <option value="rw">Rwanda</option>
                        </select>
                    </div>
                    <div class="error-message" id="country-error"></div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="prev-2">
                        <i class="fas fa-arrow-left"></i>
                        Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="next-2">
                        Next
                        <i class="fas fa-arrow-right"></i>
                        <div class="spinner" id="spinner-step2"></div>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Security -->
            <div class="step-content" id="step-3">
                <div class="form-group">
                    <label for="password" class="form-label">Create Password</label>
                    <div class="input-icon" style="position: relative;">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" class="form-input" placeholder="Create a secure password" required>
                        <span class="password-toggle" id="password-toggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-meter" id="password-meter"></div>
                    </div>
                    <div class="password-strength-text" id="password-strength-text">Password strength</div>
                    
                    <div class="password-requirements">
                        <div class="password-requirement" id="req-length">
                            <i class="fas fa-circle requirement-unmet"></i>
                            At least 8 characters
                        </div>
                        <div class="password-requirement" id="req-uppercase">
                            <i class="fas fa-circle requirement-unmet"></i>
                            At least 1 uppercase letter
                        </div>
                        <div class="password-requirement" id="req-lowercase">
                            <i class="fas fa-circle requirement-unmet"></i>
                            At least 1 lowercase letter
                        </div>
                        <div class="password-requirement" id="req-number">
                            <i class="fas fa-circle requirement-unmet"></i>
                            At least 1 number
                        </div>
                        <div class="password-requirement" id="req-special">
                            <i class="fas fa-circle requirement-unmet"></i>
                            At least 1 special character
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm-password" class="form-label">Confirm Password</label>
                    <div class="input-icon" style="position: relative;">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm-password" class="form-input" placeholder="Confirm your password" required>
                        <span class="password-toggle" id="confirm-password-toggle">
                            <i class="far fa-eye"></i>
                        </span>
                    </div>
                    <div class="error-message" id="password-error"></div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="prev-3">
                        <i class="fas fa-arrow-left"></i>
                        Previous
                    </button>
                    <button type="button" class="btn btn-primary" id="next-3">
                        Create Account
                        <i class="fas fa-check"></i>
                        <div class="spinner" id="spinner-step3"></div>
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Approval -->
            <div class="step-content" id="step-4">
                <div class="success-message">
                    <div class="success-icon">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <h2>Approval</h2>
                    <p>Your account has been successfully created. Please wait for approval.
                        It may take up to 36 hours for your account to be approved and login into the portal.
                    </p>
                </div>
                
                <div class="form-actions" style="justify-content: center;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Continue
                    </a>
                </div>
                
                <div class="form-note">
                    Need help? <a href="https://wa.me/254750059353">Contact Support</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Step navigation
            const steps = document.querySelectorAll('.step');
            const stepContents = document.querySelectorAll('.step-content');
            let currentStep = 1; // Start at step 1 as requested
            
            // Initialize - show step 1
            updateSteps();
            
            // Next buttons with server validation
            document.getElementById('next-1').addEventListener('click', function() {
                if (validateClientStep(1)) {
                    // Show spinner
                    document.getElementById('spinner-step1').style.display = 'inline-block';
                    
                    // Disable button
                    this.disabled = true;
                    
                    // Server-side validation (check if email exists)
                    const email = document.getElementById('email').value;
                    
                    fetch('registration.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `step=email_check&email=${encodeURIComponent(email)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide spinner
                        document.getElementById('spinner-step1').style.display = 'none';
                        
                        // Enable button
                        this.disabled = false;
                        
                        if (data.success) {
                            // Email is valid and unique
                            currentStep = 2;
                            updateSteps();
                        } else {
                            // Show error message
                            const errorEl = document.getElementById('email-error');
                            errorEl.textContent = data.message;
                            errorEl.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('spinner-step1').style.display = 'none';
                        this.disabled = false;
                        
                        // Show generic error
                        const errorEl = document.getElementById('email-error');
                        errorEl.textContent = 'An error occurred. Please try again.';
                        errorEl.style.display = 'block';
                    });
                }
            });
            
            document.getElementById('next-2').addEventListener('click', function() {
                if (validateClientStep(2)) {
                    // Show spinner
                    document.getElementById('spinner-step2').style.display = 'inline-block';
                    
                    // Disable button
                    this.disabled = true;
                    
                    // Server-side validation (check if company name and phone are unique)
                    const companyName = document.getElementById('company-name').value;
                    const fullName = document.getElementById('full-name').value;
                    const phone = document.getElementById('phone').value;
                    const country = document.getElementById('country').value;
                    
                    fetch('registration.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `step=business_check&company_name=${encodeURIComponent(companyName)}&full_name=${encodeURIComponent(fullName)}&phone=${encodeURIComponent(phone)}&country=${encodeURIComponent(country)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide spinner
                        document.getElementById('spinner-step2').style.display = 'none';
                        
                        // Enable button
                        this.disabled = false;
                        
                        if (data.success) {
                            // Business details are valid and unique
                            currentStep = 3;
                            updateSteps();
                        } else {
                            // Show error message in the appropriate field
                            if (data.message.includes('company name')) {
                                const errorEl = document.getElementById('company-error');
                                errorEl.textContent = data.message;
                                errorEl.style.display = 'block';
                            } else if (data.message.includes('full name')) {
                                const errorEl = document.getElementById('full-name-error');
                                errorEl.textContent = data.message;
                                errorEl.style.display = 'block';
                            } else if (data.message.includes('phone number')) {
                                const errorEl = document.getElementById('phone-error');
                                errorEl.textContent = data.message;
                                errorEl.style.display = 'block';
                            } else if (data.message.includes('country')) {
                                const errorEl = document.getElementById('country-error');
                                errorEl.textContent = data.message;
                                errorEl.style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('spinner-step2').style.display = 'none';
                        this.disabled = false;
                        
                        // Show generic error
                        alert('An error occurred. Please try again.');
                    });
                }
            });
            
            document.getElementById('next-3').addEventListener('click', function() {
                if (validateClientStep(3)) {
                    // Show spinner
                    document.getElementById('spinner-step3').style.display = 'inline-block';
                    
                    // Disable button
                    this.disabled = true;
                    
                    // Send all data to server
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm-password').value;
                    
                    fetch('registration.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `step=register&password=${encodeURIComponent(password)}&confirm_password=${encodeURIComponent(confirmPassword)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Hide spinner
                        document.getElementById('spinner-step3').style.display = 'none';
                        
                        // Enable button
                        this.disabled = false;
                        
                        if (data.success) {
                            // Registration complete
                            currentStep = 4;
                            updateSteps();
                        } else {
                            // Show error message
                            const errorEl = document.getElementById('password-error');
                            errorEl.textContent = data.message;
                            errorEl.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('spinner-step3').style.display = 'none';
                        this.disabled = false;
                        
                        // Show generic error
                        const errorEl = document.getElementById('password-error');
                        errorEl.textContent = 'An error occurred. Please try again.';
                        errorEl.style.display = 'block';
                    });
                }
            });
            
            // Previous buttons
            document.getElementById('prev-2').addEventListener('click', function() {
                currentStep = 1;
                updateSteps();
            });
            
            document.getElementById('prev-3').addEventListener('click', function() {
                currentStep = 2;
                updateSteps();
            });
            
           
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
            
            document.getElementById('confirm-password-toggle').addEventListener('click', function() {
                const passwordInput = document.getElementById('confirm-password');
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
            
            // Password strength meter
            const passwordInput = document.getElementById('password');
            const passwordMeter = document.getElementById('password-meter');
            const passwordStrengthText = document.getElementById('password-strength-text');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Update meter width and color
                passwordMeter.style.width = `${strength.score * 25}%`;
                
                // Update meter color
                if (strength.score === 0) {
                    passwordMeter.style.backgroundColor = '#ef4444'; // Red
                } else if (strength.score === 1) {
                    passwordMeter.style.backgroundColor = '#f97316'; // Orange
                } else if (strength.score === 2) {
                    passwordMeter.style.backgroundColor = '#f59e0b'; // Amber
                } else if (strength.score === 3) {
                    passwordMeter.style.backgroundColor = '#84cc16'; // Lime
                } else {
                    passwordMeter.style.backgroundColor = '#10b981'; // Green
                }
                
                // Update strength text
                passwordStrengthText.textContent = `Password strength: ${strength.message}`;
                
                // Update requirements
                updatePasswordRequirements(password);
            });
            
            function calculatePasswordStrength(password) {
                if (!password) {
                    return { score: 0, message: 'None' };
                }
                
                let score = 0;
                
                // Length check
                if (password.length >= 8) score++;
                if (password.length >= 12) score++;
                
                // Character variety checks
                if (/[A-Z]/.test(password)) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                // Adjust final score (max 4)
                score = Math.min(4, Math.floor(score / 1.5));
                
                // Return score and message
                const messages = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
                return { score, message: messages[score] };
            }
            
            function updatePasswordRequirements(password) {
                // Check each requirement
                const reqLength = document.getElementById('req-length');
                const reqUppercase = document.getElementById('req-uppercase');
                const reqLowercase = document.getElementById('req-lowercase');
                const reqNumber = document.getElementById('req-number');
                const reqSpecial = document.getElementById('req-special');
                
                // Length requirement
                updateRequirement(reqLength, password.length >= 8);
                
                // Uppercase letter requirement
                updateRequirement(reqUppercase, /[A-Z]/.test(password));
                
                // Lowercase letter requirement
                updateRequirement(reqLowercase, /[a-z]/.test(password));
                
                // Number requirement
                updateRequirement(reqNumber, /[0-9]/.test(password));
                
                // Special character requirement
                updateRequirement(reqSpecial, /[^A-Za-z0-9]/.test(password));
            }
            
            function updateRequirement(element, isMet) {
                const icon = element.querySelector('i');
                
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
            
            function validateClientStep(step) {
                // Clear previous error messages
                document.querySelectorAll('.error-message').forEach(el => {
                    el.style.display = 'none';
                });
                
                // Simple validation for demo purposes
                if (step === 1) {
                    const email = document.getElementById('email').value;
                    if (!email) {
                        const errorEl = document.getElementById('email-error');
                        errorEl.textContent = 'Please enter your email address';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (!isValidEmail(email)) {
                        const errorEl = document.getElementById('email-error');
                        errorEl.textContent = 'Please enter a valid email address';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    return true;
                }
                
                if (step === 2) {
                    const companyName = document.getElementById('company-name').value;
                    const fullName = document.getElementById('full-name').value;
                    const phone = document.getElementById('phone').value;
                    const country = document.getElementById('country').value;
                    
                    if (!companyName) {
                        const errorEl = document.getElementById('company-error');
                        errorEl.textContent = 'Please enter your company name';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (!fullName) {
                        const errorEl = document.getElementById('full-name-error');
                        errorEl.textContent = 'Please enter your full name';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (!phone) {
                        const errorEl = document.getElementById('phone-error');
                        errorEl.textContent = 'Please enter your phone number';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (!country) {
                        const errorEl = document.getElementById('country-error');
                        errorEl.textContent = 'Please select your country';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    return true;
                }
                
                if (step === 3) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm-password').value;
                    
                    if (!password) {
                        const errorEl = document.getElementById('password-error');
                        errorEl.textContent = 'Please create a password';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (calculatePasswordStrength(password).score < 2) {
                        const errorEl = document.getElementById('password-error');
                        errorEl.textContent = 'Please create a stronger password';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (!confirmPassword) {
                        const errorEl = document.getElementById('password-error');
                        errorEl.textContent = 'Please confirm your password';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    if (password !== confirmPassword) {
                        const errorEl = document.getElementById('password-error');
                        errorEl.textContent = 'Passwords do not match';
                        errorEl.style.display = 'block';
                        return false;
                    }
                    return true;
                }
                
                return true;
            }
            
            function isValidEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }
            
            function updateSteps() {
                // Update step indicators
                steps.forEach(step => {
                    const stepNumber = parseInt(step.getAttribute('data-step'));
                    
                    if (stepNumber < currentStep) {
                        step.classList.add('completed');
                        step.classList.remove('active');
                    } else if (stepNumber === currentStep) {
                        step.classList.add('active');
                        step.classList.remove('completed');
                    } else {
                        step.classList.remove('active');
                        step.classList.remove('completed');
                    }
                });
                
                // Show current step content
                stepContents.forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(`step-${currentStep}`).classList.add('active');
            }
            
            // Reset error on input
            document.getElementById('email').addEventListener('input', function() {
                document.getElementById('email-error').style.display = 'none';
            });
            
            document.getElementById('company-name').addEventListener('input', function() {
                document.getElementById('company-error').style.display = 'none';
            });
            
            document.getElementById('full-name').addEventListener('input', function() {
                document.getElementById('full-name-error').style.display = 'none';
            });
            
            document.getElementById('phone').addEventListener('input', function() {
                document.getElementById('phone-error').style.display = 'none';
            });
            
            document.getElementById('country').addEventListener('change', function() {
                document.getElementById('country-error').style.display = 'none';
            });
            
            document.getElementById('password').addEventListener('input', function() {
                document.getElementById('password-error').style.display = 'none';
            });
            
            document.getElementById('confirm-password').addEventListener('input', function() {
                document.getElementById('password-error').style.display = 'none';
            });
        });
    </script>
</body>
</html>