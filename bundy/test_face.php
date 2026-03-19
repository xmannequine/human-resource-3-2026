<!DOCTYPE html>
<html>
<head>
    <title>Face API Test</title>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #4CAF50; }
        .error { color: #f44336; }
        #status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        #webcam { width: 100%; max-width: 640px; border: 2px solid #00334E; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Face-API.js Model Test</h2>
        
        <div id="status">Initializing...</div>
        
        <video id="webcam" width="640" height="480" autoplay muted></video>
        <br>
        <button id="testBtn" class="btn-capture" style="background: #00334E; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-top: 10px; cursor: pointer;">Test Face Detection</button>
        
        <div id="result"></div>
    </div>

    <script>
    const video = document.getElementById('webcam');
    const status = document.getElementById('status');
    const result = document.getElementById('result');
    const testBtn = document.getElementById('testBtn');

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

    // Start camera
    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            status.innerHTML = '✅ Camera ready. Loading models...';
            status.className = 'success';
        } catch (err) {
            status.innerHTML = '❌ Camera error: ' + err.message;
            status.className = 'error';
        }
    }

    // Test models
    async function testModels() {
        status.innerHTML = 'Loading models from ./models folder...';
        
        try {
            // Wait for faceapi first
            await waitForFaceAPI();
            
            // Check if models folder is accessible
            const modelCheck = await fetch('./models/tiny_face_detector_model-weights_manifest.json');
            if (!modelCheck.ok) {
                throw new Error('Models folder not accessible at ./models');
            }
            
            // Load models
            await faceapi.nets.tinyFaceDetector.loadFromUri('./models');
            await faceapi.nets.faceLandmark68Net.loadFromUri('./models');
            await faceapi.nets.faceRecognitionNet.loadFromUri('./models');
            
            status.innerHTML = '✅ All models loaded successfully!';
            status.className = 'success';
            
            return true;
        } catch (err) {
            status.innerHTML = '❌ Model loading failed: ' + err.message;
            status.className = 'error';
            console.error(err);
            return false;
        }
    }

    // Test detection
    async function testDetection() {
        result.innerHTML = 'Detecting face...';
        
        try {
            const detection = await faceapi
                .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            if (detection) {
                result.innerHTML = '✅ Face detected successfully!<br>';
                result.innerHTML += '📊 Face descriptor length: ' + detection.descriptor.length + '<br>';
                result.innerHTML += '🎯 Confidence: ' + detection.detection.score;
                result.className = 'success';
            } else {
                result.innerHTML = '❌ No face detected. Please position your face in the camera.';
                result.className = 'error';
            }
        } catch (err) {
            result.innerHTML = '❌ Detection error: ' + err.message;
            result.className = 'error';
        }
    }

    // Initialize
    startCamera();
    
    // Register Service Worker for model caching
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('./sw.js').catch(err => console.log('SW registration failed:', err));
    }
    
    // Load models when page loads
    window.onload = async function() {
        await testModels();
    };
    
    // Test detection on button click
    testBtn.addEventListener('click', testDetection);
    </script>
</body>
</html>