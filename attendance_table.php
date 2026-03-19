<?php
session_start();
require_once('config.php');

/* -------------------------
   LOGIN CHECK
------------------------- */
if (!isset($_SESSION['email']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];
$userRole = $_SESSION['user_role'];
$allowedRoles = ['Admin','User'];
if (!in_array($userRole, $allowedRoles)) {
    header("Location: index.php");
    exit;
}

/* -------------------------
   FETCH USER INFO
------------------------- */
$stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $user['username'] ?? '';
$profileImage = !empty($user['profile_image'])
    ? 'uploads/' . htmlspecialchars($user['profile_image'])
    : 'uploads/default-avatar.png';

/* -------------------------
   HANDLE DELETE
------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && $userRole === 'Admin') {
    $delStmt = $conn->prepare("DELETE FROM attendance WHERE id = ?");
    $delStmt->execute([$_POST['delete_id']]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

/* -------------------------
   FETCH EMPLOYEES
------------------------- */
$empStmt = $conn->query("SELECT id, CONCAT(firstname,' ',lastname) AS fullname FROM employee ORDER BY firstname, lastname");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   FILTERS
------------------------- */
$filter_emp   = isset($_GET['employee_id']) && is_numeric($_GET['employee_id']) ? intval($_GET['employee_id']) : '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end   = $_GET['end_date'] ?? '';

$query = "
    SELECT a.*, CONCAT(e.firstname,' ',e.lastname) AS employee_name, e.id AS emp_id
    FROM attendance a
    JOIN employee e ON a.employee_id = e.id
    WHERE 1=1
";
$params = [];

if ($filter_emp) {
    $query .= " AND a.employee_id = :emp_id";
    $params['emp_id'] = $filter_emp;
}
if ($filter_start) {
    $query .= " AND a.date >= :start_date";
    $params['start_date'] = $filter_start;
}
if ($filter_end) {
    $query .= " AND a.date <= :end_date";
    $params['end_date'] = $filter_end;
}

$query .= " ORDER BY a.date DESC, a.time_in DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management System | HRIS</title>
    
    <!-- Bootstrap 5 & Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --light: #f8fafc;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #334155;
            line-height: 1.6;
        }

        /* Modern Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #e2e8f0;
        }

        ::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Layout */
        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Modern Redesign */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header img {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            margin-bottom: 1rem;
            border: 3px solid rgba(255,255,255,0.1);
            padding: 8px;
            background: rgba(255,255,255,0.05);
            object-fit: cover;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: white;
            margin: 0;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .sidebar-nav a i {
            width: 24px;
            font-size: 1.25rem;
            margin-right: 12px;
            text-align: center;
        }

        .sidebar-nav a:hover {
            background: rgba(37, 99, 235, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 10px 20px -10px var(--primary);
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.1);
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: white;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
            background: #f1f5f9;
            min-height: 100vh;
        }

        /* Header Area */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            position: relative;
        }

        .page-header h1:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        .date-badge {
            background: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            font-weight: 500;
            color: var(--secondary);
        }

        .date-badge i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Filter Card - Modern Design */
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-card .form-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.6rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .btn-filter {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px var(--primary);
        }

        /* Table Card */
        .table-card {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Modern Table Styling */
        .table {
            margin: 0;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
            border: none;
            white-space: nowrap;
        }

        .table tbody tr {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.05);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border: none;
            font-size: 0.95rem;
            color: #334155;
        }

        /* Employee ID Badge */
        .emp-id-badge {
            background: #e2e8f0;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
        }

        /* Time Badge */
        .time-badge {
            background: #f1f5f9;
            color: #2563eb;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-block;
        }

        .time-badge.out {
            color: #ef4444;
        }

        /* Address Text */
        .address-text {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Delete Button */
        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            background: #ef4444;
            color: white;
            transform: scale(1.05);
        }

        /* DataTable Customization */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1.5rem;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            margin-left: 0.5rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 10px !important;
            margin: 0 3px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: white !important;
            border: none !important;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>

