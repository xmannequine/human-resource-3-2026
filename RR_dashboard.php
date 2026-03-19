<?php
session_start();
require_once('config.php');

// Set default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Get current user email for password verification
$currentUserEmail = $_SESSION['email'] ?? '';

$message = '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

// === AI ANALYTICS ENDPOINT ===
if (isset($_GET['ai']) && $_GET['ai'] === 'analyze') {
    header('Content-Type: application/json');
    
    try {
        $total = (int)$conn->query("SELECT COUNT(*) FROM reimbursements WHERE is_deleted=0")->fetchColumn();
        $approved = (int)$conn->query("SELECT COUNT(*) FROM reimbursements WHERE status='approved' AND is_deleted=0")->fetchColumn();
        $rejected = (int)$conn->query("SELECT COUNT(*) FROM reimbursements WHERE status='rejected' AND is_deleted=0")->fetchColumn();
        $pending  = (int)$conn->query("SELECT COUNT(*) FROM reimbursements WHERE status='pending' AND is_deleted=0")->fetchColumn();
        $deletedAfterReject = (int)$conn->query("SELECT COUNT(*) FROM reimbursements WHERE is_deleted=1 AND status='rejected'")->fetchColumn();

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

// === PASSWORD VERIFICATION ENDPOINT ===
if (isset($_POST['verify_password']) && isset($_POST['password'])) {
    header('Content-Type: application/json');
    
    try {
        $email = $_SESSION['email'] ?? '';
        $enteredPassword = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($enteredPassword, $user['password'])) {
            // Generate a temporary token (valid for 5 minutes)
            $token = bin2hex(random_bytes(32));
            $_SESSION['receipt_access_token'] = $token;
            $_SESSION['receipt_token_expiry'] = time() + 300; // 5 minutes
            
            echo json_encode([
                'success' => true,
                'token' => $token
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid password'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Verification failed'
        ]);
    }
    exit;
}

/**
 * Generate AI recommendations based on metrics
 */
function getAIRecommendation($rejectionRate, $pending, $approved) {
    if ($rejectionRate > 30) {
        return "⚠️ High rejection trend detected. Consider reviewing reimbursement approval criteria and providing clearer guidelines to employees.";
    } elseif ($pending > 10) {
        return "📊 Unusual number of pending requests. Consider allocating more time for review.";
    } elseif ($approved > 50) {
        return "✅ Reimbursement processing is healthy. Continue monitoring for unusual patterns.";
    } else {
        return "📈 Reimbursement trend is stable. Regular monitoring recommended.";
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
        'rejected' => 'danger'
    ];
    $color = $colors[strtolower($status)] ?? 'secondary';
    $icons = [
        'pending' => 'hourglass-split',
        'approved' => 'check-circle',
        'rejected' => 'x-circle'
    ];
    $icon = $icons[strtolower($status)] ?? 'question-circle';
    
    return "<span class='badge bg-{$color} bg-opacity-10 text-{$color} px-3 py-2 rounded-pill'>
                <i class='bi bi-{$icon} me-1'></i>{$status}
            </span>";
}

// Handle Approve / Reject / Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $conn->beginTransaction();
    
    try {
        $requestId = $_POST['request_id'];
        $action = $_POST['action'];
        $remarks = $_POST['remarks'] ?? null;
        $validator = $_SESSION['admin_name'] ?? $_SESSION['username'] ?? 'System Administrator';

        // Check current status
        $stmt = $conn->prepare("SELECT status FROM reimbursements WHERE request_id=? AND is_deleted=0 FOR UPDATE");
        $stmt->execute([$requestId]);
        $currentStatus = strtolower($stmt->fetchColumn() ?? 'pending');

        if ($action === 'delete') {
            if (empty($remarks)) {
                throw new Exception("Delete remarks are required");
            }
            $stmt = $conn->prepare("UPDATE reimbursements SET is_deleted=1, deleted_remarks=?, deleted_at=? WHERE request_id=?");
            $stmt->execute([$remarks, date('Y-m-d H:i:s'), $requestId]);
            $_SESSION['success_message'] = "Request #$requestId has been moved to Recycle Bin.";
            
        } elseif ($currentStatus === 'pending' && in_array($action, ['approved', 'rejected'])) {
            if ($action === 'rejected') {
                if (empty($remarks)) {
                    throw new Exception("Rejection remarks are required");
                }
                $stmt = $conn->prepare("UPDATE reimbursements SET status=?, validated_by=?, validated_at=?, rejected_remarks=? WHERE request_id=? AND is_deleted=0");
                $stmt->execute([$action, $validator, date('Y-m-d H:i:s'), $remarks, $requestId]);
            } else {
                $stmt = $conn->prepare("UPDATE reimbursements SET status=?, validated_by=?, validated_at=? WHERE request_id=? AND is_deleted=0");
                $stmt->execute([$action, $validator, date('Y-m-d H:i:s'), $requestId]);
            }
            $_SESSION['success_message'] = "Request #$requestId has been " . ucfirst($action) . " by $validator.";
            
            error_log("Reimbursement {$action}: Request #{$requestId} by {$validator} at " . date('Y-m-d H:i:s'));
            
        } else {
            $_SESSION['error_message'] = "Request #$requestId is already " . ucfirst($currentStatus) . ".";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        error_log("Reimbursement action error: " . $e->getMessage());
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Handle Export to Word
if (isset($_POST['export_word'])) {
    exportToWord($conn, $_POST['start_date'] ?? '', $_POST['end_date'] ?? '');
}

/**
 * Export data to Word document with Philippine Time
 */
function exportToWord($conn, $startDate, $endDate) {
    header("Content-Type: application/vnd.ms-word");
    header("Content-Disposition: attachment; filename=reimbursements_" . date('Y-m-d') . ".doc");

    $query = "SELECT * FROM reimbursements WHERE is_deleted=0";
    $params = [];
    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND DATE(date_submitted) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
    }
    $query .= " ORDER BY id DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAmount = array_sum(array_column($rows, 'amount'));
    $approvedAmount = array_sum(array_map(function($r) {
        return strtolower($r['status']) === 'approved' ? $r['amount'] : 0;
    }, $rows));

    echo "<html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #145374; }
            h2 { color: #00334E; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th { background: #145374; color: white; padding: 10px; }
            td { padding: 8px; border: 1px solid #ddd; }
            .summary { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #145374; }
            .total { font-weight: bold; color: #145374; }
            .ph-time { font-size: 0.9em; color: #666; margin-top: 5px; }
        </style>
    </head>
    <body>
        <h1>Reimbursement Requests Report</h1>
        <p>Generated on: " . date('F d, Y h:i A') . " PHT</p>
        <p class='ph-time'><i>Philippine Time (UTC+8)</i></p>
        
        <div class='summary'>
            <h3>Summary</h3>
            <p>Total Requests: " . count($rows) . "</p>
            <p>Total Amount: ₱" . number_format($totalAmount, 2) . "</p>
            <p>Approved Amount: ₱" . number_format($approvedAmount, 2) . "</p>
        </div>
        
        <table>
            <tr>
                <th>Request ID</th>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Purpose</th>
                <th>Amount (₱)</th>
                <th>Date Submitted (PHT)</th>
                <th>Status</th>
                <th>Validated By</th>
                <th>Validated At (PHT)</th>
            </tr>";
    
    foreach ($rows as $r) {
        echo "<tr>
                <td>{$r['request_id']}</td>
                <td>{$r['employee_id']}</td>
                <td>" . htmlspecialchars($r['employee_name']) . "</td>
                <td>" . htmlspecialchars($r['purpose']) . "</td>
                <td align='right'>" . number_format($r['amount'], 2) . "</td>
                <td>" . date('M d, Y h:i A', strtotime($r['date_submitted'])) . "</td>
                <td>" . ucfirst($r['status']) . "</td>
                <td>" . ($r['validated_by'] ?? '-') . "</td>
                <td>" . ($r['validated_at'] ? date('M d, Y h:i A', strtotime($r['validated_at'])) : '-') . "</td>
              </tr>";
    }
    
    echo "</table>
        <p class='total'>Total Amount: ₱" . number_format($totalAmount, 2) . "</p>
        <p class='ph-time'><i>All times are in Philippine Time (PHT, UTC+8)</i></p>
    </body></html>";
    exit;
}

// Fetch reimbursement requests with employee details
$query = "SELECT r.*, 
          DATE(r.date_submitted) as submitted_date,
          TIME(r.date_submitted) as submitted_time
          FROM reimbursements r 
          WHERE r.is_deleted=0";
$params = [];

if ($start_date && $end_date) {
    $query .= " AND DATE(r.date_submitted) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
}
$query .= " ORDER BY 
            CASE r.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            r.date_submitted DESC";
            
$stmt = $conn->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalRequests = count($requests);
$pendingRequests = count(array_filter($requests, fn($r)=>strtolower($r['status'])==='pending'));
$approvedRequests = count(array_filter($requests, fn($r)=>strtolower($r['status'])==='approved'));
$rejectedRequests = count(array_filter($requests, fn($r)=>strtolower($r['status'])==='rejected'));

// Compute amounts
$approvedAmount = array_sum(array_map(fn($r)=>strtolower($r['status'])==='approved' ? $r['amount'] : 0, $requests));
$pendingAmount  = array_sum(array_map(fn($r)=>strtolower($r['status'])==='pending' ? $r['amount'] : 0, $requests));
$totalAmount = array_sum(array_column($requests, 'amount'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimbursement Dashboard | HR3 System</title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
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
            --info: #3498db;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #2c3e50;
        }

        /* Sidebar Styles */
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
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            padding: 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-brand img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.2);
            padding: 5px;
            margin-bottom: 1rem;
            object-fit: cover;
        }

        .sidebar-brand h2 {
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            opacity: 0.9;
            margin: 0;
            line-height: 1.4;
        }

        .sidebar-brand small {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0;
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
            font-size: 0.95rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-nav a i {
            width: 20px;
            font-size: 1.2rem;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.8rem;
            text-align: center;
            opacity: 0.7;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title h1 {
            font-size: 1.6rem;
            font-weight: 700;
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

        .timezone-badge {
            background: #e9ecef;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            color: #495057;
            margin-left: 0.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.05);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.pending { background: linear-gradient(135deg, #f1c40f, #f39c12); }
        .stat-icon.approved { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stat-icon.rejected { background: linear-gradient(135deg, #c0392b, #e74c3c); }

        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            color: #2c3e50;
        }

        .stat-details p {
            margin: 0;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Amount Cards */
        .amount-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .amount-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .amount-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.05);
        }

        .amount-info h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-dark);
        }

        .amount-info p {
            margin: 0.5rem 0 0;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .amount-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .amount-icon.approved { background: rgba(39, 174, 96, 0.1); color: #27ae60; }
        .amount-icon.pending { background: rgba(243, 156, 18, 0.1); color: #f39c12; }

        /* Filter Card */
        .filter-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .filter-title {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* AI Analytics Card */
        .ai-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .ai-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .ai-card .card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .ai-card .card-title i {
            font-size: 2rem;
        }

        .ai-metrics {
            display: none;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .metric-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .metric-label {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 0.3rem;
        }

        .metric-value {
            font-size: 1.3rem;
            font-weight: 700;
        }

        /* Tables */
        .table-container {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        .section-title {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8f9fa;
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #e9ecef;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
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

        .btn-info {
            background: var(--info);
            border-color: var(--info);
            color: white;
        }

        .action-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Receipt button */
        .receipt-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            background: rgba(20, 83, 116, 0.1);
            color: var(--primary);
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .receipt-btn:hover {
            background: var(--primary);
            color: white;
        }

        .receipt-btn.locked {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            cursor: not-allowed;
        }

        .receipt-btn.locked:hover {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            transform: none;
        }

        /* Password Modal */
        .password-modal .modal-header {
            background: var(--primary-dark);
            color: white;
            border-bottom: none;
        }

        .password-modal .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .password-modal .modal-body {
            padding: 2rem;
        }

        .password-input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .password-input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 1.2rem;
        }

        .password-input-group input {
            padding-left: 45px;
            height: 50px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .password-input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20, 83, 116, 0.1);
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .security-note {
            background: #e3f2fd;
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .security-note i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem;
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary);
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        .spinner-dark {
            border-top-color: var(--primary);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alert Message */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 350px;
            animation: slideInRight 0.3s ease;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Philippine Time Indicator */
        .pht-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #e9ecef;
            color: #495057;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: normal;
            margin-left: 0.5rem;
        }

        .pht-badge i {
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid,
            .amount-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- Alert Message -->
<?php if(isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-message alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-message alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>
    <?= htmlspecialchars($_SESSION['error_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Password Verification Modal -->
<div class="modal fade password-modal" id="passwordVerificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock me-2"></i>
                    Secure File Access
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="security-note">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>For security purposes, please verify your password to access receipt files.</span>
                </div>
                
                <div class="password-input-group">
                    <i class="bi bi-lock"></i>
                    <input type="password" 
                           class="form-control" 
                           id="verificationPassword" 
                           placeholder="Enter your password"
                           autocomplete="off">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('verificationPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                
                <div id="passwordError" class="text-danger small mt-2" style="display: none;">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    Invalid password. Please try again.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="verifyPasswordBtn" onclick="verifyPassword()">
                    <i class="bi bi-check-lg me-1"></i>
                    Verify & Access
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <img src="logo.jpg" alt="HR3 Logo" onerror="this.src='https://via.placeholder.com/90'">
        <h2>HR3 SYSTEM</h2>
        <small>Reimbursement Module</small>
    </div>
    
    <div class="sidebar-nav">
        <a href="index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="reimbursements.php" class="active">
            <i class="bi bi-cash-stack"></i> Reimbursements
        </a>
        <a href="reimbursements_recycle_bin.php">
            <i class="bi bi-trash"></i> Recycle Bin
        </a>
        <a href="reports.php">
            <i class="bi bi-file-text"></i> Reports
        </a>
        <a href="settings.php">
            <i class="bi bi-gear"></i> Settings
        </a>
    </div>
    
    <div class="sidebar-footer">
        <i class="bi bi-clock"></i> <?= date('Y-m-d H:i') ?> PHT
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="page-title">
            <h1>Reimbursement Dashboard</h1>
            <p>
                <i class="bi bi-calendar3"></i> <?= date('l, F d, Y') ?>
                <span class="mx-2">|</span>
                <i class="bi bi-clock"></i> <?= date('h:i A') ?> 
                <span class="timezone-badge">PHT (UTC+8)</span>
            </p>
        </div>
        <div class="user-info">
            <div class="user-badge">
                <span>Welcome, <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></strong></span>
                <div class="user-avatar">
                    <i class="bi bi-person"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Analytics Card -->
    <div class="ai-card" id="aiCard">
        <div class="card-title">
            <i class="bi bi-cpu"></i>
            <span>AI Analytics Dashboard</span>
            <span class="pht-badge"><i class="bi bi-clock"></i> PHT</span>
        </div>
        <p class="mb-0 opacity-75">Click to analyze reimbursement patterns and get intelligent recommendations</p>
        <div id="aiMetrics" class="ai-metrics">
            <div class="metric-grid" id="aiMetricsContent"></div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="bi bi-files"></i>
            </div>
            <div class="stat-details">
                <h3><?= $totalRequests ?></h3>
                <p>Total Requests</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon pending">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-details">
                <h3><?= $pendingRequests ?></h3>
                <p>Pending</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon approved">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?= $approvedRequests ?></h3>
                <p>Approved</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon rejected">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-details">
                <h3><?= $rejectedRequests ?></h3>
                <p>Rejected</p>
            </div>
        </div>
    </div>

    <!-- Amount Cards -->
    <div class="amount-grid">
        <div class="amount-card">
            <div class="amount-info">
                <h4>₱<?= number_format($approvedAmount, 2) ?></h4>
                <p><i class="bi bi-check-circle-fill text-success me-1"></i>Total Approved Amount</p>
            </div>
            <div class="amount-icon approved">
                <i class="bi bi-cash"></i>
            </div>
        </div>
        
        <div class="amount-card">
            <div class="amount-info">
                <h4>₱<?= number_format($pendingAmount, 2) ?></h4>
                <p><i class="bi bi-hourglass-split text-warning me-1"></i>Pending for Approval</p>
            </div>
            <div class="amount-icon pending">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-title">
            <i class="bi bi-funnel"></i> Filter Reimbursement Requests
        </div>
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                    <a href="reimbursements.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Actions Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="export_word" value="1">
                <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                <button type="submit" class="btn btn-info">
                    <i class="bi bi-file-word"></i> Export to Word
                </button>
            </form>
            <a href="reimbursements_recycle_bin.php" class="btn btn-secondary">
                <i class="bi bi-trash"></i> Recycle Bin
            </a>
        </div>
        <span class="text-muted">
            <i class="bi bi-info-circle"></i> Total Amount: ₱<?= number_format($totalAmount, 2) ?>
        </span>
    </div>

    <!-- Reimbursement Requests Table -->
    <div class="table-container">
        <h5 class="section-title">
            <i class="bi bi-list-ul"></i> Reimbursement Requests
            <span class="pht-badge"><i class="bi bi-clock"></i> All times in PHT</span>
        </h5>

        <div class="table-responsive">
            <table class="table table-hover" id="reimbursementTable">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Employee</th>
                        <th>Purpose</th>
                        <th>Amount</th>
                        <th>Date Submitted (PHT)</th>
                        <th>Status</th>
                        <th>Receipt</th>
                        <th>Validated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($requests as $req): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                #<?= $req['request_id'] ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($req['employee_name']) ?></strong>
                            <br>
                            <small class="text-muted">ID: <?= $req['employee_id'] ?></small>
                        </td>
                        <td>
                            <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($req['purpose']) ?>">
                                <?= strlen($req['purpose']) > 30 ? substr(htmlspecialchars($req['purpose']), 0, 30) . '...' : htmlspecialchars($req['purpose']) ?>
                            </span>
                        </td>
                        <td>
                            <strong>₱<?= number_format($req['amount'], 2) ?></strong>
                        </td>
                        <td>
                            <?= date('M d, Y', strtotime($req['date_submitted'])) ?>
                            <br>
                            <small class="text-muted"><?= date('h:i A', strtotime($req['date_submitted'])) ?></small>
                        </td>
                        <td><?= getStatusBadge($req['status']) ?></td>
                        <td>
                            <?php if (!empty($req['receipt_path'])): ?>
                                <?php
                                    $parts = pathinfo($req['receipt_path']);
                                    $dir = $parts['dirname'];
                                    $file = rawurlencode($parts['basename']);
                                    $fullPath = $dir . '/' . $file;
                                ?>
                                <button onclick="requestReceiptAccess('<?= htmlspecialchars($fullPath) ?>')" class="receipt-btn">
                                    <i class="bi bi-receipt"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted fst-italic">
                                    <i class="bi bi-ban"></i> No receipt
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($req['validated_by'])): ?>
                                <?= htmlspecialchars($req['validated_by']) ?>
                                <br>
                                <small class="text-muted"><?= date('M d, Y h:i A', strtotime($req['validated_at'])) ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-group">
                                <?php if(strtolower($req['status']) === 'pending'): ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#approveModal" 
                                            data-id="<?= $req['request_id'] ?>"
                                            data-name="<?= htmlspecialchars($req['employee_name']) ?>">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal" 
                                            data-id="<?= $req['request_id'] ?>"
                                            data-name="<?= htmlspecialchars($req['employee_name']) ?>">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-sm btn-danger" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteModal" 
                                        data-id="<?= $req['request_id'] ?>"
                                        data-name="<?= htmlspecialchars($req['employee_name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted mb-3 d-block"></i>
                            <p class="text-muted">No reimbursement requests found</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" onsubmit="return confirm('Approve this reimbursement request?')">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle"></i> Approve Reimbursement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve the reimbursement request for <strong id="approveEmployee"></strong>?</p>
                    <input type="hidden" name="request_id" id="approveId">
                    <input type="hidden" name="action" value="approved">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg"></i> Confirm Approval
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
                        <i class="bi bi-x-circle"></i> Reject Reimbursement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reject reimbursement request for <strong id="rejectEmployee"></strong>?</p>
                    <input type="hidden" name="request_id" id="rejectId">
                    <input type="hidden" name="action" value="rejected">
                    <div class="mb-3">
                        <label class="form-label">Rejection Remarks <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg"></i> Confirm Rejection
                    </button>
                </div>
            </form>
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
                        <i class="bi bi-trash"></i> Delete Reimbursement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the reimbursement request for <strong id="deleteEmployee"></strong>?</p>
                    <input type="hidden" name="request_id" id="deleteId">
                    <input type="hidden" name="action" value="delete">
                    <div class="mb-3">
                        <label class="form-label">Delete Remarks <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Please provide reason for deletion..." required></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This action will move the request to recycle bin. You can restore it later.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Request
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
    // Variables for receipt access
    let pendingReceiptPath = '';
    const passwordModal = new bootstrap.Modal(document.getElementById('passwordVerificationModal'));

    // Request receipt access
    function requestReceiptAccess(receiptPath) {
        pendingReceiptPath = receiptPath;
        document.getElementById('verificationPassword').value = '';
        document.getElementById('passwordError').style.display = 'none';
        passwordModal.show();
    }

    // Toggle password visibility
    function togglePasswordVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }

    // Verify password
    function verifyPassword() {
        const password = document.getElementById('verificationPassword').value;
        const verifyBtn = document.getElementById('verifyPasswordBtn');
        const errorDiv = document.getElementById('passwordError');
        
        if (!password) {
            errorDiv.textContent = 'Please enter your password';
            errorDiv.style.display = 'block';
            return;
        }

        // Show loading state
        verifyBtn.innerHTML = '<span class="spinner"></span> Verifying...';
        verifyBtn.disabled = true;
        errorDiv.style.display = 'none';

        // Send verification request
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'verify_password=1&password=' + encodeURIComponent(password)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Password verified, open receipt in new tab
                window.open(pendingReceiptPath, '_blank');
                passwordModal.hide();
                
                // Reset button
                verifyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Verify & Access';
                verifyBtn.disabled = false;
            } else {
                // Show error
                errorDiv.textContent = data.message || 'Invalid password';
                errorDiv.style.display = 'block';
                
                // Reset button
                verifyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Verify & Access';
                verifyBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorDiv.textContent = 'Verification failed. Please try again.';
            errorDiv.style.display = 'block';
            
            // Reset button
            verifyBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Verify & Access';
            verifyBtn.disabled = false;
        });
    }

    // Form validation functions
    function validateRejectForm() {
        var remarks = document.querySelector('#rejectModal textarea[name="remarks"]').value;
        if (!remarks.trim()) {
            alert('Please provide rejection remarks');
            return false;
        }
        return confirm('Are you sure you want to reject this request?');
    }

    function validateDeleteForm() {
        var remarks = document.querySelector('#deleteModal textarea[name="remarks"]').value;
        if (!remarks.trim()) {
            alert('Please provide delete remarks');
            return false;
        }
        return confirm('Are you sure you want to delete this request?');
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize DataTable
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#reimbursementTable')) {
            $('#reimbursementTable').DataTable().destroy();
        }
        
        if ($('#reimbursementTable tbody tr').length > 0 && !$('#reimbursementTable tbody tr td[colspan]').length) {
            $('#reimbursementTable').DataTable({
                pageLength: 25,
                order: [[4, 'desc']],
                language: {
                    search: "<i class='bi bi-search'></i> Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ requests",
                    infoEmpty: "Showing 0 to 0 of 0 requests",
                    infoFiltered: "(filtered from _MAX_ total requests)",
                    paginate: {
                        first: '<i class="bi bi-chevron-double-left"></i>',
                        previous: '<i class="bi bi-chevron-left"></i>',
                        next: '<i class="bi bi-chevron-right"></i>',
                        last: '<i class="bi bi-chevron-double-right"></i>'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [6, 8] }
                ]
            });
        }
    });

    // Modal data population
    document.addEventListener('DOMContentLoaded', function() {
        // Approve Modal
        var approveModal = document.getElementById('approveModal');
        if (approveModal) {
            approveModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('approveId').value = button.getAttribute('data-id');
                document.getElementById('approveEmployee').textContent = button.getAttribute('data-name');
            });
        }

        // Reject Modal
        var rejectModal = document.getElementById('rejectModal');
        if (rejectModal) {
            rejectModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('rejectId').value = button.getAttribute('data-id');
                document.getElementById('rejectEmployee').textContent = button.getAttribute('data-name');
                
                // Clear previous remarks
                var textarea = rejectModal.querySelector('textarea[name="remarks"]');
                if (textarea) {
                    textarea.value = '';
                }
            });
        }

        // Delete Modal
        var deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                document.getElementById('deleteId').value = button.getAttribute('data-id');
                document.getElementById('deleteEmployee').textContent = button.getAttribute('data-name');
                
                // Clear previous remarks
                var textarea = deleteModal.querySelector('textarea[name="remarks"]');
                if (textarea) {
                    textarea.value = '';
                }
            });
        }
    });

    // AI Analytics
    document.getElementById('aiCard').addEventListener('click', function() {
        const aiMetrics = document.getElementById('aiMetrics');
        const aiMetricsContent = document.getElementById('aiMetricsContent');
        const card = this;
        
        // Show loading state
        card.style.opacity = '0.7';
        aiMetrics.style.display = 'block';
        aiMetricsContent.innerHTML = `
            <div class="col-12 text-center py-3">
                <div class="spinner"></div>
                <p class="mt-2">Analyzing data...</p>
            </div>
        `;
        
        fetch('?ai=analyze')
            .then(response => response.json())
            .then(data => {
                card.style.opacity = '1';
                
                if (data.success) {
                    aiMetricsContent.innerHTML = `
                        <div class="metric-item">
                            <div class="metric-label">Approval Rate</div>
                            <div class="metric-value">${data.approval_rate}%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Rejection Rate</div>
                            <div class="metric-value">${data.rejection_rate}%</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Pending</div>
                            <div class="metric-value">${data.pending}</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-label">Risk Level</div>
                            <div class="metric-value" style="color: ${data.risk_color}">${data.risk}</div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="alert alert-light mb-0">
                                <i class="bi bi-robot"></i> ${data.recommendation}
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-clock"></i> Last updated: ${data.timestamp} PHT
                            </small>
                        </div>
                    `;
                } else {
                    aiMetricsContent.innerHTML = `
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle"></i> ${data.error || 'Unable to load analytics'}
                            </div>
                        </div>
                    `;
                }
            })
            .catch(error => {
                card.style.opacity = '1';
                aiMetricsContent.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> Error loading analytics. Please try again.
                        </div>
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

    // Clear password field when modal is hidden
    document.getElementById('passwordVerificationModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('verificationPassword').value = '';
        document.getElementById('passwordError').style.display = 'none';
    });

    // Allow Enter key in password field
    document.getElementById('verificationPassword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyPassword();
        }
    });
</script>

</body>
</html>