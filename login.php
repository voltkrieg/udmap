<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

require_once 'db.php';

if (isset($_GET['role']) && strtolower($_GET['role']) === 'guest') {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = 0;
    $_SESSION['user_role'] = 'guest';
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, firstname, lastname, email, password, role, isactive FROM users WHERE studentid = ? OR email = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['isactive'] == '0') die("Account inactive.");

        if (password_verify($password, $user['password'])) {
            $_SESSION['temp_user_id'] = $user['id'];
            $_SESSION['role'] = strtolower($user['role']); 
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = time() + (5 * 60);
            
            $conn->query("UPDATE otp SET isactive = 0 WHERE userid = " . $user['id']);
            $otp_stmt = $conn->prepare("INSERT INTO otp (userid, otp, timer, isactive) VALUES (?, ?, ?, 1)");
            $otp_stmt->bind_param("isi", $user['id'], $otpCode, $expiry);
            
            if ($otp_stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = $_ENV['smtphost'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['smtpemail'];
                    $mail->Password = $_ENV['smtppass'];
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = $_ENV['smtpport'];
                    $mail->setFrom('no-reply@udmap.com', 'UDMap Guardian');
                    $mail->addAddress($user['email']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Security Code';
                    $mail->Body = "Hello {$user['firstname']}, your code is: <b>$otpCode</b>";
                    $mail->send();
                    

                    logUserAction($conn, $user['id'], 'LOGIN_SUCCESS', 'User logged into the system.');
                    header("Location: otp_verify.html"); 
                    
                    exit();
                } catch (Exception $e) { die("Mail Error: {$mail->ErrorInfo}"); }
            }
        } else {
            echo "<script>alert('Invalid password.'); history.back();</script>";
        }
    } else {
        echo "<script>alert('User not found.'); history.back();</script>";
    }
    exit();
}

$currentRole = $_GET['role'] ?? 'student';
$theme = [
    'student' => ['color' => '#b6ff92', 'glow' => 'rgba(82, 255, 0, 0.1)', 'title' => 'Student Portal', 'label' => 'Student ID or Email'],
    'faculty' => ['color' => '#92e3ff', 'glow' => 'rgba(0, 183, 255, 0.1)', 'title' => 'Faculty Portal', 'label' => 'Faculty Email or ID'],
    'admin'   => ['color' => '#ff9292', 'glow' => 'rgba(255, 0, 0, 0.1)', 'title' => 'Admin Portal', 'label' => 'Admin Email or ID']
];
$ui = $theme[$currentRole] ?? $theme['student'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UDMap - <?php echo $ui['title']; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;700;900&display=swap" rel="stylesheet"/>
  <style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #011a10; font-family: 'Nunito', sans-serif; color: white; overflow: hidden; }
    .bg-glow { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, <?php echo $ui['glow']; ?> 0%, transparent 70%); pointer-events: none; }
    .login-card { background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(15px); border: 1px solid <?php echo $ui['color']; ?>44; border-radius: 30px; padding: 40px; width: 100%; max-width: 420px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 10; }
    .card-header h2 { font-family: 'Cinzel', serif; font-size: 1.8rem; letter-spacing: 2px; color: <?php echo $ui['color']; ?>; margin-bottom: 10px; }
    .input-group { margin-bottom: 20px; text-align: left; }
    .input-group label { display: block; font-size: 12px; text-transform: uppercase; margin-bottom: 8px; color: rgba(255,255,255,0.6); font-weight: 700; }
    .input-group input { width: 100%; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: white; font-size: 1rem; outline: none; transition: 0.3s; box-sizing: border-box; }
    .input-group input:focus { border-color: <?php echo $ui['color']; ?>; background: rgba(255,255,255,0.1); }
    
    .btn-submit { width: 100%; padding: 15px; background: #011a10; color: white; border: 1px solid <?php echo $ui['color']; ?>; border-radius: 50px; font-weight: 900; text-transform: uppercase; cursor: pointer; transition: 0.3s; margin-top: 10px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px <?php echo $ui['glow']; ?>; }
    
    /* Google Auth Styles */
    .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: rgba(255,255,255,0.4); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .divider::before { margin-right: .5em; }
    .divider::after { margin-left: .5em; }
    
    .btn-google { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 50px; color: white; font-weight: 700; text-decoration: none; transition: 0.3s; box-sizing: border-box; }
    .btn-google:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); }
    .btn-google svg { width: 20px; height: 20px; }

    .back-link { margin-top: 25px; display: block; color: rgba(255,255,255,0.4); text-decoration: none; font-size: 14px; transition: 0.3s; }
    .back-link:hover { color: <?php echo $ui['color']; ?>; }
  </style>
</head>
<body>
  <div class="bg-glow"></div>
  <div class="login-card">
    <div class="card-header">
      <h2><?php echo $ui['title']; ?></h2>
      <p style="font-size: 14px; opacity: 0.7; margin-top: 0;">Identify yourself to enter the forest.</p>
    </div>
    
    <form action="login.php" method="POST">
      <div class="input-group">
        <label><?php echo $ui['label']; ?></label>
        <input type="text" name="identifier" required>
      </div>
      <div class="input-group">
        <label>Passcode</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn-submit">Unlock Access</button>
    </form>

    <?php if ($currentRole !== 'admin'): ?>
    <div class="divider">Or</div>
    
    <a href="googlelogin.php" class="btn-google">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Continue with Google
    </a>
    <?php endif; ?>

    <a href="index.php" class="back-link">← Return to Gate</a>
  </div>
</body>
</html>