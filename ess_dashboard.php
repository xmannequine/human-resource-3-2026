<?php
session_start();
require_once('config.php');

if (!isset($_SESSION['employee_id'])) {
    header("Location: ess_login.php");
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];

// --- Handle profile image upload ---
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_image']['tmp_name'];
    $fileName = $_FILES['profile_image']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($fileExtension, $allowedExtensions)) {
        $newFileName = 'profile_' . $employee_id . '.' . $fileExtension;
        $uploadDir = 'uploads/'; // make sure this folder exists and is writable
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Update the database with new file name
            $stmt = $conn->prepare("UPDATE employee SET face_image = ? WHERE id = ?");
            $stmt->execute([$newFileName, $employee_id]);

            // Refresh page to show the new image
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo "<script>alert('Error moving uploaded file.');</script>";
        }
    } else {
        echo "<script>alert('Invalid file type. Only JPG, PNG, GIF allowed.');</script>";
    }
}

// --- Handle face enrollment ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['face_descriptor'])) {
    $face_descriptor = $_POST['face_descriptor'];
    $face_image_data = $_POST['face_image_data'] ?? '';
    
    // Save face image if provided
    $imagePath = null;
    if ($face_image_data) {
        $imagePath = 'uploads/face_' . $employee_id . '_' . time() . '.png';
        file_put_contents($imagePath, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $face_image_data)));
    }
    
    // Update employee record
    $stmt = $conn->prepare("UPDATE employee SET face_descriptor = ?, face_registered = 1, face_image = COALESCE(?, face_image) WHERE id = ?");
    $stmt->execute([$face_descriptor, $imagePath, $employee_id]);
    
    echo "<script>alert('Face enrolled successfully! You can now use face login.');</script>";
    echo "<script>window.location.href = '".$_SERVER['PHP_SELF']."';</script>";
    exit;
}

// --- Fetch employee info ---
$stmt = $conn->prepare("SELECT id, firstname, lastname, job_title, face_image, face_registered FROM employee WHERE id=? LIMIT 1");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);
$employee_name = $employee['firstname'].' '.$employee['lastname'];
$employee_job_title = $employee['job_title'];
$employee_id_display = str_pad($employee_id, 3, '0', STR_PAD_LEFT);

// Handle face image path - it may be stored with or without 'uploads/' prefix
$faceImagePath = null;
if (!empty($employee['face_image'])) {
    $imagePath = $employee['face_image'];
    // If it already starts with 'uploads/', use as is
    if (strpos($imagePath, 'uploads/') === 0) {
        $faceImagePath = $imagePath;
    } else {
        // Otherwise, prepend uploads/
        $faceImagePath = 'uploads/' . $imagePath;
    }
    // Verify file exists
    if (!file_exists($faceImagePath)) {
        $faceImagePath = null; // File doesn't exist, don't show broken image
    }
}

$profileImage = !empty($employee['face_image']) ? 'uploads/'.$employee['face_image'] : 'uploads/default-avatar.png';
$face_registered = $employee['face_registered'] ?? 0;

// --- Attendance today ---
$attendanceStmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id=? AND date=CURDATE() LIMIT 1");
$attendanceStmt->execute([$employee_id]);
$attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
$attendanceToday = $attendance && $attendance['time_in'] ? "✔" : "✖";

// --- Leave credits ---
$leaveCreditsStmt = $conn->prepare("SELECT leave_type, total_credits, used_credits FROM leave_credits WHERE employee_id=?");
$leaveCreditsStmt->execute([$employee_id]);
$leaveCredits = $leaveCreditsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalLeaveRemaining = array_sum(array_map(fn($c)=>$c['total_credits']-$c['used_credits'], $leaveCredits));

