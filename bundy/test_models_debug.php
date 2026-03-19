<!DOCTYPE html>
<html>
<head>
<title>Debug: Model Files Check</title>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.file-check { margin: 10px 0; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
</style>
</head>
<body>
<div class="container">
<h2>🔍 Model Debug Check</h2>

<h3>1. Check if models are accessible via HTTP:</h3>
<div id="files"></div>

<h3>2. Check faceapi.js:</h3>
<div id="faceapi-check"></div>

<h3>3. Try loading models:</h3>
<button onclick="testLoad()">Start Loading Test</button>
<div id="load-result"></div>
</div>

<script>
// 1. Check if model files are accessible
async function checkFiles() {
    const files = [
        'tiny_face_detector_model-weights_manifest.json',
        'tiny_face_detector_model-shard1',
        'face_landmark_68_model-weights_manifest.json',
        'face_landmark_68_model-shard1',
        'face_recognition_model-weights_manifest.json',
        'face_recognition_model-shard1',
        'face_recognition_model-shard2'
    ];
    
    let html = '';
    for (const file of files) {
        try {
            const response = await fetch('./models/' + file, { method: 'HEAD' });
            const size = response.headers.get('content-length');
            const sizeMB = (size / (1024 * 1024)).toFixed(2);
            html += `<div class="file-check"><span class="success">✅</span> ${file} (${sizeMB} MB)</div>`;
        } catch (err) {
            html += `<div class="file-check"><span class="error">❌</span> ${file} - Not found</div>`;
        }
    }
    document.getElementById('files').innerHTML = html;
}

// 2. Check faceapi
function checkFaceAPI() {
    let html = '';
    if (typeof faceapi !== 'undefined') {
        html = '<div class="file-check"><span class="success">✅ face-api.js loaded</span></div>';
        html += '<div class="file-check">Available nets:<br>';
        if (faceapi.nets.tinyFaceDetector) html += '  ✅ tinyFaceDetector<br>';
        if (faceapi.nets.faceLandmark68Net) html += '  ✅ faceLandmark68Net<br>';
        if (faceapi.nets.faceRecognitionNet) html += '  ✅ faceRecognitionNet<br>';
        html += '</div>';
    } else {
        html = '<div class="file-check"><span class="error">❌ face-api.js NOT loaded</span></div>';
    }
    document.getElementById('faceapi-check').innerHTML = html;
}

// 3. Test loading
async function testLoad() {
    const result = document.getElementById('load-result');
    result.innerHTML = '<div class="warning">Testing model load...</div>';
    
    try {
        if (typeof faceapi === 'undefined') {
            throw new Error('faceapi not defined');
        }
        
        result.innerHTML += '<div class="warning">⏳ Loading TinyFaceDetector (2-3 MB)...</div>';
        const t1 = Date.now();
        await faceapi.nets.tinyFaceDetector.loadFromUri('./models');
        const t2 = Date.now();
        result.innerHTML += `<div class="success">✅ TinyFaceDetector loaded in ${t2-t1}ms</div>`;
        
        result.innerHTML += '<div class="warning">⏳ Loading FaceLandmark (350 KB)...</div>';
        const t3 = Date.now();
        await faceapi.nets.faceLandmark68Net.loadFromUri('./models');
        const t4 = Date.now();
        result.innerHTML += `<div class="success">✅ FaceLandmark loaded in ${t4-t3}ms</div>`;
        
        result.innerHTML += '<div class="warning">⏳ Loading FaceRecognition (160 MB - LARGE!)...</div>';
        const t5 = Date.now();
        await faceapi.nets.faceRecognitionNet.loadFromUri('./models');
        const t6 = Date.now();
        result.innerHTML += `<div class="success">✅ FaceRecognition loaded in ${t6-t5}ms</div>`;
        
        const totalTime = (t6-t1) / 1000;
        result.innerHTML += `<div class="success"><strong>✅ ALL MODELS LOADED in ${totalTime.toFixed(1)} seconds</strong></div>`;
        
    } catch (err) {
        result.innerHTML += `<div class="error">❌ Error: ${err.message}</div>`;
        console.error(err);
    }
}

// Run on load
window.onload = () => {
    checkFiles();
    checkFaceAPI();
};
</script>
</body>
</html>
