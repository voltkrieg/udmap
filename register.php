<?php
session_start();


require_once 'db.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $allowed_roles = ['student', 'faculty', 'admin'];
    $role = trim($_POST['role'] ?? 'student');
    if (!in_array($role, $allowed_roles)) {
        $role = 'student'; // Failsafe fallback
    }

    $studentid = null;
    if ($role === 'student') {
        $studentid = trim($_POST['studentid'] ?? '');
    }
    if (empty($firstname) || empty($lastname) || empty($email) || empty($password)) {
        die("<script>alert('Please fill in all required fields.'); window.history.back();</script>");
    }
    if ($role === 'student' && empty($studentid)) {
        die("<script>alert('Student ID is required for student registration.'); window.history.back();</script>");
    }

    if ($password !== $confirm_password) {
        die("<script>alert('Passcodes do not match.'); window.history.back();</script>");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);


    if ($role === 'student') {
        $check_sql = "SELECT id FROM users WHERE studentid = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $studentid, $email);
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
    }
    
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        die("<script>alert('An account with this Email (or Student ID) already exists.'); window.history.back();</script>");
    }
    $check_stmt->close();
    $isverified = 0;
    $isactive = 1;
    $failedatt = 0;

    $insert_sql = "INSERT INTO users (firstname, lastname, studentid, email, password, isverified, isactive, role, failedatt) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                   
    $insert_stmt = $conn->prepare($insert_sql);
    
    $insert_stmt->bind_param("sssssiisi", $firstname, $lastname, $studentid, $email, $hashed_password, $isverified, $isactive, $role, $failedatt);

    if ($insert_stmt->execute()) {
        echo "<script>alert('Registration Successful! Welcome to UDMap.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Error creating account. Please try again later.'); window.history.back();</script>";
    }

    $insert_stmt->close();
} else {
    header("Location: signup.html");
    exit();
}

$conn->close();
?>