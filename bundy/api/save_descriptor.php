<?php
require_once('../config.php');
$data = json_decode(file_get_contents('php://input'), true);
$employee_id = (int)$data['employee_id'];
$descriptor = $data['descriptor'] ?? null;

if($employee_id && $descriptor){
    $descriptor_json = json_encode($descriptor);
    $stmt = $conn->prepare("UPDATE employee SET face_descriptor=?, face_registered=1 WHERE id=?");
    $stmt->execute([$descriptor_json, $employee_id]);
    echo json_encode(['success'=>true]);
}else{
    echo json_encode(['success'=>false,'message'=>'Invalid data']);
}
?>