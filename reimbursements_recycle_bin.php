<?php
session_start();
require_once('config.php');

$message = '';

// Handle Restore / Permanent Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($requestId && $action === 'restore') {
        $stmt = $conn->prepare("UPDATE reimbursements SET is_deleted = 0 WHERE request_id = ?");
        $stmt->execute([$requestId]);
        $message = "Request $requestId has been restored.";
    }

    if ($requestId && $action === 'permanent_delete') {
        $stmt = $conn->prepare("DELETE FROM reimbursements WHERE request_id = ?");
        $stmt->execute([$requestId]);
        $message = "Request $requestId has been permanently deleted.";
    }
}

// Fetch deleted reimbursement requests
$stmt = $conn->prepare("SELECT * FROM reimbursements WHERE is_deleted = 1 ORDER BY id DESC");
$stmt->execute();
$deletedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Recycle Bin - Reimbursements</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.table-img { 
    max-width: 80px; 
    max-height: 80px; 
}

body {
    background-color: #17758f;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    padding: 20px;
}

</style>
</head>
<body>
<div class="container">
    <h2>Recycle Bin - Reimbursements</h2>
    <div class="mb-3">
        <a href="RR_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th>Request ID</th>
                <th>Employee ID</th>
                <th>Employee Name</th>
                <th>Purpose</th>
                <th>Amount</th>
                <th>Date Submitted</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if($deletedRequests): ?>
            <?php foreach($deletedRequests as $req): 
                $employee_id_display = str_pad($req['employee_id'], 3, '0', STR_PAD_LEFT);
            ?>
            <tr>
                <td><?= htmlspecialchars($req['request_id']) ?></td>
                <td><?= htmlspecialchars($employee_id_display) ?></td>
                <td><?= htmlspecialchars($req['employee_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($req['purpose'])) ?></td>
                <td><?= number_format($req['amount'],2) ?></td>
                <td><?= htmlspecialchars($req['date_submitted']) ?></td>
                <td><?= ucfirst($req['status']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($req['request_id']) ?>">
                        <button type="submit" name="action" value="restore" class="btn btn-success btn-sm">Restore</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="request_id" value="<?= htmlspecialchars($req['request_id']) ?>">
                        <button type="submit" name="action" value="permanent_delete" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete request <?= htmlspecialchars($req['request_id']) ?>?')">Delete Permanently</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" class="text-center">No deleted reimbursements.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
