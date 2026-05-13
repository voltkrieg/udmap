<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
header('Content-Type: application/json');

require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

require_once 'db.php';

$userId = $_SESSION['user_id'] ?? $_SESSION['temp_user_id'] ?? null;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'resend') {
    $conn->query("UPDATE otp SET isactive = 0 WHERE userid = $userId");

    $newOtp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + (5 * 60);

    $stmt = $conn->prepare("INSERT INTO otp (userid, otp, timer, isactive) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("isi", $userId, $newOtp, $expiry);
    
    if ($stmt->execute()) {
        $stmt->close(); // Good practice to close statements

        $uRes = $conn->query("SELECT email, firstname FROM users WHERE id = $userId");
        $user = $uRes->fetch_assoc();
        
        if (!$user || empty($user['email'])) {
            echo json_encode(['success' => false, 'message' => 'Fatal Error: Could not find user email in database.']);
            exit();
        }
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['smtphost']; 
            $mail->SMTPAuth   = true;          
            $mail->Username   = $_ENV['smtpemail']; 
            $mail->Password   = $_ENV['smtppass']; 
            $mail->SMTPSecure = 'tls';            
            $mail->Port       = $_ENV['smtpport']; 
            
            $mail->setFrom('no-reply@udmap.com', 'UDMap Guardian');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Your New Security Code';
            $mail->Body    = "Hello {$user['firstname']}, your new code is: <b>$newOtp</b>";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'New code sent!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => "SMTP Error: {$mail->ErrorInfo}"]);
        }   
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: Could not insert OTP.']);
    }
}

if ($action === 'verify') {
    $submittedOtp = $data['otp'] ?? '';

    $stmt = $conn->prepare("SELECT id FROM otp WHERE userid = ? AND otp = ? AND isactive = 1");
    $stmt->bind_param("is", $userId, $submittedOtp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close(); 

        $userStmt = $conn->prepare("SELECT role, firstname, lastname FROM users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        $userStmt->close();

        $cleanRole = strtolower(trim($userData['role'] ?? 'student'));
        unset($_SESSION['temp_user_id']);
        
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $cleanRole;
        $_SESSION['full_name'] = trim(($userData['firstname'] ?? '') . ' ' . ($userData['lastname'] ?? ''));

        $conn->query("DELETE FROM otp WHERE userid = $userId"); 
        
        logUserAction($conn, $_SESSION['user_id'], 'OTP_VERIFIED', 'User verified login through OTP.');
        
        switch ($cleanRole) {
            case 'admin':
                $target = 'admindashboard.php';
                break;
            case 'faculty':
            case 'professor': 
                $target = 'facultydashboard.php';
                break;
            default:
                $target = 'dashboard.php';
                break;
        }

        echo json_encode([
            'success' => true, 
            'redirect' => $target,
            'debug_role_from_db' => $cleanRole 
        ]);
    } else {
        $stmt->close(); 
        echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
    }
}

$conn->close();
?>