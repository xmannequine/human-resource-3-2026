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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
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

        /* Mobile sidebar styles */
        .sidebar-open {
            transform: translateX(0) !important;
        }
        
        .sidebar-closed {
            transform: translateX(-100%);
        }
        
        /* Improved touch targets for mobile */
        @media (max-width: 768px) {
            button, a {
                min-height: 44px;
                min-width: 44px;
            }
            
            .nav-link-active, nav a, nav button {
                padding: 12px 16px !important;
            }
            
            .stat-glow {
                margin-bottom: 8px;
            }
            
            .chart-container {
                height: 200px;
            }
        }
        
        /* Better font scaling on mobile */
        @media (max-width: 480px) {
            html {
                font-size: 14px;
            }
            
            h1 {
                font-size: 1.5rem !important;
            }
            
            .glass-panel {
                padding: 16px !important;
            }
        }
        
        /* Safe area insets for modern mobile devices */
        @supports (padding: max(0px)) {
            body {
                padding-left: env(safe-area-inset-left);
                padding-right: env(safe-area-inset-right);
            }
            
            .mobile-header {
                padding-top: env(safe-area-inset-top);
            }
            
            .mobile-footer {
                padding-bottom: env(safe-area-inset-bottom);
            }
        }
        
        /* Mobile menu overlay */
        .menu-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            transition: opacity 0.3s ease;
        }
    </style>
</head>
<body class="bg-[#f0f7f9] text-slate-700 antialiased">

