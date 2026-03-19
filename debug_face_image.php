<!DOCTYPE html>
<html>
<head>
<title>Debug Face Image Path</title>
<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info-box { background: #e7f3ff; border-left: 5px solid #2196F3; padding: 15px; margin: 15px 0; }
.problem-box { background: #fff3cd; border-left: 5px solid #ff9800; padding: 15px; margin: 15px 0; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #f2f2f2; }
.code { background: #f4f4f4; padding: 10px; border-radius: 3px; font-family: monospace; overflow-x: auto; }
</style>
</head>
<body>
<div class="container">
<h2>🔍 Debug: Face Image Path</h2>

<div id="debug-output"></div>
</div>

<script>
async function debug() {
    const output = document.getElementById('debug-output');
    let html = '';

    try {
        // Fetch debug info
        const response = await fetch('debug_face_path.php');
        const data = await response.json();

        html += `<table>
            <tr><th>Property</th><th>Value</th></tr>
            <tr>
                <td><strong>Logged In</strong></td>
                <td>${data.logged_in ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
            </tr>
            <tr>
                <td><strong>Employee ID</strong></td>
                <td>${data.employee_id || 'N/A'}</td>
            </tr>
            <tr>
                <td><strong>Face Registered</strong></td>
                <td>${data.face_registered ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
            </tr>
            <tr>
                <td><strong>face_image from DB</strong></td>
                <td><code>${data.face_image_from_db || 'NULL'}</code></td>
            </tr>
            <tr>
                <td><strong>Full Path Used</strong></td>
                <td><code>${data.full_path_used || 'N/A'}</code></td>
            </tr>
            <tr>
                <td><strong>File Exists</strong></td>
                <td>${data.file_exists ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No - File not found</span>'}</td>
            </tr>
            <tr>
                <td><strong>File Size</strong></td>
                <td>${data.file_size ? data.file_size + ' bytes' : 'N/A'}</td>
            </tr>
            <tr>
                <td><strong>Directory /uploads exists</strong></td>
                <td>${data.uploads_dir_exists ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
            </tr>
        </table>`;

        // Analysis
        html += '<div class="problem-box" style="margin-top: 20px;">';
        html += '<h3>📋 Analysis</h3>';

        if (!data.logged_in) {
            html += '<p><span class="error">❌ NOT LOGGED IN</span> - Please login first</p>';
        } else if (!data.face_registered) {
            html += '<p><span class="error">❌ FACE NOT REGISTERED</span> - Enroll a face first</p>';
        } else if (!data.file_exists) {
            html += '<p><span class="error">❌ FILE NOT FOUND</span></p>';
            html += `<p>Database says: <code>${data.face_image_from_db}</code></p>`;
            html += `<p>Looking in: <code>${data.full_path_used}</code></p>`;
            html += '<p><strong>Solution:</strong> The file path in database might be wrong</p>';
        } else {
            html += '<p><span class="success">✅ ALL OK - File found!</span></p>';
            html += `<p>Image path: <code>${data.full_path_used}</code></p>`;
            html += `<p>File size: ${data.file_size} bytes</p>`;
            html += '<h4>To display on dashboard:</h4>';
            html += `<pre style="background: #f4f4f4; padding: 10px; border-radius: 5px;">&lt;img src="${data.full_path_used}" alt="Face"&gt;</pre>`;
        }

        html += '</div>';

        // Instructions
        html += '<div class="info-box">';
        html += '<h3>🔧 Quick Fixes</h3>';
        html += '<ol>';
        html += '<li>If face_image is NULL → Re-enroll face at <a href="ess_face_enroll.php">Face Enrollment</a></li>';
        html += '<li>If file not found → Check /uploads folder exists and is writable</li>';
        html += '<li>If path wrong → Database might have wrong path stored</li>';
        html += '</ol>';
        html += '</div>';

    } catch (err) {
        html = `<div class="error">Error: ${err.message}</div>`;
    }

    output.innerHTML = html;
}

debug();
</script>
</body>
</html>
