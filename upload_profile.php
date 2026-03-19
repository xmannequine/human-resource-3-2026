<?php
session_start(); // ⚠ Must start session to preserve login
require_once('config.php');

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
    $fileName = basename($_FILES['profile_picture']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($fileExt, $allowedExtensions)) {
        $newFileName = 'profile_' . time() . '.' . $fileExt;
        $uploadDir = 'uploads/';
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Update user profile in database
            $stmt = $conn->prepare("UPDATE users SET profile_image = :img WHERE email = :email");
            $stmt->execute([
                ':img' => $newFileName,
                ':email' => $_SESSION['email']
            ]);
        } else {
            $_SESSION['upload_error'] = "Error moving uploaded file.";
        }
    } else {
        $_SESSION['upload_error'] = "Invalid file type. Only JPG, PNG, GIF allowed.";
    }
}

header("Location: index.php"); // Redirect back to dashboard
exit;
