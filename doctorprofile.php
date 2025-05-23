<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pulse_clinic";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fetch user data
    $userId = $_SESSION['user_id'];
    $userQuery = "SELECT full_name, email FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Fetch doctor data
    $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
    if (!$doctorId) {
        throw new Exception("Invalid doctor ID");
    }

    $doctorQuery = "
        SELECT u.full_name AS doctor_name, u.email, d.specialty,
               up.phone, up.address, up.bio, up.profile_image
        FROM doctors d
        JOIN users u ON d.doctor_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE d.doctor_id = :doctor_id
    ";
    $stmt = $conn->prepare($doctorQuery);
    $stmt->execute(['doctor_id' => $doctorId]);
    $doctor = $stmt->fetch();

    if (!$doctor) {
        throw new Exception("Doctor not found");
    }

    // Fallback values
    $doctor['doctor_name'] = $doctor['doctor_name'] ?? 'Dr. Unknown';
    $doctor['specialty'] = $doctor['specialty'] ?? 'General';
    $doctor['email'] = $doctor['email'] ?? 'Not provided';
    $doctor['phone'] = $doctor['phone'] ?? 'Not provided';
    $doctor['address'] = $doctor['address'] ?? 'Not provided';
    $doctor['bio'] = $doctor['bio'] ?? 'No biography available.';
    $doctor['profile_image'] = $doctor['profile_image'] ?? 'https://placehold.co/200x200';

} catch (PDOException $e) {
    error_log("Database error in doctor-profile.php: " . $e->getMessage());
    $error = "An error occurred: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    error_log("Error in doctor-profile.php: " . $e->getMessage());
    $error = $e->getMessage();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
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
            font-size: 16px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 80px;
            padding: 0 30px;
        }

        .logo {
            color: #3A79A9;
            font-size: 26px;
            font-weight: 800;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 8px;
            font-size: 28px;
        }

        .nav-menu {
            display: flex;
            gap: 30px;
        }

        .nav-item {
            font-size: 16px;
            font-weight: 500;
            color: #555;
            padding: 8px 0;
            transition: color 0.3s ease;
            position: relative;
        }

        .nav-item:hover, .nav-item.active-nav {
            color: #3A79A9;
        }

        .active-nav::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: #3A79A9;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-name {
            font-size: 15px;
            font-weight: 500;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
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
            padding: 8px 0;
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
            padding: 8px 15px;
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #555;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            margin-right: 8px;
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

        /* Doctor Profile */
        .doctor-profile {
            display: flex;
            align-items: center;
            padding: 40px 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            position: relative;
        }

        .doctor-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            margin-left: 20px;
            transition: all 0.3s ease;
        }

        .doctor-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .doctor-details {
            margin-left: 30px;
            flex: 1;
        }

        .doctor-name {
            font-size: 28px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 10px;
        }

        .doctor-specialty {
            font-size: 18px;
            color: #666;
            font-weight: 500;
        }

        .contact-info {
            width: 350px;
            background: #3A79A9;
            border-radius: 8px;
            padding: 20px;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .contact-info:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .contact-header {
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 15px;
            text-decoration: underline;
        }

        .contact-row {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
        }

        .contact-label {
            width: 80px;
            font-size: 15px;
            font-weight: 500;
            color: #f5f5f5;
        }

        .contact-value {
            flex: 1;
            font-size: 15px;
            font-weight: 400;
        }

        .contact-value a {
            color: #f5f5f5;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-value a:hover {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Divider */
        .divider {
            width: 100%;
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }

        /* Doctor Info Section */
        .doctor-info-section {
            padding: 20px 0;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .doctor-bio {
            font-size: 16px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 20px;
            max-width: 800px;
        }

        /* Book Button */
        .book-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #3A79A9;
            color: white;
            font-size: 16px;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .book-btn:hover {
            background: #2c5d84;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .book-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Error Message */
        .error-message {
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: center;
            font-size: 14px;
            background: #fee2e2;
            color: #dc2626;
        }

        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .doctor-profile, .doctor-info-section {
            animation: slideUp 0.8s ease forwards;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .doctor-profile {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }

            .doctor-avatar {
                margin: 0 auto;
            }

            .doctor-details {
                margin: 20px 0;
                text-align: center;
            }

            .contact-info {
                width: 100%;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                height: 60px;
                padding: 0 15px;
            }

            .logo {
                font-size: 22px;
            }

            .nav-menu {
                gap: 15px;
            }

            .nav-item {
                font-size: 14px;
            }

            .user-name {
                display: none;
            }

            .doctor-avatar {
                width: 120px;
                height: 120px;
            }

            .doctor-name {
                font-size: 24px;
            }

            .doctor-specialty {
                font-size: 16px;
            }

            .section-title {
                font-size: 20px;
            }

            .doctor-bio {
                font-size: 14px;
            }

            .book-btn {
                width: 100%;
                padding: 10px;
            }
        }

        @media (max-width: 576px) {
            .doctor-avatar {
                width: 100px;
                height: 100px;
            }

            .contact-row {
                flex-direction: column;
                gap: 5px;
            }

            .contact-label {
                width: auto;
            }

            .contact-value {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container container">
            <a href="afterlogin.php" class="logo">
                <i class="fas fa-heartbeat"></i> Pulse Clinic
            </a>
            <nav class="nav-menu">
                <a href="afterlogin.php" class="nav-item">Home</a>
                <a href="all_doctors.php" class="nav-item active-nav">Doctors</a>
                <a href="myappointment.php" class="nav-item">My Appointments</a>
                <a href="contact.php" class="nav-item">Contact</a>
            </nav>
            <div class="user-section">
                <div class="user-name">
                    Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                </div>
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'https://placehold.co/40x40'); ?>" 
                         alt="User avatar" 
                         onerror="this.src='https://placehold.co/40x40'">
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item"><i class="fas fa-user"></i> Profile</a>
                        <a href="myappointment.php" class="dropdown-item"><i class="fas fa-calendar-check"></i> My Appointments</a>
                        <a href="medicalrecords.php" class="dropdown-item"><i class="fas fa-file-medical"></i> Medical Records</a>
                        <div class="dropdown-divider"></div>
                        <a href="settings.php" class="dropdown-item"><i class="fas fa-cog"></i> Settings</a>
                        <a href="logout.php" class="dropdown-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Doctor Profile -->
        <div class="doctor-profile">
            <img class="doctor-avatar" 
                 src="<?php echo htmlspecialchars($doctor['profile_image']); ?>" 
                 alt="Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?>">
            <div class="doctor-details">
                <div class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></div>
                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></div>
            </div>
            <div class="contact-info">
                <div class="contact-header">Contact Information</div>
                <div class="contact-row">
                    <div class="contact-label">Email:</div>
                    <div class="contact-value">
                        <a href="mailto:<?php echo htmlspecialchars($doctor['email']); ?>">
                            <?php echo htmlspecialchars($doctor['email']); ?>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-label">Phone:</div>
                    <div class="contact-value">
                        <a href="tel:<?php echo htmlspecialchars($doctor['phone']); ?>">
                            <?php echo htmlspecialchars($doctor['phone']); ?>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-label">Address:</div>
                    <div class="contact-value"><?php echo nl2br(htmlspecialchars($doctor['address'])); ?></div>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Doctor Info Section -->
        <div class="doctor-info-section">
            <div class="section-title">About Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></div>
            <div class="doctor-bio"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></div>
            <a href="booking.php?doctor_id=<?php echo $doctorId; ?>" class="book-btn">Book Appointment</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Book button
            const bookBtn = document.querySelector('.book-btn');
            bookBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Redirecting to the appointment booking page...');
                window.location.href = this.href;
            });

            // Email and phone links
            const emailLink = document.querySelector('a[href^="mailto"]');
            emailLink.addEventListener('click', function() {
                console.log('Opening email client for: <?php echo htmlspecialchars($doctor['email']); ?>');
            });

            const phoneLink = document.querySelector('a[href^="tel"]');
            phoneLink.addEventListener('click', function() {
                console.log('Opening phone dialer for: <?php echo htmlspecialchars($doctor['phone']); ?>');
            });

            // Set active nav
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.getAttribute('href') === 'all_doctors.php') {
                    item.classList.add('active-nav');
                }
            });
        });
    </script>
</body>
</html>