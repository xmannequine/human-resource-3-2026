<?php
session_start();
require_once('config2.php');
date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['bundy_employee_id'])) {
    header("Location: bundy_login.php");
    exit;
}

$employeeId   = $_SESSION['bundy_employee_id'];
$employeeName = $_SESSION['bundy_employee_name'] ?? '';
$today = date('Y-m-d');

// Get employee details
$stmt = $conn->prepare("SELECT firstname, lastname, username FROM employee WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// last punch today
$stmt = $conn->prepare("
    SELECT * FROM attendance
    WHERE employee_id=? AND date=?
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$employeeId,$today]);
$last = $stmt->fetch(PDO::FETCH_ASSOC);

$status = 'Not Timed In';
$statusColor = '#6c757d';
$statusBg = '#f8f9fa';
if($last && $last['time_in'] && !$last['time_out']) {
    $status = 'On Duty';
    $statusColor = '#28a745';
    $statusBg = '#d4edda';
}
if($last && $last['time_out']) {
    $status = 'Timed Out';
    $statusColor = '#dc3545';
    $statusBg = '#f8d7da';
}

// Format time function
function formatTime($time) {
    return $time ? date('h:i A', strtotime($time)) : '-';
}

function formatAddress($street, $barangay, $city) {
    $parts = array_filter([$street, $barangay, $city]);
    return !empty($parts) ? implode(', ', $parts) : 'No location recorded';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bundy Clock | Employee Time Management</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        :root {
            --primary: #145374;
            --primary-dark: #00334E;
            --primary-light: #5588A3;
            --accent: #E8E8E8;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --dark: #2c3e50;
            --light: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-shape {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-shape .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            top: -200px;
            right: -100px;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -100px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            bottom: 50%;
            right: 20%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        /* Main Container */
        .container-custom {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 700px;
        }

        /* Bundy Card */
        .bundy-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Header with Logo and Employee Info */
        .bundy-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .logo-wrapper {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .logo-wrapper i {
            font-size: 2.5rem;
            color: white;
        }

        .employee-info h2 {
            color: var(--primary-dark);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .employee-info p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .employee-info p i {
            color: var(--primary);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 25px;
            background: <?= $statusBg ?>;
            color: <?= $statusColor ?>;
            border: 1px solid <?= $statusColor ?>;
        }

        .status-badge i {
            font-size: 1rem;
        }

        /* Live Clock */
        .clock-container {
            background: linear-gradient(145deg, var(--primary-dark), var(--primary));
            border-radius: 30px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .clock-date {
            color: var(--accent);
            font-size: 1rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .clock-date i {
            margin-right: 8px;
        }

        .clock-time {
            font-size: 4rem;
            font-weight: 700;
            color: white;
            letter-spacing: 4px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            font-family: monospace;
        }

        .clock-timezone {
            color: var(--accent);
            font-size: 0.8rem;
            margin-top: 10px;
            opacity: 0.7;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn-time {
            padding: 20px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-time::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-time:hover::before {
            left: 100%;
        }

        .btn-time-in {
            background: linear-gradient(145deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-time-in:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px rgba(40, 167, 69, 0.4);
        }

        .btn-time-out {
            background: linear-gradient(145deg, #dc3545, #c0392b);
            color: white;
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.3);
        }

        .btn-time-out:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px rgba(220, 53, 69, 0.4);
        }

        .btn-time i {
            font-size: 1.5rem;
        }

        .btn-time:active {
            transform: translateY(0);
        }

        .btn-time:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Location Card */
        .location-card {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
        }

        .location-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--primary-dark);
            font-weight: 600;
        }

        .location-header i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .location-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .location-item {
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .location-item h6 {
            color: var(--primary-dark);
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .location-item h6 i {
            color: var(--primary);
        }

        .location-detail {
            color: #4a5568;
            font-size: 0.95rem;
            margin-bottom: 5px;
        }

        .location-detail strong {
            color: var(--primary);
        }

        .location-time {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #dee2e6;
        }

        .location-time i {
            margin-right: 5px;
            color: var(--primary);
        }

        .no-location {
            color: #adb5bd;
            font-style: italic;
            font-size: 0.95rem;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-content {
            text-align: center;
            color: white;
        }

        .loading-content i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }

        .loading-content p {
            font-size: 1.2rem;
            font-weight: 500;
        }

        .loading-content small {
            color: rgba(255,255,255,0.5);
        }

        /* Success Message */
        .success-message {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(145deg, #28a745, #20c997);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            font-weight: 500;
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

        /* Logout Button */
        .logout-btn {
            margin-top: 20px;
            text-align: center;
        }

        .logout-btn a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .logout-btn a:hover {
            color: var(--danger);
        }

        .logout-btn a i {
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .bundy-card {
                padding: 25px;
            }
            
            .clock-time {
                font-size: 2.5rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .location-grid {
                grid-template-columns: 1fr;
            }
            
            .bundy-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Success Message -->
    <div class="success-message" id="successMessage">
        <i class="fas fa-check-circle"></i>
        <span id="successText">Successfully Timed In!</span>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Processing your request...</p>
            <small>Please wait</small>
        </div>
    </div>

    <!-- Animated Background Shapes -->
    <div class="bg-shape">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Container -->
    <div class="container-custom">
        <div class="bundy-card animate__animated animate__fadeIn">
            <!-- Header with Employee Info -->
            <div class="bundy-header">
                <div class="logo-wrapper">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="employee-info">
                    <h2><?= htmlspecialchars($employeeName) ?></h2>
                    <p>
                        <i class="fas fa-id-badge"></i>
                        Employee ID: <?= str_pad($employeeId, 4, '0', STR_PAD_LEFT) ?>
                    </p>
                    <?php if ($employee && $employee['username']): ?>
                    <p>
                        <i class="fas fa-user"></i>
                        Username: <?= htmlspecialchars($employee['username']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Badge -->
            <div class="status-badge">
                <i class="fas fa-<?= $status === 'On Duty' ? 'clock' : ($status === 'Timed Out' ? 'check-circle' : 'hourglass-start') ?>"></i>
                Current Status: <?= $status ?>
            </div>

            <!-- Live Clock -->
            <div class="clock-container">
                <div class="clock-date">
                    <i class="fas fa-calendar-alt"></i>
                    <?= date('l, F j, Y') ?>
                </div>
                <div class="clock-time" id="clock"></div>
                <div class="clock-timezone">
                    <i class="fas fa-globe-asia"></i>
                    Philippine Time (PHT)
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn-time btn-time-in" id="btnIn" <?= $status === 'On Duty' ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    TIME IN
                </button>
                <button class="btn-time btn-time-out" id="btnOut" <?= $status !== 'On Duty' ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-out-alt"></i>
                    TIME OUT
                </button>
            </div>

            <!-- Last Punch Location Card -->
            <div class="location-card">
                <div class="location-header">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Last Attendance Record</span>
                </div>

                <div class="location-grid">
                    <!-- Time In Details -->
                    <div class="location-item">
                        <h6>
                            <i class="fas fa-sign-in-alt text-success"></i>
                            Time In
                        </h6>
                        <div class="location-detail">
                            <strong>Time:</strong> <?= formatTime($last['time_in'] ?? null) ?>
                        </div>
                        <div class="location-detail">
                            <strong>Location:</strong><br>
                            <?php if ($last && $last['time_in']): ?>
                                <?= htmlspecialchars(formatAddress($last['street_in'] ?? '', $last['barangay_in'] ?? '', $last['city_in'] ?? '')) ?>
                            <?php else: ?>
                                <span class="no-location">No time in recorded today</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($last && $last['time_in']): ?>
                            <div class="location-time">
                                <i class="fas fa-clock"></i>
                                <?= date('h:i A', strtotime($last['time_in'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Time Out Details -->
                    <div class="location-item">
                        <h6>
                            <i class="fas fa-sign-out-alt text-danger"></i>
                            Time Out
                        </h6>
                        <div class="location-detail">
                            <strong>Time:</strong> <?= formatTime($last['time_out'] ?? null) ?>
                        </div>
                        <div class="location-detail">
                            <strong>Location:</strong><br>
                            <?php if ($last && $last['time_out']): ?>
                                <?= htmlspecialchars(formatAddress($last['street_out'] ?? '', $last['barangay_out'] ?? '', $last['city_out'] ?? '')) ?>
                            <?php else: ?>
                                <span class="no-location">Not timed out yet</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($last && $last['time_out']): ?>
                            <div class="location-time">
                                <i class="fas fa-clock"></i>
                                <?= date('h:i A', strtotime($last['time_out'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logout Link -->
            <div class="logout-btn">
                <a href="bundy_logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout from Bundy Clock
                </a>
            </div>
        </div>
    </div>

    <script>
        // Live Clock Update
        const clock = document.getElementById('clock');
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            clock.textContent = timeString;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // Elements
        const loadingOverlay = document.getElementById('loadingOverlay');
        const successMessage = document.getElementById('successMessage');
        const successText = document.getElementById('successText');
        const btnIn = document.getElementById('btnIn');
        const btnOut = document.getElementById('btnOut');

        // Show success message function
        function showSuccess(message) {
            successText.textContent = message;
            successMessage.style.display = 'flex';
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 3000);
        }

        // Get Location Function
        async function getLocation() {
            return new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    position => resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    }),
                    error => reject(error),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }

        // Update UI after successful punch
        function updateUIBeforeReload(action) {
            if (action === 'time_in') {
                btnIn.disabled = true;
                btnOut.disabled = false;
            } else {
                btnOut.disabled = true;
            }
        }

        // Punch Function
        async function punch(action) {
            try {
                // Show loading
                loadingOverlay.style.display = 'flex';
                
                // Disable buttons
                btnIn.disabled = true;
                btnOut.disabled = true;

                // Get location
                const location = await getLocation();
                
                // Prepare form data
                const formData = new FormData();
                formData.append('action', action);
                formData.append('lat', location.lat);
                formData.append('lng', location.lng);

                // Send request
                const response = await fetch('bundy_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Hide loading
                    loadingOverlay.style.display = 'none';
                    
                    // Show success message
                    showSuccess(action === 'time_in' ? '✅ Successfully Timed In!' : '✅ Successfully Timed Out!');
                    
                    // Update UI without reload
                    updateUIBeforeReload(action);
                    
                    // Reload the page after 2 seconds to show updated data
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                    
                } else {
                    throw new Error(result.message || 'Unknown error occurred');
                }

            } catch (error) {
                // Hide loading
                loadingOverlay.style.display = 'none';
                
                // Re-enable appropriate buttons
                if (action === 'time_in') {
                    btnIn.disabled = false;
                } else {
                    btnOut.disabled = false;
                }
                
                // Show error
                alert(error.message || 'Please allow location access and try again.');
            }
        }

        // Event Listeners
        btnIn.addEventListener('click', () => punch('time_in'));
        btnOut.addEventListener('click', () => punch('time_out'));

        // Prevent double clicks
        btnIn.addEventListener('dblclick', (e) => e.preventDefault());
        btnOut.addEventListener('dblclick', (e) => e.preventDefault());

        // Add keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Alt + I for Time In
            if (e.altKey && e.key === 'i' && !btnIn.disabled) {
                e.preventDefault();
                punch('time_in');
            }
            // Alt + O for Time Out
            if (e.altKey && e.key === 'o' && !btnOut.disabled) {
                e.preventDefault();
                punch('time_out');
            }
        });

        // Add tooltip for keyboard shortcuts
        btnIn.title = 'Keyboard shortcut: Alt + I';
        btnOut.title = 'Keyboard shortcut: Alt + O';

        // Warn user before closing if on duty
        window.addEventListener('beforeunload', (e) => {
            if ('<?= $status ?>' === 'On Duty') {
                e.preventDefault();
                e.returnValue = 'You are currently on duty. Are you sure you want to leave?';
            }
        });

        // Auto-refresh location every minute (optional)
        let refreshInterval;
        if ('<?= $status ?>' === 'On Duty') {
            refreshInterval = setInterval(() => {
                console.log('Auto-refreshing location...');
            }, 60000);
        }

        // Clean up interval on unload
        window.addEventListener('unload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>