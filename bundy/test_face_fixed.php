<!DOCTYPE html>
<html>
<head>
    <title>Face API Test - Fixed</title>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        #status, #modelStatus, #detectionStatus { padding: 10px; margin: 10px 0; border-radius: 5px; background: #f0f0f0; }
        #webcam { width: 100%; max-width: 640px; border: 2px solid #00334E; border-radius: 10px; }
        button { background: #00334E; color: white; padding: 12px 24px; border: none; border-radius: 5px; margin: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #145374; }
        button:disabled { background: #cccccc; cursor: not-allowed; }
        .info { background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Face-API.js Diagnostic Test</h2>
        
        <div class="info">
            <strong>Instructions:</strong><br>
            1. Allow camera access when prompted<br>
            2. Wait for models to load<br>
            3. Click "Test Face Detection"<br>
            4. Check browser console (F12) for detailed errors
        </div>
        
        <div id="status">⏳ Initializing...</div>
        <div id="modelStatus">⏳ Checking models...</div>
        
        <video id="webcam" width="640" height="480" autoplay muted></video>
        <br>
        
        <button id="testBtn">🔍 Test Face Detection</button>
        <button id="checkModelsBtn">📁 Check Model Files</button>
        
        <div id="detectionStatus"></div>
        
        <div id="fileList" style="margin-top: 20px; padding: 10px; background: #f9f9f9; border-radius: 5px; display: none;">
            <h4>Model Files Check:</h4>
            <div id="files"></div>
        </div>
    </div>

    <script>
    const video = document.getElementById('webcam');
    const status = document.getElementById('status');
    const modelStatus = document.getElementById('modelStatus');
    const detectionStatus = document.getElementById('detectionStatus');
    const testBtn = document.getElementById('testBtn');
    const checkModelsBtn = document.getElementById('checkModelsBtn');
    const fileList = document.getElementById('fileList');
    const files = document.getElementById('files');
    
    let modelsLoaded = false;
    let cameraReady = false;

    // Check if faceapi is loaded
    function checkFaceAPI() {
        return new Promise((resolve) => {
            if (typeof faceapi !== 'undefined') {
                resolve(true);
            } else {
                // Wait for faceapi to load
                let attempts = 0;
                const interval = setInterval(() => {
                    attempts++;
                    if (typeof faceapi !== 'undefined') {
                        clearInterval(interval);
                        resolve(true);
                    } else if (attempts > 20) { // 10 seconds timeout
                        clearInterval(interval);
                        resolve(false);
                    }
                }, 500);
            }
        });
    }

    // Start camera
    async function startCamera() {
        try {
            status.innerHTML = '📷 Requesting camera access...';
            const stream = await navigator.mediaDevices.getUserMedia({ 
                video: { 
                    width: 640, 
                    height: 480,
                    facingMode: "user" 
                } 
            });
            video.srcObject = stream;
            cameraReady = true;
            status.innerHTML = '✅ Camera ready';
            status.className = 'success';
            
            // Check faceapi after camera is ready
            const faceapiLoaded = await checkFaceAPI();
            if (faceapiLoaded) {
                status.innerHTML += '<br>✅ face-api.js loaded';
            } else {
                status.innerHTML += '<br>❌ face-api.js not loaded after 10 seconds';
                status.className = 'error';
            }
            
        } catch (err) {
            status.innerHTML = '❌ Camera error: ' + err.message;
            status.className = 'error';
            console.error('Camera error:', err);
        }
    }

    // Check model files via HTTP
    async function checkModelFiles() {
        fileList.style.display = 'block';
        files.innerHTML = 'Checking...';
        
        const modelFiles = [
            'tiny_face_detector_model-weights_manifest.json',
            'tiny_face_detector_model-shard1',
            'face_landmark_68_model-weights_manifest.json',
            'face_landmark_68_model-shard1',
            'face_recognition_model-weights_manifest.json',
            'face_recognition_model-shard1'
        ];
        
        let html = '<ul style="list-style: none; padding: 0;">';
        
        for (const file of modelFiles) {
            try {
                const response = await fetch('./models/' + file);
                if (response.ok) {
                    const size = response.headers.get('content-length');
                    html += `<li style="color: green;">✅ ${file} - Found (${size ? Math.round(size/1024) + ' KB' : 'size unknown'})</li>`;
                } else {
                    html += `<li style="color: red;">❌ ${file} - Not Found (HTTP ${response.status})</li>`;
                }
            } catch (err) {
                html += `<li style="color: red;">❌ ${file} - Error: ${err.message}</li>`;
            }
        }
        
        html += '</ul>';
        files.innerHTML = html;
    }

    // Load models
    async function loadModels() {
        modelStatus.innerHTML = '⏳ Loading models from ./models...';
        
        try {
            // First check if faceapi is defined
            if (typeof faceapi === 'undefined') {
                throw new Error('faceapi is not defined. Check if face-api.js is loaded.');
            }
            
            // Check if models folder is accessible
            const manifestCheck = await fetch('./models/tiny_face_detector_model-weights_manifest.json');
            if (!manifestCheck.ok) {
                throw new Error('Cannot access models folder. HTTP ' + manifestCheck.status);
            }
            
            console.log('Starting model load...');
            
            // Load models with timeout
            await Promise.race([
                Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri('./models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('./models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('./models')
                ]),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('Model loading timeout after 15 seconds')), 15000)
                )
            ]);
            
            modelsLoaded = true;
            modelStatus.innerHTML = '✅ All models loaded successfully!';
            modelStatus.className = 'success';
            console.log('Models loaded successfully');
            
        } catch (err) {
            modelStatus.innerHTML = '❌ Model loading failed: ' + err.message;
            modelStatus.className = 'error';
            console.error('Model loading error:', err);
        }
    }

    // Test detection
    async function testDetection() {
        if (!cameraReady) {
            detectionStatus.innerHTML = '❌ Camera not ready';
            detectionStatus.className = 'error';
            return;
        }
        
        if (!modelsLoaded) {
            detectionStatus.innerHTML = '⏳ Models still loading. Please wait...';
            detectionStatus.className = 'warning';
            return;
        }
        
        detectionStatus.innerHTML = '🔍 Detecting face...';
        
        try {
            // Double check faceapi
            if (typeof faceapi === 'undefined') {
                throw new Error('faceapi is not defined');
            }
            
            const options = new faceapi.TinyFaceDetectorOptions({
                inputSize: 512,
                scoreThreshold: 0.5
            });
            
            const detection = await faceapi
                .detectSingleFace(video, options)
                .withFaceLandmarks()
                .withFaceDescriptor();
            
            if (detection) {
                detectionStatus.innerHTML = '✅ Face detected successfully!<br>' +
                    '📊 Face descriptor length: ' + detection.descriptor.length + '<br>' +
                    '🎯 Confidence: ' + Math.round(detection.detection.score * 100) + '%';
                detectionStatus.className = 'success';
                
                // Log descriptor for debugging
                console.log('Face descriptor:', detection.descriptor);
                
            } else {
                detectionStatus.innerHTML = '❌ No face detected. Please ensure good lighting and face the camera.';
                detectionStatus.className = 'error';
            }
        } catch (err) {
            detectionStatus.innerHTML = '❌ Detection error: ' + err.message;
            detectionStatus.className = 'error';
            console.error('Detection error:', err);
        }
    }

    // Initialize
    startCamera();
    
    // Load models after a short delay to ensure faceapi is ready
    setTimeout(() => {
        loadModels();
    }, 1000);
    
    // Event listeners
    testBtn.addEventListener('click', testDetection);
    checkModelsBtn.addEventListener('click', checkModelFiles);
    
    // Log when faceapi becomes available
    Object.defineProperty(window, 'faceapi', {
        set: function(val) {
            console.log('faceapi loaded:', val);
            this._faceapi = val;
        },
        get: function() {
            return this._faceapi;
        }
    });
    </script>
</body>
</html>