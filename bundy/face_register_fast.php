<?php
session_start();
require_once('config2.php');

if (!isset($_SESSION['employee_id'])) {
    header("Location: ess_login.php");
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];
?>
<!DOCTYPE html>
<html>
<head>
<title>Register Face - Fast</title>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
body { font-family:sans-serif; text-align:center; background:#f2f2f2; padding:30px; }
video { border-radius:10px; border:2px solid #ccc; }
button { padding:10px 20px; margin-top:15px; background:#00334E; color:white; border:none; border-radius:5px; cursor:pointer; font-weight: bold; }
button:hover { background:#145374; }
button:disabled { background:#ccc; cursor: not-allowed; }
#status { margin-top:10px; font-weight:bold; min-height: 20px; }
#preview { margin-top:20px; display:none; }
canvas { border: 2px solid green; border-radius: 10px; max-width: 100%; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
</style>
</head>
<body>

<h2>Face Registration - Fast Version</h2>
<p style="color: #666;">(Detection + Landmarks only - no recognition yet)</p>

<video id="video" width="640" height="480" autoplay muted></video>
<br>
<canvas id="previewCanvas" style="display:none;"></canvas>
<button id="registerBtn" onclick="registerFace()">📷 Capture Face</button>
<button id="submitBtn" style="display:none;" onclick="submitFace()">✅ Save & Submit</button>

<p id="status"></p>

<script>
const video = document.getElementById('video');
const previewCanvas = document.getElementById('previewCanvas');
const statusText = document.getElementById('status');
const registerBtn = document.getElementById('registerBtn');
const submitBtn = document.getElementById('submitBtn');

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

// Load ONLY needed models (skip FaceRecognitionNet - it's 160MB!)
async function loadModels() {
    try {
        statusText.innerHTML = 'Loading models...';
        statusText.className = 'info';
        
        await waitForFaceAPI();
        
        if (typeof faceapi === 'undefined') {
            throw new Error('face-api.js not loaded');
        }
        
        const modelsPath = './models';
        
        // Load only detection + landmarks (much faster!)
        await Promise.race([
            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath)
            ]),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Models took too long to load')), 15000)
            )
        ]);
        
        statusText.innerHTML = '✅ Models ready. Click "Capture Face".';
        statusText.className = 'success';
        registerBtn.disabled = false;
        startCamera();
        
    } catch (err) {
        statusText.innerHTML = '❌ Error loading models: ' + err.message;
        statusText.className = 'error';
        console.error(err);
        
        // Retry button
        statusText.innerHTML += '<br><button onclick="location.reload()" style="margin-top:10px;">Retry</button>';
    }
}

function startCamera(){
    navigator.mediaDevices.getUserMedia({ 
        video: { 
            width: 640, 
            height: 480,
            facingMode: "user" 
        } 
    })
    .then(stream => {
        video.srcObject = stream;
        statusText.innerHTML = '✅ Camera ready. Click "Capture Face".';
        statusText.className = 'success';
    })
    .catch(err => {
        statusText.innerHTML = '❌ Camera access denied: ' + err.message;
        statusText.className = 'error';
    });
}

async function registerFace(){
    statusText.innerHTML = '⏳ Detecting face...';
    statusText.className = 'info';
    registerBtn.disabled = true;

    try {
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: 512,
                scoreThreshold: 0.5
            }))
            .withFaceLandmarks();

        if(!detection){
            statusText.innerHTML = '❌ No face detected! Ensure good lighting and face the camera.';
            statusText.className = 'error';
            registerBtn.disabled = false;
            return;
        }

        // Draw to canvas
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = video.videoWidth;
        previewCanvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);
        
        // Draw box around face
        const box = detection.detection.box;
        ctx.strokeStyle = '#00FF00';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        previewCanvas.style.display = 'block';
        imageData = previewCanvas.toDataURL('image/png');

        // Store landmarks as descriptor (simpler alternative)
        const landmarks = detection.landmarks.positions;
        faceDescriptorData = JSON.stringify(landmarks);

        statusText.innerHTML = '✅ Face captured! Click "Save & Submit".';
        statusText.className = 'success';
        registerBtn.style.display = 'none';
        submitBtn.style.display = 'inline-block';

    } catch(err) {
        statusText.innerHTML = '❌ Error: ' + err.message;
        statusText.className = 'error';
        registerBtn.disabled = false;
        console.error(err);
    }
}

async function submitFace() {
    statusText.innerHTML = '⏳ Submitting face data...';
    statusText.className = 'info';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch("save_descriptor.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ 
                descriptor: faceDescriptorData,
                image: imageData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            statusText.innerHTML = '✅ Face registered successfully!<br>You can now login with face recognition.';
            statusText.className = 'success';
            submitBtn.disabled = true;
            setTimeout(() => window.location.href = 'bundy.php', 2000);
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

// Initialize
window.addEventListener('load', loadModels);

// Register Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch(err => console.log('SW registration failed:', err));
}
</script>

</body>
</html>