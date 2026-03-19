<?php
session_start();
require_once('config2.php');

// Get the JSON data
$data = json_decode(file_get_contents("php://input"), true);

// Check if descriptor exists
if(!isset($data['descriptor'])){
    echo json_encode([
        'success' => false,
        'error' => 'Descriptor missing.'
    ]);
    exit;
}

// Check if employee is logged in
if(!isset($_SESSION['employee_id'])){
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in.'
    ]);
    exit;
}

$descriptor = json_encode($data['descriptor']);
$employee_id = (int)$_SESSION['employee_id'];
$imagePath = null;

// Handle face image if provided
if(isset($data['image']) && !empty($data['image'])){
    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $imagePath = $uploadDir . 'face_' . $employee_id . '_' . time() . '.png';
    
    // Remove the data:image/png;base64 part
    $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $data['image']);
    
    // Decode and save the image
    $decodedImage = base64_decode($imageData);
    if ($decodedImage === false) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to decode image.'
        ]);
        exit;
    }
    
    // Save the image file
    if (file_put_contents($imagePath, $decodedImage) === false) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to save image.'
        ]);
        exit;
    }
}

// Update employee record
try {
    if ($imagePath) {
        // Update with both descriptor and image
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
        // Update with descriptor only
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
    
    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Face registered successfully!',
            'image_saved' => ($imagePath ? true : false)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No changes made. Employee not found?'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>