<!DOCTYPE html>
<html>
<head>
<title>Check Enrolled Face Data</title>
<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ff9800; font-weight: bold; }
.info { color: #2196F3; }
.status-card { background: #f8f9fa; border-left: 5px solid #ccc; padding: 20px; margin: 15px 0; border-radius: 5px; }
.status-card.success { border-left-color: #28a745; background: #f0fff0; }
.status-card.error { border-left-color: #dc3545; background: #fff0f0; }
.status-card.warning { border-left-color: #ff9800; background: #fff8f0; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
th { background-color: #f2f2f2; font-weight: bold; }
.code { background: #f4f4f4; padding: 10px; border-radius: 3px; font-family: monospace; word-break: break-all; }
h3 { color: #00334E; margin-top: 25px; }
button { background: #00334E; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
button:hover { background: #145374; }
.face-preview { max-width: 200px; max-height: 200px; border-radius: 10px; border: 3px solid #28a745; }
</style>
</head>
<body>
<div class="container">
<h1>🔍 Enrolled Face Data Check</h1>
<p style="color: #666;">Verify if your enrolled face is properly stored and readable</p>

<button onclick="checkFaceData()">🔄 Check Face Data Now</button>

<div id="result" style="margin-top: 20px;"></div>
</div>

<script>
async function checkFaceData() {
    const result = document.getElementById('result');
    result.innerHTML = '<p style="color: #2196F3;">⏳ Checking face data...</p>';

    try {
        const response = await fetch('check_enrolled_face.php');
        const data = await response.json();

        let html = '';

        // 1. Login Status
        html += `<div class="status-card ${data.logged_in ? 'success' : 'error'}">
            <h3>1️⃣ Login Status</h3>
            <table>
                <tr>
                    <td><strong>Logged In</strong></td>
                    <td>${data.logged_in ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
                </tr>
                <tr>
                    <td><strong>Employee ID</strong></td>
                    <td>${data.employee_id || 'N/A'}</td>
                </tr>
                <tr>
                    <td><strong>Employee Name</strong></td>
                    <td>${data.employee_name || 'N/A'}</td>
                </tr>
            </table>
        </div>`;

        if (!data.logged_in) {
            html += `<div class="status-card error">
                <p><span class="error">❌ Not logged in</span> - Please login first</p>
            </div>`;
            result.innerHTML = html;
            return;
        }

        // 2. Enrollment Status
        html += `<div class="status-card ${data.face_registered ? 'success' : 'error'}">
            <h3>2️⃣ Enrollment Status</h3>
            <table>
                <tr>
                    <td><strong>Face Registered</strong></td>
                    <td>${data.face_registered ? '<span class="success">✅ Yes (1)</span>' : '<span class="error">❌ No (0)</span>'}</td>
                </tr>
            </table>
        </div>`;

        if (!data.face_registered) {
            html += `<div class="status-card error">
                <p><span class="error">❌ Face not registered</span></p>
                <p>Go to: <a href="ess_face_enroll.php" style="color: #00334E; text-decoration: underline;">Face Enrollment →</a></p>
            </div>`;
            result.innerHTML = html;
            return;
        }

        // 3. Face Image Data
        html += `<div class="status-card ${data.face_image_exists ? 'success' : 'warning'}">
            <h3>3️⃣ Face Image File</h3>
            <table>
                <tr>
                    <td><strong>Path in DB</strong></td>
                    <td><code class="code">${data.face_image_db || 'NULL'}</code></td>
                </tr>
                <tr>
                    <td><strong>Full Path</strong></td>
                    <td><code class="code">${data.face_image_full_path || 'N/A'}</code></td>
                </tr>
                <tr>
                    <td><strong>File Exists</strong></td>
                    <td>${data.face_image_exists ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
                </tr>
                <tr>
                    <td><strong>File Size</strong></td>
                    <td>${data.face_image_size ? data.face_image_size + ' bytes' : 'N/A'}</td>
                </tr>
            </table>
            ${data.face_image_exists && data.face_image_preview ? `
                <div style="margin-top: 15px; text-align: center;">
                    <p><strong>Preview:</strong></p>
                    <img src="${data.face_image_preview}" alt="Face Preview" class="face-preview">
                </div>
            ` : ''}
        </div>`;

        // 4. Face Descriptor (Landmarks)
        html += `<div class="status-card ${data.descriptor_exists ? 'success' : 'error'}">
            <h3>4️⃣ Face Descriptor (Landmarks)</h3>
            <table>
                <tr>
                    <td><strong>Descriptor Saved</strong></td>
                    <td>${data.descriptor_exists ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'}</td>
                </tr>
                <tr>
                    <td><strong>Data Length</strong></td>
                    <td>${data.descriptor_length || 0} characters</td>
                </tr>
                <tr>
                    <td><strong>Preview</strong></td>
                    <td><code class="code" style="max-height: 100px; overflow-y: auto;">${(data.descriptor_preview || 'N/A').substring(0, 200)}${(data.descriptor_preview || '').length > 200 ? '...' : ''}</code></td>
                </tr>
            </table>
        </div>`;

        // 5. Readiness Check
        const ready = data.logged_in && data.face_registered && data.face_image_exists && data.descriptor_exists;
        html += `<div class="status-card ${ready ? 'success' : 'error'}">
            <h3>5️⃣ Readiness for Face Login</h3>
            ${ready ? `
                <p><span class="success">✅ ALL SYSTEMS GO!</span></p>
                <p style="margin-top: 10px; color: #666;">Your face is properly enrolled and ready for facial recognition login.</p>
                <table>
                    <tr><td><strong>Image</strong></td><td><span class="success">✅ Available</span></td></tr>
                    <tr><td><strong>Descriptor</strong></td><td><span class="success">✅ Available</span></td></tr>
                    <tr><td><strong>Status</strong></td><td><span class="success">✅ Registered</span></td></tr>
                </table>
            ` : `
                <p><span class="error">❌ NOT READY</span></p>
                <p style="margin-top: 10px; color: #666;">Some required data is missing. Please check the items above.</p>
                ${!data.face_image_exists ? '<p>• Face image file not found</p>' : ''}
                ${!data.descriptor_exists ? '<p>• Face descriptor not found</p>' : ''}
            `}
        </div>`;

        result.innerHTML = html;

    } catch (err) {
        result.innerHTML = `<div class="status-card error"><span class="error">Error: ${err.message}</span></div>`;
        console.error(err);
    }
}

// Auto-check on page load
window.addEventListener('load', checkFaceData);
</script>
</body>
</html>
