<?php
// download_missing_models.php
set_time_limit(300);

$missing_files = [
    'tiny_face_detector_model-weights_manifest.json' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1' => 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-shard1'
];

$models_dir = __DIR__ . '/models';

if (!file_exists($models_dir)) {
    mkdir($models_dir, 0777, true);
    echo "✅ Created models folder<br>";
}

echo "<h2>Downloading Missing Tiny Face Detector Models</h2>";
echo "<div style='font-family: monospace;'>";

foreach ($missing_files as $filename => $url) {
    $destination = $models_dir . '/' . $filename;
    
    echo "Downloading: <strong>$filename</strong>... ";
    
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
echo "<h3>✅ Download attempt complete!</h3>";
echo "<a href='check_models.php'>Click here to verify all models</a>";
?>