// --- Upcoming shift ---
$shifts = [
    'SH001'=>['label'=>'Shift 1','start'=>'06:00:00','end'=>'15:00:00'],
    'SH002'=>['label'=>'Shift 2','start'=>'09:00:00','end'=>'18:00:00'],
    'SH003'=>['label'=>'Shift 3','start'=>'12:00:00','end'=>'21:00:00'],
    'SH004'=>['label'=>'Shift 4','start'=>'15:00:00','end'=>'00:00:00']
];
$scheduleStmt = $conn->prepare("SELECT schedule_date, shift_id FROM daily_schedules WHERE employee_id=? AND schedule_date>=CURDATE() ORDER BY schedule_date ASC LIMIT 7");
$scheduleStmt->execute([$employee_id]);
$schedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
$upcomingShift = !empty($schedules) ? $shifts[$schedules[0]['shift_id']]['label'] ?? "N/A" : "N/A";

// --- Fetch Leave Requests ---
$leaveStmt = $conn->prepare("
    SELECT lr.id, lr.leave_type, lr.leave_date, lr.reason, lr.status, lr.reject_remarks
    FROM leave_requests lr
    WHERE lr.employee_id=? AND lr.deleted_at IS NULL
    ORDER BY lr.created_at DESC
");
$leaveStmt->execute([$employee_id]);
$leaves = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Reimbursement Requests ---
$reimbStmt = $conn->prepare("
    SELECT * 
    FROM reimbursements
    WHERE employee_id=? AND is_deleted=0
    ORDER BY id DESC
");
$reimbStmt->execute([$employee_id]);
$reimbursements = $reimbStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Overtime Requests ---
$otStmt = $conn->prepare("
    SELECT ot_date, time_start, time_end, total_hours, reason, status
    FROM overtime_requests
    WHERE employee_id=?
    ORDER BY ot_date DESC
");
$otStmt->execute([$employee_id]);
$overtimes = $otStmt->fetchAll(PDO::FETCH_ASSOC);

// Find upcoming shifts from today onwards
$today = date('Y-m-d');
$futureSchedules = array_filter($schedules, fn($s) => $s['schedule_date'] >= $today);
usort($futureSchedules, fn($a, $b) => strcmp($a['schedule_date'], $b['schedule_date']));
$nextShifts = array_slice($futureSchedules, 0, 5);

$upcomingShiftText = '';
if (!empty($nextShifts)) {
    $lines = [];
    foreach($nextShifts as $s) {
        if (isset($s['shift_id']) && isset($shifts[$s['shift_id']])) {
            $label = $shifts[$s['shift_id']]['label'] ?? 'Shift';
            $time  = $shifts[$s['shift_id']]['time'] ?? '';
            $lines[] = $s['schedule_date'] . ' — ' . $label . ($time ? " ($time)" : '');
        }
    }
    $upcomingShiftText = implode("<br>", $lines);
} else {
    $upcomingShiftText = 'No upcoming shifts';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ESS Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fefefe;
    margin: 2% auto;
    padding: 30px;
    border: 1px solid #888;
    width: 95%;
    max-width: 900px;
    min-height: 650px;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
    align-items: center;
}
.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close:hover {
    color: black;
}
#webcam {
    width: 100%;
    max-width: 800px;
    height: 550px;
    border-radius: 12px;
    border: 3px solid #00334E;
    object-fit: cover;
}
.btn-capture {
    background-color: #00334E;
    color: white;
    padding: 15px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    margin-top: 20px;
    min-width: 200px;

    font-weight: bold;
    margin-top: 10px;
    
}

.btn-capture:hover {
    background-color: #145374;
}
.btn-capture:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
}
.status-badge {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
}
.status-badge.pending { background-color: #fef3c7; color: #92400e; }
.status-badge.approved { background-color: #d1fae5; color: #065f46; }
.status-badge.rejected { background-color: #fee2e2; color: #991b1b; }
.status-badge.completed { background-color: #dbeafe; color: #1e40af; }
.face-status {
    font-size: 0.75rem;
    margin-top: 2px;
}
.face-registered {
    color: #10b981;
}
.face-not-registered {
    color: #ef4444;
}
</style>
</head>
<body class="bg-[#E8E8E8] font-sans text-gray-800">

<!-- Removed modal - now using dedicated page -->

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#00334E] text-white flex flex-col py-6">
        <div class="flex flex-col items-center mb-6 px-4">
            <form method="post" enctype="multipart/form-data" id="profileForm" class="w-full text-center">
                <input type="file" name="profile_image" id="profileInput" class="hidden" accept="image/*" onchange="document.getElementById('profileForm').submit();">
                <label for="profileInput" class="cursor-pointer">
                    <img src="<?= $profileImage ?>" alt="Profile" class="w-20 h-20 rounded-full border-2 border-[#5588A3] mb-2 object-cover hover:opacity-80 transition">
                </label>
                <h3 class="font-semibold text-center"><?= htmlspecialchars($employee_name) ?></h3>
                <p class="text-sm text-[#E8E8E8] text-center"><?= htmlspecialchars($employee_job_title) ?></p>
                <!-- Face Registration Status -->
                <div class="face-status text-center mt-1">
                    <?php if($face_registered): ?>
                        <span class="face-registered"><i class="bi bi-check-circle-fill"></i> Face Registered</span>
                    <?php else: ?>
                        <span class="face-not-registered"><i class="bi bi-exclamation-circle-fill"></i> Face Not Registered</span>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <nav class="flex-1 px-4 space-y-2">
            <a href="#" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-speedometer2 mr-2"></i> Dashboard</a>
            <a href="#attendance" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-clock mr-2"></i> Attendance</a>
            <a href="RR_form.php" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-card-checklist mr-2"></i> Reimbursement Requests</a>
            <a href="leave/leaverequestform.php" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-card-checklist mr-2"></i> Leave Requests</a>
            <a href="overtime_request.php" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-card-checklist mr-2"></i> Overtime Requests</a>
            <a href="timesheet.php?employee_id=<?= $employee_id ?>" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-clock-history mr-2"></i> My Timesheet</a>
            <!-- Face Enrollment Link -->
            <a href="ess_face_enroll.php" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]">
                <i class="bi bi-camera mr-2"></i> 
                <?= $face_registered ? 'Update Face' : 'Enroll Face' ?>
            </a>
        </nav>
        <div class="px-4 mt-auto">
            <a href="ess_test_login.php" class="flex items-center px-3 py-2 rounded hover:bg-[#145374]"><i class="bi bi-box-arrow-right mr-2"></i> Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 space-y-6">
        <!-- Header -->
        <header class="bg-[#5588A3] text-[#E8E8E8] p-6 rounded-lg shadow flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($employee_name) ?> (ID: <?= $employee_id_display ?>)</h2>
                <p class="text-[#E8E8E8]">Job Title: <?= htmlspecialchars($employee_job_title) ?></p>
            </div>
            <div class="flex-shrink-0">
                <img src="logo.jpg" alt="Company Logo" class="h-24 w-auto object-contain">
            </div>
        </header>

        <!-- KPI Cards -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-[#E8E8E8] p-4 rounded shadow text-center border-t-4 border-[#145374]">
                <h4 class="font-semibold">Attendance Today</h4>
                <p class="text-2xl mt-2"><?= $attendanceToday ?></p>
            </div>
            <div class="bg-[#E8E8E8] p-4 rounded shadow text-center border-t-4 border-[#145374]">
                <h4 class="font-semibold">Upcoming Shifts</h4>
                <p class="text-2xl mt-2" style="line-height:1.4"><?= $upcomingShiftText ?></p>
            </div>
            <div class="bg-[#E8E8E8] p-4 rounded shadow text-center border-t-4 border-[#145374]">
                <h4 class="font-semibold">Total Leave Remaining</h4>
                <p class="text-2xl mt-2"><?= $totalLeaveRemaining ?></p>
            </div>
        </section>

        <!-- Enrolled Face Display -->
        <?php if($face_registered): ?>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-[#28a745]">
            <h3 class="text-xl font-semibold mb-4" style="color:#00334E;">
                <i class="bi bi-camera-check-fill"></i> Enrolled Face
            </h3>
            <div style="display: flex; gap: 20px; align-items: center;">
                <div>
                    <?php if($faceImagePath && file_exists($faceImagePath)): ?>
                        <img src="<?= htmlspecialchars($faceImagePath) ?>" 
                             alt="Enrolled Face" 
                             style="border-radius: 10px; border: 3px solid #28a745; max-width: 200px; height: 200px; object-fit: cover; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);">
                    <?php else: ?>
                        <div style="width: 200px; height: 200px; background: #f0f0f0; border-radius: 10px; border: 3px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #999;">
                            No image
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <p style="color: #28a745; font-weight: bold; font-size: 18px;">
                        <i class="bi bi-check-circle-fill"></i> Face Registered
                    </p>
                    <p style="color: #666; margin-top: 10px;">Your face has been successfully enrolled for biometric authentication.</p>
                    <p style="color: #666; margin-top: 5px;">You can now use your face to login to the system.</p>
                    <a href="ess_face_enroll.php" style="color: #00334E; text-decoration: none; margin-top: 10px; display: inline-block; font-weight: bold;">
                        ↻ Update Face →
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-[#dc3545]">
            <h3 class="text-xl font-semibold mb-4" style="color:#00334E;">
                <i class="bi bi-camera-slash-fill"></i> Face Not Enrolled
            </h3>
            <div style="display: flex; gap: 20px; align-items: center;">
                <div>
                    <div style="width: 200px; height: 200px; background: #f8f9fa; border-radius: 10px; border: 3px dashed #dc3545; display: flex; align-items: center; justify-content: center;">
                        <div style="text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📷</div>
                            <div style="color: #dc3545; font-weight: bold;">Not Set</div>
                        </div>
                    </div>
                </div>
                <div>
                    <p style="color: #dc3545; font-weight: bold; font-size: 18px;">
                        <i class="bi bi-exclamation-circle-fill"></i> Face Not Registered
                    </p>
                    <p style="color: #666; margin-top: 10px;">Your face has not been enrolled yet. Enroll now to enable biometric login.</p>
                    <p style="color: #666; margin-top: 5px;">This provides a more secure and convenient way to access the system.</p>
                    <a href="ess_face_enroll.php" style="background-color: #dc3545; color: white; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-top: 10px; display: inline-block; font-weight: bold;">
                        📷 Enroll Face Now →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Shift Guide -->
        <div class="bg-[#E8E8E8] p-4 rounded shadow mt-6">
            <h4 class="font-semibold mb-2">Shift Guide</h4>
            <ul class="list-disc list-inside">
                <li><strong>Shift 1 (SH001):</strong> 6:00 AM – 3:00 PM</li>
                <li><strong>Shift 2 (SH002):</strong> 9:00 AM – 6:00 PM</li>
                <li><strong>Shift 3 (SH003):</strong> 12:00 PM – 9:00 PM</li>
                <li><strong>Shift 4 (SH004):</strong> 3:00 PM – 12:00 AM</li>
            </ul>
        </div>

        <!-- Requests Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <!-- Leave Requests -->
            <div class="bg-[#E8E8E8] p-4 rounded shadow">
                <h4 class="font-semibold mb-2">Leave Requests</h4>
                <?php if($leaves): ?>
                    <?php foreach($leaves as $l): ?>
                        <div class="mb-2 border-b pb-2">
                            <p><strong>Date:</strong> <?= date('M d, Y', strtotime($l['leave_date'])) ?></p>
                            <p><strong>Status:</strong> <span class="status-badge <?= strtolower($l['status']) ?>"><?= ucfirst($l['status']) ?></span></p>
                            <?php if(strtolower($l['status'])==='rejected' && !empty($l['reject_remarks'])): ?>
                                <p class="text-red-600 font-semibold"><strong>Remarks:</strong> <?= htmlspecialchars($l['reject_remarks']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No leave requests found.</p>
                <?php endif; ?>
            </div>

            <!-- Reimbursement Requests -->
            <div class="bg-[#E8E8E8] p-4 rounded shadow">
                <h4 class="font-semibold mb-2">Reimbursement Requests</h4>
                <?php if($reimbursements): ?>
                    <?php foreach($reimbursements as $r): ?>
                        <div class="mb-2 border-b pb-2">
                            <p><strong>Amount:</strong> ₱<?= number_format($r['amount'],2) ?></p>
                            <p><strong>Status:</strong> <span class="status-badge <?= strtolower($r['status']) ?>"><?= ucfirst($r['status']) ?></span></p>
                            <?php if(strtolower($r['status'])==='rejected' && !empty($r['rejected_remarks'])): ?>
                                <p class="text-red-600 font-semibold"><strong>Remarks:</strong> <?= htmlspecialchars($r['rejected_remarks']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No reimbursement requests found.</p>
                <?php endif; ?>
            </div>

            <!-- Overtime Requests -->
            <div class="bg-[#E8E8E8] p-4 rounded shadow">
                <h4 class="font-semibold mb-2">Overtime Requests</h4>
                <?php if($overtimes): ?>
                    <?php foreach($overtimes as $ot): ?>
                        <div class="mb-2 border-b pb-2">
                            <p><strong>Date:</strong> <?= date('M d, Y', strtotime($ot['ot_date'])) ?></p>
                            <p><strong>Status:</strong> <span class="status-badge <?= strtolower($ot['status']) ?>"><?= ucfirst($ot['status']) ?></span></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No overtime requests found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// Face enrollment moved to dedicated page: ess_face_enroll.php
// This page no longer uses modal

// Register Service Worker for model caching (kept for other pages)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./bundy/sw.js').catch(err => console.log('SW registration failed:', err));
}
</script>
            } else {
                status.innerHTML = `❌ Cannot access model files.<br>
                    <button onclick="window.location.reload()" class="btn btn-sm btn-warning mt-2">Reload Page</button>`;
            }
        } catch (e) {
            status.textContent = "❌ Network error. Check connection.";
        }
        status.className = "text-red-600";
    }
}

// Capture face
async function captureFace() {
    if (!modelsLoaded) {
        status.textContent = "⏳ Models still loading. Please wait...";
        status.className = "text-orange-500";
        
        // Add retry button
        if (!modelLoadAttempted) {
            loadModels();
        }
        return;
    }
    
    status.textContent = "Detecting face...";
    status.className = "text-blue-600";
    
    try {
        if (!video.videoWidth) {
            throw new Error("Video not ready");
        }
        
        const options = new faceapi.TinyFaceDetectorOptions({
            inputSize: 320, // Smaller = faster
            scoreThreshold: 0.5
        });
        
        const detection = await faceapi
            .detectSingleFace(video, options)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if(!detection){
            status.textContent = "No face detected. Try again.";
            status.className = "text-red-600";
            return;
        }

        // Draw to canvas
        const ctx = previewCanvas.getContext('2d');
        previewCanvas.width = video.videoWidth;
        previewCanvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);
        
        const box = detection.detection.box;
        ctx.strokeStyle = '#00FF00';
        ctx.lineWidth = 3;
        ctx.strokeRect(box.x, box.y, box.width, box.height);
        
        preview.style.display = 'block';
        
        faceDescriptor.value = JSON.stringify(Array.from(detection.descriptor));
        faceImageData.value = previewCanvas.toDataURL('image/png');
        
        status.textContent = "✅ Face captured! Click 'Save Face'.";
        status.className = "text-green-600";
        
        captured = true;
        submitEnroll.disabled = false;
        
    } catch(err) {
        status.textContent = "Error: " + err.message;
        status.className = "text-red-600";
        console.error(err);
    }
}

captureBtn.addEventListener('click', captureFace);

// Register Service Worker for model caching
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./bundy/sw.js').catch(err => console.log('SW registration failed:', err));
}
</script>
</body>
</html>