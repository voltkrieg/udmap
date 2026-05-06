<?php
session_start();

// --- Database Connection (Optional: Only needed if logging guest visits) ---
$serverName = "DESKTOP-KILKG9D\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "UDMapDB",
    "Uid" => "", 
    "PWD" => "",
    "Encrypt" => true, 
    "TrustServerCertificate" => true
);
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Set explicit session variables for the Guest
$_SESSION['user_id']   = 0; // Or null, depending on how your other pages check ID
$_SESSION['email']     = 'guest@udmap.local';
$_SESSION['user_role'] = 'guest';
$_SESSION['full_name'] = 'Guest User';
$_SESSION['first_name'] = 'Guest';

// Optional: Log the guest entry
if ($conn) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $tsql = "INSERT INTO USERLOGS (USERID, ACTION, DETAILS, IPADD) VALUES (0, 'GUEST_LOGIN', 'Anonymous guest session started.', ?)";
    sqlsrv_query($conn, $tsql, array($ip));
}

// Redirect to the main dashboard
header('Location: dashboard.php');
exit();
?>