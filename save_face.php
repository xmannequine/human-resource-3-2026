<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$image = $data['image'] ?? '';

if (!$image) {
    echo json_encode(['status' => 'error', 'message' => 'No image received']);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];

// Check if already registered
$stmt = $conn->prepare("SELECT face_image FROM employee WHERE id = ?");
$stmt->execute([$employee_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!empty($emp['face_image'])) {
    echo json_encode(['status' => 'error', 'message' => 'Face already registered']);
    exit;
}

// Decode image
$image = str_replace('data:image/jpeg;base64,', '', $image);
$image = base64_decode($image);

// Save file
$dir = 'uploads/faces/';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filename = $dir . 'emp_' . $employee_id . '.jpg';
file_put_contents($filename, $image);

// Save to DB
$stmt = $conn->prepare("UPDATE employee SET face_image = ? WHERE id = ?");
$stmt->execute([$filename, $employee_id]);

echo json_encode([
    'status' => 'success',
    'message' => 'Face registered successfully!'
]);
