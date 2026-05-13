<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("UPDATE users SET googleid = NULL WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    unset($_SESSION['google_id']);
    unset($_SESSION['user']['googleid']);
    
    header('Location: dashboard.php?showSettings=1');
} else {
    header('Location: dashboard.php?error=unlink_failed');
}
exit;
?>