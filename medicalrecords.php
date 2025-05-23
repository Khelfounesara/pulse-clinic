<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pulse_clinic";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $userId = $_SESSION['user_id'];

    // Fetch user data
    $userQuery = "SELECT full_name, email, role FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found.");
    }

    // Only patients should access medical records
    if ($user['role'] !== 'patient') {
        $error = "Access denied. Only patients can view medical records.";
        $records = [];
    } else {
        // Fetch medical records directly using user_id
        $recordsQuery = "SELECT mr.record_id, mr.diagnosis, mr.treatment, mr.record_date, 
                                d.user_id AS doctor_id, d.full_name AS doctor_name
                        FROM medicalrecords mr
                        JOIN doctors doc ON mr.doctor_id = doc.doctor_id
                        JOIN users d ON doc.doctor_id = d.user_id
                        WHERE mr.patient_id = :user_id 
                        ORDER BY mr.record_date DESC";
        $recordsStmt = $conn->prepare($recordsQuery);
        $recordsStmt->execute(['user_id' => $userId]);
        $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($records) === 0) {
            $error = "No medical records found.";
        }
    }

} catch (PDOException $e) {
    error_log("Database error in medicalrecords.php: " . $e->getMessage());
    $error = "An error occurred while fetching data. Please try again later.";
} catch (Exception $e) {
    $error = $e->getMessage();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - Medical Records</title>
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
        }

        .container {
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
            position: relative;
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

        /* Medical Records Section */
        .medical-records {
            margin-top: 40px;
        }

        .medical-records-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .medical-records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .medical-records-header h2 {
            color: #3A79A9;
            font-size: 24px;
            font-weight: 600;
        }

        .medical-record {
            background-color: #f8f9fa;
            border-left: 4px solid #3A79A9;
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .medical-record:hover {
            transform: translateX(10px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .medical-record-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .medical-record-date {
            color: #6c757d;
            font-size: 14px;
        }

        .medical-record-doctor {
            color: #3A79A9;
            font-weight: 500;
        }

        .medical-record-details h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .medical-record-details p {
            color: #666;
            line-height: 1.6;
        }

        .doctor-id {
            font-size: 13px;
            color: #888;
            margin-left: 5px;
        }

        .error-message {
            background: #FFEBEE;
            color: #D32F2F;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .medical-record {
                padding: 15px;
            }

            .medical-records-header h2 {
                font-size: 20px;
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
                    <img src="image/user-avatar.jpg" alt="User avatar" onerror="this.src='image/user-avatar.png'">
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
        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Medical Records Section -->
        <div class="medical-records">
            <div class="medical-records-container">
                <div class="medical-records-header">
                    <h2>Medical Records</h2>
                </div>

                <?php if (!empty($records)): ?>
                    <?php foreach ($records as $record): ?>
                        <div class="medical-record">
                            <div class="medical-record-header">
                                <div class="medical-record-date">
                                    <?php echo htmlspecialchars(date('d/m/Y', strtotime($record['record_date']))); ?>
                                </div>
                                <div class="medical-record-doctor">
                                    Dr. <?php echo htmlspecialchars($record['doctor_name']); ?>
                                    <span class="doctor-id">(ID: <?php echo htmlspecialchars($record['doctor_id']); ?>)</span>
                                </div>
                            </div>
                            <div class="medical-record-details">
                                <h3>Diagnosis</h3>
                                <p><?php echo htmlspecialchars($record['diagnosis'] ?? 'No diagnosis available'); ?></p>
                                <h3>Treatment</h3>
                                <p><?php echo htmlspecialchars($record['treatment'] ?? 'No treatment available'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif (!isset($error)): ?>
                    <p style="text-align: center; color: #666;">No medical records available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Your existing JavaScript remains unchanged// Smooth scroll for anchor links
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