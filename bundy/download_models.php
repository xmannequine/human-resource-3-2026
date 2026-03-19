<?php
// download_models.php
set_time_limit(300); // Allow 5 minutes for downloads

$models = [
    'tiny_face_detector_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-shard1'
];

$models_dir = __DIR__ . '/models';

// Create models directory if it doesn't exist
if (!file_exists($models_dir)) {
    mkdir($models_dir, 0777, true);
    echo "✅ Created models folder<br>";
}

echo "<h2>Downloading face-api.js models</h2>";
echo "<div style='font-family: monospace;'>";

foreach ($models as $filename => $url) {
    $destination = $models_dir . '/' . $filename;
    
    echo "Downloading: <strong>$filename</strong>... ";
    
    if (file_exists($destination)) {
        echo "⚠️ File already exists, skipping.<br>";
        continue;
    }
    
    // Download file
    $ch = curl_init($url);
    $fp = fopen($destination, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    fclose($fp);
    
    if ($success && $httpCode == 200) {
        $size = filesize($destination);
        echo "✅ Done (" . round($size / 1024, 2) . " KB)<br>";
    } else {
        echo "❌ Failed (HTTP $httpCode)<br>";
        if (file_exists($destination)) {
            unlink($destination);
        }
    }
}

echo "</div>";
echo "<hr>";
echo "<h3>✅ Download complete!</h3>";
echo "<p>You can now <a href='ess_dashboard.php'>go back to ESS Dashboard</a> and try face enrollment again.</p>";
?>