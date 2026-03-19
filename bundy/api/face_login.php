<?php
header('Content-Type: application/json');
session_start();
require_once('../config2.php'); // adjust path to your config

$data = json_decode(file_get_contents("php://input"), true);

if(!isset($data['descriptor'])){
    echo json_encode(["status"=>"error","message"=>"No descriptor provided"]);
    exit;
}

$login_descriptor = $data['descriptor'];

// Fetch all employees with registered descriptors
$stmt = $conn->query("SELECT id, firstname, lastname, face_descriptor FROM employee WHERE face_descriptor IS NOT NULL");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($employees as $emp){
    $stored = json_decode($emp['face_descriptor'], true);
    $sum = 0;
    for($i=0;$i<count($login_descriptor);$i++){
        $sum += pow($login_descriptor[$i] - $stored[$i],2);
    }
    $distance = sqrt($sum);

    if($distance < 0.6){ // threshold
        // Successful login
        $_SESSION['bundy_employee_id']=$emp['id'];
        $_SESSION['bundy_employee_name']=$emp['firstname']." ".$emp['lastname'];

        echo json_encode([
            "status"=>"success",
            "employee_id"=>$emp['id'],
            "employee_name"=>$emp['firstname']." ".$emp['lastname']
        ]);
        exit;
    }
}

// Face not recognized
echo json_encode([
    "status"=>"error",
    "message"=>"Face not recognized"
]);
?>