<!DOCTYPE html>
<html>
<head>
    <title>Face Database Diagnosis</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #00334E; }
        .result { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table td, table th { padding: 10px; border: 1px solid #ddd; text-align: left; }
        table th { background: #00334E; color: white; }
        table tr:nth-child(even) { background: #f9f9f9; }
        button { padding: 10px 20px; background: #00334E; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #005a8a; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Face Database Diagnosis</h1>
    <p>Checking what's enrolled in the system...</p>
    
    <div id="result">⏳ Loading...</div>
    
    <div style="margin-top: 30px;">
        <button onclick="location.href='../fix_enrollment.php'">🔧 Fix My Enrollment</button>
        <button onclick="location.href='../ess_face_enroll.php'">📷 Re-enroll Face</button>
    </div>
</div>

<script>
async function diagnose() {
    try {
        const response = await fetch('diagnose_face_db.php');
        const data = await response.json();
        
        let html = '';
        
        if (data.status === 'error') {
            html = '<div class="result error">❌ Error: ' + data.message + '</div>';
        } else {
            html += '<div class="result success">';
            html += '<strong>Total Enrolled Faces:</strong> ' + data.total_enrolled + '<br>';
            html += '<strong>Valid for Matching:</strong> ' + data.valid_faces.length + '<br>';
            if (data.valid_faces.length === 0) {
                html += '<p style="color:red;"><strong>⚠️ No valid faces for matching!</strong> You need to re-enroll.</p>';
            }
            html += '</div>';
            
            if (data.faces && data.faces.length > 0) {
                html += '<table>';
                html += '<tr><th>Employee</th><th>ID</th><th>Registered</th><th>Format</th><th>Valid?</th></tr>';
                data.faces.forEach(face => {
                    html += '<tr>';
                    html += '<td>' + face.name + '</td>';
                    html += '<td>' + face.id + '</td>';
                    html += '<td>' + face.registered + '</td>';
                    html += '<td>' + face.descriptor_format + '</td>';
                    html += '<td>' + face.valid_for_matching + '</td>';
                    html += '</tr>';
                });
                html += '</table>';
            }
        }
        
        document.getElementById('result').innerHTML = html;
    } catch(e) {
        document.getElementById('result').innerHTML = '<div class="result error">Error: ' + e.message + '</div>';
    }
}

diagnose();
</script>

</body>
</html>
