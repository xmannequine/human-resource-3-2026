<?php
// Set header FIRST before anything else
header('Content-Type: application/json');
header('Cache-Control: no-cache');

session_start();

try {
    require_once('config2.php');
    
    $stmt = $conn->prepare("SELECT id, firstname, lastname, face_registered, face_descriptor FROM employee WHERE face_registered = 1 ORDER BY id");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $faces = [];
    foreach($employees as $emp) {
        $descriptor = json_decode($emp['face_descriptor'], true);
        $preview = '';
        if ($descriptor && is_array($descriptor)) {
            $first10 = array_slice($descriptor, 0, 10);
            $preview = implode(', ', array_map(function($v) { return number_format($v, 4); }, $first10)) . '...';
        }
        
        $faces[] = [
            'id' => $emp['id'],
            'firstname' => $emp['firstname'],
            'lastname' => $emp['lastname'],
            'face_registered' => $emp['face_registered'],
            'descriptor_length' => $descriptor ? count($descriptor) : 0,
            'descriptor_preview' => $preview
        ];
    }
    
    echo json_encode(['status' => 'success', 'faces' => $faces]);
    
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
