<?php
header('Content-Type: application/json');
require_once('config2.php');

try {
    // Get current user
    session_start();
    $emp_id = $_SESSION['employee_id'] ?? null;
    
    if (!$emp_id) {
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
        exit;
    }
    
    // Clear the old enrollment for this employee
    $stmt = $conn->prepare("UPDATE employee SET face_registered = 0, face_descriptor = NULL, face_image = NULL WHERE id = :id");
    $stmt->execute([':id' => $emp_id]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Old face enrollment cleared. Please enroll again.'
    ]);
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
