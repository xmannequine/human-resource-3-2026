<?php
session_start();
require_once('config.php');

$error = '';
$showForm = false;

// Registration code
$regCode = 'hr3@2025';

// Handle registration code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regcode_only'])) {
    $inputCode = trim($_POST['regcode_only']);
    if ($inputCode === $regCode) {
        $showForm = true; // correct code -> show form
    } else {
        $error = "Invalid Registration Code.";
    }
}

// Handle main registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firstname'])) {
    $showForm = true; // ensure the form stays visible after submission
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phonenumber = trim($_POST['phonenumber'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $agree = isset($_POST['agree']) ? true : false;

    if (empty($firstname) || empty($lastname) || empty($email) || empty($phonenumber) || empty($username) || empty($password) || empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phonenumber)) {
        $error = "Please enter a valid phone number (10–15 digits).";
    } elseif (preg_match('/\s/', $username)) {
        $error = "Username cannot contain spaces.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!$agree) {
        $error = "You must agree to the Terms of Service before registering.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (firstname, lastname, email, phonenumber, username, password) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        try {
            $stmt->execute([$firstname, $lastname, $email, $phonenumber, $username, $hashedPassword]);
            header("Location: login.php");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Username or email already exists.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register</title>
<style>
body {
    margin:0; padding:0; font-family:Arial,sans-serif;
    background:linear-gradient(135deg,#004aad 0%,#66a3ff 100%);
    display:flex; justify-content:center; align-items:center; height:100vh;
}
.register-box {
    background:#fff; padding:40px 30px; border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.3); width:380px;
}
h1 { color:#004aad; margin-bottom:25px; text-align:center; font-size:28px; }
label { display:block; margin-bottom:5px; font-weight:bold; color:#003580; }
input[type="text"], input[type="password"], input[type="tel"], input[type="email"] {
    width:100%; padding:12px; margin-bottom:15px; border:1px solid #b3c6ff; border-radius:6px; font-size:14px;
}
button { width:100%; background:#004aad; color:white; padding:12px; border:none; border-radius:6px; font-size:16px; cursor:pointer; transition: background 0.3s; }
button:hover { background:#003580; }
.error { color:red; font-weight:bold; margin-bottom:15px; text-align:center; background:#ffe6e6; padding:10px; border-radius:6px; }
p { text-align:center; margin-top:10px; font-size:14px; }
a { color:#004aad; text-decoration:none; } a:hover { text-decoration:underline; }
</style>
</head>
<body>

<div class="register-box">
<h1>Register</h1>

<?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$showForm): ?>
    <!-- Registration Code Form -->
    <form action="registration.php" method="POST" novalidate>
        <label for="regcode_only">Enter Registration Code</label>
        <input id="regcode_only" type="text" name="regcode_only" placeholder="Enter registration code" required>
        <button type="submit">Submit Code</button>
    </form>
    <p>Contact HR if you don't have the registration code.</p>
<?php else: ?>
    <!-- Main Registration Form -->
    <form action="registration.php" method="POST" novalidate>
        <label for="firstname">First Name</label>
        <input id="firstname" type="text" name="firstname" required>

        <label for="lastname">Last Name</label>
        <input id="lastname" type="text" name="lastname" required>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" required>

        <label for="phonenumber">Phone Number</label>
        <input id="phonenumber" type="tel" name="phonenumber" pattern="[0-9]{10,15}" required>

        <label for="username">Username</label>
        <input id="username" type="text" name="username" pattern="^\S+$" title="No spaces allowed" required>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required>

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" type="password" name="confirm_password" required>

        <label><input type="checkbox" name="agree" required> I agree to the Terms of Service</label>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
<?php endif; ?>

</div>

</body>
</html>
