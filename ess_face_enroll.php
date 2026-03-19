<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['employee_id'])) {
    header("Location: ess_login.php");
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];

// Fetch employee info
$stmt = $conn->prepare("SELECT firstname, lastname, face_registered FROM employee WHERE id=? LIMIT 1");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
$employee_name = $employee['firstname'].' '.$employee['lastname'];
$face_registered = $employee['face_registered'] ?? 0;

// Handle face enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['face_descriptor'])) {
    $descriptor = $_POST['face_descriptor'];
    $image_data = $_POST['face_image_data'] ?? '';
    
    $imagePath = null;
    if ($image_data) {
        $imagePath = 'uploads/face_' . $employee_id . '_' . time() . '.png';
        file_put_contents($imagePath, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image_data)));
    }
    
    $stmt = $conn->prepare("UPDATE employee SET face_descriptor = ?, face_registered = 1, face_image = COALESCE(?, face_image) WHERE id = ?");
    $stmt->execute([$descriptor, $imagePath, $employee_id]);
    
    echo json_encode(['success' => true, 'message' => 'Face enrolled successfully!']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Face Enrollment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #004aad, #6fa3ef);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 20px;
}

.container-main {
    max-width: 1000px;
    margin: 0 auto;
}

.header {
    background: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-align: center;
}

.header h1 {
    color: #00334E;
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
}

.header p {
    color: #666;
    font-size: 18px;
}

.header .employee-info {
    margin-top: 15px;
    color: #00334E;
    font-weight: 600;
    font-size: 16px;
}

.enrollment-card {
    background: white;
    padding: 40px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.video-container {
    margin: 20px 0;
    display: flex;
    justify-content: center;
}

#webcam {
    width: 100%;
    max-width: 900px;
    height: 600px;
    border-radius: 15px;
    border: 4px solid #00334E;
    object-fit: cover;
    background: #000;
}

.controls {
    margin-top: 30px;
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.btn-large {
    padding: 18px 40px;
    font-size: 18px;
    font-weight: bold;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 200px;
}

.btn-capture {
    background-color: #00334E;
    color: white;
}

.btn-capture:hover:not(:disabled) {
    background-color: #145374;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 51, 78, 0.3);
}

.btn-submit {
    background-color: #28a745;
    color: white;
}

.btn-submit:hover:not(:disabled) {
    background-color: #218838;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
}

.btn-back {
    background-color: #6c757d;
    color: white;
    text-decoration: none;
}

.btn-back:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
    color: white;
}

.btn-large:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    opacity: 0.6;
}

#status {
    margin-top: 20px;
    padding: 15px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    min-height: 40px;
    display: none;
}

#status.success {
    background-color: #d4edda;
    color: #155724;
    border: 2px solid #28a745;
    display: block;
}

#status.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 2px solid #f44336;
    display: block;
}

#status.info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 2px solid #17a2b8;
    display: block;
}

#preview {
    margin-top: 30px;
    text-align: center;
    display: none;
}

#previewCanvas {
    max-width: 100%;
    border-radius: 15px;
    border: 4px solid #28a745;
    display: block;
    margin: 0 auto;
}

.preview-message {
    color: #28a745;
    margin-top: 15px;
    font-size: 18px;
    font-weight: bold;
}

.instructions {
    background-color: #e7f3ff;
    border-left: 5px solid #00334E;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 5px;
}

.instructions h5 {
    color: #00334E;
    font-weight: bold;
    margin-bottom: 10px;
}

.instructions ul {
    margin-bottom: 0;
    color: #333;
}

.instructions li {
    margin-bottom: 8px;
}
</style>
</head>
<body>

<div class="container-main">
    <!-- Header -->
    <div class="header">
        <h1>📷 Face Enrollment</h1>
        <p>Register your face for biometric login</p>
        <div class="employee-info">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($employee_name) ?>
            <?php if($face_registered): ?>
                <span style="margin-left:10px; color:#28a745;"><i class="bi bi-check-circle-fill"></i> Already Registered</span>
            <?php else: ?>
                <span style="margin-left:10px; color:#f44336;"><i class="bi bi-exclamation-circle-fill"></i> Not Yet Registered</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="enrollment-card">
        <!-- Instructions -->
        <div class="instructions">
            <h5>📋 Instructions</h5>
            <ul>
                <li>📷 Position your face in the center of the camera</li>
                <li>💡 Ensure good lighting</li>
                <li>👁️ Look directly at the camera</li>
                <li>📸 Click "Capture Face" button when ready</li>
                <li>✅ Review the captured image and submit</li>
            </ul>
        </div>

        <!-- Video Feed -->
        <div class="video-container">
            <video id="webcam" autoplay muted></video>
        </div>

        <!-- Preview -->
        <div id="preview">
            <canvas id="previewCanvas" width="900" height="600"></canvas>
            <p class="preview-message">✅ Face captured successfully!</p>
        </div>

        <!-- Status Message -->
        <div id="status"></div>

        <!-- Controls -->
        <div class="controls">
            <button id="captureBtn" class="btn-large btn-capture" disabled>
                <i class="bi bi-camera"></i> Capture Face
            </button>
            <button id="submitBtn" class="btn-large btn-submit" disabled style="display:none;">
                <i class="bi bi-check-circle"></i> Save & Submit
            </button>
            <a href="ess_dashboard.php" class="btn-large btn-back">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<script>
