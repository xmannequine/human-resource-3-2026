<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['email']) && !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

$emp_id = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$stmt = $conn->prepare("SELECT id, firstname, lastname, job_title FROM employee WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $emp_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) die("Employee not found.");

$employee_name = $employee['firstname'] . ' ' . $employee['lastname'];
$employee_job_title = $employee['job_title'];
$STANDARD_HOURS_PER_DAY = 8;

$leaveStmt = $conn->prepare("
    SELECT leave_date, leave_type
    FROM leave_requests
    WHERE employee_id = :emp_id
      AND status = 'approved'
      AND deleted_at IS NULL
      AND MONTH(leave_date) = :month
      AND YEAR(leave_date) = :year
");
$leaveStmt->execute([
    ':emp_id' => $emp_id,
    ':month'  => $month,
    ':year'   => $year
]);
$leaveMap = [];
foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $lv) {
    $leaveMap[$lv['leave_date']] = $lv['leave_type'];
}

$sql = "SELECT date, time_in, time_out,
               TIMESTAMPDIFF(MINUTE, time_in, time_out)/60 AS total_hours
        FROM attendance
        WHERE employee_id = :emp_id
          AND MONTH(date) = :month
          AND YEAR(date) = :year";
$stmt = $conn->prepare($sql);
$stmt->execute([':emp_id' => $emp_id, ':month' => $month, ':year' => $year]);
$attendanceMap = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attendanceMap[$row['date']] = $row;
}

