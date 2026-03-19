<?php
$models_dir = __DIR__ . '/models';
echo "<h2>Checking Models Directory</h2>";

if (file_exists($models_dir)) {
    echo "✅ Models directory exists<br>";
    $files = scandir($models_dir);
    echo "Files found:<br>";
    echo "<ul>";
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
    }
    echo "</ul>";
} else {
    echo "❌ Models directory does not exist!<br>";
    echo "Please create a 'models' folder in: " . __DIR__;
}

// Check if face-api.js is loaded
echo "<h2>Checking face-api.js</h2>";
echo '<script src="https://cdn.jsdelivr.net/npm/face-api.js"></script>';
echo '<script>
    setTimeout(function() {
        if (typeof faceapi !== "undefined") {
            document.write("✅ face-api.js loaded successfully<br>");
            document.write("Version: " + faceapi.version);
        } else {
            document.write("❌ face-api.js not loaded");
        }
    }, 1000);
</script>';
?>