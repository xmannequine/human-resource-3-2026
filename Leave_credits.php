<?php
session_start();
require 'config.php';

$employee_id = $_POST['employee_id'] ?? 0;

if (!$employee_id) {
    die("No employee selected.");
}

// Fetch employee info
$stmtEmp = $conn->prepare("SELECT firstname, lastname FROM employee WHERE id = :id");
$stmtEmp->execute(['id' => $employee_id]);
$employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);
$employee_name = $employee ? $employee['firstname'] . ' ' . $employee['lastname'] : 'Unknown';

// Fetch leave credits
$stmt = $conn->prepare("
    SELECT leave_type, total_credits, used_credits, (total_credits - used_credits) AS remaining
    FROM leave_credits
    WHERE employee_id = :employee_id
");
$stmt->execute(['employee_id' => $employee_id]);
$credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Leave Credits</title>
<style>
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
.container { max-width: 600px; margin: 50px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
th { background: #2980b9; color: white; }
h2 { text-align: center; }
.back-btn { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: white; background: #2980b9; padding: 8px 16px; border-radius: 4px; }
.back-btn:hover { background: #1f6391; }
</style>
</head>
<body>
<div class="container">
<h2>Leave Credits for <?= htmlspecialchars($employee_name) ?></h2>

<?php if ($credits): ?>
<table>
<tr>
<th>Leave Type</th>
<th>Total Credits</th>
<th>Used Credits</th>
<th>Remaining</th>
</tr>
<?php foreach ($credits as $c): ?>
<tr>
<td><?= htmlspecialchars($c['leave_type']) ?></td>
<td><?= $c['total_credits'] ?></td>
<td><?= $c['used_credits'] ?></td>
<td><?= $c['remaining'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p style="text-align:center;">No leave credits found for this employee.</p>
<?php endif; ?>

<a href="javascript:history.back()" class="back-btn">Back</a>
</div>
</body>
</html>
