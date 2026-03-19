<?php
session_start();
require_once('config2.php');

// Set JSON header FIRST
header('Content-Type: application/json');

try {
    // Get JSON from request
    $data = json_decode(file_get_contents('php://input'), true);

    if(!$data || !isset($data['descriptor'])){
        echo json_encode(['status'=>'error','message'=>'No face data received.']);
        exit;
    }

    $inputDescriptor = $data['descriptor']; // array of 128 floats

    // Verify descriptor is valid
    if (!is_array($inputDescriptor) || count($inputDescriptor) != 128) {
        echo json_encode(['status'=>'error','message'=>'Invalid face descriptor format.']);
        exit;
    }

    // Fetch all employees with registered face
    $stmt = $conn->prepare("SELECT id, firstname, lastname, face_descriptor FROM employee WHERE face_registered = 1");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        echo json_encode(['status'=>'error','message'=>'No registered faces found.']);
        exit;
    }

    $threshold = 0.55; // Face similarity threshold (Euclidean distance)
    $matchedEmployee = null;
    $bestDistance = PHP_FLOAT_MAX;
    $debugDistances = []; // For debugging

    // Compare input descriptor with each registered face
    foreach($employees as $emp){
        if(empty($emp['face_descriptor'])) continue;

        $storedDescriptor = json_decode($emp['face_descriptor'], true);
        if(!$storedDescriptor || !is_array($storedDescriptor)) continue;

        // Ensure we have 128 values
        if (count($storedDescriptor) != 128) continue;

        // Euclidean distance
        $sum = 0;
        for($i=0; $i<128; $i++){
            $diff = (float)$storedDescriptor[$i] - (float)$inputDescriptor[$i];
            $sum += $diff * $diff;
        }
        $distance = sqrt($sum);

        // Keep track of best match
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $debugDistances[] = ['emp_id' => $emp['id'], 'distance' => round($distance, 4)];
            if ($distance < $threshold) {
                $matchedEmployee = $emp;
            }
        }
    }

    if($matchedEmployee){
        // Successful login - use BUNDY session keys
        $_SESSION['bundy_employee_id'] = $matchedEmployee['id'];
        $_SESSION['bundy_employee_name'] = $matchedEmployee['firstname'] . ' ' . $matchedEmployee['lastname'];
        $_SESSION['employee_id'] = $matchedEmployee['id'];
        $_SESSION['employee_name'] = $matchedEmployee['firstname'] . ' ' . $matchedEmployee['lastname'];

        echo json_encode([
            'status'=>'success',
            'redirect'=>'bundy.php',
            'message' => 'Login successful!'
        ]);
    } else {
        echo json_encode([
            'status'=>'error',
            'message'=>'Face not recognized. Try again or use username/password.',
            'debug' => [
                'threshold' => $threshold,
                'best_distance' => round($bestDistance, 4),
                'distances_checked' => $debugDistances
            ]
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'=>'error',
        'message'=>'Server error: ' . $e->getMessage()
    ]);
}
?>