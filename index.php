<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['email']) || !isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['email'];

/* ===== USER INFO ===== */
$stmt = $conn->prepare("SELECT username, profile_image FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $user['username'];
$profileImage = !empty($user['profile_image'])
    ? 'uploads/' . htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8')
    : 'uploads/default-avatar.png';

/* ===== DATE ===== */
$today = date('Y-m-d');

/* ===== KPI DATA ===== */
$totalEmployees = $conn->query("SELECT COUNT(*) FROM employee")->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE date = :today AND time_in IS NOT NULL");
$stmt->execute([':today' => $today]);
$attendanceToday = $stmt->fetchColumn();

$pendingLeaves = $conn->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'Pending'")->fetchColumn();
$pendingClaims = $conn->query("SELECT COUNT(*) FROM reimbursements WHERE status = 'Pending'")->fetchColumn();

/* ===== CHART DATA ===== */
$attendanceData = [];
$attendanceLabels = [];
$leaveData = [];
$claimsData = [];

for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $attendanceLabels[] = date('D', strtotime($date));

    $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE date = :date AND time_in IS NOT NULL");
    $stmt->execute([':date' => $date]);
    $attendanceData[] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE leave_date = :date AND status = 'Pending'");
    $stmt->execute([':date' => $date]);
    $leaveData[] = $stmt->fetchColumn();

    $stmt = $conn->prepare("SELECT COUNT(*) FROM reimbursements WHERE date_submitted = :date AND status = 'Pending'");
    $stmt->execute([':date' => $date]);
    $claimsData[] = $stmt->fetchColumn();
}

/* ===== NOTIFICATIONS ===== */
$notifications = [];

