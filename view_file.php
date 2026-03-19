<?php
session_start();
require 'config.php';

$admin_password = "admin123"; // change this

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT supporting_doc FROM leave_requests WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$data){
    die("File not found.");
}

$file = "uploads/".$data['supporting_doc'];
$authorized = false;
$error = "";

if(isset($_POST['password'])){
    if($_POST['password'] === $admin_password){
        $authorized = true;
    } else {
        $error = "Incorrect admin password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Secure File Viewer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.viewer{
    width:100%;
    height:80vh;
    border:1px solid #ccc;
    border-radius:8px;
}
</style>
</head>

<body class="bg-light">

<div class="container mt-4">

<a href="javascript:history.back()" class="btn btn-secondary mb-3">
← Exit / Back to Dashboard
</a>

<?php if(!$authorized): ?>

<div class="card shadow" style="max-width:500px;">
<div class="card-header bg-dark text-white">
Admin File Verification
</div>

<div class="card-body">

<?php if($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST">
<div class="mb-3">
<label>Enter Admin Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<button class="btn btn-primary w-100">Open File</button>
</form>

</div>
</div>

<?php else: ?>

<h5 class="mb-3">File Viewer</h5>

<?php
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if($ext === "pdf"){
    echo "<iframe src='$file' class='viewer'></iframe>";
}
elseif(in_array($ext,["jpg","jpeg","png","gif"])){
    echo "<img src='$file' class='img-fluid'>";
}
else{
    echo "<p>Preview not available. <a href='$file'>Download File</a></p>";
}
?>

<?php endif; ?>

</div>

</body>
</html>