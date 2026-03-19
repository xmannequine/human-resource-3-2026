<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');

    if ($entered_otp == $_SESSION['otp']) {
        // OTP correct, log user in
        $_SESSION['email'] = $_SESSION['otp_email'];
        unset($_SESSION['otp'], $_SESSION['otp_email']);
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid OTP. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify OTP | HR3 System</title>
</head>
<body>
<h1>Enter OTP</h1>
<?php if($error) echo "<p style='color:red'>$error</p>"; ?>
<form method="POST">
    <input type="text" name="otp" required placeholder="Enter OTP">
    <button type="submit">Verify OTP</button>
</form>
</body>
</html>
