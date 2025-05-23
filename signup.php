<?php
// Start session for error/success messages
session_start();

// Database connection parameters
$host = "localhost";
$dbname = "pulse_clinic";  // Corrected database name
$username = "root";
$password = "";

// Initialize variables
$fullname = $emailphone = $email = $phone = $user_password = "";
$errors = [];

// Process form data when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $fullname = trim(htmlspecialchars($_POST["fullname"] ?? ''));
    $emailphone = trim(htmlspecialchars($_POST["emailphone"] ?? ''));
    $user_password = $_POST["password"] ?? '';
    
    // Validate full name
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    } elseif (!preg_match("/^[a-zA-Z ]*$/", $fullname)) {
        $errors[] = "Only letters and spaces allowed in full name";
    }
    
    // Determine if input is email or phone
    if (filter_var($emailphone, FILTER_VALIDATE_EMAIL)) {
        $email = $emailphone;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
    } else {
        $phone = preg_replace('/[^0-9]/', '', $emailphone);
        if (strlen($phone) < 10) {
            $errors[] = "Phone number must be at least 10 digits";
        }
    }
    
    // Validate that either email or phone is provided
    if (empty($email) && empty($phone)) {
        $errors[] = "Valid email or phone number is required";
    }
    
    // Validate password
    if (strlen($user_password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif (!preg_match('/[A-Z]/', $user_password) || !preg_match('/[0-9]/', $user_password)) {
        $errors[] = "Password must contain at least one uppercase letter and one number";
    }
    
    // Only check database if no errors so far
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if email already exists
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Email already exists";
                }
            }
            
            // Check if phone already exists
            if (!empty($phone)) {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE phone_number = ?");
                $stmt->execute([$phone]);
                if ($stmt->rowCount() > 0) {
                    $errors[] = "Phone number already exists";
                }
            }
            
            // If no errors, create account
            if (empty($errors)) {
                $password_hash = password_hash($user_password, PASSWORD_DEFAULT);
                $role = "patient";
                
                $stmt = $pdo->prepare("INSERT INTO users 
                    (full_name, email, password_hash, role, phone_number) 
                    VALUES (?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $fullname,
                    !empty($email) ? $email : null,
                    $password_hash,
                    $role,
                    !empty($phone) ? $phone : null
                ]);
                
                $_SESSION['success_message'] = "Account created successfully! You can now log in.";
                header("Location: login.php");
                exit();
            }
        } catch(PDOException $e) {
            $errors[] = "Database error: Please try again later";
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sign up for Pulse Clinic to book medical appointments online">
    <title>Pulse Clinic - Create Your Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3A79A9;
            --primary-dark: #1a4b7a;
            --primary-light: #4f8cbf;
            --secondary: #f5f5f5;
            --text-dark: #333333;
            --text-light: #ffffff;
            --error: #d32f2f;
            --success: #388e3c;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Arial', sans-serif;
        }
        
        body {
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .container {
            flex: 1;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            position: relative;
            padding: 0 20px;
        }
        
        .header {
            width: 100%;
            padding: 1.5rem 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .logo {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logo i {
            font-size: 1.5rem;
        }
        
        .logo:hover {
            color: var(--primary-dark);
        }
        
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 0;
        }
        
        .signup-card {
            width: 100%;
            max-width: 480px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border-top: 5px solid var(--primary);
        }
        
        .card-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .card-title {
            color: var(--primary);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .card-subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }
        
        .input-field {
            width: 100%;
            height: 50px;
            background: var(--secondary);
            border-radius: var(--border-radius);
            border: 2px solid transparent;
            padding: 0 20px 0 45px;
            font-size: 1rem;
            transition: var(--transition);
            color: var(--text-dark);
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(58, 121, 169, 0.1);
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 38px;
            color: #777;
            transition: var(--transition);
        }
        
        .input-field:focus + .input-icon {
            color: var(--primary);
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 38px;
            cursor: pointer;
            color: #777;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary);
        }
        
        .password-strength {
            height: 5px;
            margin-top: 8px;
            border-radius: 5px;
            background: #e0e0e0;
            overflow: hidden;
        }
        
        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: var(--transition);
        }
        
        .password-hint {
            color: #777;
            font-size: 0.8rem;
            margin-top: 8px;
            padding-left: 5px;
        }
        
        .submit-btn {
            width: 100%;
            height: 50px;
            background: var(--primary);
            border-radius: var(--border-radius);
            border: none;
            color: var(--text-light);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .submit-btn:active {
            transform: translateY(0);
        }
        
        .login-link {
            margin-top: 1.5rem;
            color: #555;
            font-size: 0.95rem;
            text-align: center;
        }
        
        .login-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: #777;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            padding: 0 10px;
            font-size: 0.9rem;
        }
        
        .social-login {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: var(--transition);
            color: #555;
            font-size: 1.2rem;
        }
        
        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }
        
        .google-btn:hover {
            color: #DB4437;
        }
        
        .facebook-btn:hover {
            color: #4267B2;
        }
        
        .apple-btn:hover {
            color: #000;
        }
        
        .footer {
            margin-top: auto;
            width: 100%;
            padding: 1.5rem 5%;
            background: white;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            color: #666;
        }
        
        .footer-links {
            display: flex;
            gap: 20px;
        }
        
        .footer a {
            color: #666;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer a:hover {
            color: var(--primary);
        }
        
        .error-message {
            background-color: rgba(255, 235, 235, 0.8);
            color: var(--error);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--error);
        }
        
        .error-message ul {
            list-style-type: none;
            padding-left: 10px;
            margin: 5px 0;
        }
        
        .success-message {
            background-color: rgba(235, 255, 235, 0.8);
            color: var(--success);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--success);
        }
        
        .validation-message {
            font-size: 0.8rem;
            color: var(--error);
            margin-top: 5px;
            display: none;
        }
        
        .loading {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: var(--border-radius);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .loading.active {
            opacity: 1;
            visibility: visible;
        }
        
        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 1rem 5%;
            }
            
            .logo {
                font-size: 1.5rem;
            }
            
            .signup-card {
                padding: 1.5rem;
                margin: 1rem auto;
                border-radius: var(--border-radius);
            }
            
            .card-title {
                font-size: 1.5rem;
            }
            
            .footer {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .footer-links {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <a href="index.php" class="logo">
                <i class="fas fa-heartbeat"></i>
                Pulse Clinic
            </a>
            <nav>
                <!-- Navigation could be added here -->
            </nav>
        </header>
        
        <main class="main-content">
            <div class="signup-card">
                <div class="card-header">
                    <h1 class="card-title">Create Your Account</h1>
                    <p class="card-subtitle">Join Pulse Clinic to book appointments easily</p>
                </div>
                
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); 
                        unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($errors)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form id="signup-form" action="signup.php" method="post" novalidate>
                    <div class="form-group">
                        <label for="fullname" class="form-label">Full Name</label>
                        <input type="text" class="input-field" id="fullname" name="fullname" placeholder="Enter your full name" required 
                            value="<?php echo isset($fullname) ? htmlspecialchars($fullname) : ''; ?>">
                        <i class="fas fa-user input-icon"></i>
                        <div class="validation-message" id="fullname-validation"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="emailphone" class="form-label">Email or Phone Number</label>
                        <input type="text" class="input-field" id="emailphone" name="emailphone" placeholder="Enter your email or phone number" required
                            value="<?php echo isset($emailphone) ? htmlspecialchars($emailphone) : ''; ?>">
                        <i class="fas fa-envelope input-icon"></i>
                        <div class="validation-message" id="emailphone-validation"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" class="input-field" id="password" name="password" placeholder="Create a strong password" required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye toggle-password" id="toggle-password"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-meter" id="password-strength-meter"></div>
                        </div>
                        <p class="password-hint" id="password-hint">Password must contain at least 8 characters, including uppercase letter and number</p>
                        <div class="validation-message" id="password-validation"></div>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submit-btn">
                        Create Account
                        <div class="loading" id="loading">
                            <div class="spinner"></div>
                        </div>
                    </button>
                </form>
                
                <div class="divider">
                    <span>or sign up with</span>
                </div>
                
                <div class="social-login">
                    <a href="#" class="social-btn google-btn" title="Sign up with Google">
                        <i class="fab fa-google"></i>
                    </a>
                    <a href="#" class="social-btn facebook-btn" title="Sign up with Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-btn apple-btn" title="Sign up with Apple">
                        <i class="fab fa-apple"></i>
                    </a>
                </div>
                
                <p class="login-link">Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </main>
    </div>

    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Pulse Clinic. All rights reserved.</p>
        <div class="footer-links">
            <a href="privacy-policy.php">Privacy Policy</a>
            <a href="terms-of-service.php">Terms of Service</a>
            <a href="contact.php">Contact Us</a>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('signup-form');
            const fullnameInput = document.getElementById('fullname');
            const emailphoneInput = document.getElementById('emailphone');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('toggle-password');
            const passwordStrengthMeter = document.getElementById('password-strength-meter');
            const passwordHint = document.getElementById('password-hint');
            const submitBtn = document.getElementById('submit-btn');
            const loading = document.getElementById('loading');
            
            // Toggle password visibility
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Check password strength
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let color = '';
                
                if (password.length >= 8) strength += 25;
                if (password.match(/[A-Z]/)) strength += 25;
                if (password.match(/[0-9]/)) strength += 25;
                if (password.match(/[$@#&!]/)) strength += 25;
                
                passwordStrengthMeter.style.width = strength + '%';
                
                if (strength <= 25) {
                    color = '#d32f2f';
                    passwordHint.textContent = 'Weak password - add uppercase letters, numbers, and special characters.';
                } else if (strength <= 50) {
                    color = '#ff9800';
                    passwordHint.textContent = 'Moderate password - add more variety for better security.';
                } else if (strength <= 75) {
                    color = '#ffeb3b';
                    passwordHint.textContent = 'Good password - consider adding special characters.';
                } else {
                    color = '#4caf50';
                    passwordHint.textContent = 'Strong password!';
                }
                
                passwordStrengthMeter.style.backgroundColor = color;
            });
            
            // Form validation
            function validateFullname() {
                const fullname = fullnameInput.value.trim();
                const fullnameValidation = document.getElementById('fullname-validation');
                
                if (fullname === '') {
                    fullnameValidation.textContent = 'Please enter your full name';
                    fullnameValidation.style.display = 'block';
                    fullnameInput.style.borderColor = 'var(--error)';
                    return false;
                } else if (fullname.length < 3) {
                    fullnameValidation.textContent = 'Name must be at least 3 characters';
                    fullnameValidation.style.display = 'block';
                    fullnameInput.style.borderColor = 'var(--error)';
                    return false;
                } else {
                    fullnameValidation.style.display = 'none';
                    fullnameInput.style.borderColor = 'var(--primary)';
                    return true;
                }
            }
            
            function validateEmailPhone() {
                const emailphone = emailphoneInput.value.trim();
                const emailphoneValidation = document.getElementById('emailphone-validation');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const phoneRegex = /^\+?[0-9]{10,15}$/;
                
                if (emailphone === '') {
                    emailphoneValidation.textContent = 'Please enter your email or phone number';
                    emailphoneValidation.style.display = 'block';
                    emailphoneInput.style.borderColor = 'var(--error)';
                    return false;
                } else if (!emailRegex.test(emailphone) && !phoneRegex.test(emailphone)) {
                    emailphoneValidation.textContent = 'Please enter a valid email or phone number';
                    emailphoneValidation.style.display = 'block';
                    emailphoneInput.style.borderColor = 'var(--error)';
                    return false;
                } else {
                    emailphoneValidation.style.display = 'none';
                    emailphoneInput.style.borderColor = 'var(--primary)';
                    return true;
                }
            }
            
            function validatePassword() {
                const password = passwordInput.value;
                const passwordValidation = document.getElementById('password-validation');
                const passwordRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
                
                if (password === '') {
                    passwordValidation.textContent = 'Please enter a password';
                    passwordValidation.style.display = 'block';
                    passwordInput.style.borderColor = 'var(--error)';
                    return false;
                } else if (!passwordRegex.test(password)) {
                    passwordValidation.textContent = 'Password must be at least 8 characters with one uppercase letter and one number';
                    passwordValidation.style.display = 'block';
                    passwordInput.style.borderColor = 'var(--error)';
                    return false;
                } else {
                    passwordValidation.style.display = 'none';
                    passwordInput.style.borderColor = 'var(--primary)';
                    return true;
                }
            }
            
            // Input validation events
            fullnameInput.addEventListener('blur', validateFullname);
            emailphoneInput.addEventListener('blur', validateEmailPhone);
            passwordInput.addEventListener('blur', validatePassword);
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const isFullnameValid = validateFullname();
                const isEmailPhoneValid = validateEmailPhone();
                const isPasswordValid = validatePassword();
                
                if (isFullnameValid && isEmailPhoneValid && isPasswordValid) {
                    // Show loading spinner
                    loading.classList.add('active');
                    
                    // Simulate server request (remove this in production)
                    setTimeout(function() {
                        // In production, the form would be submitted normally here
                        form.submit();
                    }, 1500);
                }
            });
            
            // CSRF Protection (would need to be implemented server-side)
            // This is just a placeholder for where you would add the token
            form.insertAdjacentHTML('beforeend', '<input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION["csrf_token"]) ? $_SESSION["csrf_token"] : ""; ?>">');
        });
    </script>
</body>
</html>