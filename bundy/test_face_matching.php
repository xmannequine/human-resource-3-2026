<?php
session_start();
require_once('config2.php');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Face Matching Debug</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #00334E; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .video-container { display: flex; gap: 20px; }
        video { 
            width: 400px; 
            height: 300px; 
            border: 2px solid #00334E; 
            border-radius: 5px; 
            background: #000;
            object-fit: cover;
        }
        canvas { display: none; }
        button { padding: 10px 20px; background: #00334E; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px 0; }
        button:hover { background: #005a8a; }
        .result { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        .descriptor-preview { font-family: monospace; background: #f9f9f9; padding: 10px; border-radius: 3px; max-height: 200px; overflow-y: auto; }
        .comparison-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .comparison-table td { padding: 8px; border: 1px solid #ddd; }
        .comparison-table td:first-child { font-weight: bold; width: 200px; background: #f9f9f9; }
        .enrolled-faces { margin-top: 20px; }
        .face-item { background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Face Matching Debug Tool</h1>
    <p>Capture a new face and compare it with enrolled faces in the database.</p>

    <div class="section">
        <h3>1. Capture Face</h3>
        <div class="video-container">
            <div>
                <p><strong>Camera Feed:</strong></p>
                <video id="video" autoplay playsinline muted style="width: 400px; height: 300px; background: #000; border: 2px solid #00334E; border-radius: 5px;"></video>
                <button onclick="captureFrame()">📸 Capture</button>
                <button onclick="stopCamera()">Stop Camera</button>
            </div>
            <div>
                <p><strong>Captured:</strong></p>
                <canvas id="canvas" width="400" height="300"></canvas>
                <img id="capturedImage" style="width:400px; height:300px; border: 2px solid #00334E; border-radius: 5px; display:none;">
            </div>
        </div>
    </div>

    <div class="section">
        <h3>2. Face Detection Results</h3>
        <div id="detectionResults">⏳ Loading models and initializing camera...</div>
    </div>

    <div class="section">
        <h3>3. Matching Against Enrolled Faces</h3>
        <div id="matchingResults">Capture a face first...</div>
    </div>

    <div class="enrolled-faces">
        <h3>📋 Enrolled Faces in Database</h3>
        <div id="enrolledFacesList">Loading...</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let ctx = canvas.getContext('2d');
let capturedDescriptor = null;

// Load models
async function loadModels() {
    try {
        console.log('Loading face-api models...');
        const modelPath = 'models/';
        console.log('Model path:', modelPath);
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(modelPath),
            faceapi.nets.faceLandmark68Net.loadFromUri(modelPath),
            faceapi.nets.faceRecognitionNet.loadFromUri(modelPath)
        ]);
        console.log('✅ Models loaded successfully');
        document.getElementById('detectionResults').innerHTML = '<div class="result success">✅ Models loaded. Starting camera...</div>';
        startCamera();
    } catch(e) {
        console.error('Model loading error:', e);
        document.getElementById('detectionResults').innerHTML = '<div class="result error">❌ Error loading models: ' + e.message + '</div>';
    }
}

async function startCamera() {
    try {
        console.log('Requesting camera access...');
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: 400, height: 300 }
        });
        console.log('✅ Camera access granted');
        video.srcObject = stream;
        video.onloadedmetadata = () => {
            video.play();
            document.getElementById('detectionResults').innerHTML = '<div class="result success">✅ Camera ready. Click "Capture" to take a photo.</div>';
        };
    } catch(e) {
        console.error('Camera error:', e);
        document.getElementById('detectionResults').innerHTML = '<div class="result error">❌ Camera access denied: ' + e.message + '</div>';
    }
}

async function captureFrame() {
    // Draw video frame to canvas
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    // Display captured image
    document.getElementById('capturedImage').src = canvas.toDataURL('image/png');
    document.getElementById('capturedImage').style.display = 'block';
    
    try {
        // Detect face and descriptor
        const detections = await faceapi.detectAllFaces(canvas, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks()
            .withFaceDescriptors();
        
        if (detections.length === 0) {
            document.getElementById('detectionResults').innerHTML = '<div class="result error">No face detected. Try again.</div>';
            return;
        }
        
        const detection = detections[0];
        if (!detection.descriptor) {
            document.getElementById('detectionResults').innerHTML = '<div class="result error">Face detected but descriptor is missing. Try again.</div>';
            return;
        }
        capturedDescriptor = Array.from(detection.descriptor);
        
        let html = '<div class="result success">';
        html += '<strong>✅ Face Detected!</strong><br>';
        html += 'Confidence: ' + (detection.detection.score * 100).toFixed(2) + '%<br>';
        html += 'Descriptor length: ' + capturedDescriptor.length + ' values<br>';
        html += '<details><summary>View Descriptor</summary>';
        html += '<div class="descriptor-preview">';
        html += capturedDescriptor.map(v => v.toFixed(4)).join(', ');
        html += '</div></details>';
        html += '</div>';
        
        document.getElementById('detectionResults').innerHTML = html;
        
        // Now match against enrolled faces
        await matchWithEnrolled();
        
    } catch(e) {
        document.getElementById('detectionResults').innerHTML = '<div class="result error">Detection error: ' + e.message + '</div>';
    }
}

async function matchWithEnrolled() {
    if (!capturedDescriptor) return;
    
    try {
        const response = await fetch('face_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descriptor: capturedDescriptor })
        });
        
        const result = await response.json();
        
        let html = '';
        if (result.status === 'success') {
            html += '<div class="result success">';
            html += '<strong>✅ Face Matched!</strong><br>';
            html += 'Employee: ' + (result.name || result.employee_name || 'Unknown') + '<br>';
            html += 'ID: ' + result.id + '<br>';
            html += '</div>';
        } else {
            html += '<div class="result error">';
            html += '<strong>❌ Face Not Recognized</strong><br>';
            html += 'Message: ' + (result.message || 'Unknown error') + '<br>';
            if (result.debug) {
                html += '<details><summary>Debug Info</summary>';
                html += 'Threshold: ' + result.debug.threshold + '<br>';
                html += 'Best Distance: ' + result.debug.best_distance + '<br>';
                if (result.debug.distances_checked && Array.isArray(result.debug.distances_checked) && result.debug.distances_checked.length > 0) {
                    html += '<table class="comparison-table">';
                    html += '<tr><td>Employee ID</td><td>Distance</td><td>Match?</td></tr>';
                    result.debug.distances_checked.forEach(d => {
                        const match = d.distance < result.debug.threshold ? '✅ YES' : '❌ NO';
                        html += '<tr><td>' + d.emp_id + '</td><td>' + d.distance + '</td><td>' + match + '</td></tr>';
                    });
                    html += '</table>';
                }
                html += '</details>';
            }
            html += '</div>';
        }
        
        document.getElementById('matchingResults').innerHTML = html;
        
    } catch(e) {
        document.getElementById('matchingResults').innerHTML = '<div class="result error">Matching error: ' + e.message + '</div>';
    }
}

async function loadEnrolledFaces() {
    try {
        const response = await fetch('check_enrolled_face.php');
        const result = await response.json();
        
        if (result.status === 'success' && result.faces && result.faces.length > 0) {
            let html = '';
            result.faces.forEach(face => {
                html += '<div class="face-item">';
                html += '<strong>' + face.firstname + ' ' + face.lastname + '</strong> (ID: ' + face.id + ')<br>';
                html += 'Registered: ' + (face.face_registered ? '✅ Yes' : '❌ No') + '<br>';
                if (face.descriptor_length) {
                    html += 'Descriptor: ' + face.descriptor_length + ' values<br>';
                    html += '<details><summary>View First 10 Values</summary>';
                    html += '<div class="descriptor-preview">' + face.descriptor_preview + '</div>';
                    html += '</details>';
                }
                html += '</div>';
            });
            document.getElementById('enrolledFacesList').innerHTML = html;
        } else {
            document.getElementById('enrolledFacesList').innerHTML = '<div class="result error">No enrolled faces found</div>';
        }
    } catch(e) {
        document.getElementById('enrolledFacesList').innerHTML = '<div class="result error">Error: ' + e.message + '</div>';
    }
}

function stopCamera() {
    if (video.srcObject) {
        video.srcObject.getTracks().forEach(track => track.stop());
    }
}

// Initialize
console.log('Initializing face matching tool...');
document.addEventListener('DOMContentLoaded', () => {
    loadModels();
    loadEnrolledFaces();
});
</script>

</body>
</html>
