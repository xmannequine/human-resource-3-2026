<?php
require 'config.php';

$message = '';
$message_type = 'success';

// Get selected date for updates/filters
$filter_date = $_GET['date'] ?? date('Y-m-d'); // default to today

// Handle inline update of employees_needed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $dept_id = intval($_POST['update_id']);
    $min_needed = intval($_POST['min_needed_per_day'] ?? 0);

    // Check if a record exists for this department/date
    $stmt = $conn->prepare("SELECT COUNT(*) FROM department_daily_needs WHERE department_id=? AND schedule_date=?");
    $stmt->execute([$dept_id, $filter_date]);

    if ($stmt->fetchColumn() > 0) {
        // Update existing record
        $stmt = $conn->prepare("UPDATE department_daily_needs SET employees_needed=? WHERE department_id=? AND schedule_date=?");
        $stmt->execute([$min_needed, $dept_id, $filter_date]);
    } else {
        // Insert new record
        $stmt = $conn->prepare("INSERT INTO department_daily_needs (department_id, schedule_date, employees_needed, employees_scheduled) VALUES (?, ?, ?, 0)");
        $stmt->execute([$dept_id, $filter_date, $min_needed]);
    }

    $message = "✅ Employees Needed Daily updated successfully!";
    $message_type = 'success';
}

// Handle Add/Delete Departments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $dept_id = $_POST['dept_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $min_needed = intval($_POST['min_needed_per_day'] ?? 0);

    if ($action === 'add' && $name) {
        $stmt = $conn->prepare("INSERT INTO departments (name, min_needed_per_day) VALUES (?, ?)");
        $stmt->execute([$name, $min_needed]);
        $message = "✅ Department added successfully!";
        $message_type = 'success';
    }

    if ($action === 'delete' && $dept_id) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
        $stmt->execute([$dept_id]);
        $message = "🗑 Department deleted!";
        $message_type = 'warning';
    }
}

// Handle filters
$filter_dept = $_GET['department'] ?? '';

// --- Ensure all departments have a record for selected date ---
$allDepartments = $conn->query("SELECT id FROM departments")->fetchAll(PDO::FETCH_COLUMN);
foreach ($allDepartments as $dept_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM department_daily_needs WHERE department_id=? AND schedule_date=?");
    $stmt->execute([$dept_id, $filter_date]);
    if ($stmt->fetchColumn() == 0) {
        // Insert default row with employees_needed = min_needed_per_day
        $min_needed_default = $conn->query("SELECT min_needed_per_day FROM departments WHERE id=$dept_id")->fetchColumn();
        $insert = $conn->prepare("INSERT INTO department_daily_needs (department_id, schedule_date, employees_needed, employees_scheduled) VALUES (?, ?, ?, 0)");
        $insert->execute([$dept_id, $filter_date, $min_needed_default]);
    }
}

// --- Fetch departments with scheduled employees for the selected date ---
$whereSQL = [];
$params = [':filter_date' => $filter_date];

if ($filter_dept) {
    $whereSQL[] = "d.id = :dept";
    $params[':dept'] = $filter_dept;
}

$sql = "
    SELECT d.id as dept_id, d.name, d.min_needed_per_day,
           COALESCE(ddn.employees_needed, d.min_needed_per_day) AS employees_needed,
           COALESCE(ddn.employees_scheduled, 0) AS employees_scheduled,
           (COALESCE(ddn.employees_needed, d.min_needed_per_day) - COALESCE(ddn.employees_scheduled, 0)) AS remaining,
           ddn.schedule_date
    FROM departments d
    LEFT JOIN department_daily_needs ddn 
        ON d.id = ddn.department_id AND ddn.schedule_date = :filter_date
";

if ($whereSQL) {
    $sql .= " WHERE " . implode(" AND ", $whereSQL);
}

$sql .= " ORDER BY d.name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all departments for dropdown
$allDepartmentsList = $conn->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Departments Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<style>
    body { background-color: #17758fff; color: #fff; }
    .card { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .table thead { background-color: #343a40; color: #fff; }
    .table tbody tr:hover { background-color: #e9ecef; color: #000; }
    .input-group .btn { min-width: 60px; }
</style>
</head>
<body>
<div class="container mt-5">

<h2 class="mb-4 text-center">Workforce Management</h2>

<?php if($message): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="container mt-5">

<h2 class="mb-4 text-center d-flex justify-content-between align-items-center">
    <span>Workforce Data</span>
    <a href="index.php" class="btn btn-secondary">🏠 Back to Home</a>
</h2>

<!-- Filter Form -->
<div class="card mb-4 text-dark">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-3">
                <input type="text" name="date" id="datepicker" class="form-control" placeholder="Select Date" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-4">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($allDepartmentsList as $id => $name): ?>
                        <option value="<?= $id ?>" <?= ($filter_dept == $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="?" class="btn btn-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Add Department Card -->


<div class="card mb-4 text-dark">
    <div class="card-body">
        <h5 class="card-title">Add New Department</h5>
        <form method="POST" class="row g-3 align-items-center">
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Department Name" required>
            </div>
            <div class="col-md-3">
                <input type="number" name="min_needed_per_day" class="form-control" placeholder="Employees Needed" min="1" value="1" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Add Department</button>
            </div>
        </form>
    </div>
</div>



<!-- Departments Table -->
<div class="card text-dark">
    <div class="card-body">
        <table class="table table-hover table-bordered align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department Name</th>
                    <th>Schedule Date</th>
                    <th>Employees Needed Daily</th>
                    <th>Scheduled</th>
                    <th>Remaining</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if($rows): foreach($rows as $row): ?>
                <tr>
                    <td><?= $row['dept_id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= $row['schedule_date'] ?></td>
                    <td>
                        <div class="input-group">
                            <input type="number" value="<?= intval($row['employees_needed']) ?>" min="1" class="form-control" disabled>
                            <button type="button" class="btn btn-sm btn-warning edit-btn">Edit</button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="update_id" value="<?= $row['dept_id'] ?>">
                                <input type="hidden" name="min_needed_per_day" class="min-needed-value">
                                <button type="submit" class="btn btn-sm btn-success save-btn" disabled>Save</button>
                            </form>
                        </div>
                    </td>
                    <td><?= $row['employees_scheduled'] ?></td>
                    <td>
                        <?php if($row['remaining'] === 0): ?>
                            <span class="badge bg-success"><?= $row['remaining'] ?></span>
                        <?php elseif($row['remaining'] < 0): ?>
                            <span class="badge bg-danger"><?= $row['remaining'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= $row['remaining'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this department?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="dept_id" value="<?= $row['dept_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center">No departments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#datepicker", { dateFormat: "Y-m-d" });

    // Enable inline editing
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const inputGroup = btn.closest('.input-group');
            const input = inputGroup.querySelector('input[type="number"]');
            const saveBtn = inputGroup.querySelector('.save-btn');
            const hiddenInput = inputGroup.querySelector('.min-needed-value');

            input.disabled = false;
            saveBtn.disabled = false;

            input.addEventListener('input', () => {
                hiddenInput.value = input.value;
            });

            hiddenInput.value = input.value;
        });
    });
</script>
</body>
</html>
