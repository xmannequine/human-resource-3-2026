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
<title>Register Face</title>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
body { font-family:sans-serif; text-align:center; background:#f2f2f2; padding:30px; }
video { border-radius:10px; border:2px solid #ccc; }
button { padding:10px 20px; margin-top:15px; background:#00334E; color:white; border:none; border-radius:5px; cursor:pointer; }
button:hover { background:#145374; }
#status { margin-top:10px; font-weight:bold; }
#preview { margin-top:20px; display:none; }
</style>
</head>
<body>

<h2>Face Registration</h2>

<video id="video" width="640" height="480" autoplay muted></video>
<br>
<canvas id="previewCanvas" style="display:none;"></canvas>
<button onclick="registerFace()">Register Face</button>

<p id="status"></p>

<script>
const video = document.getElementById('video');
const previewCanvas = document.getElementById('previewCanvas');
const statusText = document.getElementById('status');

// Get the correct model path
const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
const modelsPath = basePath + 'models';

console.log('Loading models from:', modelsPath);

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
            // Timeout after 10 seconds
            setTimeout(() => clearInterval(checkInterval), 10000);
        }
    });
}

// Load models with the correct path
waitForFaceAPI().then(() => {
    Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(modelsPath),
        faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath),
        faceapi.nets.faceRecognitionNet.loadFromUri(modelsPath)
    ])
    .then(() => {
        console.log('Models loaded successfully');
        statusText.innerText = "✅ Models loaded. Click 'Register Face'.";
        startCamera();
    })
    .catch(err => {
        console.error('Error loading models:', err);
        statusText.innerHTML = "❌ Error loading models: " + err.message + 
            "<br><button onclick='location.reload()' style='margin-top:10px;'>Retry</button>";
    });
}).catch(err => {
    statusText.innerHTML = '❌ face-api.js failed to load<br><button onclick="location.reload()" style="margin-top:10px;">Retry</button>';
    console.error('faceapi loading error:', err);
});

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
        statusText.innerText = "Camera ready. Click 'Register Face' to continue.";
    })
    .catch(() => statusText.innerText = "Camera access denied.");
}

// Register Service Worker for model caching
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch(err => console.log('SW registration failed:', err));
}

async function registerFace(){
    statusText.innerText = "Detecting face...";
    statusText.style.color = "blue";

    try {
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: 512,
                scoreThreshold: 0.5
            }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if(!detection){
            statusText.innerText = "No face detected! Please ensure good lighting and face the camera.";
            statusText.style.color = "red";
            return;
        }

        // Draw to canvas to save image
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = video.videoWidth;
        previewCanvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);
        
        // Draw box around face
        const box = detection.detection.box;
        ctx.strokeStyle = '#00FF00';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        const descriptor = Array.from(detection.descriptor);
        const imageData = previewCanvas.toDataURL('image/png');

        statusText.innerText = "Saving face data...";

        const response = await fetch("save_descriptor.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ 
                descriptor: descriptor,
                image: imageData
            })
        });

        const result = await response.json();
        
        if (result.success) {
            statusText.style.color = "green";
            statusText.innerHTML = "✅ Face registered successfully!<br>";
            if (result.message) {
                statusText.innerHTML += result.message;
            }
            // Disable button after success
            document.querySelector('button').disabled = true;
            document.querySelector('button').style.opacity = '0.5';
        } else {
            throw new Error(result.error || "Unknown error");
        }

    } catch(err) {
        statusText.style.color = "red";
        statusText.innerText = "Error: " + err.message;
        console.error(err);
    }
}
</script>

</body>
</html>