<body>
<div class="wrapper">

    <!-- SIDEBAR - Modern Redesign -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="logo.jpg" alt="Company Logo">
            <h2>HUMAN RESOURCE 3</h2>
            <p>HR Management System</p>
        </div>
        
        <div class="sidebar-nav">
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="attendance_table.php" class="active">
                <i class="fa-solid fa-calendar-check"></i> Attendance
            </a>
            <a href="leave.php">
                <i class="fa-solid fa-plane"></i> Leave Management
            </a>
            <a href="employees.php">
                <i class="fa-solid fa-users"></i> Employees
            </a>
            <a href="reports.php">
                <i class="fa-solid fa-file-alt"></i> Reports
            </a>
            <a href="settings.php">
                <i class="fa-solid fa-gear"></i> Settings
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="<?= $profileImage ?>" alt="Profile" class="user-avatar">
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($username ?: 'User') ?></div>
                    <div class="user-role">
                        <i class="fa-solid fa-circle" style="font-size: 0.5rem; color: #10b981; vertical-align: middle;"></i>
                        <?= htmlspecialchars($userRole) ?>
                    </div>
                </div>
                <a href="logout.php" class="text-white-50" title="Logout">
                    <i class="fa-solid fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Attendance Records</h1>
            <div class="date-badge">
                <i class="fa-regular fa-calendar"></i>
                <?= date('l, F j, Y') ?>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div class="filter-card">
            <form method="GET" class="row g-4">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="fa-regular fa-user me-1"></i> Employee
                    </label>
                    <select name="employee_id" class="form-select">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($filter_emp==$e['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($e['fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fa-regular fa-calendar-plus me-1"></i> From Date
                    </label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start) ?>" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fa-regular fa-calendar-check me-1"></i> To Date
                    </label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end) ?>" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fa-solid fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="fa-solid fa-list-check me-2 text-primary"></i>
                    Attendance Log
                </h5>
                <?php if($userRole === 'Admin'): ?>
                    <button class="btn btn-primary btn-sm px-3 py-2" onclick="exportTable()">
                        <i class="fa-solid fa-download me-2"></i>Export
                    </button>
                <?php endif; ?>
            </div>

            <table id="attendanceTable" class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee Name</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Location In</th>
                        <th>Time Out</th>
                        <th>Location Out</th>
                        <?php if($userRole==='Admin'): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($attendances)): ?>
                    <tr>
                        <td colspan="<?= $userRole==='Admin' ? '8' : '7' ?>" class="empty-state">
                            <i class="fa-regular fa-calendar-xmark"></i>
                            <p>No attendance records found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendances as $a): ?>
                    <tr>
                        <td>
                            <span class="emp-id-badge">
                                <i class="fa-regular fa-id-card me-1"></i>
                                <?= str_pad($a['emp_id'], 3, '0', STR_PAD_LEFT) ?>
                            </span>
                        </td>
                        <td class="fw-semibold">
                            <i class="fa-regular fa-user me-2 text-secondary"></i>
                            <?= htmlspecialchars($a['employee_name']) ?>
                        </td>
                        <td>
                            <span class="text-primary">
                                <i class="fa-regular fa-calendar me-1"></i>
                                <?= date('M d, Y', strtotime($a['date'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($a['time_in']): ?>
                                <span class="time-badge">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('h:i A', strtotime($a['time_in'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $address_in = trim(implode(', ', array_filter([
                                $a['street_in'] ?? '', 
                                $a['barangay_in'] ?? '', 
                                $a['city_in'] ?? ''
                            ])));
                            if (!empty($address_in)): ?>
                                <div class="address-text">
                                    <i class="fa-regular fa-building me-1"></i>
                                    <?= htmlspecialchars($address_in) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['time_out']): ?>
                                <span class="time-badge out">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?= date('h:i A', strtotime($a['time_out'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning">
                                    <i class="fa-regular fa-hourglass me-1"></i>Not yet
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $address_out = trim(implode(', ', array_filter([
                                $a['street_out'] ?? '', 
                                $a['barangay_out'] ?? '', 
                                $a['city_out'] ?? ''
                            ])));
                            if (!empty($address_out)): ?>
                                <div class="address-text">
                                    <i class="fa-regular fa-building me-1"></i>
                                    <?= htmlspecialchars($address_out) ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <?php if($userRole==='Admin'): ?>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this attendance record? This action cannot be undone.');" style="display: inline;">
                                <input type="hidden" name="delete_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn-delete" title="Delete Record">
                                    <i class="fa-regular fa-trash-can me-1"></i> Delete
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function(){
    // Initialize DataTable with enhanced options
    $('#attendanceTable').DataTable({
        order: [[2, 'desc']], // Sort by date column (index 2) descending
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search records...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        },
        columnDefs: [
            { orderable: false, targets: <?= $userRole==='Admin' ? 7 : 6 ?> } // Disable sorting on actions column
        ]
    });
    
    // Add animation to filter card
    $('.filter-card').hover(
        function() { $(this).css('transform', 'translateY(-2px)'); },
        function() { $(this).css('transform', 'translateY(0)'); }
    );
});

// Export function for admin
function exportTable() {
    // Simple CSV export
    const table = document.getElementById('attendanceTable');
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (const row of rows) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        for (const col of cols) {
            // Remove HTML and get clean text
            let text = col.innerText.replace(/,/g, ' ').replace(/\n/g, ' ');
            rowData.push('"' + text + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_report_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<!-- Additional improvements for better UX -->
<?php if ($userRole === 'Admin'): ?>
<script>
// Keyboard shortcut for filter (Ctrl/Cmd + F)
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[type="search"]').focus();
    }
});
</script>
<?php endif; ?>

</body>
</html>