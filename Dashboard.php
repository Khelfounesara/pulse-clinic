<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=pulse_clinic;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_doctor'])) {
        // Add Doctor
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $dob = filter_input(INPUT_POST, 'dob', FILTER_SANITIZE_STRING);
        $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $specialty = filter_input(INPUT_POST, 'specialty', FILTER_SANITIZE_STRING);
        $experience = filter_input(INPUT_POST, 'experience', FILTER_SANITIZE_NUMBER_INT);
        $qualification = filter_input(INPUT_POST, 'qualification', FILTER_SANITIZE_STRING);
        $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
        $bio = filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_STRING);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        try {
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, phone_number, gender, date_of_birth, address) VALUES (?, ?, ?, 'doctor', ?, ?, ?, ?)");
            $stmt->execute(["$first_name $last_name", $email, $password, $phone, $gender, $dob, $address]);
            $user_id = $pdo->lastInsertId();

            // Get department_id
            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ?");
            $stmt->execute([$department]);
            $department_id = $stmt->fetchColumn();

            if (!$department_id) {
                $stmt = $pdo->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $stmt->execute([$department]);
                $department_id = $pdo->lastInsertId();
            }

            // Insert into doctors table
            $stmt = $pdo->prepare("INSERT INTO doctors (doctor_id, specialty, experience_years, qualification, bio, department_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $specialty, $experience, $qualification, $bio, $department_id]);

            $success = "Doctor added successfully!";
        } catch (PDOException $e) {
            $error = "Error adding doctor: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_doctor'])) {
        // Delete Doctor
        $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE doctor_id = ?");
            $stmt->execute([$doctor_id]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$doctor_id]);
            $success = "Doctor deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting doctor: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_patient'])) {
        // Delete Patient
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
            $stmt->execute([$patient_id]);
            $success = "Patient deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting patient: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_appointment'])) {
        // Delete Appointment
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $pdo->prepare("DELETE FROM enhanced_appointments WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $success = "Appointment deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting appointment: " . $e->getMessage();
        }
    }
}

// Fetch data for dashboard
// Total Patients
$stmt = $pdo->query("SELECT COUNT(*) FROM patients");
$total_patients = $stmt->fetchColumn();

// Active Doctors
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors WHERE availability_status = 1");
$active_doctors = $stmt->fetchColumn();

// Upcoming Appointments
$stmt = $pdo->query("SELECT COUNT(*) FROM enhanced_appointments WHERE is_upcoming = 1");
$upcoming_appointments = $stmt->fetchColumn();

