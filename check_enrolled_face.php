<?php
session_start();
require_once('config.php');

header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'employee_id' => null,
    'employee_name' => null,
    'face_registered' => false,
    'face_image_db' => null,
    'face_image_full_path' => null,
    'face_image_exists' => false,
    'face_image_size' => null,
    'face_image_preview' => null,
    'descriptor_exists' => false,
    'descriptor_length' => 0,
    'descriptor_preview' => null
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
        SELECT firstname, lastname, face_registered, face_image, face_descriptor 
        FROM employee 
        WHERE id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $response['employee_name'] = $employee['firstname'] . ' ' . $employee['lastname'];
        $response['face_registered'] = (bool)$employee['face_registered'];

        // Check face image
        if (!empty($employee['face_image'])) {
            $response['face_image_db'] = $employee['face_image'];
            
            // Determine full path
            $imagePath = $employee['face_image'];
            if (strpos($imagePath, 'uploads/') === 0) {
                $fullPath = __DIR__ . '/' . $imagePath;
                $webPath = $imagePath;
            } else {
                $fullPath = __DIR__ . '/uploads/' . $imagePath;
                $webPath = 'uploads/' . $imagePath;
            }
            
            $response['face_image_full_path'] = $fullPath;
            
            if (file_exists($fullPath)) {
                $response['face_image_exists'] = true;
                $response['face_image_size'] = filesize($fullPath);
                $response['face_image_preview'] = $webPath;
            }
        }

        // Check descriptor
        if (!empty($employee['face_descriptor'])) {
            $response['descriptor_exists'] = true;
            $response['descriptor_length'] = strlen($employee['face_descriptor']);
            $response['descriptor_preview'] = $employee['face_descriptor'];
        }
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
