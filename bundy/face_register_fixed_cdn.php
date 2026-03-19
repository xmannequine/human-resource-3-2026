<!DOCTYPE html>
<html>
<head>
<title>Register Face - Fixed CDN</title>
<!-- Try multiple CDN sources for face-api.js -->
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<noscript>
    <!-- Fallback if above fails -->
    <script src="https://unpkg.com/face-api.js@0.22.2/dist/face-api.min.js"></script>
</noscript>
<style>
body { font-family:sans-serif; text-align:center; background:#f2f2f2; padding:30px; }
video { border-radius:10px; border:2px solid #ccc; max-width: 100%; }
button { padding:10px 20px; margin-top:15px; background:#00334E; color:white; border:none; border-radius:5px; cursor:pointer; font-weight: bold; }
button:hover { background:#145374; }
button:disabled { background:#ccc; cursor: not-allowed; }
#status { margin-top:10px; font-weight:bold; min-height: 20px; font-size: 16px; }
.success { color: green; }
.error { color: red; }
.info { color: blue; }
.warning { color: orange; }
</style>
</head>
<body>

<h2>Face Registration</h2>

<div id="cdnStatus" style="margin-bottom:20px; padding:10px; background:#fff3cd; border-radius:5px;"></div>

<video id="video" width="640" height="480" autoplay muted></video>
<br>
<canvas id="previewCanvas" style="display:none;"></canvas>
<button id="registerBtn" disabled onclick="registerFace()">📷 Capture Face</button>

<p id="status"></p>

<script>
const video = document.getElementById('video');
const previewCanvas = document.getElementById('previewCanvas');
const statusText = document.getElementById('status');
const registerBtn = document.getElementById('registerBtn');
const cdnStatus = document.getElementById('cdnStatus');

let imageCaptured = false;

// Check which CDN loaded faceapi
function checkCDN() {
    return new Promise((resolve) => {
        let attempts = 0;
        const checkInterval = setInterval(() => {
            attempts++;
            if (typeof faceapi !== 'undefined') {
                clearInterval(checkInterval);
                cdnStatus.innerHTML = '✅ <strong>face-api.js loaded successfully</strong>';
                cdnStatus.style.background = '#d4edda';
                resolve(true);
            } else if (attempts > 50) { // 5 seconds timeout
                clearInterval(checkInterval);
                cdnStatus.innerHTML = '❌ <strong>face-api.js failed to load from CDN</strong>';
                cdnStatus.style.background = '#f8d7da';
                resolve(false);
            }
        }, 100);
    });
}

// Wait and load models
async function initializeApp() {
    try {
        statusText.innerHTML = '⏳ Checking CDN...';
        statusText.className = 'info';
        
        const cdnLoaded = await checkCDN();
        
        if (!cdnLoaded) {
            throw new Error('face-api.js library not available');
        }
        
        statusText.innerHTML = '⏳ Loading detection models...';
        const modelsPath = './models';
        
        await Promise.race([
            Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsPath),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath)
            ]),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('Model loading timeout')), 20000)
            )
        ]);
        
        statusText.innerHTML = '✅ Models loaded. Starting camera...';
        statusText.className = 'success';
        startCamera();
        registerBtn.disabled = false;
        
    } catch (err) {
        statusText.innerHTML = '❌ Error: ' + err.message + 
            '<br><button onclick="location.reload()">Reload Page</button>';
        statusText.className = 'error';
        console.error('Error:', err);
    }
}

function startCamera(){
    navigator.mediaDevices.getUserMedia({ 
        video: { width: 640, height: 480, facingMode: "user" } 
    })
    .then(stream => {
        video.srcObject = stream;
        statusText.innerHTML = '✅ Camera ready. Click "Capture Face".';
        statusText.className = 'success';
    })
    .catch(err => {
        statusText.innerHTML = '❌ Camera denied: ' + err.message;
        statusText.className = 'error';
    });
}

async function registerFace(){
    statusText.innerHTML = '⏳ Detecting face...';
    statusText.className = 'info';
    registerBtn.disabled = true;

    try {
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks();

        if(!detection){
            statusText.innerHTML = '❌ No face detected!';
            statusText.className = 'error';
            registerBtn.disabled = false;
            return;
        }

        // Capture image
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = video.videoWidth;
        previewCanvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        
        const box = detection.detection.box;
        ctx.strokeStyle = '#00FF00';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);

        const imageData = previewCanvas.toDataURL('image/png');
        const descriptor = JSON.stringify(detection.landmarks.positions);

        statusText.innerHTML = '⏳ Saving face...';
        
        const response = await fetch("save_descriptor.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ descriptor, image: imageData })
        });

        const result = await response.json();
        
        if (result.success) {
            statusText.innerHTML = '✅ Face registered successfully!';
            statusText.className = 'success';
            registerBtn.style.display = 'none';
            setTimeout(() => window.location.href = 'bundy.php', 2000);
        } else {
            throw new Error(result.error || "Unknown error");
        }

    } catch(err) {
        statusText.innerHTML = '❌ Error: ' + err.message;
        statusText.className = 'error';
        registerBtn.disabled = false;
        console.error(err);
    }
}

// Initialize when page loads
window.addEventListener('load', initializeApp);
</script>

</body>
</html>
