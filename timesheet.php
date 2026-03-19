<?php
session_start();
require_once('config.php'); // PDO connection

function formatTime12($time) {
    if (empty($time)) return '-';
    return date('h:i A', strtotime($time));
}

// --- SESSION CHECK ---
if (isset($_SESSION['email']) && isset($_SESSION['user_role'])) {
    $userType = 'Admin';
    $userName = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $adminEmail = $_SESSION['email'];
} elseif (isset($_SESSION['employee_id'])) {
    $userType = 'Employee';
    $employeeId = (int)$_SESSION['employee_id'];
    $userName = isset($_SESSION['employee_name']) ? $_SESSION['employee_name'] : '';
} else {
    header("Location: login.php");
    exit;
}

// --- Filters ---
$filterEmployeeId = (isset($_GET['employee_id']) && is_numeric($_GET['employee_id'])) ? (int)$_GET['employee_id'] : '';
$filterMonth = (isset($_GET['month']) && is_numeric($_GET['month'])) ? (int)$_GET['month'] : date('n');
$filterYear  = (isset($_GET['year']) && is_numeric($_GET['year'])) ? (int)$_GET['year'] : date('Y');

$timesheet = array();
$totalHours = 0;
$totalRegularHours = 0;
$totalOvertimeHours = 0;
$employees = array();
$STANDARD_HOURS_PER_DAY = 8;

function getApprovedOT($conn, $employee_id, $date) {
    $stmt = $conn->prepare("
        SELECT IFNULL(SUM(total_hours),0)
        FROM overtime_requests
        WHERE employee_id = ?
          AND ot_date = ?
          AND status = 'Approved'
    ");
    $stmt->execute(array($employee_id, $date));
    return (float)$stmt->fetchColumn();
}

// --- Employee/Admin Data ---
if ($userType === 'Employee') {
    $sql = "SELECT date, time_in, time_out,
                   TIMESTAMPDIFF(MINUTE, time_in, time_out)/60 AS total_hours
            FROM attendance
            WHERE employee_id = :employee_id
              AND MONTH(date) = :month
              AND YEAR(date) = :year
            ORDER BY date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute(array(
        ':employee_id' => $employeeId,
        ':month' => $filterMonth,
        ':year' => $filterYear
    ));
    $timesheet = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sql = "SELECT a.id, a.date, a.time_in, a.time_out,
                   CONCAT(e.firstname,' ',e.lastname) AS employee_name,
                   e.id AS emp_id,
                   TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)/60 AS total_hours
            FROM attendance a
            JOIN employee e ON e.id = a.employee_id
            WHERE MONTH(a.date) = :month AND YEAR(a.date) = :year";
    $params = array(':month'=>$filterMonth, ':year'=>$filterYear);

    if ($filterEmployeeId) {
        $sql .= " AND e.id = :employee_id";
        $params[':employee_id'] = $filterEmployeeId;
    }

    $sql .= " ORDER BY a.date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $timesheet = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Employee dropdown
    $stmtEmp = $conn->query("SELECT id, CONCAT(firstname,' ',lastname) AS full_name FROM employee ORDER BY firstname ASC");
    $employees = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
}

// --- Compute totals & AI insights ---
$dailyHoursArray = array();
$alerts = array();
$employeeTotals = array(); // For top risk employees
foreach ($timesheet as $row) {
    $dailyHours = isset($row['total_hours']) ? $row['total_hours'] : 0;
    $regular = min($dailyHours, $STANDARD_HOURS_PER_DAY);

    $empIdForOT = isset($row['emp_id']) ? $row['emp_id'] : $employeeId;
    $approvedOT = getApprovedOT($conn, $empIdForOT, $row['date']);

    $totalRegularHours += $regular;
    $totalOvertimeHours += $approvedOT;
    $totalHours += ($regular + $approvedOT);

    $dailyHoursArray[] = array(
        'date' => $row['date'],
        'regular' => $regular,
        'overtime' => $approvedOT,
        'total' => $regular + $approvedOT,
        'employee_name' => isset($row['employee_name']) ? $row['employee_name'] : $userName
    );

    if (!isset($employeeTotals[$empIdForOT])) {
        $employeeTotals[$empIdForOT] = array('name' => isset($row['employee_name']) ? $row['employee_name'] : $userName, 'hours' => 0);
    }
    $employeeTotals[$empIdForOT]['hours'] += ($regular + $approvedOT);

    // AI Alert: exceeding standard hours
    if ($approvedOT > 0 && ($dailyHours + $approvedOT) > $STANDARD_HOURS_PER_DAY) {
        $alerts[] = "Employee " . (isset($row['employee_name']) ? $row['employee_name'] : $userName) . " exceeded standard hours on " . $row['date'];
    }
}

// --- Predictive data ---
$chartLabels = array();
$chartActual = array();
$chartPredicted = array();
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $filterMonth, $filterYear);

