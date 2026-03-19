<?php
session_start();
require_once('config.php');

// -------------------------
// SESSION CHECK
// -------------------------
if (!isset($_SESSION['employee_id'])) {
    header("Location: ess_login.php");
    exit;
}

$employee_id = (int)$_SESSION['employee_id'];

// -------------------------
// FORM SUBMISSION
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date   = $_POST['ot_date'];
    $start  = $_POST['time_start'];
    $end    = $_POST['time_end'];
    $reason = $_POST['reason'];

    // Calculate total hours
    $hours = (strtotime($end) - strtotime($start)) / 3600;

    if ($hours <= 0) {
        $error = "Invalid overtime duration. End time must be later than start time.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO overtime_requests
            (employee_id, ot_date, time_start, time_end, total_hours, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $date, $start, $end, $hours, $reason]);
        $success = "Overtime request submitted successfully and is pending approval.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Overtime Request Form</title>
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
                    <h5 class="mb-0">Overtime Request Form</h5>
                </div>

                <div class="card-body p-4">

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Overtime Date</label>
                            <input type="date" name="ot_date" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Start Time</label>
                                <input type="time" name="time_start" class="form-control" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">End Time</label>
                                <input type="time" name="time_end" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Reason for Overtime</label>
                            <textarea name="reason" class="form-control" rows="4"
                                      placeholder="Please provide a clear justification for the overtime request."
                                      required></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="ess_dashboard.php" class="btn btn-outline-secondary">
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
