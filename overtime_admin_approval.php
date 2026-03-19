<?php
session_start();
require_once('config.php');

// ADMIN SESSION CHECK
if (!isset($_SESSION['email']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

$adminEmail = $_SESSION['email'];

// HANDLE APPROVE / REJECT
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    $id = (int)($_GET['approve'] ?? $_GET['reject']);
    $status = isset($_GET['approve']) ? 'Approved' : 'Rejected';

    // Update OT status
    $stmt = $conn->prepare("
        UPDATE overtime_requests
        SET status = ?, approved_by = ?, approved_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $adminEmail, $id]);

    // Flash message and stay on the same page
    $_SESSION['flash_message'] = "Overtime request {$status} successfully.";
    header("Location: " . basename(__FILE__));
    exit;
}

// FETCH OT REQUESTS
$stmt = $conn->query("
    SELECT o.*, CONCAT(e.firstname,' ',e.lastname) AS employee_name
    FROM overtime_requests o
    JOIN employee e ON e.id = o.employee_id
    ORDER BY 
        CASE o.status 
            WHEN 'Pending' THEN 1 
            WHEN 'Approved' THEN 2 
            ELSE 3 
        END,
        o.created_at DESC
");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate stats
$totalPending = 0;
$totalApproved = 0;
$totalRejected = 0;
$totalHours = 0;

foreach ($requests as $r) {
    if ($r['status'] == 'Pending') $totalPending++;
    elseif ($r['status'] == 'Approved') $totalApproved++;
    elseif ($r['status'] == 'Rejected') $totalRejected++;
    $totalHours += $r['total_hours'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overtime Approval | HR3 Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f5fa;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            padding: 2rem;
        }

        /* Main Container */
        .overtime-container {
            max-width: 1400px;
            margin: 0 auto;
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
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header h1 i {
            color: #3b82f6;
            background: #fff;
            padding: 0.75rem;
            border-radius: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .btn-dashboard {
            background: #fff;
            color: #1e293b;
            padding: 0.75rem 1.5rem;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .btn-dashboard:hover {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
            transform: translateX(-5px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
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

        .stat-icon.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-icon.approved { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.rejected { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-icon.total { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        .stat-content h3 {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .stat-content .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .stat-content .stat-sub {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }

        /* Flash Message */
        .flash-message {
            background: #3b82f6;
            color: #fff;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 12px rgba(59,130,246,0.3);
            animation: slideDown 0.5s ease;
        }

        .flash-message i {
            font-size: 1.5rem;
        }

        /* Table Card */
        .table-card {
            background: #fff;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h5 {
            color: #0f172a;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-header h5 i {
            color: #3b82f6;
        }

        .filter-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-badge i {
            color: #3b82f6;
        }

        /* Table */
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
            white-space: nowrap;
        }

        .table tbody td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            color: #1e293b;
            font-size: 0.95rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: #f8fafc;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Employee Info */
        .employee-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .employee-details {
            line-height: 1.3;
        }

        .employee-name {
            font-weight: 600;
            color: #0f172a;
        }

        .employee-id {
            font-size: 0.75rem;
            color: #64748b;
        }

        /* Time Badge */
        .time-badge {
            background: #f1f5f9;
            padding: 0.4rem 0.8rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .time-badge i {
            color: #3b82f6;
            font-size: 0.8rem;
        }

        /* Hours Badge */
        .hours-badge {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.4rem 0.8rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-block;
        }

        /* Status Badge */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-approve {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
            color: #fff;
        }

        .btn-reject {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
            color: #fff;
        }

        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        /* Reason Tooltip */
        .reason-cell {
            max-width: 200px;
            position: relative;
        }

        .reason-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help;
        }

        /* Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody td {
                display: block;
                text-align: right;
                padding-left: 50%;
                position: relative;
            }
            
            .table tbody td:before {
                content: attr(data-label);
                position: absolute;
                left: 1rem;
                width: 45%;
                text-align: left;
                font-weight: 600;
                color: #475569;
            }
        }
    </style>
</head>
<body>
    <div class="overtime-container">
        <!-- Header -->
        <div class="page-header fade-in">
            <h1>
                <i class="bi bi-clock-history"></i>
                Overtime Approval
            </h1>
            <a href="timesheet.php" class="btn-dashboard">
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card fade-in delay-1">
                <div class="stat-icon pending">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Requests</h3>
                    <div class="stat-number"><?= $totalPending ?></div>
                    <div class="stat-sub">Awaiting review</div>
                </div>
            </div>
            <div class="stat-card fade-in delay-2">
                <div class="stat-icon approved">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Approved</h3>
                    <div class="stat-number"><?= $totalApproved ?></div>
                    <div class="stat-sub">Completed</div>
                </div>
            </div>
            <div class="stat-card fade-in delay-3">
                <div class="stat-icon rejected">
                    <i class="bi bi-x-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Rejected</h3>
                    <div class="stat-number"><?= $totalRejected ?></div>
                    <div class="stat-sub">Declined</div>
                </div>
            </div>
            <div class="stat-card fade-in delay-4">
                <div class="stat-icon total">
                    <i class="bi bi-calculator"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Hours</h3>
                    <div class="stat-number"><?= number_format($totalHours, 1) ?></div>
                    <div class="stat-sub">All requests</div>
                </div>
            </div>
        </div>

        <!-- Flash Message -->
        <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message fade-in">
            <i class="bi bi-check-circle-fill"></i>
            <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
        </div>
        <?php endif; ?>

        <!-- Table Card -->
        <div class="table-card fade-in">
            <div class="table-header">
                <h5>
                    <i class="bi bi-list-check"></i>
                    Overtime Requests
                </h5>
                <div class="filter-badge">
                    <i class="bi bi-funnel"></i>
                    Showing all requests
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Hours</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests): ?>
                            <?php foreach ($requests as $r): ?>
                            <tr>
                                <td data-label="Employee">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?= strtoupper(substr($r['employee_name'], 0, 1)) ?>
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name"><?= htmlspecialchars($r['employee_name']) ?></div>
                                            <div class="employee-id">ID: <?= $r['employee_id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Date">
                                    <div class="time-badge">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('M d, Y', strtotime($r['ot_date'])) ?>
                                    </div>
                                </td>
                                <td data-label="Time">
                                    <div class="time-badge">
                                        <i class="bi bi-clock"></i>
                                        <?= $r['time_start'] ?> – <?= $r['time_end'] ?>
                                    </div>
                                </td>
                                <td data-label="Hours">
                                    <span class="hours-badge">
                                        <?= number_format($r['total_hours'], 1) ?> hrs
                                    </span>
                                </td>
                                <td data-label="Reason" class="reason-cell" title="<?= htmlspecialchars($r['reason']) ?>">
                                    <span class="reason-text">
                                        <?= htmlspecialchars($r['reason']) ?>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge 
                                        <?= $r['status']=='Approved' ? 'status-approved' : 
                                            ($r['status']=='Rejected' ? 'status-rejected' : 'status-pending') ?>">
                                        <i class="bi bi-<?= $r['status']=='Approved' ? 'check-circle' : 
                                            ($r['status']=='Rejected' ? 'x-circle' : 'hourglass') ?>"></i>
                                        <?= $r['status'] ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <?php if ($r['status'] === 'Pending'): ?>
                                            <a href="<?= basename(__FILE__) ?>?approve=<?= $r['id'] ?>" 
                                               class="btn-approve"
                                               onclick="return confirm('Approve this overtime request?')">
                                                <i class="bi bi-check-lg"></i>
                                                Approve
                                            </a>
                                            <a href="<?= basename(__FILE__) ?>?reject=<?= $r['id'] ?>" 
                                               class="btn-reject"
                                               onclick="return confirm('Reject this overtime request?')">
                                                <i class="bi bi-x-lg"></i>
                                                Reject
                                            </a>
                                        <?php else: ?>
                                            <span class="btn-disabled">
                                                <i class="bi bi-lock"></i>
                                                Processed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <h5>No overtime requests found</h5>
                                        <p>When employees submit overtime requests, they'll appear here</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>