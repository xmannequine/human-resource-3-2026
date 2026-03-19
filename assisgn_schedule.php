
<?php
// assign_schedule.php
require 'config.php'; // make sure this contains your PDO $conn connection
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $schedule_date = $_POST['schedule_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    if (!empty($employee_id) && !empty($schedule_date) && !empty($start_time) && !empty($end_time)) {
        $stmt = $conn->prepare("INSERT INTO schedules (employee_id, schedule_date, start_time, end_time) VALUES (?, ?, ?, ?)");
        $stmt->execute([$employee_id, $schedule_date, $start_time, $end_time]);
        $message = "✅ Schedule assigned successfully!";
    } else {
        $message = "⚠ Please fill in all fields.";
    }
}
// Fetch employees
$employees = $conn->query("SELECT id, CONCAT(firstname, ' ', lastname) AS name FROM employee ORDER BY firstname ASC")->fetchAll(PDO::FETCH_ASSOC);
// Fetch schedules with employee names
$schedules = $conn->query("
    SELECT s.id, e.firstname, e.lastname, s.schedule_date, s.start_time, s.end_time
    FROM schedules s
    JOIN employee e ON s.employee_id = e.id
    ORDER BY s.schedule_date ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Schedule</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        form { margin-bottom: 20px; }
        .message { margin-bottom: 10px; color: green; font-weight: bold; }
        .error { color: red; }
    </style>
</head>
<body>
<h2>Assign Schedule</h2>
<?php if (!empty($message)): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<form method="POST">
    <label>Employee:</label>
    <select name="employee_id" required>
        <option value="">-- Select Employee --</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <label>Date:</label>
    <input type="date" name="schedule_date" required>
    <br><br>
    <label>Start Time:</label>
    <input type="time" name="start_time" required>
    <br><br>
    <label>End Time:</label>
    <input type="time" name="end_time" required>
    <br><br>
    <button type="submit">Assign Schedule</button>
</form>
<h2>Schedule List</h2>
<table>
    <tr>
        <th>ID</th>
        <th>Employee</th>
        <th>Date</th>
        <th>Start</th>
        <th>End</th>
    </tr>
    <?php if (!empty($schedules)): ?>
        <?php foreach ($schedules as $sch): ?>
        <tr>
            <td><?= $sch['id'] ?></td>
            <td><?= htmlspecialchars($sch['firstname'] . ' ' . $sch['lastname']) ?></td>
            <td><?= $sch['schedule_date'] ?></td>
            <td><?= $sch['start_time'] ?></td>
            <td><?= $sch['end_time'] ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="5">No schedules assigned yet.</td></tr>
    <?php endif; ?>
</table>
</body>
</html>
