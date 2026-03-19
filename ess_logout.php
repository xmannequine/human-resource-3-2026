<?php
session_start();
session_unset();
session_destroy();
header("Location: ess_login.php"); // redirect to employee login
exit;
