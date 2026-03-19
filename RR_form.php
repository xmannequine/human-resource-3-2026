<?php
session_start();
require_once('config.php'); // DB connection

$message = '';

// Ensure employee is logged in
if (!isset($_SESSION['employee_id'], $_SESSION['employee_name'])) {
    die("Error: Employee not logged in.");
}

// Format employee ID as 3 digits
$employee_id_display = str_pad($_SESSION['employee_id'], 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Secure values from session
    $employee_id    = $_SESSION['employee_id'];
    $employee_name  = $_SESSION['employee_name'];
    $purpose        = trim($_POST['purpose'] ?? '');
    $amount         = trim($_POST['amount'] ?? '');
    $date_submitted = $_POST['date_submitted'] ?? '';
    $reason         = trim($_POST['reason'] ?? '');

    // ---------- VALIDATION ----------
    if (empty($purpose) || empty($amount) || empty($date_submitted)) {
        $message = "All required fields must be filled.";
    } elseif (empty($_FILES['receipt']['name'])) {
        $message = "Receipt upload is required.";
    } else {

        // Generate request_id
        $stmt = $conn->query("SELECT request_id FROM reimbursements ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        $newRequestId = $last ? str_pad(intval($last) + 1, 3, "0", STR_PAD_LEFT) : "001";

        // ---------- FILE UPLOAD ----------
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['receipt']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowedTypes = ['jpg', 'jpeg', 'png'];

        if (!in_array($fileType, $allowedTypes)) {
            $message = "Invalid file type. Only JPG, JPEG, and PNG are allowed.";
        } elseif (!move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFilePath)) {
            $message = "Failed to upload receipt.";
        } else {

            // ---------- INSERT ----------
            $stmt = $conn->prepare("
                INSERT INTO reimbursements 
                (request_id, employee_id, employee_name, purpose, amount, date_submitted, receipt_path, reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $newRequestId,
                $employee_id,
                $employee_name,
                $purpose,
                $amount,
                $date_submitted,
                $targetFilePath,
                $reason
            ]);

            $message = "Reimbursement request submitted successfully! Request ID: $newRequestId";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reimbursement Request Form</title>
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
                    <h5 class="mb-0">Reimbursement Request Form</h5>
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
                            <input type="text" class="form-control" value="<?= htmlspecialchars($employee_id_display) ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Employee Name</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['employee_name']) ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Purpose</label>
                            <select name="purpose" class="form-select" required>
                                <option value="">-- Select Purpose --</option>
                                <option value="Office Supplies Purchase">Office Supplies Purchase</option>
                                <option value="Packaging Materials">Packaging Materials</option>
                                <option value="Courier / Delivery Expense">Courier / Delivery Expense</option>
                                <option value="Online Marketing Expense">Online Marketing Expense</option>
                                <option value="Software / Subscription Fee">Software / Subscription Fee</option>
                                <option value="Client or Supplier Meeting Expense">Client or Supplier Meeting Expense</option>
                                <option value="Other Business-related Expense">Other Business-related Expense</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date Submitted</label>
                            <input type="date" name="date_submitted" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason (Optional)</label>
                            <textarea name="reason" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Upload Receipt <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="receipt" class="form-control" accept="image/*" required>
                        </div>

                        <div class="d-flex justify-content-between">
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
