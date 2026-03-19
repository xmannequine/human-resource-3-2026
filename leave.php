<?php
session_start();
require 'config.php';

// Set default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// === AI ANALYTICS ENDPOINT ===
if (isset($_GET['ai']) && $_GET['ai'] === 'analyze') {
    header('Content-Type: application/json');
    
    try {
        $total = (int)$conn->query("SELECT COUNT(*) FROM leave_requests")->fetchColumn();
        $approved = (int)$conn->query("SELECT COUNT(*) FROM leave_requests WHERE status='approved'")->fetchColumn();
        $rejected = (int)$conn->query("SELECT COUNT(*) FROM leave_requests WHERE status='rejected'")->fetchColumn();
        $pending  = (int)$conn->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();
        $deletedAfterReject = (int)$conn->query("
            SELECT COUNT(*) FROM leave_requests 
            WHERE deleted_at IS NOT NULL AND status='rejected'
        ")->fetchColumn();

        $approvalRate  = $total ? round(($approved / $total) * 100) : 0;
        $rejectionRate = $total ? round(($rejected / $total) * 100) : 0;

        $risk = 'Low';
        $riskColor = '#28a745';
        if ($rejectionRate > 40) {
            $risk = 'High';
            $riskColor = '#dc3545';
        } elseif ($rejectionRate > 25) {
            $risk = 'Medium';
            $riskColor = '#ffc107';
        }

        echo json_encode([
            'success' => true,
            'approval_rate' => $approvalRate,
            'rejection_rate' => $rejectionRate,
            'pending' => $pending,
            'deleted_after_reject' => $deletedAfterReject,
            'risk' => $risk,
            'risk_color' => $riskColor,
            'recommendation' => getAIRecommendation($rejectionRate, $pending, $approved),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Analytics temporarily unavailable'
        ]);
    }
    exit;
}

/**
 * Generate AI recommendations based on metrics
 */
function getAIRecommendation($rejectionRate, $pending, $approved) {
    if ($rejectionRate > 30) {
        return "⚠️ High rejection trend detected. Consider reviewing approval criteria and providing clearer guidelines to employees.";
    } elseif ($pending > 10) {
        return "📊 Unusual number of pending requests. Consider allocating more time for review.";
    } elseif ($approved > 50) {
        return "✅ Leave usage is healthy. Continue monitoring for seasonal patterns.";
    } else {
        return "📈 Leave approval trend is stable. Regular monitoring recommended.";
    }
}

/**
 * Format date to Philippine Time
 */
function formatDate($date, $includeTime = false) {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    $format = $includeTime ? 'M d, Y h:i A' : 'M d, Y';
    return date($format, $timestamp);
}

/**
 * Get time elapsed string in Philippine Time
 */
function timeElapsedString($datetime) {
    if (empty($datetime)) return '';
    
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $colors = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary'
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    return "<span class='badge bg-{$color} px-3 py-2'>{$status}</span>";
}

/**
 * Update employee leave credits - Fixed version
 */
function updateLeaveCredits($conn, $employee_id, $leave_type) {
    $leave_type = $leave_type ?: 'Vacation Leave';
    
    try {
        // Check if record exists
        $stmtCheck = $conn->prepare("
            SELECT id, used_credits 
            FROM leave_credits 
            WHERE employee_id = ? AND leave_type = ?
        ");
        $stmtCheck->execute([$employee_id, $leave_type]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing record - only update used_credits
            $stmtUpdate = $conn->prepare("
                UPDATE leave_credits 
                SET used_credits = used_credits + 1 
                WHERE employee_id = ? AND leave_type = ?
            ");
            $stmtUpdate->execute([$employee_id, $leave_type]);
        } else {
            // Insert new record with default values
            $stmtInsert = $conn->prepare("
                INSERT INTO leave_credits (employee_id, leave_type, total_credits, used_credits) 
                VALUES (?, ?, 15, 1)
            ");
            $stmtInsert->execute([$employee_id, $leave_type]);
        }
    } catch (PDOException $e) {
        // Log error but don't stop the approval process
        error_log("Leave credits update error: " . $e->getMessage());
    }
}

// Handle Approve / Reject / Delete / Restore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    error_log('leave.php POST: ' . json_encode($_POST));

    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $validator = $_SESSION['username'] ?? 'System Administrator';

    $conn->beginTransaction();

    try {
        $stmt = $conn->prepare("SELECT employee_id, leave_type, status FROM leave_requests WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $request_id]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$leave) {
            throw new Exception("Leave request not found");
        }

        switch ($action) {
            case 'approve':
            case 'reject':
                $new_status = ($action === 'approve') ? 'approved' : 'rejected';
                $remarks = ($action === 'reject') ? ($_POST['reject_remarks'] ?? '') : null;

                if ($action === 'reject' && empty($remarks)) {
                    throw new Exception("Rejection remarks are required");
                }

                $sql = "UPDATE leave_requests 
                        SET status = :status, 
                            validated_by = :validator, 
                            validated_at = :validated_at";
                
                if ($remarks !== null) {
                    $sql .= ", reject_remarks = :remarks";
                }
                
                $sql .= " WHERE id = :id";

                $params = [
                    'status' => $new_status,
                    'validator' => $validator,
                    'validated_at' => date('Y-m-d H:i:s'),
                    'id' => $request_id
                ];
                
                if ($remarks !== null) {
                    $params['remarks'] = $remarks;
                }

                $stmtUpdate = $conn->prepare($sql);
                $stmtUpdate->execute($params);

                // Update leave credits for approved requests
                if ($new_status === 'approved') {
                    updateLeaveCredits($conn, $leave['employee_id'], $leave['leave_type']);
                }
                break;

            case 'delete':
                $delete_remarks = $_POST['delete_remarks'] ?? 'No remarks provided';
                $stmt = $conn->prepare("
                    UPDATE leave_requests 
                    SET deleted_at = :deleted_at, 
                        delete_remarks = :remarks,
                        status = 'cancelled'
                    WHERE id = :id
                ");
                $stmt->execute([
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'id' => $request_id,
                    'remarks' => $delete_remarks
                ]);
                break;

            case 'restore':
                $stmt = $conn->prepare("
                    UPDATE leave_requests 
                    SET deleted_at = NULL, 
                        delete_remarks = NULL,
                        status = 'pending'
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $request_id]);
                break;

            case 'delete_permanent':
                $stmt = $conn->prepare("DELETE FROM leave_requests WHERE id = :id");
                $stmt->execute(['id' => $request_id]);
                break;
        }

        $conn->commit();
        
        $_SESSION['success_message'] = "Leave request #{$request_id} successfully " . ($action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : ($action === 'delete' ? 'deleted' : ($action === 'restore' ? 'restored' : 'updated'))));

        error_log("Action {$action} performed on leave request ID: {$request_id} by {$validator} at " . date('Y-m-d H:i:s'));

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error processing leave request: " . $e->getMessage());
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['debug_message'] = 'POST action: ' . ($action ?? 'unknown') . ' request_id: ' . ($request_id ?? 'unknown');
    }

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Build filter conditions
$where = ["lr.deleted_at IS NULL"];
$params = [];

$filterFields = [
    'employee' => "(e.firstname LIKE :employee OR e.lastname LIKE :employee OR e.id LIKE :employee)",
    'leave_type' => "lr.leave_type = :leave_type",
    'status' => "LOWER(lr.status) = :status"
];

foreach ($filterFields as $key => $condition) {
    if (!empty($_GET[$key])) {
        $where[] = $condition;
        if ($key === 'employee') {
            $params[$key] = "%" . $_GET[$key] . "%";
        } elseif ($key === 'status') {
            $params[$key] = strtolower($_GET[$key]);
        } else {
            $params[$key] = $_GET[$key];
        }
    }
}

// Date range filter
if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
    $where[] = "DATE(lr.leave_date) BETWEEN :from_date AND :to_date";
    $params['from_date'] = $_GET['from_date'];
    $params['to_date'] = $_GET['to_date'];
}

// Get active leave requests
$sql = "
    SELECT lr.id, lr.employee_id, e.firstname, e.lastname,
           lr.leave_type, lr.leave_date, lr.reason, lr.status, 
           lr.created_at, lr.validated_by, lr.validated_at, 
           lr.supporting_doc, lr.reject_remarks
    FROM leave_requests lr
    LEFT JOIN employee e ON lr.employee_id = e.id
";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY 
            CASE lr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            lr.created_at DESC 
          LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get trashed requests
$stmtTrash = $conn->prepare("
    SELECT lr.id, lr.employee_id, e.firstname, e.lastname,
           lr.leave_type, lr.leave_date, lr.reason, 
           lr.deleted_at, lr.supporting_doc, lr.delete_remarks
    FROM leave_requests lr
    LEFT JOIN employee e ON lr.employee_id = e.id
    WHERE lr.deleted_at IS NOT NULL
    ORDER BY lr.deleted_at DESC
");
$stmtTrash->execute();
$trashedRequests = $stmtTrash->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
try {
    $stats = [
        'pending' => $conn->query("SELECT COUNT(*) FROM leave_requests WHERE LOWER(status)='pending' AND deleted_at IS NULL")->fetchColumn(),
        'approved' => $conn->query("SELECT COUNT(*) FROM leave_requests WHERE LOWER(status)='approved' AND deleted_at IS NULL")->fetchColumn(),
        'rejected' => $conn->query("SELECT COUNT(*) FROM leave_requests WHERE LOWER(status)='rejected' AND deleted_at IS NULL")->fetchColumn(),
        'total' => $conn->query("SELECT COUNT(*) FROM leave_requests WHERE deleted_at IS NULL")->fetchColumn()
    ];
} catch (Exception $e) {
    $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management Dashboard | HR3 System</title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #145374;
            --primary-dark: #00334E;
            --secondary: #5588A3;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #c0392b;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --sidebar-width: 260px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 1.5rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.2);
            padding: 5px;
            margin-bottom: 1rem;
        }

        .sidebar-brand h2 {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            opacity: 0.9;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
        }

        .sidebar-nav a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            margin: 0.2rem 1rem;
            border-radius: 8px;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-nav a i {
            width: 20px;
            font-size: 1.1rem;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.85rem;
            text-align: center;
            opacity: 0.7;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 0;
        }

        .page-title p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pht-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #e9ecef;
            color: #495057;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-icon.pending { background: linear-gradient(135deg, #f1c40f, #f39c12); }
        .stat-icon.approved { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stat-icon.rejected { background: linear-gradient(135deg, #c0392b, #e74c3c); }
        .stat-icon.total { background: linear-gradient(135deg, #3498db, #2980b9); }

        .stat-details h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .stat-details p {
            margin: 0;
            color: #6c757d;
            font-weight: 500;
        }

        .filter-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .filter-title {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }

        .ai-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .ai-card:hover {
            transform: translateY(-5px);
        }

        .ai-card .card-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .ai-metrics {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .section-title {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-dark);
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #edf2f7;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(20, 83, 116, 0.3);
        }

        .btn-success {
            background: var(--success);
            border-color: var(--success);
        }

        .btn-danger {
            background: var(--danger);
            border-color: var(--danger);
        }

        .btn-warning {
            background: var(--warning);
            border-color: var(--warning);
            color: white;
        }

        .action-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .file-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .file-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
            color: white;
        }

        .file-btn.disabled {
            background: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
        }

        .file-btn.disabled:hover {
            transform: none;
            background: #e9ecef;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
        }

        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #edf2f7;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Alert Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-message alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-message alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['debug_message'])): ?>
        <div class="alert alert-info alert-message alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars($_SESSION['debug_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['debug_message']); ?>
    <?php endif; ?>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <img src="logo.jpg" alt="HR3 Logo" onerror="this.src='https://via.placeholder.com/80'">
            <h2>HUMAN RESOURCE 3</h2>
        </div>
        
        <div class="sidebar-nav">
            <a href="index.php">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="leave.php" class="active">
                <i class="fas fa-plane"></i> Leave Requests
            </a>
            <a href="#recycle">
                <i class="fas fa-trash-alt"></i> Recycle Bin
            </a>
            <a href="reports.php">
                <i class="fas fa-file-alt"></i> Reports
            </a>
            <a href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
        
        <div class="sidebar-footer">
            <i class="fas fa-clock"></i> <?= date('Y-m-d H:i') ?> <span class="pht-badge">PHT</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Leave Management Dashboard</h1>
                <p>
                    <i class="fas fa-calendar-alt"></i> <?= date('l, F d, Y') ?>
                    <span class="pht-badge"><i class="fas fa-clock"></i> PHT (UTC+8)</span>
                </p>
            </div>
            <div class="user-info">
                <span>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- AI Analytics Card -->
        <div class="ai-card" id="aiCard">
            <div class="card-title">
                <i class="fas fa-brain fa-2x"></i>
                <h4 class="mb-0">AI Analytics Dashboard</h4>
                <span class="pht-badge"><i class="fas fa-clock"></i> PHT</span>
            </div>
            <p class="mb-0">Click to analyze leave request patterns and get intelligent recommendations</p>
            <div id="aiMetrics" class="ai-metrics"></div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-details">
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?= $stats['approved'] ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-details">
                    <h3><?= $stats['rejected'] ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-details">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-title">
                <i class="fas fa-filter"></i> Filter Leave Requests
            </div>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" 
                           class="form-control" 
                           name="employee" 
                           placeholder="Search employee..." 
                           value="<?= htmlspecialchars($_GET['employee'] ?? '') ?>">
                </div>
                
                <div class="col-md-2">
                    <select name="leave_type" class="form-select">
                        <option value="">All Leave Types</option>
                        <option value="Vacation Leave" <?= ($_GET['leave_type'] ?? '') === 'Vacation Leave' ? 'selected' : '' ?>>Vacation Leave</option>
                        <option value="Sick Leave" <?= ($_GET['leave_type'] ?? '') === 'Sick Leave' ? 'selected' : '' ?>>Sick Leave</option>
                        <option value="Emergency Leave" <?= ($_GET['leave_type'] ?? '') === 'Emergency Leave' ? 'selected' : '' ?>>Emergency Leave</option>
                        <option value="Maternity Leave" <?= ($_GET['leave_type'] ?? '') === 'Maternity Leave' ? 'selected' : '' ?>>Maternity Leave</option>
                        <option value="Paternity Leave" <?= ($_GET['leave_type'] ?? '') === 'Paternity Leave' ? 'selected' : '' ?>>Paternity Leave</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <input type="date" 
                           class="form-control" 
                           name="from_date" 
                           value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>"
                           placeholder="From date">
                </div>
                
                <div class="col-md-2">
                    <input type="date" 
                           class="form-control" 
                           name="to_date" 
                           value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>"
                           placeholder="To date">
                </div>
                
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Leave Requests Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title">
                    <i class="fas fa-list"></i> Active Leave Requests
                    <span class="pht-badge"><i class="fas fa-clock"></i> All times in PHT</span>
                </h5>
                <form method="POST" action="leave_export.php" class="d-inline">
                    <button class="btn btn-success">
                        <i class="fas fa-file-word"></i> Export to Word
                    </button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover" id="activeRequestsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Requested (PHT)</th>
                            <th>Validated By</th>
                            <th>File</th>
                            <th>Actions</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveRequests as $lr): ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?= $lr['id'] ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($lr['firstname'] . ' ' . $lr['lastname']) ?></strong>
                                <br>
                                <small class="text-muted">ID: <?= htmlspecialchars($lr['employee_id']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($lr['leave_type']) ?></td>
                            <td><?= formatDate($lr['leave_date']) ?></td>
                            <td>
                                <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($lr['reason']) ?>">
                                    <?= strlen($lr['reason']) > 30 ? substr(htmlspecialchars($lr['reason']), 0, 30) . '...' : htmlspecialchars($lr['reason']) ?>
                                </span>
                            </td>
                            <td><?= getStatusBadge($lr['status']) ?></td>
                            <td>
                                <?= formatDate($lr['created_at'], true) ?>
                                <br>
                                <small class="text-muted"><?= timeElapsedString($lr['created_at']) ?></small>
                            </td>
                            <td>
                                <?php if ($lr['validated_by']): ?>
                                    <?= htmlspecialchars($lr['validated_by']) ?>
                                    <br>
                                    <small class="text-muted"><?= formatDate($lr['validated_at'], true) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($lr['supporting_doc']): ?>
                                    <a href="view_file.php?id=<?= $lr['id'] ?>" class="file-btn" target="_blank" title="View Document">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="file-btn disabled">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <?php if(strtolower($lr['status']) === 'pending'): ?>
                                        <!-- Approve Form -->
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Approve this leave request?')">
                                            <input type="hidden" name="request_id" value="<?= $lr['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-sm btn-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>

                                        <!-- Reject Button (opens modal) -->
                                        <button type="button" class="btn btn-sm btn-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#rejectModal" 
                                                data-id="<?= $lr['id'] ?>" 
                                                data-name="<?= htmlspecialchars($lr['firstname'] . ' ' . $lr['lastname']) ?>"
                                                title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>

                                    <!-- Delete Button (opens modal) -->
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal" 
                                            data-id="<?= $lr['id'] ?>" 
                                            data-name="<?= htmlspecialchars($lr['firstname'] . ' ' . $lr['lastname']) ?>"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <form method="POST" action="view_credits.php" class="d-inline">
                                    <input type="hidden" name="employee_id" value="<?= $lr['employee_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" title="View Leave Credits">
                                        <i class="fas fa-coins"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($leaveRequests)): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No active leave requests found</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recycle Bin -->
        <div class="table-container" id="recycle">
            <h5 class="section-title">
                <i class="fas fa-trash-alt"></i> Deleted Requests (Recycle Bin)
                <span class="pht-badge"><i class="fas fa-clock"></i> PHT</span>
            </h5>

            <div class="table-responsive">
                <table class="table table-hover" id="deletedRequestsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Date</th>
                            <th>Reason</th>
                            <th>Deleted At (PHT)</th>
                            <th>Delete Remarks</th>
                            <th>File</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trashedRequests as $tr): ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?= $tr['id'] ?></span></td>
                            <td>
                                <strong><?= htmlspecialchars($tr['firstname'] . ' ' . $tr['lastname']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($tr['leave_type']) ?></td>
                            <td><?= formatDate($tr['leave_date']) ?></td>
                            <td><?= htmlspecialchars($tr['reason']) ?></td>
                            <td><?= formatDate($tr['deleted_at'], true) ?></td>
                            <td>
                                <?php if ($tr['delete_remarks']): ?>
                                    <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($tr['delete_remarks']) ?>">
                                        <?= strlen($tr['delete_remarks']) > 30 ? substr(htmlspecialchars($tr['delete_remarks']), 0, 30) . '...' : htmlspecialchars($tr['delete_remarks']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($tr['supporting_doc']): ?>
                                    <a href="uploads/<?= urlencode($tr['supporting_doc']) ?>" class="file-btn" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="file-btn disabled">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Restore this request?')">
                                        <input type="hidden" name="request_id" value="<?= $tr['id'] ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-sm btn-success" title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('⚠️ Permanently delete this request? This action cannot be undone!')">
                                        <input type="hidden" name="request_id" value="<?= $tr['id'] ?>">
                                        <input type="hidden" name="action" value="delete_permanent">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Permanently Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($trashedRequests)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <i class="fas fa-trash-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Recycle bin is empty</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" onsubmit="return validateDeleteForm()">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-trash-alt"></i> Delete Leave Request
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the leave request for <strong id="delEmployee"></strong>?</p>
                        <input type="hidden" name="request_id" id="delId">
                        <input type="hidden" name="action" value="delete">
                        <div class="mb-3">
                            <label class="form-label">Delete Remarks <span class="text-danger">*</span></label>
                            <textarea name="delete_remarks" class="form-control" rows="3" placeholder="Please provide reason for deletion..." required></textarea>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            This action will move the request to recycle bin. You can restore it later.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" onsubmit="return validateRejectForm()">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-times-circle"></i> Reject Leave Request
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reject leave request for <strong id="rejEmployee"></strong>?</p>
                        <input type="hidden" name="request_id" id="rejId">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">Rejection Remarks <span class="text-danger">*</span></label>
                            <textarea name="reject_remarks" class="form-control" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize DataTables
        $(document).ready(function() {
            // Destroy existing DataTables instances if they exist
            if ($.fn.DataTable.isDataTable('#activeRequestsTable')) {
                $('#activeRequestsTable').DataTable().destroy();
            }
            
            // Initialize active requests table if it has data
            if ($('#activeRequestsTable tbody tr').length > 0 && !$('#activeRequestsTable tbody tr td[colspan]').length) {
                $('#activeRequestsTable').DataTable({
                    pageLength: 25,
                    order: [[6, 'desc']],
                    language: {
                        search: "<i class='fas fa-search'></i> Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ requests"
                    }
                });
            }

            // Destroy existing DataTables instances if they exist
            if ($.fn.DataTable.isDataTable('#deletedRequestsTable')) {
                $('#deletedRequestsTable').DataTable().destroy();
            }
            
            // Initialize deleted requests table if it has data
            if ($('#deletedRequestsTable tbody tr').length > 0 && !$('#deletedRequestsTable tbody tr td[colspan]').length) {
                $('#deletedRequestsTable').DataTable({
                    pageLength: 10,
                    order: [[5, 'desc']],
                    language: {
                        search: "<i class='fas fa-search'></i> Search:"
                    }
                });
            }
        });

        // Modal data population
        document.addEventListener('DOMContentLoaded', function() {
            // Delete Modal
            var deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    document.getElementById('delId').value = button.getAttribute('data-id');
                    document.getElementById('delEmployee').textContent = button.getAttribute('data-name');
                });
            }

            // Reject Modal
            var rejectModal = document.getElementById('rejectModal');
            if (rejectModal) {
                rejectModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    document.getElementById('rejId').value = button.getAttribute('data-id');
                    document.getElementById('rejEmployee').textContent = button.getAttribute('data-name');
                });
            }
        });

        // Form validation functions
        function validateDeleteForm() {
            var remarks = document.querySelector('#deleteModal textarea[name="delete_remarks"]').value;
            if (!remarks.trim()) {
                alert('Please provide delete remarks');
                return false;
            }
            return confirm('Are you sure you want to delete this request?');
        }

        function validateRejectForm() {
            var remarks = document.querySelector('#rejectModal textarea[name="reject_remarks"]').value;
            if (!remarks.trim()) {
                alert('Please provide rejection remarks');
                return false;
            }
            return confirm('Are you sure you want to reject this request?');
        }

        // AI Analytics
        document.getElementById('aiCard').addEventListener('click', function() {
            const aiMetrics = document.getElementById('aiMetrics');
            const card = this;
            
            card.style.opacity = '0.7';
            aiMetrics.innerHTML = '<div class="text-center"><div class="spinner"></div> Analyzing data...</div>';
            aiMetrics.style.display = 'block';
            
            fetch('?ai=analyze')
                .then(response => response.json())
                .then(data => {
                    card.style.opacity = '1';
                    
                    if (data.success) {
                        aiMetrics.innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="metric-item">
                                        <span>Approval Rate:</span>
                                        <strong>${data.approval_rate}%</strong>
                                    </div>
                                    <div class="metric-item">
                                        <span>Rejection Rate:</span>
                                        <strong>${data.rejection_rate}%</strong>
                                    </div>
                                    <div class="metric-item">
                                        <span>Pending Requests:</span>
                                        <strong>${data.pending}</strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="metric-item">
                                        <span>Deleted After Rejection:</span>
                                        <strong>${data.deleted_after_reject}</strong>
                                    </div>
                                    <div class="metric-item">
                                        <span>Risk Level:</span>
                                        <strong style="color: ${data.risk_color}">${data.risk}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-light mt-3 mb-0">
                                <i class="fas fa-robot"></i> ${data.recommendation}
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="fas fa-clock"></i> Last updated: ${data.timestamp} PHT
                            </small>
                        `;
                    } else {
                        aiMetrics.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${data.error || 'Unable to load analytics'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    card.style.opacity = '1';
                    aiMetrics.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Error loading analytics. Please try again.
                        </div>
                    `;
                    console.error('Error:', error);
                });
        });

        // Auto-hide alert messages after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(function(alert) {
                if (alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>