$2y$10$smkjSa0/YLZzrgR4h5TSJe11R5hJbhvB4kBZDUwfQZ8gO8KL2yBa6<?php
// Database connection settings
$serverName = "DESKTOP-KILKG9D\SQLEXPRESS";
$connectionOptions = array(
    "Database"               => "UDMapDB",
    "Uid"                    => "", 
    "PWD"                    => "",
    "Encrypt"                => true, 
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}

// Admin Details
$firstName = "System";
$lastName  = "Admin";
$email     = "admin@udmap.test";
$username  = "admin2026";
$userType  = "admin";
$plainPassword = "AdminPassword123!"; 

// 1. Hash the password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// 2. Prepare the SQL (adjust column names if they differ from your [USER] table)
$tsql = "INSERT INTO [USER] (FIRSTNAME, LASTNAME, EMAIL, STUDENTID, PASSWORD, USERTYPE, FAILED_ATTEMPTS) 
         VALUES (?, ?, ?, ?, ?, ?, ?)";

$params = array($firstName, $lastName, $email, $username, $hashedPassword, $userType, 0);

$stmt = sqlsrv_query($conn, $tsql, $params);

if ($stmt === false) {
    echo "Error in account creation: <br/>";
    die(print_r(sqlsrv_errors(), true));
} else {
    echo "<h2>Test Admin Created Successfully!</h2>";
    echo "<b>Email/Username:</b> " . htmlspecialchars($email) . "<br/>";
    echo "<b>Password:</b> " . htmlspecialchars($plainPassword) . "<br/>";
    echo "<br/><i>You can now log in using the Admin role in your login page.</i>";
}

sqlsrv_free_stmt($stmt);
sqlsrv_close($conn);
?>