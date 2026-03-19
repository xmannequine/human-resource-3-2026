<?php
session_start();

if (!isset($_SESSION['verify_face_id'])) {
    exit;
}

$_SESSION['bundy_employee_id'] = $_SESSION['verify_face_id'];
$_SESSION['bundy_employee_name'] = $_SESSION['verify_face_name'];

unset($_SESSION['verify_face_id']);
unset($_SESSION['verify_face_name']);

echo "success";