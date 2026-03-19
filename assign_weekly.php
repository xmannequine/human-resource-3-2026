<?php
require 'config.php';

$message = '';
$message_type = 'success';

// ----- Define Shifts -----
$shifts = [
    'SH001' => ['label' => 'Shift 1', 'time' => '6:00 AM – 3:00 PM', 'start'=>'06:00:00','end'=>'15:00:00'],
    'SH002' => ['label' => 'Shift 2', 'time' => '9:00 AM – 6:00 PM', 'start'=>'09:00:00','end'=>'18:00:00'],
    'SH003' => ['label' => 'Shift 3', 'time' => '12:00 PM – 9:00 PM', 'start'=>'12:00:00','end'=>'21:00:00'],
    'SH004' => ['label' => 'Shift 4', 'time' => '3:00 PM – 12:00 AM', 'start'=>'15:00:00','end'=>'00:00:00'],
];

// Get month/year filter or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch employees
$employees = $conn->query("
    SELECT id, CONCAT(firstname, ' ', lastname) AS name, job_title, firstname, lastname
    FROM employee 
    ORDER BY job_title, firstname
")->fetchAll(PDO::FETCH_ASSOC);

// Assign colors by job_title
$roleColors = [
    'Admin' => '#4361ee',
    'Manager' => '#f72585',
    'Supplier Helper' => '#ffb703',
    'Warehouse Staff' => '#3a0ca3',
    'Stock Controller' => '#4cc9f0',
    'Delivery Driver' => '#2ec4b6'
];

$employeeColors = [];
foreach ($employees as $emp) {
    $employeeColors[$emp['id']] = $roleColors[$emp['job_title']] ?? '#7209b7';
}

// Fetch schedules (daily) for the month
$stmt = $conn->prepare("
    SELECT d.id, d.employee_id, d.schedule_date, d.start_time, d.end_time, d.shift_id,
           e.firstname, e.lastname, e.job_title
    FROM daily_schedules d
    JOIN employee e ON d.employee_id = e.id
    WHERE MONTH(d.schedule_date)=? AND YEAR(d.schedule_date)=?
    ORDER BY d.schedule_date, e.job_title, e.firstname
");
$stmt->execute([$month, $year]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group schedules by date
$scheduleByDate = [];
foreach ($schedules as $sch) { 
    $scheduleByDate[$sch['schedule_date']][] = $sch; 
}

// Fetch approved leaves for this month
$stmt = $conn->prepare("
    SELECT l.id, l.employee_id, l.leave_date, l.reason, e.firstname, e.lastname, e.job_title
    FROM leave_requests l
    JOIN employee e ON l.employee_id = e.id
    WHERE MONTH(l.leave_date)=? AND YEAR(l.leave_date)=? AND l.status='Approved'
    ORDER BY l.leave_date
");
$stmt->execute([$month, $year]);
$leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group leaves by date and build quick lookup
$leaveByDate = [];
foreach ($leaves as $lv) {
    $leaveByDate[$lv['leave_date']][] = $lv;
}

// Build occupied lookup
$occupiedByDate = [];
foreach ($scheduleByDate as $date => $list) {
    foreach ($list as $s) {
        $occupiedByDate[$date][] = (int)$s['employee_id'];
    }
}
foreach ($leaveByDate as $date => $list) {
    foreach ($list as $l) {
        $occupiedByDate[$date][] = (int)$l['employee_id'];
    }
}
// normalize
foreach ($occupiedByDate as $d => $arr) {
    $occupiedByDate[$d] = array_values(array_unique($arr));
}

// Calendar vars
$firstDay = mktime(0,0,0,$month,1,$year);
$daysInMonth = date("t",$firstDay);
$firstDayOfWeek = date("w",$firstDay);
$monthName = date("F",$firstDay);

$totalSchedules = count($schedules);
$totalEmployees = count($employees);

// ---- Handle POST Actions (save/delete/restore/reset) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'save') {
        $employee_id = isset($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
        $schedule_date = $_POST['schedule_date'] ?? null;
        $shift_id  = $_POST['shift_id'] ?? null;

        if (!$employee_id || !$schedule_date || !$shift_id) {
            $message = "Please fill all schedule fields.";
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("SELECT 1 FROM leave_requests WHERE employee_id = ? AND leave_date = ? AND status = 'Approved' LIMIT 1");
            $stmt->execute([$employee_id, $schedule_date]);
            $isOnLeave = $stmt->fetchColumn();

            if ($isOnLeave) {
                $message = "❌ Cannot schedule — the selected employee is on approved leave for $schedule_date.";
                $message_type = 'danger';
            } else {
                $stmt = $conn->prepare("SELECT 1 FROM daily_schedules WHERE employee_id = ? AND schedule_date = ? LIMIT 1");
                $stmt->execute([$employee_id, $schedule_date]);
                $already = $stmt->fetchColumn();
                if ($already) {
                    $message = "❌ Cannot schedule — the selected employee already has a schedule on $schedule_date.";
                    $message_type = 'danger';
                } else {
                    $startTime = $shifts[$shift_id]['start'] ?? '00:00:00';
                    $endTime = $shifts[$shift_id]['end'] ?? '00:00:00';
                    $stmt = $conn->prepare("INSERT INTO daily_schedules (employee_id, schedule_date, shift_id, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$employee_id, $schedule_date, $shift_id, $startTime, $endTime]);

                    $message = "✅ Schedule saved for $schedule_date!";
                    $message_type = 'success';

                    $newId = $conn->lastInsertId();
                    $stmt = $conn->prepare("SELECT firstname, lastname, job_title FROM employee WHERE id = ? LIMIT 1");
                    $stmt->execute([$employee_id]);
                    $empInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $sRec = [
                        'id' => $newId,
                        'employee_id' => $employee_id,
                        'schedule_date' => $schedule_date,
                        'shift_id' => $shift_id,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'firstname' => $empInfo['firstname'],
                        'lastname' => $empInfo['lastname'],
                        'job_title' => $empInfo['job_title']
                    ];
                    $schedules[] = $sRec;
                    $scheduleByDate[$schedule_date][] = $sRec;
                    $occupiedByDate[$schedule_date][] = $employee_id;
                    $totalSchedules++;
                }
            }
        }
    }

    elseif ($_POST['action'] === 'delete' && isset($_POST['schedule_id'])) {
        $scheduleId = $_POST['schedule_id'];
        $stmt = $conn->prepare("
            INSERT INTO deleted_schedules (employee_id, schedule_date, shift_id, start_time, end_time)
            SELECT employee_id, schedule_date, shift_id, start_time, end_time
            FROM daily_schedules WHERE id=?
        ");
        $stmt->execute([$scheduleId]);
        $stmt = $conn->prepare("DELETE FROM daily_schedules WHERE id=?");
        $stmt->execute([$scheduleId]);

        foreach ($scheduleByDate as $date => $list) {
            foreach ($list as $key => $s) {
                if ($s['id'] == $scheduleId) {
                    unset($scheduleByDate[$date][$key]);
                    $occupiedByDate[$date] = array_values(array_diff($occupiedByDate[$date], [$s['employee_id']]));
                }
            }
        }

        $message = "🗑 Schedule moved to recycle bin!";
        $message_type = 'warning';
    }

    elseif ($_POST['action'] === 'restore' && isset($_POST['deleted_id'])) {
        $stmt = $conn->prepare("SELECT * FROM deleted_schedules WHERE id=?");
        $stmt->execute([$_POST['deleted_id']]);
        $del = $stmt->fetch(PDO::FETCH_ASSOC);

        if($del){
            $stmt = $conn->prepare("INSERT INTO daily_schedules (employee_id, schedule_date, shift_id, start_time, end_time) VALUES (?,?,?,?,?)");
            $stmt->execute([$del['employee_id'], $del['schedule_date'], $del['shift_id'], $del['start_time'], $del['end_time']]);
            $newId = $conn->lastInsertId();

            $empInfo = $conn->query("SELECT firstname, lastname, job_title FROM employee WHERE id=".$del['employee_id'])->fetch(PDO::FETCH_ASSOC);
            $sRec = [
                'id' => $newId,
                'employee_id' => $del['employee_id'],
                'schedule_date' => $del['schedule_date'],
                'shift_id' => $del['shift_id'],
                'start_time' => $del['start_time'],
                'end_time' => $del['end_time'],
                'firstname' => $empInfo['firstname'],
                'lastname' => $empInfo['lastname'],
                'job_title' => $empInfo['job_title']
            ];
            $schedules[] = $sRec;
            $scheduleByDate[$del['schedule_date']][] = $sRec;
            $occupiedByDate[$del['schedule_date']][] = $del['employee_id'];

            $stmt = $conn->prepare("DELETE FROM deleted_schedules WHERE id=?");
            $stmt->execute([$_POST['deleted_id']]);

            $message = "✅ Schedule restored from recycle bin!";
            $message_type = 'success';
        }
    }

    elseif ($_POST['action'] === 'reset') {
        $stmt = $conn->prepare("DELETE FROM daily_schedules WHERE MONTH(schedule_date)=? AND YEAR(schedule_date)=?");
        $stmt->execute([$month, $year]);
        $scheduleByDate = [];
        $occupiedByDate = [];
        $message = "⚠ All schedules reset for $month/$year!";
        $message_type = 'warning';
    }
}

// Fetch recycle bin entries
$deletedSchedules = $conn->query("
    SELECT d.id, d.employee_id, d.schedule_date, d.shift_id, d.start_time, d.end_time, e.firstname, e.lastname, e.job_title
    FROM deleted_schedules d
    JOIN employee e ON d.employee_id = e.id
    ORDER BY d.deleted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare JSON for front-end quick lookups
$occupiedJson = json_encode($occupiedByDate);
$leaveDetailedJson = json_encode($leaveByDate);
$scheduleIdsByDate = [];
foreach ($scheduleByDate as $d => $list) {
    $arr = [];
    foreach ($list as $s) $arr[] = (int)$s['employee_id'];
    $scheduleIdsByDate[$d] = array_values(array_unique($arr));
}

// ---- AI Scheduler Suggestion for Top Unscheduled Employees ----
$unscheduledCounts = [];
foreach ($employees as $emp) $unscheduledCounts[$emp['id']] = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $currDate = sprintf("%04d-%02d-%02d",$year,$month,$d);
    foreach ($employees as $emp) {
        $eid = $emp['id'];
        $onSchedule = in_array($eid, $scheduleIdsByDate[$currDate] ?? []);
        $onLeave = isset($leaveByDate[$currDate]) && array_search($eid, array_column($leaveByDate[$currDate], 'employee_id'))!==false;
        if (!$onSchedule && !$onLeave) $unscheduledCounts[$eid]++;
    }
}
arsort($unscheduledCounts);
$topSuggestions = array_slice($unscheduledCounts,0,3,true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR3 | Monthly Schedule Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f7fb;
            font-family: 'Inter', sans-serif;
        }

        /* Modern Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a2639 0%, #0f172a 100%);
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

        .sidebar-brand p {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 0.25rem;
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
            background: #2d3a5e;
            color: #fff;
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: #3b82f6;
            color: #fff;
            box-shadow: 0 4px 10px rgba(59,130,246,0.3);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        /* Header Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
        }

        .stat-info h3 {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-info .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .stat-icon.employees { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.schedules { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.ai { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        /* Alert Styles */
        .alert-modern {
            border: none;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #ffedd5; color: #92400e; }

        /* Form Card */
        .form-card {
            background: #fff;
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #3b82f6;
            border: none;
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59,130,246,0.4);
        }

        /* AI Suggestion Card */
        .ai-suggestion {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
        }

        .ai-suggestion h6 {
            color: #475569;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .suggestion-item {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background: #fff;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
        }

        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 4px;
            margin-right: 10px;
        }

        /* Calendar */
        .calendar-container {
            background: #fff;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            margin-top: 2rem;
        }

        .calendar {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .calendar th {
            padding: 1rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            border-radius: 12px;
        }

        .calendar td {
            background: #f8fafc;
            border: none;
            border-radius: 16px;
            padding: 1rem;
            height: 140px;
            vertical-align: top;
            transition: all 0.3s ease;
        }

        .calendar td:hover {
            background: #f1f5f9;
            transform: scale(0.98);
        }

        .day-number {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .emp-badge {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            margin: 0.25rem;
            border-radius: 10px;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
            width: 100%;
            text-align: left;
        }

        .emp-badge:hover {
            transform: translateX(5px);
            opacity: 0.9;
        }

        .leave-badge {
            background: #64748b !important;
        }

        /* Shift Guide */
        .shift-guide {
            background: #fff;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .shift-guide h5 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .shift-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .shift-item {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.9rem;
            border-left: 4px solid #3b82f6;
        }

        .shift-item strong {
            color: #1e293b;
            display: block;
            margin-bottom: 0.25rem;
        }

        .shift-item span {
            color: #64748b;
            font-size: 0.85rem;
        }

        /* Legend */
        .legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="logo.jpg" alt="HR3 Logo">
        <h2>HUMAN RESOURCE 3</h2>
        <p>Schedule Management</p>
    </div>
    <div class="sidebar-nav">
        <a href="index.php" class="active">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>
        <a href="index.php">
            <i class="bi bi-trash"></i>
            Recycle Bin
        </a>
        <a href="index.php">
            <i class="bi bi-box-arrow-right"></i>
            Back to Home
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold" style="color: #1e293b;">Monthly Schedule</h1>
            <p class="text-muted">Manage and assign employee schedules efficiently</p>
        </div>
        <div class="d-flex gap-2">
            <select class="form-select" style="width: auto;" onchange="window.location.href='?month='+this.value+'&year=<?= $year ?>'">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m==$month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <select class="form-select" style="width: auto;" onchange="window.location.href='?month=<?= $month ?>&year='+this.value">
                <?php for($y=date('Y')-2; $y<=date('Y')+2; $y++): ?>
                    <option value="<?= $y ?>" <?= $y==$year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card fade-in">
            <div class="stat-info">
                <h3>Total Employees</h3>
                <div class="stat-number"><?= $totalEmployees ?></div>
            </div>
            <div class="stat-icon employees">
                <i class="bi bi-people-fill text-white"></i>
            </div>
        </div>
        <div class="stat-card fade-in" style="animation-delay: 0.1s;">
            <div class="stat-info">
                <h3>Schedules This Month</h3>
                <div class="stat-number"><?= $totalSchedules ?></div>
            </div>
            <div class="stat-icon schedules">
                <i class="bi bi-calendar-check-fill text-white"></i>
            </div>
        </div>
        <div class="stat-card fade-in" style="animation-delay: 0.2s;">
            <div class="stat-info">
                <h3>AI Suggestions</h3>
                <div class="stat-number"><?= count($topSuggestions) ?></div>
            </div>
            <div class="stat-icon ai">
                <i class="bi bi-robot text-white"></i>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <div id="alertArea">
    <?php if($message): ?>
    <div class="alert alert-modern alert-<?= $message_type === 'success' ? 'success' : ($message_type==='danger' ? 'danger' : 'warning') ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : ($message_type==='danger' ? 'exclamation-triangle-fill' : 'info-circle-fill') ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    </div>

    <!-- Shift Guide -->
    <div class="shift-guide fade-in" style="animation-delay: 0.3s;">
        <h5><i class="bi bi-clock-history me-2"></i>Shift Guide</h5>
        <div class="shift-list">
            <?php foreach($shifts as $id=>$s): ?>
            <div class="shift-item">
                <strong><?= $s['label'] ?> (<?= $id ?>)</strong>
                <span><?= $s['time'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Schedule Form + AI Suggestion -->
    <div class="form-card fade-in" style="animation-delay: 0.4s;">
        <form method="POST" id="scheduleForm" class="row g-4">
            <input type="hidden" name="action" value="save" id="form-action">
            
            <div class="col-md-3">
                <label class="form-label">Select Employee</label>
                <select name="employee_id" class="form-select" id="employeeSelect" required>
                    <option value="">-- Choose employee --</option>
                    <?php foreach($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']." (".$emp['job_title'].")") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Select Date</label>
                <input type="date" name="schedule_date" class="form-control" required>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Select Shift</label>
                <select name="shift_id" class="form-select" required>
                    <option value="">-- Choose shift --</option>
                    <?php foreach($shifts as $id=>$s): ?>
                    <option value="<?= $id ?>"><?= $s['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-circle me-2"></i>Assign Schedule
                </button>
            </div>

            <!-- AI Suggestion -->
            <?php if($topSuggestions): ?>
            <div class="col-12">
                <div class="ai-suggestion">
                    <h6><i class="bi bi-stars text-primary"></i> AI Suggested Employees (Most Unscheduled)</h6>
                    <div class="row g-2">
                        <?php foreach($topSuggestions as $eid => $count): 
                            $emp = array_values(array_filter($employees, fn($e)=>$e['id']==$eid))[0];
                        ?>
                        <div class="col-md-4">
                            <div class="suggestion-item">
                                <div class="color-dot" style="background: <?= $employeeColors[$eid] ?>;"></div>
                                <div>
                                    <strong><?= htmlspecialchars($emp['name']) ?></strong>
                                    <small class="text-muted d-block"><?= $count ?> unscheduled days</small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Calendar -->
    <div class="calendar-container fade-in" style="animation-delay: 0.5s;">
        <h5 class="mb-4"><i class="bi bi-calendar3 me-2"></i><?= $monthName ?> <?= $year ?> Schedule Overview</h5>
        
        <table class="calendar">
            <thead>
                <tr>
                    <?php 
                    $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach($daysOfWeek as $d): ?>
                    <th><?= $d ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $dayCount = 1;
                for($row=0; $row<6; $row++):
                    echo "<tr>";
                    for($col=0;$col<7;$col++):
                        if($row===0 && $col<$firstDayOfWeek): 
                            echo "<td></td>"; 
                            continue; 
                        endif;
                        
                        if($dayCount > $daysInMonth): 
                            echo "<td></td>"; 
                            continue; 
                        endif;
                        
                        $currDate = sprintf("%04d-%02d-%02d",$year,$month,$dayCount);
                ?>
                        <td>
                            <div class="day-number"><?= $dayCount ?></div>
                            <?php 
                            if(isset($scheduleByDate[$currDate])):
                                foreach($scheduleByDate[$currDate] as $sch):
                                    $color = $employeeColors[$sch['employee_id']] ?? '#7209b7';
                            ?>
                                <div class="emp-badge" style="background: <?= $color ?>;" 
                                     title="<?= htmlspecialchars($sch['firstname'].' '.$sch['lastname'].' ('.$sch['job_title'].')') ?>">
                                    <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($sch['firstname']) ?>
                                </div>
                            <?php 
                                endforeach;
                            endif;
                            
                            if(isset($leaveByDate[$currDate])):
                                foreach($leaveByDate[$currDate] as $lv):
                            ?>
                                <div class="emp-badge leave-badge" 
                                     title="<?= htmlspecialchars($lv['firstname'].' '.$lv['lastname'].' - On Leave') ?>">
                                    <i class="bi bi-tree-fill me-1"></i><?= htmlspecialchars($lv['firstname']) ?> (Leave)
                                </div>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </td>
                <?php 
                        $dayCount++;
                    endfor;
                    echo "</tr>";
                endfor;
                ?>
            </tbody>
        </table>

        <!-- Legend -->
        <div class="mt-4">
            <h6 class="mb-3">Role Color Legend</h6>
            <div class="legend-grid">
                <?php foreach($roleColors as $role=>$c): ?>
                <div class="legend-item">
                    <div class="legend-color" style="background: <?= $c ?>;"></div>
                    <span><?= $role ?></span>
                </div>
                <?php endforeach; ?>
                <div class="legend-item">
                    <div class="legend-color" style="background: #64748b;"></div>
                    <span>On Leave</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
</body>
</html>