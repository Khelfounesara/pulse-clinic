<?php
// Database connection
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "pulse_clinic";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Add Doctor Function
function addDoctor($data) {
    global $conn;
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // First, add to users table
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, phone_number, gender, date_of_birth, address) 
                               VALUES (:full_name, :email, :password_hash, 'doctor', :phone, :gender, :dob, :address)");
        
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $full_name = $data['first_name'] . ' ' . $data['last_name'];
        
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':gender', $data['gender']);
        $stmt->bindParam(':dob', $data['dob']);
        $stmt->bindParam(':address', $data['address']);
        
        $stmt->execute();
        $user_id = $conn->lastInsertId();
        
        // Then add to doctors table
        $stmt = $conn->prepare("INSERT INTO doctors (doctor_id, specialty, experience_years, qualification, bio, department_id) 
                               VALUES (:doctor_id, :specialty, :experience, :qualification, :bio, 
                               (SELECT department_id FROM departments WHERE department_name = :department))");
        
        $stmt->bindParam(':doctor_id', $user_id);
        $stmt->bindParam(':specialty', $data['specialty']);
        $stmt->bindParam(':experience', $data['experience']);
        $stmt->bindParam(':qualification', $data['qualification']);
        $stmt->bindParam(':bio', $data['bio']);
        $stmt->bindParam(':department', $data['department']);
        
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true, 'message' => 'Doctor added successfully!'];
    } catch(PDOException $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Get Doctors List
function getDoctorsList($search = '', $specialty = '') {
    global $conn;
    
    try {
        $sql = "SELECT u.user_id, u.full_name, u.email, u.phone_number, u.gender, 
                       d.specialty, d.experience_years, d.qualification, 
                       dep.department_name, d.availability_status
                FROM users u
                JOIN doctors d ON u.user_id = d.doctor_id
                LEFT JOIN departments dep ON d.department_id = dep.department_id
                WHERE u.role = 'doctor'";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (u.full_name LIKE :search OR u.email LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if (!empty($specialty)) {
            $sql .= " AND d.specialty = :specialty";
            $params[':specialty'] = $specialty;
        }
        
        $sql .= " ORDER BY u.full_name";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_doctor':
                $response = addDoctor($_POST);
                echo json_encode($response);
                break;
                
            case 'get_doctors':
                $search = $_POST['search'] ?? '';
                $specialty = $_POST['specialty'] ?? '';
                $doctors = getDoctorsList($search, $specialty);
                echo json_encode($doctors);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
    }
    exit;
}
?>