// Recent Appointments
$stmt = $pdo->query("
    SELECT ea.appointment_id, p.first_name, p.last_name, u.full_name AS doctor_name, ea.appointment_date, ea.appointment_time, al.name AS location_name, al.room, s.name AS status_name
    FROM enhanced_appointments ea
    JOIN patients p ON ea.patient_id = p.patient_id
    JOIN users u ON ea.doctor_id = u.user_id
    JOIN appointment_locations al ON ea.location_id = al.location_id
    JOIN appointment_statuses s ON ea.status_id = s.status_id
    ORDER BY ea.appointment_date DESC, ea.appointment_time DESC
    LIMIT 5
");
$recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Patients
$stmt = $pdo->query("
    SELECT patient_id, first_name, last_name, email, phone, gender, date_of_birth, (YEAR(CURDATE()) - YEAR(date_of_birth)) AS age, updated_at AS last_visit
    FROM patients
    ORDER BY last_visit DESC
    LIMIT 5
");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Doctors
$stmt = $pdo->query("
    SELECT d.doctor_id, u.full_name, d.specialty, d.experience_years, d.qualification, d.availability_status
    FROM doctors d
    JOIN users u ON d.doctor_id = u.user_id
    ORDER BY u.full_name
    LIMIT 5
");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Appointments
$stmt = $pdo->query("
    SELECT ea.appointment_id, p.first_name, p.last_name, u.full_name AS doctor_name, ea.appointment_date, ea.appointment_time, al.name AS location_name, al.room, s.name AS status_name
    FROM enhanced_appointments ea
    JOIN patients p ON ea.patient_id = p.patient_id
    JOIN users u ON ea.doctor_id = u.user_id
    JOIN appointment_locations al ON ea.location_id = al.location_id
    JOIN appointment_statuses s ON ea.status_id = s.status_id
    ORDER BY ea.appointment_date DESC, ea.appointment_time DESC
    LIMIT 5
");
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin Info
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: #333;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
            background-color: white;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: #3A79A9;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-info {
            text-align: right;
        }

        .admin-name {
            font-size: 16px;
            font-weight: 500;
        }

        .admin-email {
            font-size: 13px;
            color: #666;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #3A79A9;
            object-fit: cover;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3A79A9;
            font-weight: bold;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #3A79A9;
            margin-right: 15px;
        }

        /* Main Layout */
        .container {
            display: flex;
            flex-grow: 1;
            position: relative;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: #3A79A9;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            transition: transform 0.3s ease;
            z-index: 200;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 0 20px 20px;
            font-size: 22px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header i {
            font-size: 26px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 15px;
            border-left: 3px solid transparent;
        }

        .menu-item:hover,
        .menu-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid white;
        }

        .menu-item i {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex-grow: 1;
            padding: 30px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            background: #f5f7fa;
            min-height: calc(100vh - 69px);
        }

        /* Content Sections */
        .content-section {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #3A79A9;
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .nav-tab {
            padding: 10px 20px;
            border-radius: 50px;
            border: 1px solid #3A79A9;
            color: #333;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-tab:hover {
            background: rgba(58, 121, 169, 0.1);
        }

        .nav-tab.active {
            background: #3A79A9;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Dashboard Stats */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .stat-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: #3A79A9;
            margin-bottom: 5px;
        }

        .stat-change {
            font-size: 12px;
            color: #4CAF50;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.negative {
            color: #F44336;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th {
            background: #3A79A9;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
        }

        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #f5f7fa;
        }

        .table-responsive {
            overflow-x: auto;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background: #E3F2FD;
            color: #1976D2;
        }

        .badge-success {
            background: #E8F5E9;
            color: #388E3C;
        }

        .badge-warning {
            background: #FFF8E1;
            color: #FFA000;
        }

        .badge-danger {
            background: #FFEBEE;
            color: #D32F2F;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 13px;
        }

        .btn-primary {
            background: #3A79A9;
            color: white;
        }

        .btn-primary:hover {
            background: #2c5d82;
        }

        .btn-success {
            background: #34D399;
            color: white;
        }

        .btn-success:hover {
            background: #10B981;
        }

        .btn-danger {
            background: #F87171;
            color: white;
        }

        .btn-danger:hover {
            background: #EF4444;
        }

        .btn i {
            font-size: 16px;
        }

        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3A79A9;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #3A79A9;
        }

        /* User Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3A79A9;
            font-weight: bold;
        }

        .user-avatar.sm {
            width: 30px;
            height: 30px;
            font-size: 12px;
        }

        .user-avatar.lg {
            width: 80px;
            height: 80px;
            font-size: 24px;
        }

        /* Search and Filter */
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-select {
            min-width: 200px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: block;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #3A79A9;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f5f7fa;
        }

        .page-item.active .page-link {
            background: #3A79A9;
            color: white;
            border-color: #3A79A9;
        }

        .page-item.disabled .page-link {
            color: #999;
            pointer-events: none;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 150;
                display: none;
            }
            
            .overlay.active {
                display: block;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .admin-info {
                display: none;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        @media (max-width: 576px) {
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tab {
                text-align: center;
            }
            
            .search-filter {
                flex-direction: column;
                gap: 10px;
            }

            .search-box, .filter-select {
                min-width: 100%;
            }

            .btn span {
                display: none;
            }

            .data-table td, .data-table th {
                padding: 8px 10px;
                font-size: 13px;
            }
        }

        /* Utility Classes */
        .d-flex {
            display: flex;
        }
        
        .align-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #999;
        }

        .mt-3 {
            margin-top: 15px;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        .w-100 {
            width: 100%;
        }

        /* Animations */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #3A79A9;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Messages */
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 14px;
        }

        .message.success {
            background: #E8F5E9;
            color: #388E3C;
        }

        .message.error {
            background: #FFEBEE;
            color: #D32F2F;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Header -->
    <header class="header">
        <div class="d-flex align-center">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">Pulse Clinic</div>
        </div>
        <div class="admin-profile">
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                <div class="admin-email"><?php echo htmlspecialchars($admin['email']); ?></div>
            </div>
            <div class="admin-avatar"><?php echo strtoupper(substr($admin['full_name'], 0, 2)); ?></div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-heartbeat"></i>
                <span>Pulse Clinic</span>
            </div>
            <a class="menu-item active" href="#" data-section="dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a class="menu-item" href="#" data-section="patients">
                <i class="fas fa-users"></i>
                <span>Patients</span>
            </a>
            <a class="menu-item" href="#" data-section="doctors">
                <i class="fas fa-user-md"></i>
                <span>Doctors</span>
            </a>
            <a class="menu-item" href="#" data-section="appointments">
                <i class="fas fa-calendar-alt"></i>
                <span>Appointments</span>
            </a>
            <a class="menu-item" href="#" data-section="add-doctor">
                <i class="fas fa-user-plus"></i>
                <span>Add Doctor</span>
            </a>
            <div style="margin-top: auto; padding: 20px;">
                <button class="btn btn-danger w-100" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($success)): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Dashboard Section -->
            <div class="content-section active" id="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Dashboard Overview</h2>
                    <div class="d-flex align-center gap-3">
                        <div class="text-muted">Last updated: <?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-title">Total Patients</div>
                        <div class="stat-value"><?php echo $total_patients; ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i>
                            <!-- Placeholder for dynamic data -->
                            12% from last month
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Active Doctors</div>
                        <div class="stat-value"><?php echo $active_doctors; ?></div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i>
                            3 new this month
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Upcoming Appointments</div>
                        <div class="stat-value"><?php echo $upcoming_appointments; ?></div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            5% from last week
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">Revenue</div>
                        <div class="stat-value">$84,320</div>
                        <div class="stat-change">
                            <i class="fas fa-arrow-up"></i>
                            18% from last month
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Appointments</h3>
                        <a href="#" class="btn btn-primary btn-sm" data-section="appointments">
                            <i class="fas fa-calendar-alt"></i>
                            <span>View All</span>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table w-100">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appt): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center gap-2">
                                                <div class="user-avatar sm"><?php echo strtoupper(substr($appt['first_name'], 0, 1) . substr($appt['last_name'], 0, 1)); ?></div>
                                                <div><?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                                        <td><?php echo date('M d, Y - h:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])); ?></td>
                                        <td><span class="badge badge-<?php echo strtolower($appt['status_name']) === 'completed' ? 'success' : (strtolower($appt['status_name']) === 'pending' ? 'warning' : 'primary'); ?>"><?php echo htmlspecialchars($appt['status_name']); ?></span></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm view-appointment" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Patients Section -->
            <div class="content-section" id="patients-section">
                <div class="section-header">
                    <h2 class="section-title">Patients Management</h2>
                    <div class="search-filter">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" placeholder="Search patients...">
                        </div>
                        <select class="filter-select">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table w-100">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td>#PT<?php echo sprintf("%04d", $patient['patient_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-2">
                                            <div class="user-avatar sm"><?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?></div>
                                            <div><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($patient['gender'])); ?></td>
                                    <td><?php echo $patient['age']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($patient['last_visit'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm view-patient" data-id="<?php echo $patient['patient_id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-success btn-sm edit-patient" data-id="<?php echo $patient['patient_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this patient?');">
                                                <input type="hidden" name="patient_id" value="<?php echo $patient['patient_id']; ?>">
                                                <button type="submit" name="delete_patient" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </div>
            </div>

            <!-- Doctors Section -->
            <div class="content-section" id="doctors-section">
                <div class="section-header">
                    <h2 class="section-title">Doctors List</h2>
                    <div class="search-filter">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" placeholder="Search doctors...">
                        </div>
                        <select class="filter-select">
                            <option value="">All Specialties</option>
                            <option value="cardiology">Cardiology</option>
                            <option value="neurology">Neurology</option>
                            <option value="orthopedics">Orthopedics</option>
                        </select>
                        <button class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table w-100">
                        <thead>
                            <tr>
                                <th>Doctor ID</th>
                                <th>Name</th>
                                <th>Specialty</th>
                                <th>Experience</th>
                                <th>Qualification</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td>#DR<?php echo sprintf("%04d", $doctor['doctor_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-2">
                                            <div class="user-avatar sm"><?php echo strtoupper(substr($doctor['full_name'], 0, 2)); ?></div>
                                            <div><?php echo htmlspecialchars($doctor['full_name']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td><?php echo htmlspecialchars($doctor['qualification']); ?></td>
                                    <td><span class="badge badge-<?php echo $doctor['availability_status'] ? 'success' : 'warning'; ?>"><?php echo $doctor['availability_status'] ? 'Active' : 'On Leave'; ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm view-doctor" data-id="<?php echo $doctor['doctor_id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-success btn-sm edit-doctor" data-id="<?php echo $doctor['doctor_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this doctor?');">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['doctor_id']; ?>">
                                                <button type="submit" name="delete_doctor" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </div>
            </div>

            <!-- Appointments Section -->
            <div class="content-section" id="appointments-section">
                <div class="section-header">
                    <h2 class="section-title">Appointments Management</h2>
                    <div class="search-filter">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" placeholder="Search appointments...">
                        </div>
                        <select class="filter-select">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                        </select>
                        <button class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            <span>Filter</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table w-100">
                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td>#APT<?php echo sprintf("%04d", $appt['appointment_id']); ?></td>
                                    <td>
                                        <div class="d-flex align-center gap-2">
                                            <div class="user-avatar sm"><?php echo strtoupper(substr($appt['first_name'], 0, 1) . substr($appt['last_name'], 0, 1)); ?></div>
                                            <div><?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appt['doctor_name']); ?></td>
                                    <td><?php echo date('M d, Y - h:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($appt['location_name'] . ', Room ' . $appt['room']); ?></td>
                                    <td><span class="badge badge-<?php echo strtolower($appt['status_name']) === 'completed' ? 'success' : (strtolower($appt['status_name']) === 'pending' ? 'warning' : 'primary'); ?>"><?php echo htmlspecialchars($appt['status_name']); ?></span></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm view-appointment" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-success btn-sm edit-appointment" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this appointment?');">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                                <button type="submit" name="delete_appointment" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Previous</a>
                    </li>
                    <li class="page-item active">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">Next</a>
                    </li>
                </div>
            </div>

            <!-- Add Doctor Section -->
            <div class="content-section" id="add-doctor-section">
                <div class="section-header">
                    <h2 class="section-title">Add New Doctor</h2>
                </div>

                <form id="addDoctorForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="add_doctor" value="1">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select class="form-control" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-control" name="address" required>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Specialty</label>
                                <select class="form-control" name="specialty" required>
                                    <option value="">Select Specialty</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Dermatology">Dermatology</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" name="experience" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <input type="text" class="form-control" name="qualification" placeholder="MD, PhD, etc." required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select class="form-control" name="department" required>
                            <option value="">Select Department</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Neurology">Neurology</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Pediatrics">Pediatrics</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Biography</label>
                        <textarea class="form-control" name="bio" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Profile Image</label>
                        <input type="file" class="form-control" name="profile_image" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn btn-danger">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i>
                            <span>Add Doctor</span>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- View Doctor Modal -->
    <div class="modal" id="viewDoctorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Doctor Details</h3>
                <button class="modal-close" id="closeViewDoctorModal">×</button>
            </div>
            <div class="modal-body" id="doctorDetails">
                <!-- Populated via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="closeViewDoctorModalBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- View Patient Modal -->
    <div class="modal" id="viewPatientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Patient Details</h3>
                <button class="modal-close" id="closeViewPatientModal">×</button>
            </div>
            <div class="modal-body" id="patientDetails">
                <!-- Populated via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="closeViewPatientModalBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- View Appointment Modal -->
    <div class="modal" id="viewAppointmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Appointment Details</h3>
                <button class="modal-close" id="closeViewAppointmentModal">×</button>
            </div>
            <div class="modal-body" id="appointmentDetails">
                <!-- Populated via AJAX -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="closeViewAppointmentModalBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div class="modal" id="editDoctorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Doctor</h3>
                <button class="modal-close" id="closeEditDoctorModal">×</button>
            </div>
            <div class="modal-body">
                <form id="editDoctorForm" method="POST">
                    <input type="hidden" name="doctor_id" id="edit_doctor_id">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" id="edit_phone" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Specialty</label>
                                <select class="form-control" name="specialty" id="edit_specialty" required>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Neurology">Neurology</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Dermatology">Dermatology</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Years of Experience</label>
                                <input type="number" class="form-control" name="experience" id="edit_experience" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <input type="text" class="form-control" name="qualification" id="edit_qualification" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Biography</label>
                        <textarea class="form-control" name="bio" id="edit_bio" rows="4"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-danger" id="cancelEditDoctor">Cancel</button>
                        <button type="submit" name="update_doctor" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        menuToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Navigation between sections
        const menuItems = document.querySelectorAll('.menu-item');
        const contentSections = document.querySelectorAll('.content-section');

        menuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const section = this.getAttribute('data-section');

                menuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                contentSections.forEach(section => section.classList.remove('active'));
                document.getElementById(`${section}-section`).classList.add('active');

                if (window.innerWidth < 992) {
                    toggleSidebar();
                }
            });
        });

        // Navigation tabs
        const navTabs = document.querySelectorAll('.nav-tab');

        navTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                navTabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
            });
        });

        // Modal Handling
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // View Doctor Modal
        const viewDoctorModal = document.getElementById('viewDoctorModal');
        const closeViewDoctorModal = document.getElementById('closeViewDoctorModal');
        const closeViewDoctorModalBtn = document.getElementById('closeViewDoctorModalBtn');

        closeViewDoctorModal.addEventListener('click', () => closeModal('viewDoctorModal'));
        closeViewDoctorModalBtn.addEventListener('click', () => closeModal('viewDoctorModal'));

        document.querySelectorAll('.view-doctor').forEach(btn => {
            btn.addEventListener('click', async () => {
                const doctorId = btn.getAttribute('data-id');
                const response = await fetch(`get_doctor.php?id=${doctorId}`);
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('doctorDetails').innerHTML = `
                    <div class="text-center mb-4">
                        <div class="user-avatar lg mb-2">${data.full_name.substring(0, 2).toUpperCase()}</div>
                        <h3>${data.full_name}</h3>
                        <p class="text-muted">${data.specialty}</p>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <p>${data.email}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <p>${data.phone_number}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <p>${data.date_of_birth}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <p>${data.gender.charAt(0).toUpperCase() + data.gender.slice(1)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <p>${data.address}</p>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Specialty</label>
                                <p>${data.specialty}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Experience</label>
                                <p>${data.experience_years} years</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Qualifications</label>
                        <p>${data.qualification}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <p>${data.department_name}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Biography</label>
                        <p>${data.bio || 'No biography provided'}</p>
                    </div>
                `;
                viewDoctorModal.classList.add('active');
            });
        });

        // View Patient Modal
        const viewPatientModal = document.getElementById('viewPatientModal');
        const closeViewPatientModal = document.getElementById('closeViewPatientModal');
        const closeViewPatientModalBtn = document.getElementById('closeViewPatientModalBtn');

        closeViewPatientModal.addEventListener('click', () => closeModal('viewPatientModal'));
        closeViewPatientModalBtn.addEventListener('click', () => closeModal('viewPatientModal'));

        document.querySelectorAll('.view-patient').forEach(btn => {
            btn.addEventListener('click', async () => {
                const patientId = btn.getAttribute('data-id');
                const response = await fetch(`get_patient.php?id=${patientId}`);
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('patientDetails').innerHTML = `
                    <div class="text-center mb-4">
                        <div class="user-avatar lg mb-2">${(data.first_name[0] + data.last_name[0]).toUpperCase()}</div>
                        <h3>${data.first_name} ${data.last_name}</h3>
                        <p class="text-muted">Patient ID: #PT${String(data.patient_id).padStart(4, '0')}</p>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <p>${data.email}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <p>${data.phone}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <p>${data.date_of_birth}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <p>${data.gender.charAt(0).toUpperCase() + data.gender.slice(1)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <p>${data.address}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medical History</label>
                        <p>${data.medical_history || 'No medical history provided'}</p>
                    </div>
                `;
                viewPatientModal.classList.add('active');
            });
        });

        // View Appointment Modal
        const viewAppointmentModal = document.getElementById('viewAppointmentModal');
        const closeViewAppointmentModal = document.getElementById('closeViewAppointmentModal');
        const closeViewAppointmentModalBtn = document.getElementById('closeViewAppointmentModalBtn');

        closeViewAppointmentModal.addEventListener('click', () => closeModal('viewAppointmentModal'));
        closeViewAppointmentModalBtn.addEventListener('click', () => closeModal('viewAppointmentModal'));

        document.querySelectorAll('.view-appointment').forEach(btn => {
            btn.addEventListener('click', async () => {
                const appointmentId = btn.getAttribute('data-id');
                const response = await fetch(`get_appointment.php?id=${appointmentId}`);
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('appointmentDetails').innerHTML = `
                    <div class="text-center mb-4">
                        <h3>Appointment #APT${String(data.appointment_id).padStart(4, '0')}</h3>
                        <p class="text-muted">Status: ${data.status_name}</p>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Patient</label>
                                <p>${data.patient_name}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Doctor</label>
                                <p>${data.doctor_name}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Date & Time</label>
                                <p>${new Date(data.appointment_date + ' ' + data.appointment_time).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}</p>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <p>${data.location_name}, Room ${data.room}</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Visit</label>
                        <p>${data.reason_for_visit || 'Not specified'}</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <p>${data.notes || 'No notes provided'}</p>
                    </div>
                `;
                viewAppointmentModal.classList.add('active');
            });
        });

        // Edit Doctor Modal
        const editDoctorModal = document.getElementById('editDoctorModal');
        const closeEditDoctorModal = document.getElementById('closeEditDoctorModal');
        const cancelEditDoctor = document.getElementById('cancelEditDoctor');

        closeEditDoctorModal.addEventListener('click', () => closeModal('editDoctorModal'));
        cancelEditDoctor.addEventListener('click', () => closeModal('editDoctorModal'));

        document.querySelectorAll('.edit-doctor').forEach(btn => {
            btn.addEventListener('click', async () => {
                const doctorId = btn.getAttribute('data-id');
                const response = await fetch(`get_doctor.php?id=${doctorId}`);
                const data = await response.json();
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('edit_doctor_id').value = data.doctor_id;
                document.getElementById('edit_first_name').value = data.full_name.split(' ')[0];
                document.getElementById('edit_last_name').value = data.full_name.split(' ').slice(1).join(' ');
                document.getElementById('edit_email').value = data.email;
                document.getElementById('edit_phone').value = data.phone_number;
                document.getElementById('edit_specialty').value = data.specialty;
                document.getElementById('edit_experience').value = data.experience_years;
                document.getElementById('edit_qualification').value = data.qualification;
                document.getElementById('edit_bio').value = data.bio || '';
                editDoctorModal.classList.add('active');
            });
        });

        // Add Doctor Form Submission
        const addDoctorForm = document.getElementById('addDoctorForm');

        addDoctorForm.addEventListener('submit', function(e) {
            const password = this.elements.password.value;
            const confirmPassword = this.elements.confirm_password.value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });

        // Logout Button
        const logoutBtn = document.getElementById('logoutBtn');

        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });
    </script>
</body>
</html>