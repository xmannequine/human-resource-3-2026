<?php
require_once('config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username     = $_POST['username'] ?? '';
    $firstname    = $_POST['firstname'] ?? '';
    $lastname     = $_POST['lastname'] ?? '';
    $email        = $_POST['email'] ?? '';
    $phonenumber  = $_POST['phonenumber'] ?? '';
    $password     = sha1($_POST['password'] ?? '');

    // Basic validation
    if (empty($username) || empty($firstname) || empty($lastname) || empty($email) || empty($phonenumber) || empty($_POST['password'])) {
        die("All fields are required.");
    }

    try {
        $sql = "INSERT INTO users (username, firstname, lastname, email, phonenumber, password) 
                VALUES (?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $firstname, $lastname, $email, $phonenumber, $password]);

        echo "Registration successful.";
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
}

