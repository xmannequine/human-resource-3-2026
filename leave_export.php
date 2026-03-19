<?php
require 'config.php';

// Fetch leave requests (same query logic as dashboard)
$sql = "
    SELECT lr.id, e.firstname, e.lastname, lr.leave_type, lr.leave_date, lr.reason,
           lr.status, lr.created_at, lr.validated_by, lr.validated_at
    FROM leave_requests lr
    LEFT JOIN employee e ON lr.employee_id = e.id
    ORDER BY lr.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers for Word
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=leave_requests.doc");

echo "<html>";
echo "<meta charset='UTF-8'>";
echo "<body>";
echo "<h2>Leave Requests Report</h2>";
echo "<table border='1' cellspacing='0' cellpadding='5'>";
echo "<tr>
        <th>ID</th>
        <th>Employee Name</th>
        <th>Leave Type</th>
        <th>Leave Date</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Requested At</th>
        <th>Validated By</th>
        <th>Validated At</th>
      </tr>";

foreach ($leaveRequests as $lr) {
    echo "<tr>
        <td>{$lr['id']}</td>
        <td>{$lr['firstname']} {$lr['lastname']}</td>
        <td>{$lr['leave_type']}</td>
        <td>{$lr['leave_date']}</td>
        <td>{$lr['reason']}</td>
        <td>{$lr['status']}</td>
        <td>{$lr['created_at']}</td>
        <td>" . ($lr['validated_by'] ?? '-') . "</td>
        <td>" . ($lr['validated_at'] ?? '-') . "</td>
    </tr>";
}

echo "</table>";
echo "</body></html>";
exit;
