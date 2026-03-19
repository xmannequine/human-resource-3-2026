<!DOCTYPE html>
<html>
<head>
<title>Check Face Enrollment - Database Status</title>
<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
.status-box { border: 2px solid #ccc; padding: 15px; margin: 15px 0; border-radius: 5px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
th { background-color: #f2f2f2; font-weight: bold; }
</style>
</head>
<body>
<div class="container">
<h2>📊 Face Enrollment Database Check</h2>

<div id="result"></div>

<div style="margin-top: 30px; background: #f0f0f0; padding: 15px; border-radius: 5px;">
<h4>How to verify:</h4>
<ol>
<li>Check if face_registered = 1</li>
<li>Check if face_descriptor is filled</li>
<li>Check if face_image path exists</li>
<li>If all 3 are OK → Face is saved ✅</li>
</ol>
</div>
</div>

<script>
// Check database via PHP
fetch('check_face_enrollment.php')
    .then(response => response.json())
    .then(data => {
        const result = document.getElementById('result');
        let html = '';

        if (data.logged_in) {
            html += `<div class="status-box">
                <p><strong>Employee ID:</strong> ${data.employee_id}</p>
                <p><strong>Name:</strong> ${data.name}</p>
            </div>`;

            if (data.face_registered) {
                html += `<div class="status-box" style="border-color: green; background: #f0fff0;">
                    <p><span class="success">✅ FACE REGISTERED</span></p>
                    <table>
                        <tr><th>Property</th><th>Status</th></tr>
                        <tr>
                            <td>face_registered</td>
                            <td><span class="success">✅ 1 (Yes)</span></td>
                        </tr>
                        <tr>
                            <td>face_descriptor</td>
                            <td>${data.descriptor_length > 0 ? `<span class="success">✅ Saved (${data.descriptor_length} chars)</span>` : '<span class="error">❌ Empty</span>'}</td>
                        </tr>
                        <tr>
                            <td>face_image</td>
                            <td>${data.face_image ? `<span class="success">✅ ${data.face_image}</span>` : '<span class="error">❌ Empty</span>'}</td>
                        </tr>
                    </table>
                </div>`;
            } else {
                html += `<div class="status-box" style="border-color: red; background: #fff0f0;">
                    <p><span class="error">❌ FACE NOT REGISTERED</span></p>
                    <p>face_registered = 0 or NULL</p>
                    <p style="margin-top: 10px;"><a href="ess_face_enroll.php" style="color: blue; text-decoration: underline;">Go to Face Enrollment →</a></p>
                </div>`;
            }
        } else {
            html += `<div class="status-box" style="border-color: red; background: #fff0f0;">
                <p><span class="error">❌ NOT LOGGED IN</span></p>
                <p><a href="ess_login.php" style="color: blue; text-decoration: underline;">Login to ESS →</a></p>
            </div>`;
        }

        result.innerHTML = html;
    })
    .catch(err => {
        document.getElementById('result').innerHTML = `<div class="status-box" style="border-color: red;"><span class="error">Error: ${err.message}</span></div>`;
        console.error(err);
    });
</script>
</body>
</html>
