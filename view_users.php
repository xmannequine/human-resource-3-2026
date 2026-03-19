<?php
require 'config.php'; // ✅ must define $conn

try {
    // Fetch all rows from 'users' table
    $stmt = $conn->query("SELECT * FROM employees");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Users - dbhr3</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <h2>Data from Table: Employee</h2>
    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <?php if (!empty($rows)): ?>
                    <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                <?php else: ?>
                    <th>No columns found</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="100%" class="text-center">No data found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
