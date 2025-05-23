<?php
session_start();

// Check if user is logged in and has doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
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
    if (isset($_POST['update_status'])) {
        // Update Appointment Status
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
        $status_id = filter_input(INPUT_POST, 'status_id', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $pdo->prepare("UPDATE enhanced_appointments SET status_id = ? WHERE appointment_id = ?");
            $stmt->execute([$status_id, $appointment_id]);
            $success = "Appointment status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating status: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_medical_record'])) {
        // Add Medical Record
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_NUMBER_INT);
        $diagnosis = filter_input(INPUT_POST, 'diagnosis', FILTER_SANITIZE_STRING);
        $treatment = filter_input(INPUT_POST, 'treatment', FILTER_SANITIZE_STRING);
        $record_date = filter_input(INPUT_POST, 'record_date', FILTER_SANITIZE_STRING);
        try {
            // Fetch patient_id and doctor_id from appointment
            $stmt = $pdo->prepare("SELECT patient_id, doctor_id FROM enhanced_appointments WHERE appointment_id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                $stmt = $pdo->prepare("INSERT INTO medicalrecords (patient_id, doctor_id, diagnosis, treatment, record_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$appointment['patient_id'], $appointment['doctor_id'], $diagnosis, $treatment, $record_date]);
                $success = "Medical record added successfully!";
            } else {
                $error = "Invalid appointment ID.";
            }
        } catch (PDOException $e) {
            $error = "Error adding medical record: " . $e->getMessage();
        }
    }
}

// Fetch doctor info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.full_name, u.email FROM users u WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch doctor's appointments
$stmt = $pdo->prepare("
    SELECT ea.appointment_id, p.first_name, p.last_name, ea.appointment_date, ea.appointment_time, al.name AS location_name, al.room, s.status_id, s.name AS status_name
    FROM enhanced_appointments ea
    JOIN patients p ON ea.patient_id = p.patient_id
    JOIN appointment_locations al ON ea.location_id = al.location_id
    JOIN appointment_statuses s ON ea.status_id = s.status_id
    WHERE ea.doctor_id = ?
    ORDER BY ea.appointment_date DESC, ea.appointment_time DESC
");
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all possible statuses for the dropdown
$stmt = $pdo->query("SELECT status_id, name FROM appointment_statuses");
$statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic Doctor Dashboard</title>
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

        .doctor-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doctor-info {
            text-align: right;
        }

        .doctor-name {
            font-size: 16px;
            font-weight: 500;
        }

        .doctor-email {
            font-size: 13px;
            color: #666;
        }

        .doctor-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #3A79A9;
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

        /* User Avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
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
            .doctor-info {
                display: none;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        @media (max-width: 576px) {
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
        <div class="doctor-profile">
            <div class="doctor-info">
                <div class="doctor-name"><?php echo htmlspecialchars($doctor['full_name']); ?></div>
                <div class="doctor-email"><?php echo htmlspecialchars($doctor['email']); ?></div>
            </div>
            <div class="doctor-avatar"><?php echo strtoupper(substr($doctor['full_name'], 0, 2)); ?></div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-heartbeat"></i>
                <span>Pulse Clinic</span>
            </div>
            <a class="menu-item active" href="#" data-section="appointments">
                <i class="fas fa-calendar-alt"></i>
                <span>Appointments</span>
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

            <!-- Appointments Section -->
            <div class="content-section active" id="appointments-section">
                <div class="section-header">
                    <h2 class="section-title">My Appointments</h2>
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
                            <option value="cancelled">Cancelled</option>
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
                                    <td><?php echo date('M d, Y - h:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($appt['location_name'] . ($appt['room'] ? ', Room ' . $appt['room'] : '')); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['appointment_id']; ?>">
                                            <select name="status_id" class="form-control" style="width: auto; display: inline-block;">
                                                <?php foreach ($statuses as $status): ?>
                                                    <option value="<?php echo $status['status_id']; ?>" <?php echo $status['status_id'] == $appt['status_id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($status['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary btn-sm" style="margin-left: 5px;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success btn-sm add-medical-record" data-id="<?php echo $appt['appointment_id']; ?>">
                                                <i class="fas fa-file-medical"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Medical Record Modal -->
    <div class="modal" id="addMedicalRecordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Medical Record</h3>
                <button class="modal-close" id="closeAddMedicalRecordModal">×</button>
            </div>
            <div class="modal-body">
                <form id="addMedicalRecordForm" method="POST">
                    <input type="hidden" name="appointment_id" id="medical_record_appointment_id">
                    <div class="form-group">
                        <label class="form-label">Diagnosis</label>
                        <textarea class="form-control" name="diagnosis" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Treatment</label>
                        <textarea class="form-control" name="treatment" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Record Date</label>
                        <input type="date" class="form-control" name="record_date" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-danger" id="cancelAddMedicalRecord">Cancel</button>
                        <button type="submit" name="add_medical_record" class="btn btn-success">Save Record</button>
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

        // Modal Handling
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Add Medical Record Modal
        const addMedicalRecordModal = document.getElementById('addMedicalRecordModal');
        const closeAddMedicalRecordModal = document.getElementById('closeAddMedicalRecordModal');
        const cancelAddMedicalRecord = document.getElementById('cancelAddMedicalRecord');

        closeAddMedicalRecordModal.addEventListener('click', () => closeModal('addMedicalRecordModal'));
        cancelAddMedicalRecord.addEventListener('click', () => closeModal('addMedicalRecordModal'));

        document.querySelectorAll('.add-medical-record').forEach(btn => {
            btn.addEventListener('click', () => {
                const appointmentId = btn.getAttribute('data-id');
                document.getElementById('medical_record_appointment_id').value = appointmentId;
                addMedicalRecordModal.classList.add('active');
            });
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