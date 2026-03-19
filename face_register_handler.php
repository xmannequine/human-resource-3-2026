<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['employee_id'])) {
    echo "Not logged in.";
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['face_image'])) {
    $faceData = $_POST['face_image'];
    $faceData = str_replace('data:image/png;base64,', '', $faceData);
    $faceData = str_replace(' ', '+', $faceData);
    $decoded = base64_decode($faceData);

    if ($decoded) {
        // Save to uploads
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filePath = $uploadDir . 'face_' . $employee_id . '.png';
        file_put_contents($filePath, $decoded);

        // Update employee table
        $stmt = $conn->prepare("UPDATE employee SET face_image=?, face_registered=1 WHERE id=?");
        $stmt->execute([$filePath, $employee_id]);

        echo "Face registered successfully!";
    } else {
        echo "Failed to decode image.";
    }
}
?>