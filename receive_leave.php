<?php
/*
|-------------------------------------------------------------
| HR Leave Receiver - receive_leave.php
|-------------------------------------------------------------
| Receives leave requests from ESS and stores in DB
*/

// CONFIG
$SHARED_SECRET = 'ESS_TO_HR_2026'; // Must match ESS side

// DATABASE CONNECTION - change to your own
$host = 'localhost';
$db   = 'hr3_hr3_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB Connection Error";
    exit;
}

// CHECK POST METHOD
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// GET POST DATA
$employee_id = $_POST['employee_id'] ?? '';
$leave_type  = $_POST['leave_type'] ?? '';
$leave_date  = $_POST['leave_date'] ?? '';
$reason      = $_POST['reason'] ?? '';
$signature   = $_POST['signature'] ?? '';

// VERIFY SECRET
if ($signature !== $SHARED_SECRET) {
    http_response_code(403);
    echo "Invalid signature";
    exit;
}

// VALIDATE REQUIRED FIELDS
if (!$employee_id || !$leave_type || !$leave_date || !$reason) {
    http_response_code(400);
    echo "Missing required fields";
    exit;
}

// INSERT INTO leave_requests
$stmt = $conn->prepare("
    INSERT INTO leave_requests 
        (employee_id, leave_type, leave_date, reason, status, created_at)
    VALUES
        (:employee_id, :leave_type, :leave_date, :reason, 'pending', NOW())
");

$stmt->execute([
    'employee_id' => $employee_id,
    'leave_type'  => $leave_type,
    'leave_date'  => $leave_date,
    'reason'      => $reason
]);

// RETURN SUCCESS
echo "OK";
