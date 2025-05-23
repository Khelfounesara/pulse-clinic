<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$user = [];

// Database configuration
$config = [
    'host' => 'localhost',
    'dbname' => 'pulse_clinic',
    'username' => 'root',
    'password' => ''
];

try {
    // Create database connection
    $conn = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Fetch user data
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT full_name, email, phone_number, address, gender, date_of_birth FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Map database columns to display variables
    $user['phone'] = $user['phone_number'];
    $user['birthday'] = $user['date_of_birth'];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize inputs
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $gender = in_array($_POST['gender'] ?? '', ['Male', 'Female', 'Other']) ? $_POST['gender'] : '';
        $birthday = $_POST['birthday'] ?? '';

        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }
        
        if (!empty($phone) && (strlen($phone) < 10 || strlen($phone) > 15)) {
            throw new Exception("Phone number must be 10-15 digits");
        }

        if (empty($address)) {
            throw new Exception("Address is required");
        }

        if (empty($gender)) {
            throw new Exception("Please select a gender");
        }

        if (!empty($birthday) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
            throw new Exception("Invalid date format");
        }

        // Update user data
        $stmt = $conn->prepare("UPDATE users SET 
            email = ?, 
            phone_number = ?, 
            address = ?, 
            gender = ?, 
            date_of_birth = ? 
            WHERE user_id = ?");

        $stmt->execute([
            $email,
            $phone,
            $address,
            $gender,
            $birthday ?: null,
            $userId
        ]);

        // Update local user data for display
        $user = [
            'full_name' => $user['full_name'],
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'gender' => $gender,
            'birthday' => $birthday
        ];
        
        $success = "Profile updated successfully!";
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "A database error occurred: " . $e->getMessage(); // Display specific error for debugging
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Close connection
$conn = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Outfit:wght@100..900&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            flex: 1;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 90px;
            padding: 0 40px;
        }

        .logo {
            color: #3A79A9;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
            font-size: 32px;
        }

        .nav-menu {
            display: flex;
            gap: 40px;
        }

        .nav-item {
            font-size: 17px;
            font-weight: 500;
            color: #555;
            padding: 10px 0;
            transition: color 0.3s ease;
        }

        .nav-item:hover {
            color: #3A79A9;
        }

        .active-nav {
            color: #3A79A9;
            font-weight: 600;
        }

        .active-nav::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #3A79A9;
            border-radius: 5px 5px 0 0;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            font-size: 16px;
            font-weight: 500;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            width: 180px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 10px 0;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .user-avatar:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            padding: 10px 20px;
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            margin-right: 10px;
            font-size: 16px;
            color: #3A79A9;
        }

        .dropdown-item:hover {
            background: #f5f5f5;
            color: #3A79A9;
        }

        .dropdown-divider {
            border-top: 1px solid #eee;
            margin: 5px 0;
        }

        /* Profile Section */
        .profile-section {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin: 2rem 0;
            animation: slideUp 0.8s ease forwards;
        }

        .profile-image {
            width: 8rem;
            height: 8rem;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3A79A9;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            color: #1F2937;
            font-size: 2rem;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
        }

        .divider {
            border: 1px solid #ADADAD;
            margin: 2rem 0;
        }

        .section-title {
            color: #3A79A9;
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            margin: 1.5rem 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-item {
            display: grid;
            gap: 0.5rem;
        }

        .info-label {
            color: #4B5563;
            font-size: 1rem;
            font-weight: 500;
        }

        .contact-value {
            color: #3A79A9;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-value:hover {
            color: #2c5d84;
        }

        .basic-value {
            color: #6B7280;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin: 2rem 0;
        }

        .action-btn {
            padding: 1rem 2rem;
            background: #3A79A9;
            border-radius: 47px;
            color: white;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(58, 121, 169, 0.1);
        }

        .action-btn:hover {
            background: #2c5d84;
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(58, 121, 169, 0.2);
        }

        .nav-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .nav-btn {
            padding: 1rem;
            border-radius: 47px;
            border: 1px solid #3A79A9;
            text-align: center;
            color: #262626;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background-color: #3A79A9;
            color: white;
            border-color: #3A79A9;
            transform: translateY(-2px);
        }

        .edit-input, .gender-select, .date-input {
            display: none;
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #3A79A9;
            border-radius: 4px;
            font-size: 1rem;
            outline: none;
        }

        .editing .static-text {
            display: none;
        }

        .editing .edit-input, .editing .gender-select, .editing .date-input {
            display: block;
        }

        .error-message, .success-message {
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }

        .error-message {
            background: #FFEBEE;
            color: #D32F2F;
        }

        .success-message {
            background: #E7F3E7;
            color: #2E7D32;
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                height: 70px;
                padding: 0 20px;
            }

            .logo {
                font-size: 24px;
            }

            .nav-menu {
                gap: 20px;
            }

            .nav-item {
                font-size: 15px;
            }

            .user-name {
                display: none;
            }

            .profile-section {
                flex-direction: column;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-buttons {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 6rem;
                height: 6rem;
            }

            .profile-name {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container container">
            <a href="afterlogin.php" class="logo">
                <i class="fas fa-heartbeat"></i>
                Pulse Clinic
            </a>
            <nav class="nav-menu">
                <a href="afterlogin.php#hero" class="nav-item">Home</a>
                <a href="afterlogin.php#doctors" class="nav-item">Doctors</a>
                <a href="afterlogin.php#services" class="nav-item">Services</a>
                <a href="afterlogin.php#contact" class="nav-item">Contact</a>
            </nav>
            <div class="user-section">
                <div class="user-name">
                    Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                </div>
                <div class="user-avatar">
                    <img src="image/user-avatar.png" alt="User avatar" onerror="this.src='image/default-avatar.jpg'">
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="myappointment.php" class="dropdown-item">
                            <i class="fas fa-calendar-check"></i> My Appointments
                        </a>
                        <a href="medicalrecords.php" class="dropdown-item">
                            <i class="fas fa-file-medical"></i> Medical Records
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Messages -->
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Section -->
        <div class="profile-section">
            <img class="profile-image" src="image/user-avatar.png" alt="Profile Photo" onerror="this.src='image/default-avatar.jpg'">
            <div class="profile-name"><?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></div>
        </div>

        <div class="divider"></div>

        <!-- Profile Info Form -->
        <form action="profile.php" method="post" id="profile-form" onsubmit="return validateForm()">
            <!-- Contact Information -->
            <div class="section-title">Contact Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Email:</div>
                    <span class="static-text contact-value"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span>
                    <input type="email" class="edit-input" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone:</div>
                    <span class="static-text contact-value"><?php echo htmlspecialchars($user['phone'] ?? ''); ?></span>
                    <input type="tel" class="edit-input" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9]{10,15}" title="10-15 digits">
                </div>
                <div class="info-item">
                    <div class="info-label">Address:</div>
                    <span class="static-text basic-value"><?php echo nl2br(htmlspecialchars($user['address'] ?? '')); ?></span>
                    <textarea class="edit-input" name="address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Basic Information -->
            <div class="section-title">Basic Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Gender:</div>
                    <span class="static-text basic-value"><?php echo htmlspecialchars($user['gender'] ?? ''); ?></span>
                    <select class="edit-input gender-select" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="info-item">
                    <div class="info-label">Birthday:</div>
                    <span class="static-text basic-value"><?php echo !empty($user['birthday']) ? date('d F Y', strtotime($user['birthday'])) : 'Not specified'; ?></span>
                    <input type="date" class="edit-input date-input" name="birthday" value="<?php echo htmlspecialchars($user['birthday'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="action-btn edit-btn" onclick="toggleEdit()">Edit</button>
                <button type="submit" class="action-btn save-btn" style="display: none;">Save Information</button>
            </div>
        </form>

        <div class="divider"></div>

        <!-- Navigation Buttons -->
        <div class="nav-buttons">
            <a href="all_doctors.php" class="nav-btn">See Doctors</a>
            <a href="myappointment.php" class="nav-btn">My Appointments</a>
            <a href="medicalrecords.php" class="nav-btn">Medical Records</a>
            <a href="book-appointment.php" class="nav-btn">Book Appointment</a>
        </div>
    </div>

    <script>
        let isEditing = false;
        const editBtn = document.querySelector('.edit-btn');
        const saveBtn = document.querySelector('.save-btn');

        function toggleEdit() {
            isEditing = !isEditing;
            document.body.classList.toggle('editing', isEditing);
            editBtn.style.display = isEditing ? 'none' : 'block';
            saveBtn.style.display = isEditing ? 'block' : 'none';
        }

        function validateForm() {
            const email = document.querySelector('input[name="email"]').value;
            const phone = document.querySelector('input[name="phone"]').value;
            const address = document.querySelector('textarea[name="address"]').value;
            const gender = document.querySelector('select[name="gender"]').value;
            
            // Basic client-side validation
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address');
                return false;
            }
            
            if (phone && !/^[0-9]{10,15}$/.test(phone)) {
                alert('Phone number must be 10-15 digits');
                return false;
            }
            
            if (!address.trim()) {
                alert('Address is required');
                return false;
            }
            
            if (!gender) {
                alert('Please select a gender');
                return false;
            }
            
            return true;
        }

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Navigation active state
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                navItems.forEach(nav => nav.classList.remove('active-nav'));
                this.classList.add('active-nav');
            });
        });
    </script>
</body>
</html>