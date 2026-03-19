<?php
echo "<h2>🔍 Path Information</h2>";

echo "<strong>Current script path:</strong> " . __DIR__ . "<br>";
echo "<strong>Document root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>Script name:</strong> " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Check if models folder exists on hard drive
$models_path = __DIR__ . '/models';
if (file_exists($models_path)) {
    echo "<br>✅ Models folder exists at: " . $models_path . "<br>";
    
    // List files in models folder
    $files = scandir($models_path);
    echo "<br><strong>Files in models folder:</strong><br>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $size = filesize($models_path . '/' . $file);
            echo "<li>" . htmlspecialchars($file) . " (" . round($size/1024, 2) . " KB)</li>";
        }
    }
    echo "</ul>";
} else {
    echo "<br>❌ Models folder NOT found at: " . $models_path . "<br>";
}

// Try to access models via web
echo "<br><strong>Testing web access to models:</strong><br>";
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$current_folder = dirname($_SERVER['SCRIPT_NAME']);
echo "Base URL: " . $base_url . "<br>";
echo "Current folder: " . $current_folder . "<br>";

$test_files = [
    'tiny_face_detector_model-weights_manifest.json',
    'face_landmark_68_model-weights_manifest.json'
];

foreach ($test_files as $file) {
    // Try different paths
    $paths_to_try = [
        $base_url . '/models/' . $file,
        $base_url . $current_folder . '/models/' . $file,
        $base_url . dirname($current_folder) . '/models/' . $file
    ];
    
    echo "<br>Testing: $file<br>";
    foreach ($paths_to_try as $path) {
        $headers = @get_headers($path);
        if ($headers && strpos($headers[0], '200')) {
            echo "✅ Found at: " . htmlspecialchars($path) . "<br>";
            break;
        } else {
            echo "❌ Not found at: " . htmlspecialchars($path) . "<br>";
        }
    }
}
?>