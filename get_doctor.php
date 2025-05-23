<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=pulse_clinic;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $doctor_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    if (!$doctor_id) {
        echo json_encode(['error' => 'Invalid doctor ID']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT u.user_id AS doctor_id, u.full_name, u.email, u.phone_number, u.gender, u.date_of_birth, u.address,
               d.specialty, d.experience_years, d.qualification, d.bio, dep.department_name
        FROM users u
        JOIN doctors d ON u.user_id = d.doctor_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doctor) {
        echo json_encode($doctor);
    } else {
        echo json_encode(['error' => 'Doctor not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>