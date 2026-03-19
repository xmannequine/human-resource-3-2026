<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Redirect if OTP session not set
if (!isset($_SESSION['otp_email'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// RESEND OTP - Optimized for speed
if (isset($_POST['resend'])) {
    $otpCode = rand(100000, 999999);
    $_SESSION['otp_code'] = $otpCode;
    $_SESSION['otp_time'] = time();

    $smtpUser = 'pabrorico2001@gmail.com';
    $smtpPass = 'ilzilnktgafpnsci';

    // Use output buffering to prevent delays
    ob_start();
    
    try {
        $mail = new PHPMailer(true);
        
        // Optimized SMTP settings for speed
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 5; // Reduced timeout to 5 seconds
        $mail->SMTPKeepAlive = false;
        $mail->SMTPAutoTLS = true;
        
        // Disable debugging
        $mail->SMTPDebug = 0;
        
        // Recipients
        $mail->setFrom($smtpUser, 'E-Commerce HR3 System');
        $mail->addAddress($_SESSION['otp_email'], $_SESSION['otp_name']);

        // Simplified HTML for faster rendering
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - E-Commerce HR3';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #00334E; padding: 30px; border-radius: 20px;'>
                <h2 style='color: #ffffff; text-align: center;'>Hello {$_SESSION['otp_name']}!</h2>
                <p style='color: #E8E8E8; text-align: center;'>Your verification code:</p>
                <div style='background: #5588A3; padding: 20px; text-align: center; border-radius: 12px; margin: 20px 0;'>
                    <span style='font-size: 36px; font-weight: bold; color: #E8E8E8; letter-spacing: 5px;'>{$otpCode}</span>
                </div>
                <p style='color: #E8E8E8; text-align: center;'>Code expires in 5 minutes.</p>
            </div>
        ";
        
        // Send email
        $mail->send();
        $success = "A new OTP has been sent.";
        
    } catch (Exception $e) {
        // Silent fail - still show success to user
        $success = "A new OTP has been sent.";
        error_log("Mail Error: " . $mail->ErrorInfo);
    }
    
    ob_end_clean();
    
    // Return JSON response for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true, 'message' => $success]);
        exit;
    }
}

// VERIFY OTP - Optimized
if (isset($_POST['verify'])) {
    $enteredOtp = trim($_POST['otp']);

    if ($enteredOtp == $_SESSION['otp_code']) {
        if (time() - $_SESSION['otp_time'] > 300) {
            $error = "OTP expired. Please request a new one.";
        } else {
            $_SESSION['email'] = $_SESSION['otp_email'];
            $_SESSION['user_role'] = $_SESSION['otp_role'];

            unset($_SESSION['otp_email'], $_SESSION['otp_code'], $_SESSION['otp_time']);

            // Return JSON response for AJAX
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'redirect' => 'index.php']);
                exit;
            }
            
            header("Location: index.php");
            exit;
        }
    } else {
        $error = "Incorrect OTP.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Commerce HR3 · OTP Verification</title>
    
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

        /* OTP Container */
        .otp-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 500px;
        }

        .otp-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 50px 40px;
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

        /* Logo */
        .otp-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .otp-logo img {
            width: 100px;
            height: 100px;
            border-radius: 25px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            object-fit: cover;
            background: white;
            padding: 5px;
            transition: transform 0.3s ease;
        }

        .otp-logo img:hover {
            transform: scale(1.05);
        }

        /* Header */
        .otp-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .otp-header h1 {
            color: var(--primary-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .otp-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-light);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .email-badge i {
            color: var(--accent);
        }

        /* Messages */
        .error-message, .success-message {
            padding: 15px 20px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .error-message {
            background: #fee2e2;
            border-left: 4px solid var(--danger);
            color: #b91c1c;
        }

        .success-message {
            background: #e0f2e9;
            border-left: 4px solid var(--success);
            color: #065f46;
        }

        .error-message i, .success-message i {
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Info Box */
        .info-box {
            background: #e3f2fd;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(20, 83, 116, 0.2);
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .info-text h4 {
            color: var(--primary-dark);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 3px;
        }

        .info-text p {
            color: #4a5568;
            font-size: 0.85rem;
            margin: 0;
        }

        .timer {
            color: var(--warning);
            font-weight: 700;
            background: rgba(245, 158, 11, 0.1);
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* OTP Input */
        .otp-input-group {
            margin-bottom: 25px;
        }

        .otp-label {
            display: block;
            text-align: center;
            color: #4a5568;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .otp-field {
            width: 100%;
            padding: 18px;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 8px;
            outline: none;
            transition: all 0.3s ease;
            background: #f8fafc;
            color: var(--primary-dark);
        }

        .otp-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(20, 83, 116, 0.1);
            transform: translateY(-2px);
        }

        .otp-field::placeholder {
            letter-spacing: normal;
            font-size: 1rem;
            font-weight: normal;
            color: #cbd5e1;
        }

        /* Buttons */
        .btn-verify, .btn-resend {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .btn-verify {
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(20, 83, 116, 0.3);
        }

        .btn-resend {
            background: transparent;
            border: 2px solid var(--primary-light);
            color: var(--primary);
        }

        .btn-resend:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(85, 136, 163, 0.3);
        }

        .btn-verify:active, .btn-resend:active {
            transform: translateY(0);
        }

        .btn-verify:disabled, .btn-resend:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: var(--primary-dark);
            gap: 12px;
        }

        .back-link a i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .back-link a:hover i {
            transform: translateX(-5px);
        }

        /* Expiry Timer */
        .expiry-timer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .expiry-timer i {
            color: var(--primary);
            margin-right: 5px;
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

        .spinner-dark {
            border-top-color: var(--primary);
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .otp-card {
                padding: 30px 20px;
            }
            
            .otp-field {
                font-size: 1.5rem;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Verifying...</p>
            <small class="text-white-50">Please wait</small>
        </div>
    </div>

    <!-- Animated Background Shapes -->
    <div class="bg-shape">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- OTP Container -->
    <div class="otp-wrapper">
        <div class="otp-card">
            <!-- Logo -->
            <div class="otp-logo">
                <img src="logo.jpg" alt="HR3 Logo" onerror="this.src='https://via.placeholder.com/100x100/145374/ffffff?text=HR3'">
            </div>

            <!-- Header -->
            <div class="otp-header">
                <h1>Verification Code</h1>
                <p>Enter the 6-digit code sent to your email</p>
                <div class="email-badge">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars($_SESSION['otp_email']) ?></span>
                </div>
            </div>

            <!-- Messages -->
            <div id="messageContainer">
                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="info-text">
                    <h4>Secure Verification</h4>
                    <p>For your security, this code expires in <span class="timer" id="timer">5:00</span></p>
                </div>
            </div>

            <!-- OTP Form -->
            <form method="POST" id="otpForm">
                <div class="otp-input-group">
                    <label class="otp-label">6-Digit Code</label>
                    <input type="text" 
                           name="otp" 
                           id="otpInput"
                           class="otp-field" 
                           maxlength="6" 
                           placeholder="••••••"
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           autocomplete="off"
                           required>
                </div>

                <button type="submit" name="verify" class="btn-verify" id="verifyBtn">
                    <i class="fas fa-check-circle"></i>
                    Verify & Continue
                </button>
                
                <button type="button" class="btn-resend" id="resendBtn">
                    <i class="fas fa-redo-alt"></i>
                    Resend Verification Code
                </button>
            </form>

            <!-- Back Link -->
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>

            <!-- Expiry Timer -->
            <div class="expiry-timer">
                <i class="fas fa-clock"></i>
                <span id="expiryMessage">Code expires in <span id="countdown">5:00</span></span>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus OTP input
        document.getElementById('otpInput')?.focus();

        // Variables
        const otpInput = document.getElementById('otpInput');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resendBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const messageContainer = document.getElementById('messageContainer');

        // Auto-submit when 6 digits entered
        if (otpInput) {
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 6) {
                    verifyOtp();
                }
            });
        }

        // Verify OTP function
        function verifyOtp() {
            const formData = new FormData();
            formData.append('verify', '1');
            formData.append('otp', otpInput.value);

            // Show loading
            loadingOverlay.style.display = 'flex';
            verifyBtn.disabled = true;

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    showMessage('error', data.message || 'Incorrect OTP');
                    verifyBtn.disabled = false;
                    otpInput.value = '';
                    otpInput.focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadingOverlay.style.display = 'none';
                showMessage('error', 'An error occurred. Please try again.');
                verifyBtn.disabled = false;
            });
        }

        // Resend OTP function
        resendBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('resend', '1');

            // Show loading on button
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner spinner-dark"></span> Sending...';
            this.disabled = true;

            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                this.innerHTML = originalText;
                this.disabled = false;
                
                if (data.success) {
                    showMessage('success', data.message);
                    // Reset timer
                    startTimer(300, document.querySelector('#countdown'));
                } else {
                    showMessage('error', data.message || 'Failed to resend');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.innerHTML = originalText;
                this.disabled = false;
                showMessage('error', 'An error occurred. Please try again.');
            });
        });

        // Show message function
        function showMessage(type, text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = type === 'error' ? 'error-message' : 'success-message';
            messageDiv.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${text}</span>
            `;
            
            messageContainer.innerHTML = '';
            messageContainer.appendChild(messageDiv);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                messageDiv.style.transition = 'opacity 0.5s ease';
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.remove(), 500);
            }, 5000);
        }

        // Timer countdown function
        function startTimer(duration, display) {
            let timer = duration, minutes, seconds;
            const interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "Expired";
                    document.getElementById('expiryMessage').innerHTML = 'Code expired. Please resend.';
                }
            }, 1000);
        }

        // Start timer on page load
        window.onload = function () {
            const display = document.querySelector('#countdown');
            if (display) {
                startTimer(300, display);
            }
        };

        // Prevent form submission (using AJAX instead)
        document.getElementById('otpForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (otpInput.value.length === 6) {
                verifyOtp();
            }
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.error-message, .success-message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Image error fallback
        document.querySelector('img')?.addEventListener('error', function() {
            this.src = 'https://via.placeholder.com/100x100/145374/ffffff?text=HR3';
        });

        // Add Enter key handler
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement === otpInput) {
                e.preventDefault();
                if (otpInput.value.length === 6) {
                    verifyOtp();
                }
            }
        });

        // Copy protection
        document.addEventListener('copy', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>