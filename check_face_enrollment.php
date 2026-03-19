<?php
session_start();
require_once('config.php');

header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'employee_id' => null,
    'name' => null,
    'face_registered' => false,
    'descriptor_length' => 0,
    'face_image' => null
];

if (!isset($_SESSION['employee_id'])) {
    echo json_encode($response);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$response['logged_in'] = true;
$response['employee_id'] = $employee_id;

try {
    $stmt = $conn->prepare("
        SELECT firstname, lastname, face_registered, face_descriptor, face_image 
        FROM employee 
        WHERE id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $response['name'] = $employee['firstname'] . ' ' . $employee['lastname'];
        $response['face_registered'] = (bool)$employee['face_registered'];
        $response['descriptor_length'] = strlen($employee['face_descriptor'] ?? '');
        $response['face_image'] = $employee['face_image'];
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
