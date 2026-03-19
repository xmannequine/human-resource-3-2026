<?php
session_start();
require_once('config2.php');

if (!isset($_SESSION['verify_face_id'])) {
    header("Location: bundy_login.php");
    exit;
}

$stmt = $conn->prepare("SELECT face_descriptor FROM employee WHERE id=?");
$stmt->execute([$_SESSION['verify_face_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['face_descriptor']) {
    die("Face not registered.");
}

$storedDescriptor = $user['face_descriptor'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Face Verification</title>
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
body { text-align:center; font-family:sans-serif; background:#f2f2f2; padding:30px; }
video { border-radius:10px; border:2px solid #ccc; }
#status { margin-top:15px; font-weight:bold; }
</style>
</head>
<body>

<h2>Face Verification</h2>
<p><?= htmlspecialchars($_SESSION['verify_face_name']) ?></p>

<video id="video" width="320" height="240" autoplay muted></video>
<p id="status">Initializing...</p>

<script>
const video = document.getElementById('video');
const statusText = document.getElementById('status');

const storedDescriptor = new Float32Array(<?= $storedDescriptor ?>);

// Get the correct model path (relative to current page)
const modelsPath = './models';

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

// Load models after faceapi is ready
waitForFaceAPI().then(() => {
    Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(modelsPath),
        faceapi.nets.faceLandmark68Net.loadFromUri(modelsPath),
        faceapi.nets.faceRecognitionNet.loadFromUri(modelsPath)
    ])
    .then(() => {
        console.log('✅ Models loaded successfully');
        startCamera();
    })
    .catch(err => {
        statusText.innerText = '❌ Error loading models: ' + err.message;
        console.error('Model loading error:', err);
    });
}).catch(err => {
    statusText.innerText = '❌ face-api.js failed to load';
    console.error('faceapi loading error:', err);
});

function startCamera(){
    navigator.mediaDevices.getUserMedia({ video:true })
    .then(stream=>{
        video.srcObject = stream;
        statusText.innerText = "Look at the camera...";
        setTimeout(verifyFace, 2000);
    });
}

// Register Service Worker for model caching
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch(err => console.log('SW registration failed:', err));
}

async function verifyFace(){
    const detection = await faceapi
        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
        .withFaceLandmarks()
        .withFaceDescriptor();

    if(!detection){
        statusText.innerText = "No face detected!";
        return;
    }

    const distance = faceapi.euclideanDistance(
        detection.descriptor,
        storedDescriptor
    );

    if(distance < 0.5){
        statusText.style.color="green";
        statusText.innerText="Face Verified! Logging in...";

        fetch("face_success.php")
        .then(()=> window.location.href="bundy.php");
    }else{
        statusText.style.color="red";
        statusText.innerText="Face does not match!";
    }
}
</script>

</body>
</html>