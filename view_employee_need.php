<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

// Fetch all departments and create lookup [id => name]
$stmt = $conn->query("SELECT id, name FROM departments ORDER BY name");
$departments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // key=id, value=name

// Fetch daily needs
$stmt = $conn->query("SELECT * FROM department_daily_needs ORDER BY schedule_date, department_id");
$needs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build scheduled lookup
$scheduledLookup = [];
foreach ($needs as $n) {
    $scheduledLookup[$n['schedule_date']][$n['department_id']] = (int)($n['employees_scheduled'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Need per Department</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #17758fff; }
    .card { margin-top: 20px; }
    .table th, .table td { vertical-align: middle; }
    .badge-warning { color: #000; }
</style>
</head>
<body>
    <div class="container mt-5">

<h2 class="mb-4 text-center d-flex justify-content-between align-items-center">
    <span>Departments Management</span>
    <a href="index.php" class="btn btn-secondary">🏠 Back to Home</a>
</h2>

<div class="container mt-4">
    <h2 class="mb-4">Employee Need per Department</h2>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead class="table-light">
                    
                    <tr>
                        <th>Date</th>
                        <th>Department</th>
                        <th>Needed Employees</th>
                        <th>Scheduled</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($needs)): ?>
                    <?php foreach ($needs as $need): 
                        $deptId = $need['department_id'];
                        $date = $need['schedule_date'];
                        $scheduled = $scheduledLookup[$date][$deptId] ?? 0;
                        $remaining = $need['employees_needed'] - $scheduled;
                        $deptName = $departments[$deptId] ?? 'Unknown';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($date) ?></td>
                        <td><?= htmlspecialchars($deptName) ?></td>
                        <td><?= $need['employees_needed'] ?></td>
                        <td><?= $scheduled ?></td>
                        <td>
                            <?php if ($remaining === 0): ?>
                                <span class="badge bg-success"><?= $remaining ?></span>
                            <?php elseif ($remaining < 0): ?>
                                <span class="badge bg-danger"><?= $remaining ?></span>
                            <?php else: ?>
                                <span class="badge bg-warning"><?= $remaining ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">No employee need data found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
