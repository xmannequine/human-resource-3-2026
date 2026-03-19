<?php
session_start();
require_once('config.php'); // Your PDO DB connection

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, firstname, lastname, password 
                                FROM employee WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee && password_verify($password, $employee['password'])) {
            $_SESSION['employee_id'] = $employee['id'];
            $_SESSION['employee_name'] = $employee['firstname'] . ' ' . $employee['lastname'];
            $_SESSION['user_role'] = 'Employee';
            header("Location: ess_dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Login | ESS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
    margin:0; padding:0;
    font-family:'Inter', sans-serif;
    background: linear-gradient(135deg,#17758F,#1BA1B4);
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}

.login-container {
    background:#fff;
    padding:40px 35px;
    border-radius:15px;
    width:400px;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
    text-align:center;
    animation:fadeIn 0.8s ease;
}

.login-container img {
    width:160px;
    margin-bottom:20px;
}

.login-container h1 {
    margin-bottom:20px;
    color:#17758F;
    font-size:26px;
    font-weight:700;
}

.form-group { margin-bottom:18px; }

input[type="text"], input[type="password"] {
    width:90%;
    padding:14px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:16px;
    outline:none;
    transition:border-color 0.3s ease, box-shadow 0.3s ease;
    text-align:center;
}

input[type="text"]:focus, input[type="password"]:focus {
    border-color:#17758F;
    box-shadow:0 0 6px rgba(23,117,143,0.3);
}

.btn {
    width:75%;
    background:linear-gradient(90deg,#17758F,#1BA1B4);
    color:white;
    padding:14px;
    border:none;
    border-radius:8px;
    font-size:16px;
    font-weight:600;
    cursor:pointer;
    transition:transform 0.2s ease, opacity 0.3s ease;
    margin-top:10px;
}

.btn:hover {
    opacity:0.9;
    transform:translateY(-2px);
}

.error {
    padding:10px;
    border-radius:6px;
    margin-bottom:15px;
    font-size:14px;
    font-weight:500;
    background:#ffe0e0;
    color:#d8000c;
}

.register-link {
    margin-top:20px;
    display:block;
    font-size:14px;
    color:#17758F;
    text-decoration:none;
}

.register-link:hover {
    text-decoration:underline;
}

@keyframes fadeIn {
    from { opacity:0; transform:translateY(-20px); }
    to { opacity:1; transform:translateY(0); }
}
</style>
</head>
<body>

<div class="login-container">
    <img src="logo.jpg" alt="ESS Logo">
    <h1>ESS PORTAL</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <input type="text" name="username" placeholder="Enter Username" required autofocus>
        </div>

        <div class="form-group">
            <input type="password" name="password" placeholder="Enter Password" required>
        </div>

        <button type="submit" class="btn">Login</button>
    </form>

    <a href="ess_reg.php" class="register-link">Don't have an account? Register here</a>
</div>

</body>
</html>
