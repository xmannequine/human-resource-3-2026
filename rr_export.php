<?php
require_once('config.php');

// Fetch all reimbursements
$stmt = $conn->query("SELECT request_id, employee_name, purpose, amount, date_submitted, status, validated_by, validated_at FROM reimbursements ORDER BY id DESC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=reimbursements.csv');

// Output CSV
$output = fopen('php://output', 'w');

// Add column headers
fputcsv($output, ['Request ID', 'Employee Name', 'Purpose', 'Amount', 'Date Submitted', 'Status', 'Validated By', 'Validated At']);

// Add rows
foreach ($rows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
