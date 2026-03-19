<?php
header('Content-Type: application/json');
session_start();
require_once('../config2.php');

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data['descriptor'])){
    echo json_encode(["status"=>"error","message"=>"No descriptor provided"]);
    exit;
}

if(!isset($_SESSION['employee_id'])){
    echo json_encode(["status"=>"error","message"=>"Not logged in"]);
    exit;
}

// Forward to save_descriptor.php logic
$descriptor = json_encode($data['descriptor']);
$employee_id = $_SESSION['employee_id'];

$stmt = $conn->prepare("
    UPDATE employee 
    SET face_descriptor = :descriptor,
        face_registered = 1
    WHERE id = :id
");

$stmt->execute([
    ":descriptor"=>$descriptor,
    ":id"=>$employee_id
]);

echo json_encode(["status"=>"success","message"=>"Face registered successfully"]);
?>