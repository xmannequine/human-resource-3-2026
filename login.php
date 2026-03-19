<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

$error = '';
$email = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwordEntered = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($passwordEntered)) {
        $error = "Please enter both email and password.";
    } else {
        $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($passwordEntered, $user['password'])) {
            if (in_array($user['role'], ['Admin','User'])) {
                
                // Set remember me cookie if requested
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    setcookie('remember_token', $token, $expiry, '/', '', true, true);
                }
                
                $otpCode = rand(100000, 999999);

                $_SESSION['otp_email'] = $user['email'];
                $_SESSION['otp_name'] = $user['firstname'].' '.$user['lastname'];
                $_SESSION['otp_role'] = $user['role'];
                $_SESSION['otp_code'] = $otpCode;
                $_SESSION['otp_time'] = time();

                $smtpUser = 'pabrorico2001@gmail.com';
                $smtpPass = 'ilzilnktgafpnsci';

                // Optimize SMTP settings for faster sending
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings optimized for speed
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->Timeout    = 10; // Reduce timeout to 10 seconds
                    $mail->SMTPKeepAlive = false; // Don't keep connection alive
                    $mail->SMTPAutoTLS = true;
                    
                    // Disable debugging for production
                    $mail->SMTPDebug = 0;
                    
                    // Recipients
                    $mail->setFrom($smtpUser, 'E-Commerce HR3 System');
                    $mail->addAddress($user['email'], $user['firstname'].' '.$user['lastname']);
                    
                    // Content - Simplified HTML for faster rendering
                    $mail->isHTML(true);
                    $mail->Subject = 'Your OTP Code - E-Commerce HR3';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #00334E; padding: 30px; border-radius: 20px;'>
                            <h2 style='color: #ffffff; margin-bottom: 20px;'>Hello {$user['firstname']},</h2>
                            <p style='color: #E8E8E8; font-size: 16px;'>Your verification code:</p>
                            <div style='background: #5588A3; padding: 20px; text-align: center; border-radius: 12px; margin: 20px 0;'>
                                <span style='font-size: 36px; font-weight: bold; color: #E8E8E8; letter-spacing: 5px;'>{$otpCode}</span>
                            </div>
                            <p style='font-size: 14px; color: #E8E8E8;'>Code expires in <strong>5 minutes</strong>.</p>
                        </div>
                    ";
                    $mail->AltBody = "Your OTP code is: {$otpCode}";
                    
                    // Send email with output buffering to prevent output before redirect
                    ob_start();
                    $mail->send();
                    ob_end_clean();

                    // Immediate redirect - don't wait for buffer
                    header("Location: login_otp.php");
                    exit;

                } catch (Exception $e) {
                    // Log error but still try to redirect
                    error_log("Mailer Error: " . $mail->ErrorInfo);
                    
                    // Even if email fails, we still have OTP in session
                    // You might want to show the OTP on screen for debugging (remove in production)
                    // $_SESSION['debug_otp'] = $otpCode;
                    
                    header("Location: login_otp.php");
                    exit;
                }

            } else {
                $error = "Only Admin/User accounts can log in here.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce HR3 · Admin Portal</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #145374;
            --primary-dark: #00334E;
            --primary-light: #5588A3;
            --accent: #E8E8E8;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
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

        /* Login Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
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

        /* Left Panel - Branding */
        .brand-panel {
            background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary) 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><path d="M20 20 L80 20 L80 80 L20 80 Z" fill="none" stroke="white" stroke-width="2"/><circle cx="50" cy="50" r="20" fill="none" stroke="white" stroke-width="2"/></svg>') repeat;
            background-size: 50px 50px;
            animation: movePattern 30s linear infinite;
        }

        @keyframes movePattern {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50px) translateY(50px); }
        }

        .brand-logo {
            position: relative;
            z-index: 10;
            margin-bottom: 30px;
        }

        .brand-logo img {
            width: 120px;
            height: 120px;
            border-radius: 30px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            background: white;
            padding: 5px;
            transition: transform 0.3s ease;
        }

        .brand-logo img:hover {
            transform: scale(1.05);
        }

        .brand-title {
            position: relative;
            z-index: 10;
        }

        .brand-title h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            line-height: 1.2;
        }

        .brand-title .subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
            letter-spacing: 1px;
        }

        .brand-title .system-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            padding: 8px 20px;
            border-radius: 50px;
            display: inline-block;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        /* Intro Message */
        .intro-message {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 25px;
            margin: 20px 0;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: left;
        }

        .intro-message p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 15px;
            color: var(--accent);
        }

        .intro-message .highlight {
            color: white;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        .intro-message ul {
            list-style: none;
            padding: 0;
            margin: 15px 0 0;
        }

        .intro-message li {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .intro-message li i {
            color: var(--accent);
            font-size: 1rem;
            width: 20px;
        }

        .time-display {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .time-display .date {
            font-size: 1rem;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .time-display .time {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .time-display .timezone {
            font-size: 0.8rem;
            opacity: 0.7;
            margin-top: 5px;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            text-align: center;
            color: white;
        }

        .loading-spinner i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent);
        }

        .loading-spinner p {
            font-size: 1.2rem;
            font-weight: 500;
        }

        /* Right Panel - Login Form */
        .login-panel {
            padding: 60px 50px;
            background: white;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h2 {
            color: var(--primary-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-light);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .role-badge i {
            color: var(--accent);
        }

        /* Input Groups */
        .input-group {
            margin-bottom: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20, 83, 116, 0.1);
        }

        .input-group-text {
            background: #f8fafc;
            border: none;
            padding: 0 20px;
            color: var(--primary);
            font-size: 1.2rem;
        }

        .form-control {
            border: none;
            padding: 15px;
            font-size: 1rem;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            box-shadow: none;
            background: #f8fafc;
        }

        /* Password field specific */
        .password-field {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            z-index: 10;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        /* Remember Me & Forgot Password */
        .remember-forgot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 20px 0;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }

        .remember-me span {
            color: #4a5568;
            font-size: 0.9rem;
        }

        .forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 16px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(20, 83, 116, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Error Alert */
        .error-alert {
            background: #fee2e2;
            border-left: 4px solid var(--danger);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #b91c1c;
            font-weight: 500;
        }

        /* 2FA Notice */
        .twofa-notice {
            background: #e3f2fd;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            color: var(--primary-dark);
        }

        .twofa-notice i {
            font-size: 1.2rem;
            color: var(--primary);
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 30px 0;
            color: #94a3b8;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .divider span {
            padding: 0 10px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Employee Login Link */
        .employee-link {
            text-align: center;
            margin-top: 20px;
        }

        .employee-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .employee-link a:hover {
            color: var(--primary-dark);
            gap: 12px;
        }

        .employee-link a i {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .employee-link a:hover i {
            transform: rotate(180deg);
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 0.8rem;
        }

        .security-badge span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .security-badge i {
            color: var(--primary);
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                grid-template-columns: 1fr;
                max-width: 500px;
            }
            
            .brand-panel {
                padding: 40px;
            }
            
            .login-panel {
                padding: 40px 30px;
            }
            
            .intro-message {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .login-panel {
                padding: 30px 20px;
            }
            
            .brand-title h1 {
                font-size: 1.8rem;
            }
            
            .time-display .time {
                font-size: 1.5rem;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .security-badge {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Authenticating...</p>
            <small class="text-white-50">Please wait</small>
        </div>
    </div>

    <!-- Animated Background Shapes -->
    <div class="bg-shape">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Main Login Container -->
    <div class="login-container">
        <!-- Brand Panel - Left Side -->
        <div class="brand-panel">
            <div class="brand-logo">
                <img src="logo.jpg" alt="HR3 Logo" onerror="this.src='https://via.placeholder.com/120x120/145374/ffffff?text=HR3'">
            </div>
            <div class="brand-title">
                <span class="system-badge">
                    <i class="fas fa-crown me-2"></i>Enterprise Edition
                </span>
                <h1>E-COMMERCE<br>HUMAN RESOURCE 3</h1>
                <p class="subtitle">SYSTEM</p>
            </div>
            
            <!-- Intro Message - Short & Professional -->
            <div class="intro-message">
                <p><span class="highlight">Complete HR management</span> for modern e-commerce businesses. Streamline payroll, attendance, leave management, and employee data all in one secure platform.</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Real-time attendance tracking</li>
                    <li><i class="fas fa-check-circle"></i> Automated payroll processing</li>
                    <li><i class="fas fa-check-circle"></i> Leave & overtime management</li>
                </ul>
            </div>
            
            <!-- Live Time Display -->
            <div class="time-display">
                <div class="date">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?= date('l, F j, Y') ?>
                </div>
                <div class="time">
                    <i class="fas fa-clock me-2"></i>
                    <?= date('h:i:s A') ?>
                </div>
                <div class="timezone">
                    <i class="fas fa-globe-asia me-1"></i>
                    Philippine Time (PHT)
                </div>
            </div>
            
            <!-- Company Info -->
            <div class="mt-4 text-white-50 small">
                <i class="fas fa-shield-alt me-1"></i> Secure Admin Portal
            </div>
        </div>

        <!-- Login Panel - Right Side -->
        <div class="login-panel">
            <div class="login-header">
                <div class="role-badge">
                    <i class="fas fa-user-tie"></i>
                    <span>Administrator Access</span>
                </div>
                <h2>Welcome Back!</h2>
                <p>Sign in to manage your e-commerce HR system</p>
            </div>

            <!-- Error Message -->
            <?php if($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" id="loginForm">
                <!-- Email Field -->
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" 
                           placeholder="Enter email address" required autocomplete="off"
                           value="<?= htmlspecialchars($email) ?>">
                </div>
                
                <!-- Password Field -->
                <div class="input-group password-field">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Enter password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>

                <!-- 2FA Notice -->
                <div class="twofa-notice">
                    <i class="fas fa-shield-alt"></i>
                    <span>Two-factor authentication will be required after login</span>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn-login" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i>
                    Access Admin Dashboard
                </button>
            </form>

            <!-- Divider -->
            <div class="divider">
                <span>system access</span>
            </div>

            <!-- Employee Login Link -->
            <div class="employee-link">
                <a href="bundy_login.php">
                    <i class="fas fa-exchange-alt"></i>
                    Switch to Employee Bundy Clock
                </a>
            </div>

            <!-- Security Badge -->
            <div class="security-badge">
                <span><i class="fas fa-shield-alt"></i> 256-bit SSL</span>
                <span><i class="fas fa-lock"></i> 2FA Enabled</span>
                <span><i class="fas fa-clock"></i> Real-time Sync</span>
            </div>

            <!-- Version Info -->
            <div class="text-center mt-3">
                <small class="text-muted">E-Commerce HR3 v3.0 • © <?= date('Y') ?> All rights reserved</small>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form loading state with overlay
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            const overlay = document.getElementById('loadingOverlay');
            
            // Show loading overlay immediately
            overlay.style.display = 'flex';
            
            // Disable button
            button.disabled = true;
            
            // Store form data
            const formData = new FormData(this);
            
            // Use fetch for asynchronous submission
            e.preventDefault();
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is a redirect
                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    // If not redirected, reload page to show error
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                overlay.style.display = 'none';
                button.disabled = false;
                alert('An error occurred. Please try again.');
            });
        });

        // Auto-hide error message after 5 seconds
        const errorAlert = document.querySelector('.error-alert');
        if (errorAlert) {
            setTimeout(() => {
                errorAlert.style.transition = 'opacity 0.5s ease';
                errorAlert.style.opacity = '0';
                setTimeout(() => errorAlert.remove(), 500);
            }, 5000);
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.input-group').style.borderColor = 'var(--primary)';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.closest('.input-group').style.borderColor = '#e2e8f0';
                }
            });
        });

        // Smooth scroll to error
        if (errorAlert) {
            errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Live time update
        function updateTime() {
            const timeElement = document.querySelector('.time-display .time');
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                timeElement.innerHTML = `<i class="fas fa-clock me-2"></i>${timeString}`;
            }
        }
        
        setInterval(updateTime, 1000);

        // Email validation hint
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value;
                if (email && !email.includes('@')) {
                    this.closest('.input-group').style.borderColor = 'var(--danger)';
                }
            });
        }

        // Image error fallback
        document.querySelector('img')?.addEventListener('error', function() {
            this.src = 'https://via.placeholder.com/120x120/145374/ffffff?text=HR3';
        });
    </script>
</body>
</html>