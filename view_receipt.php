<?php
session_start();
require_once('config.php');

// Check if user is logged in (admin or HR)
if (!isset($_SESSION['username'])) {
    die('Access denied. Please log in.');
}

$username = $_SESSION['username'];
$message = '';
$receipt_path = $_GET['file'] ?? null;

// Handle password verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_password'])) {
    $enteredPassword = $_POST['account_password'];

    // Fetch password from users table
    $stmt = $conn->prepare("SELECT password FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($enteredPassword, $row['password'])) {
        $_SESSION['receipt_access_granted'] = true;
    } else {
        $message = "Incorrect password. Access denied.";
    }
}

// If access not granted yet, show password form
if (empty($_SESSION['receipt_access_granted'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enter Account Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="height:100vh;">
<div class="card p-4 shadow" style="width:350px;">
    <h4 class="mb-3 text-center">Password Required</h4>
    <?php if($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <input type="password" name="account_password" class="form-control" placeholder="Enter your account password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Submit</button>
    </form>
</div>
</body>
</html>
<?php
exit;
endif;

// Access granted, show receipt
if (!$receipt_path) {
    die("No receipt file specified.");
}

// Clean file path
$parts = pathinfo($receipt_path);
$dir = $parts['dirname'];
$file = rawurlencode($parts['basename']);
$fullPath = $dir . '/' . $file;

// Show the receipt
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Receipt</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; padding:30px; }
.card { padding:20px; border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,0.06); background:#fff; }
iframe { width:100%; height:600px; border:none; border-radius:10px; }
</style>
</head>
<body>
<div class="card">
    <h4 class="mb-3">Receipt Viewer</h4>
    <p><strong>User:</strong> <?= htmlspecialchars($username) ?></p>
    <iframe src="<?= htmlspecialchars($fullPath) ?>"></iframe>
    <a href="reimbursements.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</div>
</body>
</html>