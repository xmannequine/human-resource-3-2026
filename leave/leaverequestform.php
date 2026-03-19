<?php
session_start();
require '../config.php';

$message = '';

// Ensure the employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../login.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? '';
$employee_id_display = str_pad($employee_id, 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_date = $_POST['leave_date'] ?? '';
    $leave_type = $_POST['leave_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $uploaded_file = null;

    // Optional file upload handling
    if (!empty($_FILES['supporting_doc']['name'])) {
        $file_name = basename($_FILES['supporting_doc']['name']);
        $target_dir = "../uploads/";
        $target_file = $target_dir . time() . '_' . $file_name;

        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            $message = "Only PDF, JPG, JPEG, and PNG files are allowed.";
        } elseif ($_FILES['supporting_doc']['size'] > 20 * 1024 * 1024) {
            $message = "File size must be less than 20MB.";
        } elseif (!move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $target_file)) {
            $message = "Error uploading the file.";
        } else {
            $uploaded_file = basename($target_file);
        }
    }

    if (!$message) {
        if (!$leave_date) {
            $message = "Please select a leave date.";
        } elseif (!$leave_type) {
            $message = "Please select a type of leave.";
        } elseif (!$reason) {
            $message = "Please enter a reason for your leave.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO leave_requests 
                (employee_id, leave_type, leave_date, reason, supporting_doc)
                VALUES (:employee_id, :leave_type, :leave_date, :reason, :supporting_doc)
            ");
            $stmt->execute([
                ':employee_id' => $employee_id,
                ':leave_type' => $leave_type,
                ':leave_date' => $leave_date,
                ':reason' => $reason,
                ':supporting_doc' => $uploaded_file
            ]);
            $message = "Leave request submitted successfully and is pending approval.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leave Request Form</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background-color:#17758F;">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h5 class="mb-0">Leave Request Form</h5>
                </div>

                <div class="card-body p-4">

                    <?php if ($message): ?>
                        <div class="alert <?= strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-danger' ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Employee ID</label>
                            <input type="text" class="form-control" value="<?= $employee_id_display ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type of Leave</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="">-- Select Type --</option>
                                <option value="SL">Sick Leave (SL)</option>
                                <option value="VL">Vacation Leave (VL)</option>
                                <option value="Maternity Leave">Maternity Leave</option>
                                <option value="Paternity Leave">Paternity Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Leave Date</label>
                            <input type="date" name="leave_date" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for Leave</label>
                            <textarea name="reason" class="form-control" rows="4" required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Supporting Document (Optional, max 20MB)
                            </label>
                            <input type="file" name="supporting_doc" class="form-control"
                                   accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="../ess_dashboard.php" class="btn btn-outline-secondary">
                                ← Back
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                Submit Request
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
