<?php
echo "<h2>🔍 Testing Web Access</h2>";

$test_paths = [
    '/hr3/bundy/models/tiny_face_detector_model-weights_manifest.json',
    '/hr3/bundy/models/',
    '/bundy/models/tiny_face_detector_model-weights_manifest.json',
    '/models/tiny_face_detector_model-weights_manifest.json'
];

foreach ($test_paths as $path) {
    $url = 'http://localhost' . $path;
    echo "Testing: <a href='$url' target='_blank'>$url</a><br>";
    
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200')) {
        echo "✅ ACCESSIBLE<br><br>";
    } else {
        echo "❌ NOT ACCESSIBLE (404)<br><br>";
    }
}

// Check if .htaccess might be blocking
echo "<br><strong>Checking if .htaccess exists:</strong><br>";
$htaccess_path = 'C:\\xampp\\htdocs\\hr3\\bundy\\.htaccess';
if (file_exists($htaccess_path)) {
    echo "⚠️ .htaccess found in bundy folder - this might be blocking access<br>";
    echo "Contents:<br>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccess_path)) . "</pre>";
} else {
    echo "✅ No .htaccess file in bundy folder<br>";
}

// Check Apache configuration
echo "<br><strong>Apache Configuration Check:</strong><br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Script: " . __FILE__ . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>";
?>