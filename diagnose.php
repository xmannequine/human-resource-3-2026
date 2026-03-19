<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP Info</h2>";
phpinfo();

echo "<hr><h2>Database Connection Test</h2>";

$db_host = "localhost";
$db_user = "hr3_hr3_db";
$db_pass = "hr32025";
$db_name = "hr3_hr3_database";
$db_port = 3306;

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $conn = new PDO($dsn, $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green;'>✅ Database connected successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}
?>