// Build actual data
foreach ($dailyHoursArray as $d) {
    $chartLabels[] = $d['date'];
    $chartActual[] = $d['total'];
}

// Predict remaining days using average of past days
$averageSoFar = count($chartActual) > 0 ? array_sum($chartActual)/count($chartActual) : 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = date('Y-m-d', strtotime("$filterYear-$filterMonth-$d"));
    if (!in_array($dateStr, $chartLabels)) {
        $chartLabels[] = $dateStr;
        $chartPredicted[] = $averageSoFar;
        if ($averageSoFar > $STANDARD_HOURS_PER_DAY) {
            $alerts[] = "Predicted overtime on $dateStr: average hours $averageSoFar";
        }
    } else {
        $chartPredicted[] = null;
    }
}

// --- Top 5 Risk Employees ---
usort($employeeTotals, function($a,$b){ return $b['hours'] - $a['hours']; });
$top5Risk = array_slice($employeeTotals,0,5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Dashboard | HR3 Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f5fa;
            font-family: 'Inter', sans-serif;
        }

        /* Modern Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: #fff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 2rem 1rem;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            text-align: center;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 2rem;
        }

        .sidebar-brand img {
            width: 90px;
            height: 90px;
            border-radius: 20px;
            margin-bottom: 1rem;
            border: 3px solid rgba(255,255,255,0.2);
            padding: 5px;
            background: rgba(255,255,255,0.1);
            transition: transform 0.3s ease;
        }

        .sidebar-brand img:hover {
            transform: scale(1.05);
        }

        .sidebar-brand h2 {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #fff;
            margin: 0;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-nav a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 0.875rem 1.25rem;
            display: flex;
            align-items: center;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            font-size: 1.25rem;
            width: 24px;
        }

        .sidebar-nav a:hover {
            background: #3b82f6;
            color: #fff;
            transform: translateX(5px);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .page-header h1 i {
            color: #3b82f6;
            margin-right: 0.75rem;
        }

        .user-badge {
            background: #fff;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            color: #1e293b;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }

        .user-badge i {
            color: #3b82f6;
            margin-right: 0.5rem;
        }

        /* Filter Card */
        .filter-card {
            background: #fff;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-card .form-select, .filter-card .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .filter-card .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }

        .btn-filter {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 0.6rem 2rem;
            border-radius: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #fff;
        }

        .stat-icon.regular { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.overtime { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.average { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-icon.risk { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .stat-content h3 {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-content .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        /* Risk List */
        .risk-list {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
        }

        .risk-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        .risk-list li:last-child {
            border-bottom: none;
        }

        .risk-list .name {
            color: #475569;
            font-weight: 500;
        }

        .risk-list .hours {
            color: #ef4444;
            font-weight: 600;
        }

        /* Chart Card - FIXED SIZE */
        .chart-card {
            background: #fff;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .chart-card h5 {
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .chart-container {
            position: relative;
            height: 200px; /* FIXED HEIGHT - MUCH SMALLER */
            width: 100%;
            margin: 0 auto;
        }

        /* Alert Card */
        .alert-card {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-card h5 {
            color: #92400e;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .alert-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .alert-card li {
            padding: 0.5rem 0;
            color: #78350f;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-card li i {
            color: #f59e0b;
        }

        /* Table */
        .table-container {
            background: #fff;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .badge-overtime {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .btn-view {
            background: #f1f5f9;
            color: #475569;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #3b82f6;
            color: #fff;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="logo.jpg" alt="HR3 Logo">
        <h2>HUMAN RESOURCE 3</h2>
    </div>
    <div class="sidebar-nav">
        <a href="index.php">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>
        <a href="bin.php">
            <i class="bi bi-trash"></i>
            Recycle Bin
        </a>
        <a href="overtime_admin_approval.php">
            <i class="bi bi-clock-history"></i>
            Overtime Request
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <div class="page-header fade-in">
        <h1>
            <i class="bi bi-clock-history"></i>
            Timesheet Dashboard
        </h1>
        <div class="user-badge">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars($userName ?: ($userType === 'Admin' ? 'Administrator' : 'Employee')) ?>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card fade-in delay-1">
        <form method="get" class="row g-3 align-items-end">
            <?php if ($userType === 'Admin'): ?>
            <div class="col-md-3">
                <label class="form-label fw600">Select Employee</label>
                <select name="employee_id" class="form-select">
                    <option value="">All Employees</option>
                    <?php foreach($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $filterEmployeeId == $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label fw600">Month</label>
                <select name="month" class="form-select">
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == $filterMonth ? 'selected' : '' ?>>
                        <?= date('F', mktime(0,0,0,$m,1)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw600">Year</label>
                <select name="year" class="form-select">
                    <?php for($y=date('Y'); $y>=date('Y')-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $filterYear ? 'selected' : '' ?>>
                        <?= $y ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn-filter w-100">
                    <i class="bi bi-funnel me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card fade-in delay-1">
            <div class="stat-icon regular">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <h3>Regular Hours</h3>
                <div class="stat-number"><?= number_format($totalRegularHours, 1) ?></div>
            </div>
        </div>
        <div class="stat-card fade-in delay-2">
            <div class="stat-icon overtime">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3>Overtime Hours</h3>
                <div class="stat-number"><?= number_format($totalOvertimeHours, 1) ?></div>
            </div>
        </div>
        <div class="stat-card fade-in delay-3">
            <div class="stat-icon average">
                <i class="bi bi-bar-chart"></i>
            </div>
            <div class="stat-content">
                <h3>Daily Average</h3>
                <div class="stat-number"><?= $timesheet ? number_format($totalHours/count($timesheet), 1) : '0.0' ?></div>
            </div>
        </div>
        <div class="stat-card fade-in delay-4">
            <div class="stat-icon risk">
                <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="stat-content">
                <h3>Risk Employees</h3>
                <ul class="risk-list">
                    <?php foreach($top5Risk as $emp): ?>
                    <li>
                        <span class="name"><?= htmlspecialchars($emp['name']) ?></span>
                        <span class="hours"><?= number_format($emp['hours'], 1) ?>h</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Chart Card - FIXED SIZE -->
    <div class="chart-card fade-in">
        <h5><i class="bi bi-graph-up me-2"></i>Hours Analytics & Prediction</h5>
        <div class="chart-container">
            <canvas id="predictiveChart"></canvas>
        </div>
    </div>

    <!-- AI Alerts -->
    <?php if(!empty($alerts)): ?>
    <div class="alert-card fade-in">
        <h5><i class="bi bi-robot me-2"></i>AI Insights & Predictions</h5>
        <ul>
            <?php foreach($alerts as $al): ?>
            <li>
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($al) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Timesheet Table -->
    <div class="table-container fade-in">
        <h5 class="mb-4"><i class="bi bi-table me-2"></i>Detailed Timesheet</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php if($userType === 'Admin'): ?>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Actions</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Regular</th>
                        <th>Overtime</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($timesheet as $row):
                        $dailyHours = isset($row['total_hours']) ? $row['total_hours'] : 0;
                        $regular = min($dailyHours, $STANDARD_HOURS_PER_DAY);
                        $empIdForOT = isset($row['emp_id']) ? $row['emp_id'] : $employeeId;
                        $approvedOT = getApprovedOT($conn,$empIdForOT,$row['date']);
                    ?>
                    <tr>
                        <?php if($userType === 'Admin'): ?>
                        <td><span class="badge bg-light text-dark">#<?= $row['id'] ?></span></td>
                        <td>
                            <div class="fw600"><?= htmlspecialchars($row['employee_name']) ?></div>
                        </td>
                        <td>
                            <a href="timesheet_report.php?emp_id=<?= $empIdForOT ?>&month=<?= $filterMonth ?>&year=<?= $filterYear ?>" class="btn-view">
                                <i class="bi bi-eye me-1"></i>Report
                            </a>
                        </td>
                        <?php endif; ?>
                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                        <td><?= formatTime12($row['time_in']) ?></td>
                        <td><?= formatTime12($row['time_out']) ?></td>
                        <td class="fw600"><?= number_format($regular, 1) ?>h</td>
                        <td>
                            <span class="badge-overtime">
                                <?= number_format($approvedOT, 1) ?>h
                            </span>
                        </td>
                        <td class="fw600"><?= number_format($regular + $approvedOT, 1) ?>h</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Chart Configuration - FIXED SIZE
const ctx = document.getElementById('predictiveChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Actual Hours',
                data: <?= json_encode($chartActual) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                borderWidth: 2.5,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3
            },
            {
                label: 'Predicted Hours',
                data: <?= json_encode($chartPredicted) ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.1)',
                borderWidth: 2.5,
                borderDash: [5, 5],
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false, // This allows us to control height via CSS
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 15,
                    font: { size: 11, weight: 500 },
                    boxWidth: 8
                }
            },
            tooltip: {
                backgroundColor: '#1e293b',
                titleColor: '#fff',
                bodyColor: '#cbd5e1',
                borderColor: '#334155',
                borderWidth: 1,
                padding: 8,
                cornerRadius: 8,
                titleFont: { size: 12 },
                bodyFont: { size: 11 }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0',
                    drawBorder: false,
                    lineWidth: 1
                },
                title: {
                    display: true,
                    text: 'Hours',
                    color: '#64748b',
                    font: { size: 11, weight: 500 }
                },
                ticks: {
                    font: { size: 10 },
                    stepSize: 2
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    maxRotation: 45,
                    minRotation: 45
                }
            }
        },
        layout: {
            padding: {
                top: 10,
                bottom: 10,
                left: 5,
                right: 5
            }
        }
    }
});
</script>
</body>
</html>