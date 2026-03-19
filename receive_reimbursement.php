<?php
/*
 * receive_reimbursement.php
 * Receives reimbursement requests from ESS or other systems
 */

require_once('config.php'); // your DB connection

// Set headers to allow POST from ESS
header('Content-Type: text/plain');

// Shared secret for validation
$SHARED_SECRET = 'ESS_TO_HR_2026';

// Check if request comes from external ESS system
if (isset($_POST['external_reimbursement']) && $_POST['external_reimbursement']==1) {
    $signature = $_POST['signature'] ?? '';
    if ($signature !== $SHARED_SECRET) {
        http_response_code(403);
        echo 'Invalid signature';
        exit;
    }
}

// Required fields
$employee_id   = $_POST['employee_id'] ?? '';
$employee_name = trim($_POST['employee_name'] ?? '');
$purpose       = trim($_POST['purpose'] ?? '');
$amount        = $_POST['amount'] ?? '';
$date_submitted= $_POST['date_submitted'] ?? date('Y-m-d');

// Validation
if (!$employee_id || !$employee_name || !$purpose || !$amount) {
    http_response_code(400);
    echo 'Missing required fields';
    exit;
}

// Handle receipt upload
$receipt_path = null;
if (isset($_FILES['receipt']) && $_FILES['receipt']['tmp_name']) {
    $uploadDir = __DIR__ . '/uploads/reimbursements/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileTmp  = $_FILES['receipt']['tmp_name'];
    $fileName = time() . '_' . basename($_FILES['receipt']['name']);
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($fileTmp, $filePath)) {
        $receipt_path = 'uploads/reimbursements/' . $fileName;
    } else {
        http_response_code(500);
        echo 'Failed to upload receipt';
        exit;
    }
}

// Insert into DB
try {
    $stmt = $conn->prepare("
        INSERT INTO reimbursements 
        (employee_id, employee_name, purpose, amount, date_submitted, receipt_path, status, created_at, is_deleted)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), 0)
    ");
    $stmt->execute([$employee_id, $employee_name, $purpose, $amount, $date_submitted, $receipt_path]);

    echo 'OK';
} catch (Exception $e) {
    http_response_code(500);
    echo 'DB Insert Error: ' . $e->getMessage();
}
?>
