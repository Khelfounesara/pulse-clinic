<?php
// Start session for user authentication
session_start();

// Database connection parameters
$host = "localhost";
$dbname = "pulse_clinic";
$username = "root";
$password = "";

// Initialize variables
$fullname = $user_password = "";
$errors = [];

// Process form data when the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $fullname = trim(htmlspecialchars($_POST["fullName"] ?? ''));
    $user_password = $_POST["password"] ?? '';
    
    // Validate full name
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    // Validate password
    if (empty($user_password)) {
        $errors[] = "Password is required";
    } elseif (strlen($user_password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    // Only check database if no errors so far
    if (empty($errors)) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE full_name = ?");
            $stmt->execute([$fullname]);
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                // Verify password
                if (password_verify($user_password, $user['password_hash'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['admin_id'] = ($user['role'] === 'admin') ? $user['user_id'] : null;
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: Dashboard.php");
                    } elseif ($user['role'] === 'doctor') {
                        header("Location: doctor_dashboard.php");
                    } else {
                        header("Location: afterlogin.php");
                    }
                    exit();
                } else {
                    $errors[] = "Invalid password";
                }
            } else {
                $errors[] = "User not found";
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
    <title>Pulse Clinic - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Root variables for consistent design */
        :root {
            --primary: #2c6e9b;
            --primary-light: #3A79A9;
            --primary-dark: #1d4d6d;
            --secondary: #f5f5f5;
            --accent: #e9f3f8;
            --text-dark: #333333;
            --text-light: #ffffff;
            --error: #e74c3c;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(to right, rgba(44, 110, 155, 0.05), rgba(44, 110, 155, 0.1));
            color: var(--text-dark);
            line-height: 1.6;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header styles */
        .header {
            width: 100%;
            background: #ffffff;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 10;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 32px;
        }

        .nav-link {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
            padding: 10px 20px;
            border-radius: 25px;
        }

        .nav-link:hover {
            color: var(--primary);
            background-color: var(--accent);
        }

        /* Main content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-container {
            width: 100%;
            max-width: 1000px;
            display: flex;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(42, 90, 127, 0.15);
            background: #ffffff;
        }

        .login-image {
            flex: 1;
            background-color: var(--primary);
            background-image: linear-gradient(135deg, var(--primary-light), var(--primary-dark));
            color: var(--text-light);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-image::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }

        .login-image-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .welcome-text {
            font-size: 16px;
            font-weight: 400;
            opacity: 0.9;
            margin-bottom: 30px;
            max-width: 400px;
        }

        .feature-list {
            list-style: none;
            margin-bottom: 40px;
        }

        .feature-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .quote {
            font-style: italic;
            opacity: 0.8;
            position: relative;
            padding-left: 20px;
            border-left: 3px solid rgba(255, 255, 255, 0.3);
            margin-top: auto;
        }

        .login-form-container {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #777;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #aaa;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            height: 54px;
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 0 16px 0 45px;
            font-size: 15px;
            transition: var(--transition);
            color: var(--text-dark);
        }

        .form-input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(42, 90, 127, 0.1);
        }

        .form-input:focus + .input-icon {
            color: var(--primary);
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 16px;
            transform: translateY(-50%);
            color: #aaa;
            cursor: pointer;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .forgot-password {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 24px;
        }

        .forgot-link {
            color: var(--primary);
            font-size: 14px;
            text-decoration: none;
            transition: var(--transition);
        }

        .forgot-link:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }

        .login-button {
            width: 100%;
            height: 54px;
            background: var(--primary);
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 90, 127, 0.2);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #999;
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            padding: 0 15px;
        }

        .social-login {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .social-button {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 46px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            background: #fff;
            color: #555;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .social-button:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .google-btn {
            color: #DB4437;
        }

        .facebook-btn {
            color: #4267B2;
        }

        .signup-text {
            text-align: center;
            color: #777;
            font-size: 15px;
        }

        .signup-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .signup-link:hover {
            text-decoration: underline;
        }

        .error-box {
            background-color: #fdeded;
            color: var(--error);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 24px;
            border-left: 4px solid var(--error);
            font-size: 14px;
        }

        .error-box ul {
            list-style-type: none;
            padding-left: 5px;
            margin: 5px 0;
        }

        .error-box ul li {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-box ul li::before {
            content: '•';
            color: var(--error);
            font-weight: bold;
        }

        .validator-message {
            color: var(--error);
            font-size: 13px;
            margin-top: 6px;
            margin-left: 10px;
            display: none;
        }

        /* Footer */
        .footer {
            background: var(--primary);
            color: var(--text-light);
            text-align: center;
            padding: 20px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .login-image {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .login-form-container {
                padding: 30px 20px;
            }

            .header-container {
                height: 70px;
            }

            .logo {
                font-size: 22px;
            }

            .login-title {
                font-size: 24px;
            }

            .social-login {
                flex-direction: column;
                gap: 10px;
            }

            .main-content {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <header class="header">
            <div class="header-container">
                <a href="Home.html" class="logo">
                    <i class="fas fa-heartbeat"></i> Pulse Clinic
                </a>
                <a href="Home.html" class="nav-link">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <div class="login-container">
                <!-- Left Side - Image and Text -->
                <div class="login-image">
                    <div class="login-image-content">
                        <h2 class="welcome-title">Welcome Back!</h2>
                        <p class="welcome-text">Access your personal health dashboard and manage your appointments with ease.</p>
                        
                        <ul class="feature-list">
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <span>Book and manage appointments</span>
                            </li>
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <span>Access your medical records</span>
                            </li>
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-pills"></i>
                                </div>
                                <span>View prescriptions and medications</span>
                            </li>
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <span>Communicate with your healthcare team</span>
                            </li>
                        </ul>
                        
                        <div class="quote">
                            "The greatest wealth is health." — Virgil
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="login-form-container">
                    <div class="login-header">
                        <h1 class="login-title">Sign In</h1>
                        <p class="login-subtitle">Sign in to access your account</p>
                    </div>
                    
                    <?php if(!empty($errors)): ?>
                        <div class="error-box">
                            <ul>
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="post" id="loginForm">
                        <div class="form-group">
                            <label for="fullName" class="form-label">Full Name</label>
                            <div class="input-group">
                                <input 
                                    type="text" 
                                    id="fullName" 
                                    name="fullName" 
                                    class="form-input" 
                                    placeholder="Enter your full name"
                                    autocomplete="name"
                                    value="<?php echo isset($fullname) ? htmlspecialchars($fullname) : ''; ?>"
                                    required
                                >
                                <i class="fas fa-user input-icon"></i>
                            </div>
                            <div id="fullNameError" class="validator-message">Please enter your full name</div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input" 
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >
                                <i class="fas fa-lock input-icon"></i>
                                <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                            </div>
                            <div id="passwordError" class="validator-message">Password must be at least 6 characters</div>
                        </div>

                        <div class="forgot-password">
                            <a href="#" class="forgot-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="login-button">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </button>

                        <div class="divider">
                            <span>or continue with</span>
                        </div>

                        <div class="social-login">
                            <button type="button" class="social-button google-btn">
                                <i class="fab fa-google"></i> Google
                            </button>
                            <button type="button" class="social-button facebook-btn">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </button>
                        </div>

                        <p class="signup-text">
                            Don't have an account? 
                            <a href="signup.php" class="signup-link">Create an Account</a>
                        </p>
                    </form>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>© 2025 Pulse Clinic - All Rights Reserved. Privacy Policy | Terms of Service</p>
        </footer>
    </div>

    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');
        
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('fullName').value.trim();
            const password = document.getElementById('password').value;
            const fullNameError = document.getElementById('fullNameError');
            const passwordError = document.getElementById('passwordError');
            let isValid = true;

            // Reset previous error states
            fullNameError.style.display = 'none';
            passwordError.style.display = 'none';
            
            // Validate full name
            if (!fullName) {
                e.preventDefault();
                fullNameError.style.display = 'block';
                document.getElementById('fullName').classList.add('error');
                isValid = false;
            }

            // Validate password
            if (password.length < 6) {
                e.preventDefault();
                passwordError.style.display = 'block';
                document.getElementById('password').classList.add('error');
                isValid = false;
            }

            return isValid;
        });

        // Input focus effects
        const inputs = document.querySelectorAll('.form-input');
        
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('input-focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('input-focused');
            });
        });

        // Disable default form submission for demo purposes
        const socialButtons = document.querySelectorAll('.social-button');
        socialButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Social login feature coming soon!');
            });
        });

        document.querySelector('.forgot-link').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Password reset feature coming soon!');
        });
    </script>
</body>
</html>