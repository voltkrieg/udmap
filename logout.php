<?php
session_start();
require_once 'db.php';
        logUserAction($conn, $_SESSION['user_id'], 'USER_LOGOUT', 'User logged out.');
session_destroy();

header("location: index.php");
?>