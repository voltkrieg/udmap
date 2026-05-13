<?php
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['user']['id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $user_id = $_SESSION['user']['id'];
    
    $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''));
    $last_name = htmlspecialchars(trim($_POST['last_name'] ?? ''));

    if (!empty($first_name) && !empty($last_name)) {

        $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ? WHERE id = ?");
        $stmt->bind_param("ssi", $first_name, $last_name, $user_id);

        if ($stmt->execute()) {
            $_SESSION['user']['firstname'] = $first_name;
            $_SESSION['user']['lastname'] = $last_name;
            
            header('Location: dashboard.php?showSettings=1&status=success');
            exit;
        } else {
            header('Location: dashboard.php?showSettings=1&status=error');
            exit;
        }
        
        $stmt->close();
        
    } else {
        header('Location: dashboard.php?showSettings=1&status=empty');
        exit;
    }
} else {
    header('Location: dashboard.php');
    exit;
}
?>