<!-- Mobile Header - Visible only on mobile -->
<div class="lg:hidden fixed top-0 left-0 right-0 bg-gradient-to-r from-[#0D4C5E] to-[#1B6B7F] text-white p-4 z-30 shadow-lg mobile-header">
    <div class="flex items-center justify-between">
        <button id="mobileMenuToggle" class="p-2 hover:bg-white/10 rounded-lg transition-colors" aria-label="Open menu">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <h2 class="font-semibold text-lg">HR3 Dashboard</h2>
        <div class="flex items-center space-x-2">
            <button class="p-2 hover:bg-white/10 rounded-lg transition-colors relative" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications) > 0): ?>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-[#FF7F5C] rounded-full"></span>
                <?php endif; ?>
            </button>
            <img src="<?= $profileImage ?>" class="w-8 h-8 rounded-lg border-2 border-white/20 object-cover">
        </div>
    </div>
</div>

<!-- Mobile Menu Overlay -->
<div id="menuOverlay" class="lg:hidden fixed inset-0 bg-black/50 z-40 hidden"></div>

<!-- ================= SIDEBAR - Mobile Responsive ================= -->
<aside id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-gradient-to-b from-[#0D4C5E] to-[#1B6B7F] text-white shadow-2xl z-50 transition-transform duration-300 ease-in-out lg:translate-x-0 sidebar-closed lg:sidebar-open overflow-y-auto">
    
    <!-- Close button for mobile -->
    <button id="closeSidebar" class="lg:hidden absolute top-4 right-4 p-2 hover:bg-white/10 rounded-lg transition-colors" aria-label="Close menu">
        <i class="fas fa-times text-xl"></i>
    </button>
    
    <!-- Profile Summary -->
    <div class="p-6 border-b border-white/10 mt-12 lg:mt-0">
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
                <a href="assign_weekly.php" class="block py-3 hover:text-white transition">Assign Schedule</a>
                <a href="view_departments.php" class="block py-3 hover:text-white transition">Workforce Management</a>
                <a href="view_employee.php" class="block py-3 hover:text-white transition">Employees</a>
                <a href="view_employee_need.php" class="block py-3 hover:text-white transition">Departments</a>
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
    <div class="p-6">
        <p class="text-xs text-white/50">HR3 v2.0 · Teal Blue Edition</p>
    </div>
</aside>

<!-- ================= MAIN CONTENT - Mobile Adjusted ================= -->
<main class="lg:ml-72 p-4 lg:p-6 pt-20 lg:pt-6">
    <!-- Header with Date - Hidden on mobile (shown in mobile header) -->
    <div class="hidden lg:flex justify-between items-center mb-6">
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

    <!-- Mobile Date Display -->
    <div class="lg:hidden mb-4">
        <p class="text-sm text-[#1B6B7F]"><?= date('l, F j, Y') ?></p>
    </div>

    <!-- Quick Stats / Notifications - Teal Theme -->
    <?php if (!empty($notifications)): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 lg:gap-4 mb-6">
            <?php foreach ($notifications as $index => $note): ?>
                <div class="bg-white rounded-xl shadow-sm p-4 flex items-start space-x-3 border-l-4 
                    <?= $note['type'] === 'warning' ? 'border-[#FF7F5C]' : ($note['type'] === 'error' ? 'border-[#FF7F5C]' : 'border-[#1B6B7F]') ?>
                    animate-slide-in" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="rounded-full p-2 <?= $note['type'] === 'warning' ? 'bg-orange-100 text-[#FF7F5C]' : ($note['type'] === 'error' ? 'bg-red-100 text-[#FF7F5C]' : 'bg-[#E6F3F5] text-[#1B6B7F]') ?> flex-shrink-0">
                        <i class="fas <?= $note['icon'] ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-700 break-words"><?= htmlspecialchars($note['message']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- KPI Cards Enhanced - Mobile optimized grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-6">
        <!-- Total Employees -->
        <a href="view_employee.php" class="bg-white rounded-xl shadow-sm p-4 lg:p-5 hover-card stat-glow" style="border-bottom: 3px solid #0D4C5E;">
            <div class="flex justify-between items-start">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500 truncate">Total Employees</p>
                    <p class="text-xl lg:text-2xl font-bold text-[#0D4C5E] mt-1"><?= $totalEmployees ?></p>
                    <p class="text-xs text-[#1B6B7F] mt-2 flex items-center">
                        <i class="fas fa-arrow-up mr-1 flex-shrink-0"></i>
                        <span class="truncate">12% from last month</span>
                    </p>
                </div>
                <div class="bg-[#E6F3F5] p-2.5 rounded-xl flex-shrink-0 ml-2">
                    <i class="fas fa-users text-[#0D4C5E] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Attendance Today -->
        <a href="attendance_table.php" class="bg-white rounded-xl shadow-sm p-4 lg:p-5 hover-card stat-glow" style="border-bottom: 3px solid #1B6B7F;">
            <div class="flex justify-between items-start">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500 truncate">Attendance Today</p>
                    <p class="text-xl lg:text-2xl font-bold text-[#1B6B7F] mt-1"><?= $attendanceToday ?></p>
                    <p class="text-xs text-slate-500 mt-2 flex items-center">
                        <span class="w-2 h-2 bg-[#2A8B9F] rounded-full mr-2 flex-shrink-0"></span>
                        <span class="truncate"><?= round(($attendanceToday / max($totalEmployees, 1)) * 100) ?>% present</span>
                    </p>
                </div>
                <div class="bg-[#D4F0F0] p-2.5 rounded-xl flex-shrink-0 ml-2">
                    <i class="fas fa-user-check text-[#1B6B7F] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Pending Leaves -->
        <a href="leave.php" class="bg-white rounded-xl shadow-sm p-4 lg:p-5 hover-card stat-glow" style="border-bottom: 3px solid #2A8B9F;">
            <div class="flex justify-between items-start">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500 truncate">Pending Leaves</p>
                    <p class="text-xl lg:text-2xl font-bold text-[#2A8B9F] mt-1"><?= $pendingLeaves ?></p>
                    <p class="text-xs text-[#FF7F5C] mt-2 flex items-center">
                        <i class="fas fa-clock mr-1 flex-shrink-0"></i>
                        <span class="truncate">Awaiting approval</span>
                    </p>
                </div>
                <div class="bg-[#E6F3F5] p-2.5 rounded-xl flex-shrink-0 ml-2">
                    <i class="fas fa-file-alt text-[#2A8B9F] text-lg"></i>
                </div>
            </div>
        </a>

        <!-- Pending Claims -->
        <a href="RR_dashboard.php" class="bg-white rounded-xl shadow-sm p-4 lg:p-5 hover-card stat-glow" style="border-bottom: 3px solid #0F5C6B;">
            <div class="flex justify-between items-start">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-500 truncate">Pending Claims</p>
                    <p class="text-xl lg:text-2xl font-bold text-[#0F5C6B] mt-1"><?= $pendingClaims ?></p>
                    <p class="text-xs text-[#1B6B7F] mt-2 flex items-center">
                        <i class="fas fa-clock mr-1 flex-shrink-0"></i>
                        <span class="truncate">Needs review</span>
                    </p>
                </div>
                <div class="bg-[#D4F0F0] p-2.5 rounded-xl flex-shrink-0 ml-2">
                    <i class="fas fa-receipt text-[#0F5C6B] text-lg"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- AI Insights Panel Enhanced - Mobile optimized -->
    <div class="glass-panel rounded-xl shadow-xl p-4 lg:p-5 mb-6 text-white">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2 min-w-0">
                <div class="bg-white/20 p-1.5 rounded-lg flex-shrink-0">
                    <i class="fas fa-robot text-[#A8E6E6] text-sm"></i>
                </div>
                <h3 class="font-semibold text-base truncate">AI Insights & Recommendations</h3>
            </div>
            <span class="text-xs bg-white/10 px-2 py-1 rounded-full flex-shrink-0">Updated now</span>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($insights as $insight): ?>
                <div class="bg-white/10 rounded-lg p-3 backdrop-blur-sm border border-white/10">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-white/70"><?= $insight['metric'] ?></p>
                            <p class="text-lg lg:text-xl font-bold mt-0.5"><?= $insight['value'] ?></p>
                        </div>
                        <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                            <?php if ($insight['trend'] > 0): ?>
                                <i class="fas fa-arrow-up text-[#A8E6E6] text-xs"></i>
                                <span class="text-xs text-[#A8E6E6]">+<?= round($insight['trend'], 1) ?></span>
                            <?php elseif ($insight['trend'] < 0): ?>
                                <i class="fas fa-arrow-down text-[#FF7F5C] text-xs"></i>
                                <span class="text-xs text-[#FF7F5C]"><?= round($insight['trend'], 1) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <span class="text-xs text-white/80"><?= $insight['message'] ?></span>
                        <?php if ($insight['action']): ?>
                            <button class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded transition whitespace-nowrap w-full sm:w-auto text-center">
                                <?= $insight['action'] ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Charts Section - Mobile optimized -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-5 mb-5">
        <!-- Attendance Trend -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
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
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
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

    <!-- Bottom Section: Claims Chart + Activity - Mobile optimized -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-5">
        <!-- Claims Chart (takes 2 columns on desktop) -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm p-4">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3">
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
                            <div class="rounded-full p-1.5 <?= $log['type'] === 'warning' ? 'bg-orange-100' : 'bg-[#E6F3F5]' ?> flex-shrink-0">
                                <i class="fas <?= $log['icon'] ?> <?= $log['type'] === 'warning' ? 'text-[#FF7F5C]' : 'text-[#1B6B7F]' ?> text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-slate-700 truncate"><?= htmlspecialchars($log['activity']) ?></p>
                                <p class="text-xs text-slate-500 mt-0.5"><?= date('M d, Y · h:i A', strtotime($log['activity_date'])) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="w-full mt-4 text-xs text-[#1B6B7F] hover:text-[#0D4C5E] font-medium flex items-center justify-center py-2">
                    View all activity
                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer - Mobile optimized -->
    <div class="mt-6 pt-4 border-t border-[#D4F0F0] mobile-footer">
        <div class="flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-[#1B6B7F]">
            <p>© iMARKET HUMAN RESOURCE 3 SYSTEM</p>
            <p>Last updated: <?= date('M d, Y h:i A') ?></p>
        </div>
    </div>
</main>

<!-- Mobile JavaScript for sidebar toggle -->
<script>
// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('mobileMenuToggle');
    const closeSidebar = document.getElementById('closeSidebar');
    const menuOverlay = document.getElementById('menuOverlay');
    
    function openSidebar() {
        sidebar.classList.remove('sidebar-closed');
        sidebar.classList.add('sidebar-open');
        menuOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebarFunc() {
        sidebar.classList.add('sidebar-closed');
        sidebar.classList.remove('sidebar-open');
        menuOverlay.classList.add('hidden');
        document.body.style.overflow = '';
    }
    
    if (menuToggle) {
        menuToggle.addEventListener('click', openSidebar);
    }
    
    if (closeSidebar) {
        closeSidebar.addEventListener('click', closeSidebarFunc);
    }
    
    if (menuOverlay) {
        menuOverlay.addEventListener('click', closeSidebarFunc);
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('sidebar-closed');
            sidebar.classList.add('sidebar-open');
            if (menuOverlay) {
                menuOverlay.classList.add('hidden');
            }
            document.body.style.overflow = '';
        } else {
            sidebar.classList.add('sidebar-closed');
            sidebar.classList.remove('sidebar-open');
        }
    });
});

