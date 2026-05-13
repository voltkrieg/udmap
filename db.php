<?php
$host = "localhost";
$db_user = "u139167625_blue";
$db_pass = "UDMapData008";
$db_name = "u139167625_udmap";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
     die("Database error: " . $conn->connect_error); 
     }
     
function logUserAction($conn, $userid, $action, $details) {
    $ipaddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare("INSERT INTO logs (userid, action, details, ipaddress) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isss", $userid, $action, $details, $ipaddress);
        $stmt->execute();
        $stmt->close();
    }
}