const video = document.getElementById('webcam');
const previewCanvas = document.getElementById('previewCanvas');
const statusText = document.getElementById('status');
const captureBtn = document.getElementById('captureBtn');
const submitBtn = document.getElementById('submitBtn');
const preview = document.getElementById('preview');

let faceDescriptorData = null;
let imageData = null;

// Wait for faceapi to be defined
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

// Load models
async function loadModels() {
    try {
        statusText.innerHTML = '⏳ Loading detection models...';
        statusText.className = 'info';
        statusText.style.display = 'block';
        
        await waitForFaceAPI();
        
        if (typeof faceapi === 'undefined') {
            throw new Error('face-api.js not loaded');
        }
        
        const modelsPath = './bundy/models';
        
        // Load ALL models needed (including FaceRecognitionNet for descriptor)
        await Promise.race([
            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelsPath)  // IMPORTANT for descriptors
            ]),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Model loading timeout')), 30000)
            )
        ]);
        
        statusText.innerHTML = '✅ Models ready. Starting camera...';
        statusText.className = 'success';
        startCamera();
        
    } catch (err) {
        statusText.innerHTML = '❌ Error loading models: ' + err.message + 
            '<br><button onclick="location.reload()" class="btn btn-sm btn-primary mt-2">Retry</button>';
        statusText.className = 'error';
        console.error(err);
    }
}

function startCamera(){
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            width: 900, 
            height: 600,
            facingMode: "user" 
        } 
    })
    .then(stream => {
        video.srcObject = stream;
        statusText.innerHTML = '✅ Camera ready. Click "Capture Face".';
        statusText.className = 'success';
        captureBtn.disabled = false;
    })
    .catch(err => {
        statusText.innerHTML = '❌ Camera access denied: ' + err.message;
        statusText.className = 'error';
    });
}

async function captureFace(){
    statusText.innerHTML = '⏳ Detecting face... (this takes 3-5 seconds)';
    statusText.className = 'info';
    captureBtn.disabled = true;

    try {
        // Set a timeout for detection
        const detectionPromise = faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416 }))
            .withFaceLandmarks()
            .withFaceDescriptor();
        
        const timeoutPromise = new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Detection timed out. Try again or refresh the page.')), 15000)
        );
        
        const detection = await Promise.race([detectionPromise, timeoutPromise]);

        if(!detection){
            statusText.innerHTML = '❌ No face detected! Ensure good lighting and face the camera.';
            statusText.className = 'error';
            captureBtn.disabled = false;
            return;
        }

        // Draw to canvas
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = video.videoWidth;
        previewCanvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        // Draw box around face
        const box = detection.detection.box;
        ctx.strokeStyle = '#28a745';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        preview.style.display = 'block';
        imageData = previewCanvas.toDataURL('image/png');
        
        // Store the FACE DESCRIPTOR (128 float array for recognition)
        // NOT landmarks - we need the actual descriptor for matching
        if (!detection.descriptor) {
            throw new Error('Face descriptor not detected. Try again.');
        }
        faceDescriptorData = JSON.stringify(Array.from(detection.descriptor));

        statusText.innerHTML = '✅ Face captured! Click "Save & Submit".';
        statusText.className = 'success';
        
        captureBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';
        submitBtn.disabled = false;

    } catch(err) {
        statusText.innerHTML = '❌ Error: ' + err.message;
        statusText.className = 'error';
        captureBtn.disabled = false;
        console.error(err);
    }
}

async function submitFace() {
    statusText.innerHTML = '⏳ Submitting face data...';
    statusText.className = 'info';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch("ess_face_save.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ 
                descriptor: faceDescriptorData,
                image: imageData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            statusText.innerHTML = '✅ Face registered successfully!<br>Redirecting to dashboard...';
            statusText.className = 'success';
            submitBtn.disabled = true;
            setTimeout(() => window.location.href = 'ess_dashboard.php', 2000);
        } else {
            throw new Error(result.error || "Unknown error");
        }

    } catch(err) {
        statusText.innerHTML = '❌ Error: ' + err.message;
        statusText.className = 'error';
        submitBtn.disabled = false;
        console.error(err);
    }
}

// Event listeners
captureBtn.addEventListener('click', captureFace);
submitBtn.addEventListener('click', submitFace);

// Initialize
window.addEventListener('load', loadModels);
</script>

</body>
</html>
