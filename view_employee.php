<?php
require 'config.php'; // PDO connection ($conn)

try {
    $stmt = $conn->query("SELECT * FROM employee");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employees | HR3</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f6f9;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        /* =======================
           SIDEBAR
        ======================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
           background: #4b5563;
            color: #fff;
            box-shadow: 4px 0 12px rgba(0,0,0,.15);
            z-index: 1000;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255,255,255,.2);
        }

        .sidebar-brand img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #fff;
            padding: 3px;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 22px;
            color: #e3f2fd;
            text-decoration: none;
            font-size: 14px;
            transition: .3s;
        }

        .sidebar-menu a i {
            font-size: 18px;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,.15);
            color: #fff;
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,.25);
            border-left: 4px solid #ffeb3b;
            color: #fff;
        }

        .sidebar-menu a.logout {
            margin-top: 30px;
            color: #ffcdd2;
        }

        .sidebar-menu a.logout:hover {
            background: rgba(255,0,0,.25);
            color: #fff;
        }

        /* =======================
           MAIN CONTENT
        ======================== */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #0d47a1, #1976d2);
            color: #fff;
            padding: 20px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 6px 12px rgba(0,0,0,.15);
        }

        .table-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,.08);
        }

        table.dataTable thead {
            background: #0d47a1;
            color: #fff;
        }

        table.dataTable tbody tr:hover {
            background-color: #e3f2fd;
        }

        .dataTables_filter input,
        .dataTables_length select {
            border-radius: 6px;
            border: 1px solid #0d47a1;
            padding: 4px 8px;
        }
    </style>
</head>

<body>

<!-- =======================
     SIDEBAR
======================== -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="logo.jpg" alt="HR3 Logo">
        <span>HUMAN RESOURCE 3</span>
    </div>

    <div class="sidebar-menu">
        <a href="index.php">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>

        <a href="employees.php" class="active">
            <i class="bi bi-people"></i>
            Employees
        </a>

        <a href="reimbursements_recycle_bin.php">
            <i class="bi bi-trash"></i>
            Recycle Bin
        </a>

        <a href="index.php" class="logout">
            <i class="bi bi-box-arrow-right"></i>
            Back to Home
        </a>
    </div>
</div>

<!-- =======================
     MAIN CONTENT
======================== -->
<div class="main-content">

    <div class="page-header">
        <h3 class="mb-0">Employee Records</h3>
        <small>HR Management System</small>
    </div>

    <div class="table-card table-responsive">
        <table id="employeeTable" class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <?php if (!empty($rows)): ?>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars(ucwords(str_replace("_", " ", $col))) ?></th>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <th>No Columns Found</th>
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
                    <tr>
                        <td colspan="100%" class="text-center text-muted">
                            No employee records found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#employeeTable').DataTable({
        paging: true,
        searching: true,
        ordering: true,
        order: [],
        pageLength: 10,
        language: {
            search: "Search Employee:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ employees"
        }
    });
});
</script>

</body>
</html>