function getApprovedOT($conn, $employee_id, $date) {
    $stmt = $conn->prepare("
        SELECT IFNULL(SUM(total_hours),0)
        FROM overtime_requests
        WHERE employee_id = ?
          AND ot_date = ?
          AND status = 'Approved'
    ");
    $stmt->execute([$employee_id, $date]);
    return (float)$stmt->fetchColumn();
}

$timesheet = [];
$totalHours = 0;
$totalRegular = 0;
$totalOT = 0;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $weekday = date('N', strtotime($date)); // 6,7 = weekend

    if (isset($leaveMap[$date])) {
        $timesheet[] = [
            'date' => $date,
            'status' => 'LEAVE',
            'leave_type' => $leaveMap[$date],
            'time_in' => null,
            'time_out' => null,
            'regular' => 0,
            'ot' => 0,
            'total' => 0,
            'is_weekend' => $weekday >= 6
        ];
        continue;
    }

    if (isset($attendanceMap[$date])) {
        $row = $attendanceMap[$date];
        $dailyHours = $row['total_hours'] ?? 0;
        $regular = min($dailyHours, $STANDARD_HOURS_PER_DAY);
        $approvedOT = getApprovedOT($conn, $emp_id, $date);

        $timesheet[] = [
            'date' => $date,
            'status' => 'PRESENT',
            'leave_type' => null,
            'time_in' => $row['time_in'],
            'time_out' => $row['time_out'],
            'regular' => $regular,
            'ot' => $approvedOT,
            'total' => $regular + $approvedOT,
            'is_weekend' => $weekday >= 6
        ];

        $totalRegular += $regular;
        $totalOT += $approvedOT;
        $totalHours += ($regular + $approvedOT);
        continue;
    }

    $timesheet[] = [
        'date' => $date,
        'status' => 'ABSENT',
        'leave_type' => null,
        'time_in' => null,
        'time_out' => null,
        'regular' => 0,
        'ot' => 0,
        'total' => 0,
        'is_weekend' => $weekday >= 6
    ];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="timesheet_' . $employee['id'] . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date','Status','Leave Type','Time In','Time Out','Regular Hours','Overtime Hours','Total Hours']);
    foreach ($timesheet as $row) {
        fputcsv($output, [
            $row['date'],
            $row['status'],
            $row['leave_type'] ?? '-',
            $row['time_in'] ?? '-',
            $row['time_out'] ?? '-',
            number_format($row['regular'],2),
            number_format($row['ot'],2),
            number_format($row['total'],2)
        ]);
    }
    fputcsv($output, ['TOTAL','','','', '', number_format($totalRegular,2), number_format($totalOT,2), number_format($totalHours,2)]);
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Timesheet Report - <?= htmlspecialchars($employee_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
body { background:#E8E8E8; color:#00334E; font-family:'Segoe UI', sans-serif; }
.report-header { display:flex; align-items:center; background:#00334E; color:#fff; padding:15px 25px; border-radius:8px; margin-bottom:20px; gap:15px; }
.report-header img { height:50px; width:auto; border-radius:5px; }
.report-header h2 { margin:0; font-size:22px; }
.employee-info { background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:20px; }
.summary-cards { display:flex; gap:15px; margin-bottom:20px; }
.summary-card { flex:1; background:#00334E; color:#fff; padding:15px; border-radius:8px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
table { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1); width:100%; }
th { background:#145374; color:#E8E8E8; text-align:center; }
td, th { padding:12px; text-align:center; }
tr:nth-child(even){background:#dce6f0;}
.badge-status { padding:5px 10px; border-radius:8px; color:#fff; font-weight:bold; }
.badge-PRESENT { background:#2ecc71; }
.badge-LEAVE { background:#3498db; }
.badge-ABSENT { background:#e74c3c; }
.weekend { background:#f0f0f0; }
.text-regular { color:#28a745; font-weight:bold; }
.text-ot { color:#dc3545; font-weight:bold; }
.btn-export { margin-bottom:15px; }
.btn-back { margin-bottom:15px; background:#5588A3; color:#fff; }
.btn-back:hover { background:#145374; color:#fff; }
@media print { .btn-export, .summary-cards, .btn-back { display:none; } }
</style>
</head>
<body>
<div class="container py-4">
    <div class="report-header">
        <img src="logo.jpg" alt="Company Logo">
        <h2>Timesheet Report</h2>
    </div>

    <div class="employee-info">
        <h5>Employee: <?= htmlspecialchars($employee_name) ?></h5>
        <p>Job Title: <?= htmlspecialchars($employee_job_title) ?> | Month: <?= date('F', mktime(0,0,0,$month,1)) ?> | Year: <?= $year ?></p>
    </div>

    <a href="timesheet.php" class="btn btn-back">⬅ Back to Attendance</a>
    <a href="?emp_id=<?= $emp_id ?>&month=<?= $month ?>&year=<?= $year ?>&export=csv" class="btn btn-success btn-export">⬇ Download CSV</a>

    <div class="summary-cards">
        <div class="summary-card">
            <h4>Total Regular Hours</h4>
            <h2><?= number_format($totalRegular,2) ?></h2>
        </div>
        <div class="summary-card">
            <h4>Total Overtime Hours</h4>
            <h2><?= number_format($totalOT,2) ?></h2>
        </div>
        <div class="summary-card">
            <h4>Total Hours</h4>
            <h2><?= number_format($totalHours,2) ?></h2>
        </div>
    </div>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Leave Type</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Regular Hours</th>
                <th>Overtime Hours</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($timesheet as $row): ?>
            <tr class="<?= $row['is_weekend'] ? 'weekend' : '' ?>">
                <td><?= $row['date'] ?></td>
                <td><span class="badge-status badge-<?= $row['status'] ?>"><?= $row['status'] ?></span></td>
                <td><?= $row['leave_type'] ?? '-' ?></td>
                <td><?= $row['time_in'] ?? '-' ?></td>
                <td><?= $row['time_out'] ?? '-' ?></td>
                <td class="text-regular"><?= number_format($row['regular'],2) ?></td>
                <td class="text-ot"><?= number_format($row['ot'],2) ?></td>
                <td><?= number_format($row['total'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="fw-bold">
                <td colspan="5">TOTAL</td>
                <td class="text-regular"><?= number_format($totalRegular,2) ?></td>
                <td class="text-ot"><?= number_format($totalOT,2) ?></td>
                <td><?= number_format($totalHours,2) ?></td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
