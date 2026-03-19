<?php
$models_dir = __DIR__ . '/models';
$required_files = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1'
];

echo "<h3>Checking Model Files:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>File</th><th>Status</th><th>Size</th></tr>";

foreach ($required_files as $file) {
    $path = $models_dir . '/' . $file;
    if (file_exists($path)) {
        $size = filesize($path);
        $status = "✅ OK";
        $size_display = round($size / 1024, 2) . " KB";
    } else {
        $status = "❌ MISSING";
        $size_display = "-";
    }
    echo "<tr><td>$file</td><td>$status</td><td>$size_display</td></tr>";
}
echo "</table>";
?>