if ($pendingLeaves > 0) {
    $notifications[] = [
        'message' => 'Pending leave requests require approval',
        'type' => 'warning',
        'icon' => 'fa-clock'
    ];
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM employee e LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = :today WHERE a.time_in IS NULL");
$stmt->execute([':today' => $today]);
$absentToday = $stmt->fetchColumn();
if ($absentToday > 0) {
    $notifications[] = [
        'message' => "$absentToday employee(s) are absent today",
        'type' => 'error',
        'icon' => 'fa-user-slash'
    ];
}

if ($pendingClaims > 0) {
    $notifications[] = [
        'message' => 'Pending claims awaiting review',
        'type' => 'info',
        'icon' => 'fa-file-invoice'
    ];
}

/* ===== RECENT ACTIVITY ===== */
$stmt = $conn->query("
    SELECT 'Leave Request' AS activity, created_at AS activity_date, 
           'fa-calendar-check' as icon, 'warning' as type FROM leave_requests WHERE status = 'Pending'
    UNION ALL
    SELECT 'Claim Submitted', date_submitted, 'fa-receipt', 'info' FROM reimbursements WHERE status = 'Pending'
    ORDER BY activity_date DESC
    LIMIT 6
");
$activityLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== AI-INSIGHTS ENHANCED ===== */
$aiInsights = [];

// Weekly averages
$week1Attendance = array_slice($attendanceData, 0, 7);
$week2Attendance = array_slice($attendanceData, 7);
$avgWeek1Attendance = array_sum($week1Attendance) / max(count($week1Attendance), 1);
$avgWeek2Attendance = array_sum($week2Attendance) / max(count($week2Attendance), 1);

$week1Leaves = array_slice($leaveData, 0, 7);
$week2Leaves = array_slice($leaveData, 7);
$avgWeek1Leaves = array_sum($week1Leaves) / max(count($week1Leaves), 1);
$avgWeek2Leaves = array_sum($week2Leaves) / max(count($week2Leaves), 1);

$week1Claims = array_slice($claimsData, 0, 7);
$week2Claims = array_slice($claimsData, 7);
$avgWeek1Claims = array_sum($week1Claims) / max(count($week1Claims), 1);
$avgWeek2Claims = array_sum($week2Claims) / max(count($week2Claims), 1);

// Calculate trends
$attendanceTrend = $avgWeek2Attendance - $avgWeek1Attendance;
$leaveTrend = $avgWeek2Leaves - $avgWeek1Leaves;
$claimsTrend = $avgWeek2Claims - $avgWeek1Claims;

// Enhanced insights with actionable recommendations
$insights = [
    [
        'metric' => 'Attendance',
        'value' => round($avgWeek2Attendance, 1),
        'trend' => $attendanceTrend,
        'status' => $attendanceToday >= $avgWeek2Attendance ? 'positive' : 'warning',
        'message' => $attendanceToday >= $avgWeek2Attendance 
            ? 'Attendance is above average today' 
            : 'Attendance is below average',
        'action' => $attendanceToday < $avgWeek2Attendance ? 'Consider sending a reminder' : null
    ],
    [
        'metric' => 'Leave Requests',
        'value' => $pendingLeaves,
        'trend' => $leaveTrend,
        'status' => $pendingLeaves <= $avgWeek2Leaves * 1.2 ? 'positive' : 'warning',
        'message' => $pendingLeaves <= $avgWeek2Leaves * 1.2 
            ? 'Leave requests are normal' 
            : 'Higher than usual leave requests',
        'action' => $pendingLeaves > $avgWeek2Leaves * 1.2 ? 'Review pending approvals' : null
    ],
    [
        'metric' => 'Claims',
        'value' => $pendingClaims,
        'trend' => $claimsTrend,
        'status' => $pendingClaims <= $avgWeek2Claims * 1.2 ? 'positive' : 'warning',
        'message' => $pendingClaims <= $avgWeek2Claims * 1.2 
            ? 'Claims volume is normal' 
            : 'Claims are piling up',
        'action' => $pendingClaims > $avgWeek2Claims * 1.2 ? 'Process claims queue' : null
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR3 Dashboard · Teal Blue Edition</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * { font-family: 'Inter', sans-serif; }
        
        /* Custom Teal Blue Color Palette */
        :root {
            --deep-teal: #0D4C5E;
            --teal: #1B6B7F;
            --light-teal: #2A8B9F;
            --soft-teal: #E6F3F5;
            --mint: #D4F0F0;
            --ocean: #0F5C6B;
            --seafoam: #A8E6E6;
            --coral-accent: #FF7F5C;
        }
        
        /* Smooth transitions */
        .transition-all { transition: all 0.2s ease; }
        
        /* Card hover effects */
        .hover-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hover-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(13, 76, 94, 0.1), 0 8px 10px -6px rgba(13, 76, 94, 0.05);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e6f3f5; }
        ::-webkit-scrollbar-thumb { background: #1B6B7F; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #0D4C5E; }
        
        /* Sidebar active link - Teal Gradient */
        .nav-link-active {
            background: linear-gradient(90deg, #0D4C5E 0%, #1B6B7F 100%);
            box-shadow: 0 4px 6px -1px rgba(13, 76, 94, 0.2);
        }
        
        /* Stats card glow - Teal version */
        .stat-glow {
            position: relative;
            overflow: hidden;
        }
        .stat-glow::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(27, 107, 127, 0.08) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .stat-glow:hover::after {
            opacity: 1;
        }
        
        /* Animation for notifications */
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease forwards;
        }

        /* Chart container sizing */
        .chart-container {
            position: relative;
            height: 180px;
            width: 100%;
        }

        /* Custom badge styles */
        .teal-badge {
            background-color: var(--soft-teal);
            color: var(--deep-teal);
        }
        
        /* Glass morphism effect for AI panel */
        .glass-panel {
            background: linear-gradient(145deg, rgba(13, 76, 94, 0.95), rgba(27, 107, 127, 0.95));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Button hover effect */
        .teal-button {
            background: var(--deep-teal);
            color: white;
            transition: all 0.3s ease;
        }
        .teal-button:hover {
            background: var(--teal);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 76, 94, 0.2);
        }
    </style>
</head>
<body class="bg-[#f0f7f9] text-slate-700 antialiased">

<!-- ================= SIDEBAR ENHANCED WITH TEAL ================= -->
<aside class="fixed inset-y-0 left-0 w-72 bg-gradient-to-b from-[#0D4C5E] to-[#1B6B7F] text-white shadow-2xl">
    <!-- Profile Summary -->
    <div class="p-6 border-b border-white/10">
        <div class="flex items-center space-x-4">
            <img src="<?= $profileImage ?>" class="w-14 h-14 rounded-xl border-2 border-white/20 object-cover">
            <div>
                <h2 class="font-semibold text-lg"><?= htmlspecialchars($username) ?></h2>
                <p class="text-sm text-white/70 flex items-center mt-1">
                    <i class="fas fa-circle text-[#A8E6E6] text-[8px] mr-2"></i>
                    Administrator
                </p>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <nav class="p-4 space-y-1 text-sm">
        <a href="dashboard.php" class="nav-link-active flex items-center px-4 py-3 rounded-xl text-white font-medium transition-all">
            <i class="fas fa-home w-6 text-lg"></i>
            <span class="ml-3">Dashboard</span>
            <span class="ml-auto bg-white/20 text-xs px-2 py-1 rounded-full">12</span>
        </a>

        <div x-data="{ open: false }" class="space-y-1">
            <button @click="open = !open" class="w-full flex items-center px-4 py-3 rounded-xl hover:bg-white/10 transition-all text-white/80 hover:text-white">
                <i class="fas fa-calendar-alt w-6 text-lg"></i>
                <span class="ml-3 flex-1 text-left">Shift & Schedule</span>
                <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
            </button>
            <div x-show="open" class="ml-12 space-y-1 text-white/70 text-sm" x-cloak>
                <a href="assign_weekly.php" class="block py-2 hover:text-white transition">Assign Schedule</a>
                <a href="view_departments.php" class="block py-2 hover:text-white transition">Workforce Management</a>
                <a href="view_employee.php" class="block py-2 hover:text-white transition">Employees</a>
                <a href="view_employee_need.php" class="block py-2 hover:text-white transition">Departments</a>
            </div>
        </div>

        <a href="attendance_table.php" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/10 transition-all text-white/80 hover:text-white">
            <i class="fas fa-user-check w-6 text-lg"></i>
            <span class="ml-3">Time & Attendance</span>
        </a>
        
        <a href="timesheet.php" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/10 transition-all text-white/80 hover:text-white">
            <i class="fas fa-clock w-6 text-lg"></i>
            <span class="ml-3">Timesheet</span>
        </a>
        
        <a href="leave.php" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/10 transition-all text-white/80 hover:text-white">
            <i class="fas fa-file-alt w-6 text-lg"></i>
            <span class="ml-3">Leave Management</span>
            <?php if ($pendingLeaves > 0): ?>
                <span class="ml-auto bg-[#FF7F5C] text-white text-xs px-2 py-1 rounded-full"><?= $pendingLeaves ?></span>
            <?php endif; ?>
        </a>
        
        <a href="RR_dashboard.php" class="flex items-center px-4 py-3 rounded-xl hover:bg-white/10 transition-all text-white/80 hover:text-white">
            <i class="fas fa-receipt w-6 text-lg"></i>
            <span class="ml-3">Claims</span>
            <?php if ($pendingClaims > 0): ?>
                <span class="ml-auto bg-[#A8E6E6] text-[#0D4C5E] text-xs px-2 py-1 rounded-full"><?= $pendingClaims ?></span>
            <?php endif; ?>
        </a>
        
        <div class="pt-4 mt-4 border-t border-white/10">
            <a href="logout.php" class="flex items-center px-4 py-3 rounded-xl hover:bg-[#FF7F5C]/80 transition-all text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-6 text-lg"></i>
                <span class="ml-3">Logout</span>
            </a>
        </div>
    </nav>
    
    <!-- Version Info -->
    <div class="absolute bottom-4 left-6 right-6">
        <p class="text-xs text-white/50">HR3 v2.0 · Teal Blue Edition</p>
    </div>
</aside>

<!-- ================= MAIN CONTENT ================= -->
<main class="ml-72 p-6">

    <!-- Header with Date -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#0D4C5E]">Dashboard Overview</h1>
            <p class="text-sm text-[#1B6B7F] mt-1"><?= date('l, F j, Y') ?></p>
        </div>
        <div class="flex items-center space-x-4">
            <button class="p-2 hover:bg-white rounded-lg transition relative">
                <i class="fas fa-bell text-[#1B6B7F]"></i>
                <?php if (count($notifications) > 0): ?>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-[#FF7F5C] rounded-full"></span>
                <?php endif; ?>
            </button>
            <button class="p-2 hover:bg-white rounded-lg transition">
                <i class="fas fa-cog text-[#1B6B7F]"></i>
            </button>
        </div>
    </div>

    <!-- Quick Stats / Notifications - Teal Theme -->
    <?php if (!empty($notifications)): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <?php foreach ($notifications as $index => $note): ?>
                <div class="bg-white rounded-xl shadow-sm p-4 flex items-start space-x-3 border-l-4 
                    <?= $note['type'] === 'warning' ? 'border-[#FF7F5C]' : ($note['type'] === 'error' ? 'border-[#FF7F5C]' : 'border-[#1B6B7F]') ?>
                    animate-slide-in" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="rounded-full p-2 <?= $note['type'] === 'warning' ? 'bg-orange-100 text-[#FF7F5C]' : ($note['type'] === 'error' ? 'bg-red-100 text-[#FF7F5C]' : 'bg-[#E6F3F5] text-[#1B6B7F]') ?>">
                        <i class="fas <?= $note['icon'] ?>"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-700"><?= htmlspecialchars($note['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards Enhanced - Teal Color Scheme -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
        <!-- Total Employees -->
        <a href="view_employee.php" class="bg-white rounded-xl shadow-sm p-5 hover-card stat-glow" style="border-bottom: 3px solid #0D4C5E;">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-slate-500">Total Employees</p>
                    <p class="text-2xl font-bold text-[#0D4C5E] mt-1"><?= $totalEmployees ?></p>
                    <p class="text-xs text-[#1B6B7F] mt-2 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i> 12% from last month
                    </p>
                </div>
                <div class="bg-[#E6F3F5] p-2.5 rounded-xl">
                    <i class="fas fa-users text-[#0D4C5E] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Attendance Today -->
        <a href="attendance_table.php" class="bg-white rounded-xl shadow-sm p-5 hover-card stat-glow" style="border-bottom: 3px solid #1B6B7F;">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-slate-500">Attendance Today</p>
                    <p class="text-2xl font-bold text-[#1B6B7F] mt-1"><?= $attendanceToday ?></p>
                    <p class="text-xs text-slate-500 mt-2 flex items-center">
                        <span class="w-2 h-2 bg-[#2A8B9F] rounded-full mr-2"></span>
                        <?= round(($attendanceToday / max($totalEmployees, 1)) * 100) ?>% present
                    </p>
                </div>
                <div class="bg-[#D4F0F0] p-2.5 rounded-xl">
                    <i class="fas fa-user-check text-[#1B6B7F] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Pending Leaves -->
        <a href="leave.php" class="bg-white rounded-xl shadow-sm p-5 hover-card stat-glow" style="border-bottom: 3px solid #2A8B9F;">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-slate-500">Pending Leaves</p>
                    <p class="text-2xl font-bold text-[#2A8B9F] mt-1"><?= $pendingLeaves ?></p>
                    <p class="text-xs text-[#FF7F5C] mt-2 flex items-center">
                        <i class="fas fa-clock mr-1"></i> Awaiting approval
                    </p>
                </div>
                <div class="bg-[#E6F3F5] p-2.5 rounded-xl">
                    <i class="fas fa-file-alt text-[#2A8B9F] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Pending Claims -->
        <a href="RR_dashboard.php" class="bg-white rounded-xl shadow-sm p-5 hover-card stat-glow" style="border-bottom: 3px solid #0F5C6B;">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs font-medium text-slate-500">Pending Claims</p>
                    <p class="text-2xl font-bold text-[#0F5C6B] mt-1"><?= $pendingClaims ?></p>
                    <p class="text-xs text-[#1B6B7F] mt-2 flex items-center">
                        <i class="fas fa-clock mr-1"></i> Needs review
                    </p>
                </div>
                <div class="bg-[#D4F0F0] p-2.5 rounded-xl">
                    <i class="fas fa-receipt text-[#0F5C6B] text-lg"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- AI Insights Panel Enhanced - Teal Gradient -->
    <div class="glass-panel rounded-xl shadow-xl p-5 mb-6 text-white">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
                <div class="bg-white/20 p-1.5 rounded-lg">
                    <i class="fas fa-robot text-[#A8E6E6] text-sm"></i>
                </div>
                <h3 class="font-semibold text-base">AI Insights & Recommendations</h3>
            </div>
            <span class="text-xs bg-white/10 px-2 py-1 rounded-full">Updated just now</span>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <?php foreach ($insights as $insight): ?>
                <div class="bg-white/10 rounded-lg p-3 backdrop-blur-sm border border-white/10">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-white/70"><?= $insight['metric'] ?></p>
                            <p class="text-xl font-bold mt-0.5"><?= $insight['value'] ?></p>
                        </div>
                        <div class="flex items-center space-x-1">
                            <?php if ($insight['trend'] > 0): ?>
                                <i class="fas fa-arrow-up text-[#A8E6E6] text-xs"></i>
                                <span class="text-xs text-[#A8E6E6]">+<?= round($insight['trend'], 1) ?></span>
                            <?php elseif ($insight['trend'] < 0): ?>
                                <i class="fas fa-arrow-down text-[#FF7F5C] text-xs"></i>
                                <span class="text-xs text-[#FF7F5C]"><?= round($insight['trend'], 1) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-white/80"><?= $insight['message'] ?></span>
                        <?php if ($insight['action']): ?>
                            <button class="text-xs bg-white/20 hover:bg-white/30 px-2 py-0.5 rounded transition">
                                <?= $insight['action'] ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Charts Section - Teal Theme -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <!-- Attendance Trend -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold text-[#0D4C5E] text-sm">Attendance Trend</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-xs bg-[#E6F3F5] text-[#0D4C5E] px-2 py-1 rounded">Last 14 days</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Leave Trend -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold text-[#1B6B7F] text-sm">Leave Requests</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-xs bg-[#D4F0F0] text-[#1B6B7F] px-2 py-1 rounded">Pending only</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="leaveChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Claims Chart + Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Claims Chart (takes 2 columns) -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-4">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold text-[#0F5C6B] text-sm">Claims Overview</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-xs bg-[#E6F3F5] text-[#0F5C6B] px-2 py-1 rounded">Pending claims</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="claimsChart"></canvas>
            </div>
        </div>

        <!-- Recent Activity (takes 1 column) -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <h3 class="font-semibold text-[#0D4C5E] text-sm mb-3 flex items-center">
                <i class="fas fa-history text-[#1B6B7F] mr-2"></i>
                Recent Activity
            </h3>
            
            <?php if (empty($activityLog)): ?>
                <p class="text-xs text-slate-500 text-center py-6">No recent activity available.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($activityLog as $log): ?>
                        <div class="flex items-start space-x-2">
                            <div class="rounded-full p-1.5 <?= $log['type'] === 'warning' ? 'bg-orange-100' : 'bg-[#E6F3F5]' ?>">
                                <i class="fas <?= $log['icon'] ?> <?= $log['type'] === 'warning' ? 'text-[#FF7F5C]' : 'text-[#1B6B7F]' ?> text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-xs font-medium text-slate-700"><?= htmlspecialchars($log['activity']) ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?= date('M d, Y · h:i A', strtotime($log['activity_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="w-full mt-3 text-xs text-[#1B6B7F] hover:text-[#0D4C5E] font-medium flex items-center justify-center">
                    View all activity
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-6 pt-4 border-t border-[#D4F0F0]">
        <div class="flex justify-between items-center text-xs text-[#1B6B7F]">
            <p>© iMARKET HUMAN RESOURCE 3 SYSTEM · Teal Blue Edition</p>
            <p>Last updated: <?= date('M d, Y h:i A') ?></p>
        </div>
    </div>
</main>

<!-- Charts JavaScript - Updated with Teal Colors -->
<script>
// Chart configurations
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 10;
Chart.defaults.color = '#1B6B7F';

// Attendance Chart - Teal theme
new Chart(document.getElementById('attendanceChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($attendanceLabels) ?>,
        datasets: [{
            label: 'Present',
            data: <?= json_encode($attendanceData) ?>,
            borderColor: '#0D4C5E',
            backgroundColor: 'rgba(13, 76, 94, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#0D4C5E',
            pointBorderColor: 'white',
            pointBorderWidth: 1.5,
            pointRadius: 3,
            pointHoverRadius: 4,
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                backgroundColor: '#0D4C5E',
                titleFont: { size: 11 },
                bodyFont: { size: 10 },
                padding: 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { font: { size: 9 } }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { size: 9 } }
            }
        }
    }
});

// Leave Chart - Teal theme
new Chart(document.getElementById('leaveChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($attendanceLabels) ?>,
        datasets: [{
            data: <?= json_encode($leaveData) ?>,
            backgroundColor: '#1B6B7F',
            borderRadius: 4,
            barPercentage: 0.5,
            categoryPercentage: 0.8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                backgroundColor: '#0D4C5E',
                titleFont: { size: 11 },
                bodyFont: { size: 10 },
                padding: 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { font: { size: 9 } }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { size: 9 } }
            }
        }
    }
});

// Claims Chart - Teal theme
new Chart(document.getElementById('claimsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($attendanceLabels) ?>,
        datasets: [{
            data: <?= json_encode($claimsData) ?>,
            backgroundColor: '#2A8B9F',
            borderRadius: 4,
            barPercentage: 0.5,
            categoryPercentage: 0.8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { 
                backgroundColor: '#0D4C5E',
                titleFont: { size: 11 },
                bodyFont: { size: 10 },
                padding: 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { font: { size: 9 } }
            },
            x: { 
                grid: { display: false },
                ticks: { font: { size: 9 } }
            }
        }
    }
});

// Add Alpine.js for dropdown functionality
document.addEventListener('alpine:init', () => {
    Alpine.data('dropdown', () => ({
        open: false,
        toggle() { this.open = !this.open; }
    }));
});
</script>
<script src="//unpkg.com/alpinejs" defer></script>
</body>
</html>