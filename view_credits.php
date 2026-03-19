<?php
session_start();

// Safe require: ensure correct path
require __DIR__ . '/config.php';

// Get employee_id from POST (from dashboard) or session
$employee_id = $_POST['employee_id'] ?? $_SESSION['employee_id'] ?? null;
$employee_name = $_SESSION['employee_name'] ?? '';

// Redirect if no employee ID
if (!$employee_id) {
    header("Location: index.php");
    exit;
}

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
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave Credits</title>
<style>
body { font-family: Arial; padding: 20px; background: #f0f2f5; }
h2 { text-align: center; }
table { width: 60%; margin: 20px auto; border-collapse: collapse; background: white; }
th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
th { background: #2980b9; color: white; }
.remaining { font-weight: bold; }
.back-btn { display: block; width: 180px; margin: 20px auto; padding: 10px; text-align: center; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
.back-btn:hover { background: #5a6268; }
</style>
</head>
<body>

<h2><?= $employee_name ? htmlspecialchars($employee_name) . "'s Leave Credits" : "Leave Credits" ?></h2>

<?php if (!empty($credits)): ?>
<table>
    <thead>
        <tr>
            <th>Leave Type</th>
            <th>Total Credits</th>
            <th>Used Credits</th>
            <th>Remaining Credits</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($credits as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['leave_type']) ?></td>
            <td><?= $c['total_credits'] ?></td>
            <td><?= $c['used_credits'] ?></td>
            <td class="remaining"><?= $c['remaining'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center;">No leave credits found.</p>
<?php endif; ?>

<a href="leave.php" class="back-btn">Back to Dashboard</a>

</body>
</html>
