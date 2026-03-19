<?php
echo "<h2>🔍 Model Finder</h2>";

// Start searching from htdocs
$search_root = 'C:\\xampp\\htdocs\\';
echo "Searching in: " . $search_root . "<br><br>";

// Function to search for model files
function findModelFiles($dir) {
    $found = [];
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . '\\' . $file;
        
        if (is_dir($path)) {
            // Search in subdirectories
            $subResults = findModelFiles($path);
            $found = array_merge($found, $subResults);
        } else {
            // Check if it's a model file
            if (strpos($file, 'tiny_face_detector') !== false ||
                strpos($file, 'face_landmark_68') !== false ||
                strpos($file, 'face_recognition') !== false) {
                $found[] = $path;
            }
        }
    }
    
    return $found;
}

// Search for model files
$modelFiles = findModelFiles($search_root);

if (empty($modelFiles)) {
    echo "❌ No model files found in XAMPP htdocs!<br>";
    echo "Please tell me: Where did you save the model files?<br>";
    echo "Did you create a 'models' folder somewhere?";
} else {
    echo "✅ Found model files:<br>";
    echo "<ul>";
    foreach ($modelFiles as $file) {
        // Convert to web path
        $webPath = str_replace('C:\\xampp\\htdocs\\', '', $file);
        $webPath = str_replace('\\', '/', $webPath);
        $folderPath = dirname($webPath);
        
        echo "<li>";
        echo "File: " . basename($file) . "<br>";
        echo "Folder: <strong>/" . $folderPath . "</strong><br>";
        echo "Full path: " . $file . "<br>";
        
        // Create test link
        $testUrl = 'http://localhost/' . str_replace('\\', '/', $folderPath) . '/' . basename($file);
        echo "Test: <a href='$testUrl' target='_blank'>Click to test</a>";
        echo "</li><br>";
    }
    echo "</ul>";
}

// Also check common locations
echo "<br><strong>Common model locations to check manually:</strong><br>";
echo "1. C:\\xampp\\htdocs\\models\\<br>";
echo "2. C:\\xampp\\htdocs\\hr3\\models\\<br>";
echo "3. C:\\xampp\\htdocs\\hr3\\bundy\\models\\<br>";
echo "4. C:\\xampp\\htdocs\\bundy\\models\\<br>";
?>