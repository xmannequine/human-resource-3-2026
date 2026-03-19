<?php
header('Content-Type: application/json');
require_once('config2.php');

try {
    // Check what's in the database
    $stmt = $conn->prepare("SELECT id, firstname, lastname, face_registered, face_descriptor FROM employee WHERE face_registered = 1");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $diagnosis = [];
    
    foreach($employees as $emp) {
        $desc = $emp['face_descriptor'];
        
        // Check format
        $decoded = json_decode($desc, true);
        $isValid = false;
        $length = 0;
        $format = 'unknown';
        
        if ($decoded) {
            $length = count($decoded);
            if ($length === 128) {
                $isValid = true;
                $format = 'CORRECT (128 values)';
            } else if ($length > 100 && $length < 150) {
                $format = 'Possibly old landmarks (' . $length . ' values)';
            } else {
                $format = 'Invalid (' . $length . ' values)';
            }
        }
        
        $diagnosis[] = [
            'id' => $emp['id'],
            'name' => $emp['firstname'] . ' ' . $emp['lastname'],
            'registered' => $emp['face_registered'] == 1 ? 'YES' : 'NO',
            'descriptor_format' => $format,
            'valid_for_matching' => $isValid ? 'YES ✅' : 'NO ❌',
            'data_snippet' => substr($desc, 0, 100) . '...'
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'total_enrolled' => count($diagnosis),
        'valid_faces' => array_filter($diagnosis, fn($d) => strpos($d['valid_for_matching'], '✅') !== false),
        'faces' => $diagnosis
    ]);
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
