<?php
session_start();

// Database connection
$host = "localhost";
$dbname = "pulse_clinic";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize variables
$errors = [];
$success = [];
$userData = [];
$medicalData = [];

// Get user data if logged in
if (isset($_SESSION['user_id'])) {
    // Get personal info from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get medical info from patient_medical_info (linked to patients table)
    $stmt = $pdo->prepare("SELECT pmi.* FROM patient_medical_info pmi 
                          JOIN patients p ON p.patient_id = pmi.patient_id 
                          WHERE p.patient_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $medicalData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Process Personal Information Form
if (isset($_POST['personal_info'])) {
    $firstName = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $dateOfBirth = filter_input(INPUT_POST, 'dateOfBirth', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
    
    // Validate inputs
    if (empty($firstName) || empty($lastName) || empty($dateOfBirth) || empty($address)) {
        $errors[] = "All personal information fields are required";
    } else {
        try {
            if (isset($_SESSION['user_id'])) {
                $fullName = $firstName . ' ' . $lastName;
                
                // Update users table
                $stmt = $pdo->prepare("UPDATE users SET 
                    full_name = :full_name,
                    date_of_birth = :dob,
                    address = :address
                    WHERE user_id = :user_id");
                
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':dob' => $dateOfBirth,
                    ':address' => $address,
                    ':user_id' => $_SESSION['user_id']
                ]);
                
                // Update or insert into patients table
                $stmt = $pdo->prepare("INSERT INTO patients (patient_id, first_name, last_name, date_of_birth, address) 
                                     VALUES (:patient_id, :first_name, :last_name, :dob, :address)
                                     ON DUPLICATE KEY UPDATE 
                                     first_name = :first_name,
                                     last_name = :last_name,
                                     date_of_birth = :dob,
                                     address = :address");
                
                $stmt->execute([
                    ':patient_id' => $_SESSION['user_id'],
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':dob' => $dateOfBirth,
                    ':address' => $address
                ]);
                
                // Update session data
                $_SESSION['full_name'] = $fullName;
                $userData['full_name'] = $fullName;
                $userData['date_of_birth'] = $dateOfBirth;
                $userData['address'] = $address;
                
                $success[] = "Personal information updated successfully!";
            } else {
                $errors[] = "User not logged in";
            }
        } catch(PDOException $e) {
            $errors[] = "Error updating personal information: " . $e->getMessage();
        }
    }
}

// Process Medical Information Form
if (isset($_POST['medical_info'])) {
    $allergies = filter_input(INPUT_POST, 'allergies', FILTER_SANITIZE_STRING);
    $medications = filter_input(INPUT_POST, 'medications', FILTER_SANITIZE_STRING);
    $conditions = filter_input(INPUT_POST, 'conditions', FILTER_SANITIZE_STRING);
    $emergency = filter_input(INPUT_POST, 'emergency', FILTER_SANITIZE_STRING);
    
    if (empty($emergency)) {
        $errors[] = "Emergency contact is required";
    } else {
        try {
            if (isset($_SESSION['user_id'])) {
                // Check if patient exists
                $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    $errors[] = "Please complete personal information first";
                } else {
                    if (!empty($medicalData)) {
                        // Update existing record
                        $stmt = $pdo->prepare("UPDATE patient_medical_info SET 
                            allergies = :allergies,
                            current_medications = :medications,
                            chronic_conditions = :conditions,
                            emergency_contact_name = :emergency,
                            last_updated = NOW()
                            WHERE patient_id = :patient_id");
                    } else {
                        // Insert new record
                        $stmt = $pdo->prepare("INSERT INTO patient_medical_info (
                            patient_id, allergies, current_medications, 
                            chronic_conditions, emergency_contact_name, last_updated
                        ) VALUES (
                            :patient_id, :allergies, :medications, 
                            :conditions, :emergency, NOW()
                        )");
                    }
                    
                    $stmt->execute([
                        ':patient_id' => $_SESSION['user_id'],
                        ':allergies' => $allergies,
                        ':medications' => $medications,
                        ':conditions' => $conditions,
                        ':emergency' => $emergency
                    ]);
                    
                    // Update local medical data
                    $medicalData = [
                        'allergies' => $allergies,
                        'current_medications' => $medications,
                        'chronic_conditions' => $conditions,
                        'emergency_contact_name' => $emergency
                    ];
                    
                    $success[] = "Medical information updated successfully!";
                }
            } else {
                $errors[] = "User not logged in";
            }
        } catch(PDOException $e) {
            $errors[] = "Error updating medical information: " . $e->getMessage();
        }
    }
}

// Process Appointment Form
if (isset($_POST['appointment_info'])) {
    $appointmentDate = filter_input(INPUT_POST, 'appointmentDate', FILTER_SANITIZE_STRING);
    $appointmentTime = filter_input(INPUT_POST, 'appointmentTime', FILTER_SANITIZE_STRING);
    $appointmentAmPm = filter_input(INPUT_POST, 'appointmentAmPm', FILTER_SANITIZE_STRING);
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    
    if (empty($appointmentDate) || empty($appointmentTime) || empty($appointmentAmPm) || !$doctor_id) {
        $errors[] = "All appointment fields, including doctor selection, are required";
    } else {
        try {
            if (isset($_SESSION['user_id'])) {
                // Validate time format
                if (!preg_match("/^(1[0-2]|[1-9]):[0-5][0-9]$/", $appointmentTime)) {
                    $errors[] = "Invalid time format. Use HH:MM";
                } else {
                    // Convert to 24-hour format for database
                    $timeParts = explode(':', $appointmentTime);
                    $hour = (int)$timeParts[0];
                    $minutes = $timeParts[1];
                    if ($appointmentAmPm === 'PM' && $hour < 12) {
                        $hour += 12;
                    } elseif ($appointmentAmPm === 'AM' && $hour == 12) {
                        $hour = 0;
                    }
                    $dbTime = sprintf("%02d:%s:00", $hour, $minutes);
                    
                    // Validate date
                    $dateObj = DateTime::createFromFormat('Y-m-d', $appointmentDate);
                    if (!$dateObj || $dateObj->format('Y-m-d') !== $appointmentDate) {
                        $errors[] = "Invalid date format";
                    } elseif ($dateObj < new DateTime()) {
                        $errors[] = "Cannot schedule appointments in the past";
                    }
                    
                    if (empty($errors)) {
                        // Check if patient exists
                        $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        if (!$stmt->fetch()) {
                            $errors[] = "Patient profile not found. Please complete personal information first.";
                        } else {
                            // Verify selected doctor exists
                            $stmt = $pdo->prepare("SELECT doctor_id FROM doctors WHERE doctor_id = ?");
                            $stmt->execute([$doctor_id]);
                            if (!$stmt->fetch()) {
                                $errors[] = "Selected doctor does not exist.";
                            } else {
                                // Check if a location exists
                                $stmt = $pdo->query("SELECT location_id FROM appointment_locations LIMIT 1");
                                $location = $stmt->fetch(PDO::FETCH_ASSOC);
                                if (!$location) {
                                    $errors[] = "No locations available. Please contact the clinic.";
                                } else {
                                    $location_id = $location['location_id'];
                                    
                                    // Check if status exists
                                    $stmt = $pdo->query("SELECT status_id FROM appointment_statuses WHERE status_id = 1");
                                    if (!$stmt->fetch()) {
                                        $errors[] = "Appointment status not configured. Please contact the clinic.";
                                    } else {
                                        // Insert appointment
                                        $stmt = $pdo->prepare("INSERT INTO enhanced_appointments (
                                            patient_id, doctor_id, location_id, appointment_date, 
                                            appointment_time, status_id
                                        ) VALUES (
                                            :patient_id, :doctor_id, :location_id, :appointment_date,
                                            :appointment_time, 1
                                        )");
                                        
                                        $stmt->execute([
                                            ':patient_id' => $_SESSION['user_id'],
                                            ':doctor_id' => $doctor_id,
                                            ':location_id' => $location_id,
                                            ':appointment_date' => $appointmentDate,
                                            ':appointment_time' => $dbTime
                                        ]);
                                        
                                        $success[] = "Appointment scheduled successfully!";
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $errors[] = "User not logged in";
            }
        } catch(PDOException $e) {
            $errors[] = "Error scheduling appointment: " . $e->getMessage();
        }
    }
}

// Split full name into first and last names
$firstName = '';
$lastName = '';
if (!empty($userData['full_name'])) {
    $nameParts = explode(' ', $userData['full_name']);
    $firstName = $nameParts[0] ?? '';
    $lastName = implode(' ', array_slice($nameParts, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse-Clinic - Patient Portal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&family=Outfit:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            background: white;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 40px;
            border-bottom: 1px solid #e5e7eb;
        }

        .logo {
            color: #1f2937;
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .logo:hover {
            color: #3a79a9;
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-img:hover {
            transform: scale(1.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            padding: 20px 40px;
            gap: 15px;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(58, 121, 169, 0.3);
        }

        .user-name {
            color: #1f2937;
            font-size: 24px;
            font-family: 'Outfit', sans-serif;
            font-weight: 500;
        }

        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 20px 40px;
        }

        .section-title {
            color: #1f2937;
            font-size: 20px;
            font-weight: 600;
            margin: 20px 40px 10px;
        }

        .form-section {
            padding: 0 40px;
        }

        .input-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .input-field {
            flex: 1;
            min-width: 200px;
            height: 40px;
            border: 1px solid #3a79a9;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: #2c5d80;
            box-shadow: 0 0 0 2px rgba(58, 121, 169, 0.2);
        }

        .input-field::placeholder {
            color: #9ca3af;
        }

        .input-field:disabled {
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .wider-field {
            flex: 2;
            min-width: 300px;
        }

        .btn {
            height: 40px;
            padding: 0 20px;
            background: #3a79a9;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-family: 'Outfit', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn:hover {
            background: #2c5d80;
            transform: translateY(-1px);
        }

        .btn:disabled {
            background: #6b7280;
            cursor: not-allowed;
        }

        .date-time-section {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .date-field {
            min-width: 150px;
        }

        .hour-field {
            min-width: 100px;
        }

        .ampm-field {
            min-width: 80px;
        }

        .form-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease forwards;
        }

        .notification.success {
            background: #22c55e;
        }

        .notification.error {
            background: #ef4444;
        }

        .notification.info {
            background: #3b82f6;
        }

        .error-box, .success-box {
            margin: 20px 40px;
            padding: 15px;
            border-radius: 6px;
            font-size: 14px;
        }

        .error-box {
            background: #fee2e2;
            color: #b91c1c;
        }

        .success-box {
            background: #dcfce7;
            color: #15803d;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 10px;
            }

            header, .user-profile, .form-section {
                padding: 10px 20px;
            }

            .input-row {
                flex-direction: column;
            }

            .input-field, .wider-field {
                min-width: 100%;
            }
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="afterlogin.php" class="logo">Pulse-Clinic</a>
            <img class="profile-img" src="image/8200435763dd55fbba824456fe180d76.jpg" alt="User profile">
        </header>

        <div class="user-profile">
            <img class="user-avatar" src="image/8200435763dd55fbba824456fe180d76.jpg" alt="User avatar">
            <div class="user-name">
                <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Guest User'; ?>
            </div>
        </div>
        <div class="divider"></div>

        <?php if (!empty($errors)): ?>
            <div class="error-box">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-box">
                <?php foreach ($success as $msg): ?>
                    <p><?php echo htmlspecialchars($msg); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section-container">
            <h2 class="section-title">Personal Information</h2>
            <form class="form-section" method="post">
                <div class="input-row">
                    <input type="text" class="input-field" name="firstName" placeholder="First Name" required 
                        value="<?php echo htmlspecialchars($firstName); ?>">
                    <input type="text" class="input-field" name="lastName" placeholder="Last Name" required 
                        value="<?php echo htmlspecialchars($lastName); ?>">
                    <input type="date" class="input-field" name="dateOfBirth" required 
                        value="<?php echo !empty($userData['date_of_birth']) ? htmlspecialchars($userData['date_of_birth']) : ''; ?>">
                </div>
                <div class="form-actions">
                    <input type="text" class="input-field wider-field" name="address" placeholder="Address" required 
                        value="<?php echo !empty($userData['address']) ? htmlspecialchars($userData['address']) : ''; ?>">
                    <button type="submit" name="personal_info" class="btn">Save</button>
                </div>
            </form>
        </div>
        <div class="divider"></div>

        <div class="section-container">
            <h2 class="section-title">Medical Information</h2>
            <form class="form-section" method="post">
                <div class="input-row">
                    <input type="text" class="input-field" name="allergies" placeholder="Allergies" 
                        value="<?php echo !empty($medicalData['allergies']) ? htmlspecialchars($medicalData['allergies']) : ''; ?>">
                    <input type="text" class="input-field" name="medications" placeholder="Current Medications" 
                        value="<?php echo !empty($medicalData['current_medications']) ? htmlspecialchars($medicalData['current_medications']) : ''; ?>">
                    <input type="text" class="input-field" name="conditions" placeholder="Medical Conditions" 
                        value="<?php echo !empty($medicalData['chronic_conditions']) ? htmlspecialchars($medicalData['chronic_conditions']) : ''; ?>">
                </div>
                <div class="form-actions">
                    <input type="text" class="input-field wider-field" name="emergency" placeholder="Emergency Contact" required 
                        value="<?php echo !empty($medicalData['emergency_contact_name']) ? htmlspecialchars($medicalData['emergency_contact_name']) : ''; ?>">
                    <button type="submit" name="medical_info" class="btn">Save</button>
                </div>
            </form>
        </div>
        <div class="divider"></div>

        <div class="section-container">
            <h2 class="section-title">Schedule Appointment</h2>
            <form class="form-section" method="post">
                <div class="input-row">
                    <select class="input-field" name="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php
                        $stmt = $pdo->query("SELECT d.doctor_id, u.full_name 
                                           FROM doctors d 
                                           JOIN users u ON d.doctor_id = u.user_id");
                        while ($doctor = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$doctor['doctor_id']}'>" . htmlspecialchars($doctor['full_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-actions">
                    <div class="date-time-section">
                        <input type="date" class="input-field date-field" name="appointmentDate" required>
                        <input type="text" class="input-field hour-field" name="appointmentTime" placeholder="HH:MM" required 
                            pattern="^(1[0-2]|[1-9]):[0-5][0-9]$" title="Use HH:MM format (e.g., 9:30)">
                        <select class="input-field ampm-field" name="appointmentAmPm">
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    <button type="submit" name="appointment_info" class="btn">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            const notificationContainer = document.createElement('div');
            document.body.appendChild(notificationContainer);

            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const inputs = form.querySelectorAll('.input-field');
                    let isValid = true;

                    inputs.forEach(input => {
                        if (input.required && !input.value.trim()) {
                            isValid = false;
                            input.style.borderColor = '#ef4444';
                        } else if (input.pattern && !new RegExp(input.pattern).test(input.value)) {
                            isValid = false;
                            input.style.borderColor = '#ef4444';
                        } else {
                            input.style.borderColor = '#3a79a9';
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        showNotification('Please fill all required fields correctly', 'error');
                    }
                });
            });

            function showNotification(message, type) {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.textContent = message;
                notificationContainer.appendChild(notification);

                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>