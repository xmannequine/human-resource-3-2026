<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get user data from session
$username = $_SESSION['username'];
$firstname = $_SESSION['firstname'] ?? '';
$lastname = $_SESSION['lastname'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
</head>
<body>

<div class="container mt-5">
    <h1>
        Welcome, 
        <?php 
            if ($firstname || $lastname) {
                echo htmlspecialchars(trim("$firstname $lastname"));
            } else {
                echo htmlspecialchars($username);
            }
        ?>!
    </h1>
    <a href="logout.php" class="btn btn-danger mt-3">Logout</a>
    <a href="login.php" class="btn btn-primary mt-3 ms-2">Login</a>
</div>

</body>
</html>
