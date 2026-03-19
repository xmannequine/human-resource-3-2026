<?php
session_start();
require_once('config.php');

$employee_id = $_SESSION['employee_id'] ?? null;
$employee_name = $_SESSION['employee_name'] ?? 'Unknown';

if (!$employee_id) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Face Enrollment</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 30px; border-radius: 8px; }
        h1 { color: #00334E; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 5px; color: #155724; }
        button { padding: 10px 20px; background: #00334E; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 10px 5px 10px 0; }
        button:hover { background: #005a8a; }
        .result { margin-top: 20px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔧 Fix Your Face Enrollment</h1>
    
    <div class="warning">
        <strong>⚠️ Important:</strong> Your previous face enrollment was stored incorrectly. This tool will:
        <ol>
            <li>Delete the old enrollment data</li>
            <li>Allow you to re-enroll with the corrected system</li>
        </ol>
    </div>
    
    <p><strong>Employee:</strong> <?= htmlspecialchars($employee_name) ?></p>
    <p><strong>Employee ID:</strong> <?= htmlspecialchars($employee_id) ?></p>
    
    <button onclick="clearAndRedirect()">✅ Clear Old Enrollment & Re-enroll</button>
    
    <div class="result" id="result"></div>
</div>

<script>
async function clearAndRedirect() {
    document.getElementById('result').innerHTML = '⏳ Processing...';
    
    try {
        const response = await fetch('bundy/clear_enrollments.php', { method: 'POST' });
        const result = await response.json();
        
        if (result.status === 'success') {
            document.getElementById('result').innerHTML = '<div class="success">✅ Old enrollment cleared! Redirecting to enrollment page...</div>';
            setTimeout(() => {
                window.location.href = 'ess_face_enroll.php';
            }, 2000);
        } else {
            document.getElementById('result').innerHTML = '<div style="color:red;">❌ Error: ' + result.message + '</div>';
        }
    } catch(e) {
        document.getElementById('result').innerHTML = '<div style="color:red;">❌ Error: ' + e.message + '</div>';
    }
}
</script>

</body>
</html>
