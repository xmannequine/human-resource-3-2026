<?php
// populate_faces.php

// ---------------------------
// Database connection
// ---------------------------
$host = '127.0.0.1';
$db   = 'hr3_hr3_db';
$user = 'root';  // change if different
$pass = '';      // change if your MySQL has a password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ---------------------------
// Fetch employees with face images
// ---------------------------
$stmt = $pdo->query("SELECT id, face_image FROM employee WHERE face_image IS NOT NULL AND face_image != ''");
$employees = $stmt->fetchAll();

if (empty($employees)) {
    die("No employees found with face images.");
}

// ---------------------------
// Populate employee_faces table
// ---------------------------
$insertStmt = $pdo->prepare("INSERT INTO employee_faces (employee_id, face_image, created_at) VALUES (:employee_id, :face_image, NOW())");

foreach ($employees as $emp) {
    $employee_id = $emp['id'];
    $face_path = $emp['face_image'];

    // Check if file exists
    if (!file_exists($face_path)) {
        echo "Warning: File not found for employee ID $employee_id: $face_path\n";
        continue;
    }

    // Get the image content and base64 encode it
    $image_data = file_get_contents($face_path);
    $image_base64 = base64_encode($image_data);

    // Insert into employee_faces
    $insertStmt->execute([
        ':employee_id' => $employee_id,
        ':face_image' => $image_base64
    ]);

    echo "Employee ID $employee_id face populated.\n";
}

echo "All done!";
