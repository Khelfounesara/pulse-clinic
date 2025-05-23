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
    $userQuery = "SELECT full_name, email, phone, address FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    // Fetch medical records
    $recordsQuery = "SELECT document_id, file_path, upload_date 
                    FROM medical_documents 
                    WHERE patient_id = :user_id 
                    ORDER BY upload_date DESC";
    $recordsStmt = $conn->prepare($recordsQuery);
    $recordsStmt->execute(['user_id' => $userId]);
    $records = $recordsStmt->fetchAll();

    // Fetch medications
    $medsQuery = "SELECT medication_name, dosage, frequency, cause, notes 
                 FROM patient_medications 
                 WHERE patient_id = :user_id AND is_active = 1";
    $medsStmt = $conn->prepare($medsQuery);
    $medsStmt->execute(['user_id' => $userId]);
    $medications = $medsStmt->fetchAll();

    // Fetch conditions
    $conditionsQuery = "SELECT condition_name, notes 
                       FROM patient_conditions 
                       WHERE patient_id = :user_id AND status = 'active'";
    $conditionsStmt = $conn->prepare($conditionsQuery);
    $conditionsStmt->execute(['user_id' => $userId]);
    $conditions = $conditionsStmt->fetchAll();

    // Fetch doctors
    $doctorsQuery = "SELECT d.doctor_id, u.full_name, d.specialty 
                    FROM patient_doctors pd
                    JOIN doctors d ON pd.doctor_id = d.doctor_id
                    JOIN users u ON d.user_id = u.user_id
                    WHERE pd.patient_id = :user_id AND pd.is_active = 1";
    $doctorsStmt = $conn->prepare($doctorsQuery);
    $doctorsStmt->execute(['user_id' => $userId]);
    $doctors = $doctorsStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in medicalhistory.php: " . $e->getMessage());
    $error = "An error occurred while fetching medical history. Please try again later.";
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse Clinic - Medical History</title>
    <!-- Rest of head section remains the same -->
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo" onclick="window.location.href='afterlogin.php'">Pulse Clinic</div>
            <div class="user-avatar">
                <img src="image/user-avatar.png" alt="Profile" onclick="window.location.href='profile.php'">
            </div>
        </div>

        <!-- Main Content -->
        <div class="profile-section">
            <img class="profile-large" src="image/user-avatar.png" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
            <div class="username"><?php echo htmlspecialchars($user['full_name']); ?></div>
            
            <!-- Contact Information -->
            <div class="contact-info">
                <div class="contact-title">CONTACT INFORMATION</div>
                <div class="contact-row">
                    <div class="contact-label">Email:</div>
                    <div class="contact-value">
                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="contact-link">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-label">Phone:</div>
                    <div class="contact-value">
                        <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="contact-link">
                            <?php echo htmlspecialchars($user['phone']); ?>
                        </a>
                    </div>
                </div>
                <div class="contact-row">
                    <div class="contact-label">Address:</div>
                    <div class="contact-value"><?php echo nl2br(htmlspecialchars($user['address'])); ?></div>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Records Section -->
        <div class="section-title">Records</div>

        <div class="upload-container">
            <form action="upload.php" method="post" enctype="multipart/form-data">        
                <label class="upload-btn">
                    <span class="upload-text">Upload Records</span>
                    <input id="fileUpload" class="upload-input" type="file" name="file1" accept="image/*,.pdf" hidden>
                </label>
            </form>
        </div>
        
        <div class="records-container" id="recordsContainer">
            <?php foreach ($records as $record): ?>
                <div class="record-card" onclick="window.open('<?php echo htmlspecialchars($record['file_path']); ?>')">
                    <img src="<?php echo strpos($record['file_path'], '.pdf') !== false ? 'image/pdf-icon.jpg' : htmlspecialchars($record['file_path']); ?>" 
                         alt="Medical Record">
                    <div class="record-footer">
                        <span>Date: <?php echo date('d/m/Y', strtotime($record['upload_date'])); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Medications Section -->
        <div class="section-title">My Medications:</div>
        <div class="med-table-container">
            <table class="med-table">
                <tr>
                    <th>Name</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Cause</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($medications as $med): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                        <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                        <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                        <td><?php echo htmlspecialchars($med['cause']); ?></td>
                        <td><?php echo htmlspecialchars($med['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Illnesses Section -->
        <div class="section-title">Conditions:</div>
        <?php foreach ($conditions as $condition): ?>
            <div class="illness-card">
                <h3><?php echo htmlspecialchars($condition['condition_name']); ?></h3>
                <p><?php echo htmlspecialchars($condition['notes']); ?></p>
            </div>
        <?php endforeach; ?>

        <!-- Doctors Section -->
        <div class="section-title">Doctors:</div>
        <div class="doctors-container">
            <?php foreach ($doctors as $doctor): ?>
                <img class="doctor-avatar" 
                     src="image/doc<?php echo $doctor['doctor_id'] % 4 + 1; ?>.png" 
                     alt="<?php echo htmlspecialchars($doctor['full_name']); ?>"
                     onclick="window.location.href='doctor-profile.php?id=<?php echo $doctor['doctor_id']; ?>'">
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // File upload handling
        document.getElementById('fileUpload').addEventListener('change', function (event) {
            const file = event.target.files[0];
            if (!file) return;
        
            const formData = new FormData();
            formData.append('file', file);
            formData.append('patient_id', <?php echo $userId; ?>);
            
            fetch('upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Refresh to show new record
                } else {
                    alert('Error uploading file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading file');
            });
        });
    </script>
</body>
</html>