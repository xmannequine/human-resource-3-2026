<?php
session_start();
require_once('config.php');

header('Content-Type: application/json');

$response = [
    'logged_in' => false,
    'employee_id' => null,
    'face_registered' => false,
    'face_image_from_db' => null,
    'full_path_used' => null,
    'file_exists' => false,
    'file_size' => null,
    'uploads_dir_exists' => false
];

// Check if uploads directory exists
$response['uploads_dir_exists'] = is_dir(__DIR__ . '/uploads');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode($response);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$response['logged_in'] = true;
$response['employee_id'] = $employee_id;

try {
    $stmt = $conn->prepare("SELECT face_registered, face_image FROM employee WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $response['face_registered'] = (bool)$employee['face_registered'];
        $response['face_image_from_db'] = $employee['face_image'];

        if (!empty($employee['face_image'])) {
            // Try different path combinations
            $paths_to_try = [
                __DIR__ . '/' . $employee['face_image'],                    // If stored as: uploads/face_1_123.png
                __DIR__ . '/uploads/' . $employee['face_image'],            // If stored as: face_1_123.png
                __DIR__ . '/' . $employee['face_image'],                    // Direct path
            ];

            foreach ($paths_to_try as $path) {
                if (file_exists($path)) {
                    $response['full_path_used'] = $path;
                    $response['file_exists'] = true;
                    $response['file_size'] = filesize($path);
                    break;
                }
            }

            // If not found, show what we tried
            if (!$response['file_exists']) {
                $response['full_path_used'] = "Tried: " . implode(", ", $paths_to_try);
            }
        }
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
