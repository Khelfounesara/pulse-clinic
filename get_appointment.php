<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=pulse_clinic;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $appointment_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
    if (!$appointment_id) {
        echo json_encode(['error' => 'Invalid appointment ID']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT ea.appointment_id, p.first_name, p.last_name, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               u.full_name AS doctor_name, ea.appointment_date, ea.appointment_time, 
               al.name AS location_name, al.room, s.name AS status_name, 
               ea.reason_for_visit, ea.notes
        FROM enhanced_appointments ea
        JOIN patients p ON ea.patient_id = p.patient_id
        JOIN users u ON ea.doctor_id = u.user_id
        JOIN appointment_locations al ON ea.location_id = al.location_id
        JOIN appointment_statuses s ON ea.status_id = s.status_id
        WHERE ea.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($appointment) {
        echo json_encode($appointment);
    } else {
        echo json_encode(['error' => 'Appointment not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>