// Charts JavaScript (unchanged)
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.font.size = 10;
Chart.defaults.color = '#1B6B7F';

// Attendance Chart
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
            pointRadius: window.innerWidth < 640 ? 2 : 3,
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
                titleFont: { size: window.innerWidth < 640 ? 10 : 11 },
                bodyFont: { size: window.innerWidth < 640 ? 9 : 10 },
                padding: window.innerWidth < 640 ? 4 : 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxTicksLimit: window.innerWidth < 640 ? 5 : 8
                }
            },
            x: { 
                grid: { display: false },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxRotation: window.innerWidth < 640 ? 45 : 0
                }
            }
        }
    }
});

// Leave Chart
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
                titleFont: { size: window.innerWidth < 640 ? 10 : 11 },
                bodyFont: { size: window.innerWidth < 640 ? 9 : 10 },
                padding: window.innerWidth < 640 ? 4 : 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxTicksLimit: window.innerWidth < 640 ? 5 : 8
                }
            },
            x: { 
                grid: { display: false },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxRotation: window.innerWidth < 640 ? 45 : 0
                }
            }
        }
    }
});

// Claims Chart
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
                titleFont: { size: window.innerWidth < 640 ? 10 : 11 },
                bodyFont: { size: window.innerWidth < 640 ? 9 : 10 },
                padding: window.innerWidth < 640 ? 4 : 6
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#E6F3F5', lineWidth: 0.5 },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxTicksLimit: window.innerWidth < 640 ? 5 : 8
                }
            },
            x: { 
                grid: { display: false },
                ticks: { 
                    font: { size: window.innerWidth < 640 ? 8 : 9 },
                    maxRotation: window.innerWidth < 640 ? 45 : 0
                }
            }
        }
    }
});
</script>
<script src="//unpkg.com/alpinejs" defer></script>
</body>
</html>