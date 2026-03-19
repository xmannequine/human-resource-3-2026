<?php
session_start();
require_once('config2.php');

// Set default timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

$error = '';

// --- Username/password login ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if($username && $password){
        $stmt = $conn->prepare("
            SELECT id, firstname, lastname, password
            FROM employee
            WHERE username = :username
            LIMIT 1
        ");
        $stmt->execute([':username'=>$username]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if($employee && password_verify($password, $employee['password'])){
            // Set session for bundy
            $_SESSION['employee_id'] = $employee['id'];
            $_SESSION['employee_name'] = $employee['firstname'] . " " . $employee['lastname'];

            header("Location: bundy.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bundy Clock | Employee Login</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- face-api.js -->
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    
    <style>
        :root {
            --primary: #17758F;
            --primary-dark: #0e5a6f;
            --primary-light: #5da6b3;
            --secondary: #145374;
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
            background: linear-gradient(145deg, var(--primary) 0%, var(--primary-light) 100%);
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
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
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
            margin-bottom: 40px;
        }

        .brand-logo img {
            width: 140px;
            height: 140px;
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
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .brand-title p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .time-display {
            position: relative;
            z-index: 10;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 30px;
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

        /* Right Panel - Login Forms */
        .login-panel {
            padding: 60px 50px;
            background: white;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h2 {
            color: var(--dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }

        /* Tab Switcher */
        .login-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 15px;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 12px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1rem;
        }

        .tab-btn i {
            margin-right: 8px;
        }

        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        /* Forms */
        .login-form {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .login-form.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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
            box-shadow: 0 0 0 4px rgba(23, 117, 143, 0.1);
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

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, var(--primary) 0%, var(--secondary) 100%);
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
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(23, 117, 143, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Face Login Section */
        .face-login-container {
            text-align: center;
            margin-top: 20px;
        }

        .camera-wrapper {
            background: #1a1a1a;
            border-radius: 20px;
            overflow: hidden;
            margin: 20px 0;
            position: relative;
            border: 3px solid var(--primary);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        #webcam {
            width: 100%;
            height: auto;
            display: block;
            transform: scaleX(-1); /* Mirror effect */
        }

        .camera-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            border: 3px solid var(--primary);
            border-radius: 17px;
            box-shadow: inset 0 0 30px rgba(23, 117, 143, 0.3);
        }

        .face-detection-frame {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px dashed rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.05); opacity: 0.8; }
        }

        .btn-capture {
            background: linear-gradient(145deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin: 15px 0;
            cursor: pointer;
        }

        .btn-capture:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
        }

        .btn-capture:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-capture i {
            font-size: 1.2rem;
        }

        /* Status Messages */
        .status-message {
            padding: 15px;
            border-radius: 12px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            animation: slideIn 0.3s ease;
        }

        .status-message.info {
            background: #e3f2fd;
            color: #0d47a1;
        }

        .status-message.success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .status-message.error {
            background: #ffebee;
            color: #b71c1c;
        }

        .status-message.warning {
            background: #fff3e0;
            color: #bf360c;
        }

        .status-message i {
            font-size: 1.2rem;
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
        }

        @media (max-width: 576px) {
            .login-panel {
                padding: 30px 20px;
            }
            
            .brand-title h1 {
                font-size: 2rem;
            }
            
            .time-display .time {
                font-size: 1.5rem;
            }
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
    </style>
</head>
<body>
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
                <img src="logo.jpg" alt="Company Logo" onerror="this.src='https://via.placeholder.com/140'">
            </div>
            <div class="brand-title">
                <h1>BUNDY CLOCK</h1>
                <p>Employee Time Management System</p>
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
                <i class="fas fa-shield-alt me-1"></i> Secure Login System
            </div>
        </div>

        <!-- Login Panel - Right Side -->
        <div class="login-panel">
            <div class="login-header">
                <h2>Welcome Back!</h2>
                <p>Choose your preferred login method</p>
            </div>

            <!-- Error Message -->
            <?php if($error): ?>
                <div class="error-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Login Method Tabs -->
            <div class="login-tabs">
                <button class="tab-btn active" onclick="switchTab('password')" id="tabPassword">
                    <i class="fas fa-key"></i> Password
                </button>
                <button class="tab-btn" onclick="switchTab('face')" id="tabFace">
                    <i class="fas fa-face-smile"></i> Face Recognition
                </button>
            </div>

            <!-- Password Login Form -->
            <div class="login-form active" id="passwordForm">
                <form method="post">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" 
                               placeholder="Enter username" required autocomplete="off">
                    </div>
                    
                    <div class="input-group password-field">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i>
                        Login with Password
                    </button>
                </form>
            </div>

            <!-- Face Recognition Login Form -->
            <div class="login-form" id="faceForm">
                <div class="face-login-container">
                    <div class="camera-wrapper">
                        <video id="webcam" autoplay muted playsinline></video>
                        <div class="camera-overlay"></div>
                        <div class="face-detection-frame"></div>
                    </div>

                    <button id="captureBtn" class="btn-capture">
                        <i class="fas fa-camera"></i>
                        Capture & Verify Face
                    </button>

                    <div id="status" class="status-message info">
                        <i class="fas fa-info-circle"></i>
                        <span>Initializing camera...</span>
                    </div>

                    <div class="divider">
                        <span>or</span>
                    </div>

                    <button class="btn-login" onclick="switchTab('password')">
                        <i class="fas fa-key"></i>
                        Use Password Instead
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab Switching Function
        function switchTab(tab) {
            const passwordTab = document.getElementById('tabPassword');
            const faceTab = document.getElementById('tabFace');
            const passwordForm = document.getElementById('passwordForm');
            const faceForm = document.getElementById('faceForm');
            
            if (tab === 'password') {
                passwordTab.classList.add('active');
                faceTab.classList.remove('active');
                passwordForm.classList.add('active');
                faceForm.classList.remove('active');
            } else {
                faceTab.classList.add('active');
                passwordTab.classList.remove('active');
                faceForm.classList.add('active');
                passwordForm.classList.remove('active');
            }
        }

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

        // Face Recognition Script
        const video = document.getElementById('webcam');
        const captureBtn = document.getElementById('captureBtn');
        const statusDiv = document.getElementById('status');
        
        // Update status message
        function updateStatus(message, type = 'info') {
            statusDiv.className = `status-message ${type}`;
            statusDiv.innerHTML = `
                <i class="fas fa-${type === 'info' ? 'info-circle' : 
                                    type === 'success' ? 'check-circle' : 
                                    type === 'error' ? 'exclamation-circle' : 
                                    'exclamation-triangle'}"></i>
                <span>${message}</span>
            `;
        }

        // Wait for faceapi
        function waitForFaceAPI() {
            return new Promise((resolve) => {
                if (typeof faceapi !== 'undefined') {
                    resolve();
                } else {
                    const checkInterval = setInterval(() => {
                        if (typeof faceapi !== 'undefined') {
                            clearInterval(checkInterval);
                            resolve();
                        }
                    }, 100);
                    setTimeout(() => clearInterval(checkInterval), 10000);
                }
            });
        }

        // Start camera
        async function startCamera() {
            try {
                updateStatus('Requesting camera access...', 'info');
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    } 
                });
                video.srcObject = stream;
                updateStatus('Camera ready. Position your face in the frame.', 'info');
            } catch (err) {
                updateStatus('Camera access denied or unavailable', 'error');
                console.error(err);
            }
        }

        // Load models
        async function loadModels() {
            try {
                await waitForFaceAPI();
                
                if (typeof faceapi === 'undefined') {
                    throw new Error('Face detection library not loaded');
                }
                
                updateStatus('Loading face detection models...', 'info');
                
                // Load detection models with timeout
                await Promise.race([
                    Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri('./models'),
                        faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
                        faceapi.nets.faceRecognitionNet.loadFromUri('./models')
                    ]),
                    new Promise((_, reject) => 
                        setTimeout(() => reject(new Error('Model loading timeout')), 30000)
                    )
                ]);
                
                updateStatus('Ready for face recognition!', 'success');
                
            } catch (err) {
                updateStatus('Error loading models: ' + err.message, 'error');
                console.error(err);
            }
        }

        // Capture face
        async function captureFace() {
            updateStatus('Detecting face...', 'info');
            captureBtn.disabled = true;
            
            try {
                // Detect face
                const detection = await faceapi
                    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                        inputSize: 320,
                        scoreThreshold: 0.5
                    }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if(!detection){
                    updateStatus('No face detected. Please try again.', 'warning');
                    captureBtn.disabled = false;
                    return;
                }

                updateStatus('Face detected! Verifying...', 'info');
                
                // Send descriptor to server
                const descriptor = Array.from(detection.descriptor);
                
                const resp = await fetch("face_login.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({descriptor: descriptor})
                });

                // Check response
                if (!resp.ok) {
                    throw new Error(`Server error: ${resp.status}`);
                }

                const result = await resp.json();
                
                if(result.status === "success"){
                    updateStatus('✅ Match found! Redirecting...', 'success');
                    setTimeout(() => {
                        status.textContent = "✅ Match found! Logging in...";
                        console.log('Redirecting to:', result.redirect);
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1500);
                    }, 1500);
                } else {
                    updateStatus(result.message || 'Face not recognized', 'error');
                    captureBtn.disabled = false;
                }
                
            } catch(err){
                updateStatus('Error: ' + err.message, 'error');
                console.error(err);
                captureBtn.disabled = false;
            }
        }

        // Initialize camera and models
        startCamera();
        loadModels();
        
        // Add event listener to capture button
        if (captureBtn) {
            captureBtn.addEventListener('click', captureFace);
        }

        // Auto-hide status message after 5 seconds for success
        setInterval(() => {
            if (statusDiv.classList.contains('success')) {
                setTimeout(() => {
                    statusDiv.style.opacity = '0';
                    setTimeout(() => {
                        statusDiv.style.opacity = '1';
                        updateStatus('Ready for face recognition!', 'success');
                    }, 500);
                }, 5000);
            }
        }, 1000);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add loading animation to login button
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn) {
                    btn.innerHTML = '<span class="spinner"></span> Processing...';
                    btn.disabled = true;
                }
            });
        });

        // Handle video playback issues
        video.addEventListener('playing', () => {
            console.log('Video is playing');
        });

        video.addEventListener('error', (e) => {
            updateStatus('Camera error: ' + e.message, 'error');
        });
    </script>
</body>
</html>