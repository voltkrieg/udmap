<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['user_id']['role'];

switch ($role) {
    case 'admin':
        header("Location: admindashboard.php");
        break;
    case 'faculty':
        header("Location: facultydashboard.php");
        break;
    case 'student':
    default:
        header("Location: dashboard.php");
        break;
}
exit();