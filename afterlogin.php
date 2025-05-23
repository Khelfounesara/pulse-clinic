<?php
// Start the session to access session variables
session_start();

// Check if user is logged in, redirect to login page if not
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
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get current user information
    $userId = $_SESSION['user_id'];
    $userQuery = "SELECT full_name, email FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Get doctors information with their specialties and availability
    $doctorsQuery = "
        SELECT d.doctor_id, u.full_name, d.specialty, d.qualification, d.experience_years, 
               d.availability_status, d.bio, dp.department_name
        FROM doctors d
        JOIN users u ON d.doctor_id = u.user_id
        LEFT JOIN departments dp ON d.department_id = dp.department_id
        ORDER BY d.availability_status DESC, u.full_name
    ";
    $doctorsResult = $conn->query($doctorsQuery);
    $doctors = [];
    if ($doctorsResult && $doctorsResult->num_rows > 0) {
        while ($row = $doctorsResult->fetch_assoc()) {
            $doctors[] = $row;
        }
    }

    // Close statement
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "An error occurred while fetching data. Please try again later.";
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Outfit:wght@100..900&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Reset & Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #f9fafb;
        }

        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }

        img {
            max-width: 100%;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #3A79A9;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(58, 121, 169, 0.1);
        }

        .btn:hover {
            background: #2c5d84;
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(58, 121, 169, 0.2);
        }

        .btn-secondary {
            background: #F5F5F5;
            color: #333;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            color: #000;
        }

        .btn-large {
            padding: 16px 32px;
            font-size: 18px;
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
            position: relative;
            font-size: 17px;
            font-weight: 500;
            color: #555;
            padding: 10px 0;
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

        /* Hero Section */
        .hero {
            padding: 30px 0;
        }

        .hero-container {
            background: linear-gradient(135deg, #3A79A9, #2c5d84);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(58, 121, 169, 0.2);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 60px;
        }

        .hero-content {
            flex: 1;
            color: white;
            z-index: 2;
            max-width: 600px;
        }

        .hero-title {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .hero-subtitle {
            font-size: 20px;
            font-weight: 300;
            margin-bottom: 30px;
            opacity: 0.9;
            font-family: 'Poppins', sans-serif;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
        }

        .hero-image {
            flex: 1;
            z-index: 1;
            text-align: right;
            margin-right: -40px;
            transform: translateY(20px);
        }

        .hero-image img {
            max-height: 480px;
            filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.15));
        }

        .hero-shape {
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        /* Doctors Section */
        .doctors {
            padding: 60px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-subtitle {
            color: #3A79A9;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #333;
        }

        .section-desc {
            max-width: 700px;
            margin: 0 auto;
            color: #666;
            font-size: 18px;
        }

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .doctor-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .doctor-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .doctor-card-header {
            background: linear-gradient(135deg, #3A79A9, #2c5d84);
            color: white;
            padding: 20px;
            position: relative;
            height: 140px;
        }

        .doctor-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
        }

        .doctor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .doctor-card-body {
            padding: 60px 20px 20px;
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .doctor-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }

        .doctor-specialty {
            color: #3A79A9;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .doctor-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #666;
        }

        .doctor-info-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .doctor-info-item i {
            color: #3A79A9;
            font-size: 16px;
        }

        .doctor-bio {
            margin-bottom: 20px;
            font-size: 15px;
            color: #666;
            max-height: 80px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .doctor-card-footer {
            padding: 0 20px 20px;
            margin-top: auto;
        }

        .doctor-status {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-available {
            color: #10B981;
        }

        .status-available .status-dot {
            background: #10B981;
        }

        .status-unavailable {
            color: #EF4444;
        }

        .status-unavailable .status-dot {
            background: #EF4444;
        }

        .doctor-card .btn {
            width: 100%;
        }

        /* Services Section */
        .services {
            padding: 60px 0;
            background-color: #f0f9ff;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .service-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            background: rgba(58, 121, 169, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .service-icon i {
            font-size: 36px;
            color: #3A79A9;
        }

        .service-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }

        .service-desc {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .service-link {
            color: #3A79A9;
            font-weight: 600;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
        }

        .service-link i {
            margin-left: 5px;
            transition: all 0.3s ease;
        }

        .service-link:hover i {
            transform: translateX(5px);
        }

        /* CTA Section */
        .cta {
            padding: 80px 0;
            text-align: center;
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #333;
        }

        .cta-desc {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }

        /* Footer */
        .footer {
            background: #1e3a5f;
            color: white;
            padding: 60px 0 0;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            padding-bottom: 40px;
        }

        .footer-col:first-child {
            grid-column: span 2;
        }

        .footer-logo {
            color: white;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .footer-logo i {
            margin-right: 10px;
            font-size: 26px;
        }

        .footer-desc {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            max-width: 400px;
            font-size: 15px;
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social-link {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }

        .footer-social-link:hover {
            background: #3A79A9;
            transform: translateY(-5px);
        }

        .footer-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            position: relative;
        }

        .footer-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 2px;
            background: #3A79A9;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.8);
            font-size: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .footer-link i {
            margin-right: 10px;
            font-size: 14px;
            color: #3A79A9;
        }

        .footer-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .footer-contact {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 15px;
        }

        .footer-contact-item i {
            color: #3A79A9;
            font-size: 18px;
            width: 20px;
            text-align: center;
            margin-top: 3px;
        }

        .footer-newsletter {
            margin-top: 20px;
        }

        .footer-newsletter p {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 15px;
        }

        .newsletter-form {
            display: flex;
            gap: 10px;
        }

        .newsletter-input {
            flex: 1;
            padding: 12px 15px;
            border-radius: 50px;
            border: none;
            font-size: 14px;
            outline: none;
        }

        .newsletter-btn {
            background: #3A79A9;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .newsletter-btn:hover {
            background: #2c5d84;
        }

        .copyright {
            padding: 20px 0;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .copyright a {
            color: #3A79A9;
            font-weight: 600;
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

        .animate-slideUp {
            animation: slideUp 0.8s ease forwards;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-container {
                flex-direction: column;
                padding: 40px 20px;
                text-align: center;
            }

            .hero-content {
                max-width: 100%;
                margin-bottom: 30px;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-image {
                margin-right: 0;
            }

            .hero-image img {
                max-height: 350px;
            }

            .footer-col:first-child {
                grid-column: span 1;
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

            .section-title {
                font-size: 30px;
            }

            .section-desc {
                font-size: 16px;
            }

            .doctors-grid, .services-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 32px;
            }

            .hero-subtitle {
                font-size: 18px;
            }

            .hero-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .hero-image img {
                max-height: 280px;
            }

            .footer-container {
                grid-template-columns: 1fr;
            }

            .newsletter-form {
                flex-direction: column;
            }
        }

        /* Error Message */
        .error-message {
            background: #FFEBEE;
            color: #D32F2F;
            padding: 10px;
            border-radius: 5px;
            margin: 20px auto;
            max-width: 600px;
            text-align: center;
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
                <a href="#hero" class="nav-item active-nav">Home</a>
                <a href="#doctors" class="nav-item">Doctors</a>
                <a href="#services" class="nav-item">Services</a>
                <a href="#contact" class="nav-item">Contact</a>
            </nav>
            <div class="user-section">
                <div class="user-name">
                   <a href="profile.php">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></a> 
                </div>
                <div class="user-avatar">
                    <img src="image/user-avatar.png" alt="User avatar" onerror="this.src='image/default-avatar.jpg'">
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="appointments.php" class="dropdown-item">
                            <i class="fas fa-calendar-check"></i> My Appointments
                        </a>
                        <a href="medical-records.php" class="dropdown-item">
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

    <!-- Error Message -->
    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="container">
            <div class="hero-container animate-slideUp">
                <div class="hero-content">
                    <h1 class="hero-title">Your Health Is Our Priority</h1>
                    <p class="hero-subtitle">Access quality healthcare services from top specialists in a comfortable and caring environment.</p>
                    <div class="hero-buttons">
                        <a href="book-appointment.php" class="btn btn-large">
                            <i class="far fa-calendar-plus"></i> Book Appointment
                        </a>
                        <a href="#services" class="btn btn-secondary btn-large">
                            <i class="fas fa-info-circle"></i> Our Services
                        </a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="image/c658c97ed95a94b2990dc7abe2715c96-removebg-preview.png" alt="Doctor image">
                </div>
                <div class="hero-shape"></div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section class="doctors" id="doctors">
        <div class="container">
            <div class="section-header animate-slideUp">
                <div class="section-subtitle">Medical Specialists</div>
                <h2 class="section-title">Meet Our Experienced Doctors</h2>
                <p class="section-desc">
                    Our team of qualified doctors are here to provide the best medical care with years of experience in various specialties.
                </p>
            </div>

            <div class="doctors-grid animate-slideUp">
                <?php if (empty($doctors)): ?>
                    <div style="grid-column: span 3; text-align: center; padding: 40px;">
                        <i class="fas fa-user-md" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3>No doctors available at the moment</h3>
                        <p>Please check back later or contact administration for assistance.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="doctor-card">
                            <div class="doctor-card-header">
                                <div class="doctor-avatar">
                                    <img src="image/doc<?php echo rand(1, 3); ?>.png" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>">
                                </div>
                            </div>
                            <div class="doctor-card-body">
                                <h3 class="doctor-name">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                                <div class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty'] ?? 'General Physician'); ?></div>
                                <div class="doctor-info">
                                    <div class="doctor-info-item">
                                        <i class="fas fa-graduation-cap"></i>
                                        <span><?php echo $doctor['experience_years'] ?? '5'; ?>+ Years</span>
                                    </div>
                                    <div class="doctor-info-item">
                                        <i class="fas fa-hospital"></i>
                                        <span><?php echo htmlspecialchars($doctor['department_name'] ?? 'General'); ?></span>
                                    </div>
                                </div>
                                <div class="doctor-bio">
                                    <?php echo htmlspecialchars($doctor['bio'] ?? 'A dedicated healthcare professional committed to providing excellent patient care and medical services.'); ?>
                                </div>
                            </div>
                            <div class="doctor-card-footer">
                                <div class="doctor-status <?php echo $doctor['availability_status'] ? 'status-available' : 'status-unavailable'; ?>">
                                    <span class="status-dot"></span>
                                    <span><?php echo $doctor['availability_status'] ? 'Available' : 'On Leave'; ?></span>
                                </div>
                                <a href="book-appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" class="btn">
                                    <i class="far fa-calendar-plus"></i> Book Appointment
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header animate-slideUp">
                <div class="section-subtitle">Our Services</div>
                <h2 class="section-title">Healthcare Services</h2>
                <p class="section-desc">
                    Explore our wide range of medical services designed to cater to all your healthcare needs with expertise and care.
                </p>
            </div>

            <div class="services-grid animate-slideUp">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <h3 class="service-title">Consultation</h3>
                    <p class="service-desc">Talk to a certified doctor at your convenience with secure and private video consultations tailored to your needs.</p>
                    <a href="book-appointment.php" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <h3 class="service-title">Cardiology</h3>
                    <p class="service-desc">Specialized care for heart-related conditions with advanced diagnostic and treatment options.</p>
                    <a href="book-appointment.php" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-x-ray"></i>
                    </div>
                    <h3 class="service-title">Diagnostics</h3>
                    <p class="service-desc">Comprehensive diagnostic services including imaging, lab tests, and health screenings.</p>
                    <a href="book-appointment.php" class="service-link">Learn More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="cta animate-slideUp">
                <div class="cta-container">
                    <h2 class="cta-title">Ready to Book Your Appointment?</h2>
                    <p class="cta-desc">Schedule a visit with one of our specialists today and take the first step towards better health.</p>
                    <a href="book-appointment.php" class="btn btn-large">
                        <i class="far fa-calendar-plus"></i> Book Now
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-container">
                <div class="footer-col">
                    <div class="footer-logo">
                        <i class="fas fa-heartbeat"></i>
                        Pulse Clinic
                    </div>
                    <p class="footer-desc">
                        Good health is not something we can buy. However, it can be an extremely valuable savings account.
                    </p>
                    <div class="footer-social">
                        <a href="#" class="footer-social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="footer-social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="footer-social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="footer-social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Quick Links</h3>
                    <div class="footer-links">
                        <a href="#hero" class="footer-link"><i class="fas fa-home"></i> Home</a>
                        <a href="#doctors" class="footer-link"><i class="fas fa-user-md"></i> Doctors</a>
                        <a href="#services" class="footer-link"><i class="fas fa-stethoscope"></i> Services</a>
                        <a href="#contact" class="footer-link"><i class="fas fa-envelope"></i> Contact</a>
                    </div>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Contact Info</h3>
                    <div class="footer-contact">
                        <div class="footer-contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <span>033-445-6667-77</span>
                        </div>
                        <div class="footer-contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>doctor@gmail.com</span>
                        </div>
                        <div class="footer-contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>L'adresse de Clinic, City</span>
                        </div>
                    </div>
                </div>
                <div class="footer-col">
                    <h3 class="footer-title">Newsletter</h3>
                    <div class="footer-newsletter">
                        <p>Stay updated with our latest news and offers.</p>
                        <form class="newsletter-form" method="POST" action="subscribe.php">
                            <input type="email" class="newsletter-input" placeholder="Enter your email" required>
                            <button type="submit" class="newsletter-btn"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="copyright">
            Copyright &copy; 2025 Pulse Clinic - All Rights Reserved.
        </div>
    </footer>

    <script>
        // Add active class to current navigation item
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                navItems.forEach(nav => nav.classList.remove('active-nav'));
                this.classList.add('active-nav');
            });
        });

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

        // Add animation on scroll
        const animateElements = document.querySelectorAll('.animate-slideUp');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-slideUp');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });

        animateElements.forEach(element => {
            observer.observe(element);
        });
    </script>
</body>
</html>