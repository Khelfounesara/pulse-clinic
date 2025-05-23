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

    $userId = $_SESSION['user_id'];

    // Fetch user data
    $userQuery = "SELECT full_name, email FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Map user_id to patient_id
    $patientQuery = "SELECT patient_id FROM patients WHERE email = :email";
    $patientStmt = $conn->prepare($patientQuery);
    $patientStmt->execute(['email' => $user['email']]);
    $patient = $patientStmt->fetch();

    if (!$patient) {
        throw new Exception("No patient profile found for this user.");
    }

    $patientId = $patient['patient_id'];

    // Fetch upcoming appointments from enhanced_appointments table
    $upcomingQuery = "
        SELECT ea.appointment_id, ea.appointment_date, ea.appointment_time, 
               sts.name AS status, u.full_name AS doctor_name, d.specialty, d.doctor_id
        FROM enhanced_appointments ea
        JOIN doctors d ON ea.doctor_id = d.doctor_id
        JOIN users u ON d.doctor_id = u.user_id
        JOIN appointment_statuses sts ON ea.status_id = sts.status_id
        WHERE ea.patient_id = :patient_id AND ea.appointment_date >= CURDATE()
        ORDER BY ea.appointment_date ASC
    ";
    $upcomingStmt = $conn->prepare($upcomingQuery);
    $upcomingStmt->execute(['patient_id' => $patientId]);
    $upcomingAppointments = $upcomingStmt->fetchAll();

    // Fetch past appointments from enhanced_appointments table
    $pastQuery = "
        SELECT ea.appointment_id, ea.appointment_date, ea.appointment_time, 
               sts.name AS status, u.full_name AS doctor_name, d.specialty, d.doctor_id
        FROM enhanced_appointments ea
        JOIN doctors d ON ea.doctor_id = d.doctor_id
        JOIN users u ON d.doctor_id = u.user_id
        JOIN appointment_statuses sts ON ea.status_id = sts.status_id
        WHERE ea.patient_id = :patient_id AND ea.appointment_date < CURDATE()
        ORDER BY ea.appointment_date DESC
    ";
    $pastStmt = $conn->prepare($pastQuery);
    $pastStmt->execute(['patient_id' => $patientId]);
    $pastAppointments = $pastStmt->fetchAll();

    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    // Handle appointment updates for enhanced_appointments table
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['csrf_token'])) {
        if ($_POST['csrf_token'] !== $csrf_token) {
            throw new Exception("Invalid CSRF token");
        }

        if ($_POST['action'] === 'edit') {
            $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
          $date = filter_input(INPUT_POST, 'appointment_date', FILTER_DEFAULT);
          $time = filter_input(INPUT_POST, 'appointment_time', FILTER_DEFAULT);

            // Validate inputs
            if (!$appointmentId || !$date || !$time) {
                throw new Exception("All fields are required");
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < time()) {
                throw new Exception("Invalid or past date");
            }
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                throw new Exception("Invalid time format");
            }

            $updateQuery = "UPDATE enhanced_appointments SET 
                            appointment_date = :date, 
                            appointment_time = :time 
                            WHERE appointment_id = :appointment_id AND patient_id = :patient_id";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->execute([
                'date' => $date,
                'time' => $time,
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId
            ]);

            $success = "Appointment updated successfully!";

            // Refresh appointments
            $upcomingStmt->execute(['patient_id' => $patientId]);
            $upcomingAppointments = $upcomingStmt->fetchAll();

        } elseif ($_POST['action'] === 'cancel') {
            $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
            $reason = filter_input(INPUT_POST, 'cancel_reason', FILTER_SANITIZE_STRING) ?? '';

            if (!$appointmentId) {
                throw new Exception("Invalid appointment ID");
            }

            // Update status_id to cancelled (status_id = 4)
            $cancelQuery = "UPDATE enhanced_appointments SET 
                            status_id = 4, 
                            notes = CONCAT(IFNULL(notes, ''), :reason) 
                            WHERE appointment_id = :appointment_id AND patient_id = :patient_id";
            $cancelStmt = $conn->prepare($cancelQuery);
            $cancelStmt->execute([
                'reason' => "Cancellation reason: " . $reason,
                'appointment_id' => $appointmentId,
                'patient_id' => $patientId
            ]);

            $success = "Appointment canceled successfully!";

            // Refresh appointments
            $upcomingStmt->execute(['patient_id' => $patientId]);
            $upcomingAppointments = $upcomingStmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    error_log("Database error in myappointment.php: " . $e->getMessage());
    $error = "An error occurred: " . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    error_log("Error in myappointment.php: " . $e->getMessage());
    $error = $e->getMessage();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - My Appointments</title>
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
            font-size: 15px;
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

        /* Main Content */
        .section-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .status-indicator {
            font-weight: 600;
            margin-left: 8px;
            text-transform: capitalize;
        }

        .status-pending {
            color: #3A79A9;
        }

        .status-cancelled {
            color: #EF4444;
        }

        .status-confirmed {
            color: #10B981;
        }

        .status-completed {
            color: #4B5563;
        }

        .appointments-list {
            width: 100%;
            animation: slideUp 0.8s ease forwards;
        }

        .appointment-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.3s ease;
            background: white;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .appointment-card:hover {
            background: #f8fafc;
        }

        .doctor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .doctor-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .appointment-details {
            flex: 1;
            margin: 0 20px;
        }

        .doctor-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .appointment-time {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }

        .appointment-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            background: #3A79A9;
            color: white;
            border: none;
            border-radius: 20px;
            font-size: 14px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            background: #2c5d84;
            transform: translateY(-1px);
        }

        .action-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .records-btn {
            background: #10B981;
        }

        .records-btn:hover {
            background: #059669;
        }

        .divider {
            width: 100%;
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }

        .form-input, .form-select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-input[readonly] {
            background: #f5f5f5;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-cancel {
            background: #ADADAD;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            font-size: 16px;
            color: #666;
        }

        .close-btn:hover {
            color: #333;
        }

        .error-message, .success-message {
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: center;
            font-size: 14px;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
        }

        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
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

            .appointment-card {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }

            .appointment-details {
                margin: 10px 0;
            }

            .appointment-actions {
                flex-direction: column;
                width: 100%;
                gap: 8px;
            }

            .action-btn {
                width: 100%;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .doctor-avatar {
                width: 50px;
                height: 50px;
            }

            .section-title {
                font-size: 18px;
            }

            .modal-content {
                padding: 15px;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container container">
            <a href="Home.html" class="logo">
                <i class="fas fa-heartbeat"></i> Pulse Clinic
            </a>
            <nav class="nav-menu">
                <a href="afterlogin.php" class="nav-item">Home</a>
                <a href="all_doctors.php" class="nav-item">Doctors</a>
                <a href="myappointment.php" class="nav-item active-nav">My Appointments</a>
            </nav>
            <div class="user-section">
                <div class="user-name">
                    Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?>
                </div>
                <div class="user-avatar">
                    <a href="profile.php">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? 'User'); ?></a> 
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
        <!-- Messages -->
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Upcoming Appointments -->
        <div class="section-title">
            Upcoming Appointments
            <?php if (!empty($upcomingAppointments)): ?>
                <span class="status-indicator status-<?php echo htmlspecialchars(strtolower($upcomingAppointments[0]['status'])); ?>">
                    <?php
                    $date = new DateTime($upcomingAppointments[0]['appointment_date']);
                    $now = new DateTime();
                    $interval = $now->diff($date);
                    echo $interval->days . ' days left';
                    ?>
                </span>
            <?php else: ?>
                <span class="status-indicator status-pending">None scheduled</span>
            <?php endif; ?>
        </div>

        <div class="appointments-list">
            <?php if (!empty($upcomingAppointments)): ?>
                <?php foreach ($upcomingAppointments as $appt): ?>
                    <div class="appointment-card" 
                         data-id="<?php echo $appt['appointment_id']; ?>" 
                         data-date="<?php echo htmlspecialchars($appt['appointment_date']); ?>" 
                         data-time="<?php echo htmlspecialchars($appt['appointment_time']); ?>">
                        <a href="doctor-profile.php?doctor_id=<?php echo $appt['doctor_id']; ?>" class="doctor-link">
                            <img class="doctor-avatar" 
                                 src="image/doctor-avatar.jpg" 
                                 alt="Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?>" 
                                 onerror="this.src='https://placehold.co/60x60'">
                        </a>
                        <div class="appointment-details">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?> (<?php echo htmlspecialchars($appt['specialty'] ?? 'General'); ?>)</div>
                            <div class="appointment-time"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?>, <?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-actions">
                            <button class="action-btn edit-btn" 
                                    data-id="<?php echo $appt['appointment_id']; ?>" 
                                    <?php echo $appt['status'] === 'cancelled' ? 'disabled' : ''; ?>>Edit</button>
                            <button class="action-btn cancel-btn" 
                                    data-id="<?php echo $appt['appointment_id']; ?>" 
                                    <?php echo $appt['status'] === 'cancelled' ? 'disabled' : ''; ?>>Cancel</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No upcoming appointments scheduled.</p>
            <?php endif; ?>
        </div>

        <div class="divider"></div>

        <!-- Appointment History -->
        <div class="section-title">Appointment History</div>

        <div class="appointments-list">
            <?php if (!empty($pastAppointments)): ?>
                <?php foreach ($pastAppointments as $appt): ?>
                    <div class="appointment-card">
                        <a href="doctor-profile.php?doctor_id=<?php echo $appt['doctor_id']; ?>" class="doctor-link">
                            <img class="doctor-avatar" 
                                 src="image/doctor-avatar.jpg" 
                                 alt="Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?>" 
                                 onerror="this.src='https://placehold.co/60x60'">
                        </a>
                        <div class="appointment-details">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?> (<?php echo htmlspecialchars($appt['specialty'] ?? 'General'); ?>)</div>
                            <div class="appointment-time"><?php echo date('h:i A', strtotime($appt['appointment_time'])); ?>, <?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></div>
                        </div>
                        <div class="appointment-actions">
                            <a href="medicalrecords.php?appointment_id=<?php echo $appt['appointment_id']; ?>" 
                               class="action-btn records-btn">View Records</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 20px;">No past appointments found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <span class="close-btn" id="closeEditModal">×</span>
            <div class="modal-title">Edit Appointment</div>
            <form class="modal-form" id="editForm" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="appointment_id" id="editAppointmentId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="form-group">
                    <label class="form-label">Doctor</label>
                    <input type="text" class="form-input" id="editDoctorName" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-input" name="appointment_date" id="appointmentDate" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Time</label>
                    <select class="form-select" name="appointment_time" id="appointmentTime" required>
                        <option value="08:00:00">8:00 AM</option>
                        <option value="09:00:00">9:00 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="13:00:00">1:00 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="15:00:00">3:00 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                    </select>
                </div>
                <div class="form-buttons">
                    <button type="button" class="action-btn btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="action-btn">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <span class="close-btn" id="closeCancelModal">×</span>
            <div class="modal-title">Cancel Appointment</div>
            <form class="modal-form" id="cancelForm" method="post">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <p>Are you sure you want to cancel your appointment with <span id="cancelDoctorName"></span> on <span id="appointmentDateText"></span> at <span id="appointmentTimeText"></span>?</p>
                <div class="form-group">
                    <label class="form-label">Reason for cancellation (optional)</label>
                    <textarea class="form-input" name="cancel_reason" rows="3" placeholder="Provide a reason (optional)"></textarea>
                </div>
                <div class="form-buttons">
                    <button type="button" class="action-btn btn-cancel" id="backBtn">Back</button>
                    <button type="submit" class="action-btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-btn');
            const cancelButtons = document.querySelectorAll('.cancel-btn');
            const editModal = document.getElementById('editModal');
            const cancelModal = document.getElementById('cancelModal');
            const closeEditModal = document.getElementById('closeEditModal');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const closeCancelModal = document.getElementById('closeCancelModal');
            const backBtn = document.getElementById('backBtn');

            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const card = this.closest('.appointment-card');
                    const appointmentId = card.dataset.id;
                    const doctorName = card.querySelector('.doctor-name').textContent;
                    const date = card.dataset.date;
                    const time = card.dataset.time;

                    document.getElementById('editAppointmentId').value = appointmentId;
                    document.getElementById('editDoctorName').value = doctorName;
                    document.getElementById('appointmentDate').value = date;
                    document.getElementById('appointmentTime').value = time;

                    editModal.style.display = 'flex';
                });
            });

            cancelButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const card = this.closest('.appointment-card');
                    const appointmentId = card.dataset.id;
                    const doctorName = card.querySelector('.doctor-name').textContent;
                    const date = card.dataset.date;
                    const time = card.querySelector('.appointment-time').textContent;
                    const dateObj = new Date(date);
                    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

                    document.getElementById('cancelAppointmentId').value = appointmentId;
                    document.getElementById('cancelDoctorName').textContent = doctorName;
                    document.getElementById('appointmentDateText').textContent = dateObj.toLocaleDateString('en-US', options);
                    document.getElementById('appointmentTimeText').textContent = time;

                    cancelModal.style.display = 'flex';
                });
            });

            function closeModals() {
                editModal.style.display = 'none';
                cancelModal.style.display = 'none';
            }

            closeEditModal.addEventListener('click', closeModals);
            cancelEditBtn.addEventListener('click', closeModals);
            closeCancelModal.addEventListener('click', closeModals);
            backBtn.addEventListener('click', closeModals);

            window.addEventListener('click', function(event) {
                if (event.target === editModal || event.target === cancelModal) {
                    closeModals();
                }
            });

            // Validate date input
            const dateInput = document.getElementById('appointmentDate');
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate < today) {
                    alert('Please select a future date.');
                    this.value = '';
                }
            });

            // Set active nav
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-item').forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active-nav');
                }
            });
        });
    </script>
</body>
</html>