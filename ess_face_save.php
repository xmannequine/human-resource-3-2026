<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data['descriptor'])){
    echo json_encode(['success' => false, 'error' => 'Descriptor missing']);
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
$descriptor = $data['descriptor'];
$imagePath = null;

// Save face image if provided
if(isset($data['image']) && !empty($data['image'])){
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $imagePath = $uploadDir . 'face_' . $employee_id . '_' . time() . '.png';
    $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $data['image']);
    $decodedImage = base64_decode($imageData);
    
    if ($decodedImage === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to decode image']);
        exit;
    }
    
    if (file_put_contents($imagePath, $decodedImage) === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to save image']);
        exit;
    }
}

// Update employee record
try {
    if ($imagePath) {
        $stmt = $conn->prepare("
            UPDATE employee 
            SET face_descriptor = :descriptor,
                face_registered = 1,
                face_image = :image
            WHERE id = :id
        ");
        
        $stmt->execute([
            ":descriptor" => $descriptor,
            ":image" => $imagePath,
            ":id" => $employee_id
        ]);
    } else {
        $stmt = $conn->prepare("
            UPDATE employee 
            SET face_descriptor = :descriptor,
                face_registered = 1
            WHERE id = :id
        ");
        
        $stmt->execute([
            ":descriptor" => $descriptor,
            ":id" => $employee_id
        ]);
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Face registered successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update employee record'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
