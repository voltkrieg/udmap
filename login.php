<?php
session_start();

function logAudit($conn, $userId, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $tsql = "INSERT INTO USERLOGS (USERID, ACTION, DETAILS, IPADD) VALUES (?, ?, ?, ?)";
    sqlsrv_query($conn, $tsql, array($userId, $action, $details, $ip));
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['user_role'] === 'admin' ? 'admindashboard.php' : 'dashboard.php'));
    exit();
}

$serverName = "DESKTOP-KILKG9D\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "UDMapDB",
    "Uid" => "", 
    "PWD" => "",
    "Encrypt" => true, 
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) { die(print_r(sqlsrv_errors(), true)); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $role       = $_POST['role'];               
    $identifier = trim($_POST['identifier']);  
    $password   = $_POST['password'];

    $tsql = "SELECT USERID, FIRSTNAME, LASTNAME, PASSWORD, USERTYPE, EMAIL, FAILED_ATTEMPTS, LOCKOUT_TIME FROM [USER] WHERE (EMAIL = ? OR STUDENTID = ?) AND USERTYPE = ?";
    $stmt = sqlsrv_query($conn, $tsql, array($identifier, $identifier, $role));

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $now = new DateTime();
        
        if ($row['LOCKOUT_TIME'] && $row['LOCKOUT_TIME'] > $now) {
            $error = "Account locked.";
            logAudit($conn, $row['USERID'], 'LOGIN_BLOCKED', 'Attempted login while account was locked.');
        } else {
            if (password_verify($password, $row['PASSWORD'])) {
                
                // UPDATE THIS QUERY TO INCLUDE ISACTIVE = 1
                sqlsrv_query($conn, "UPDATE [USER] SET FAILED_ATTEMPTS = 0, LOCKOUT_TIME = NULL, ISACTIVE = 1 WHERE USERID = ?", array($row['USERID']));

                $_SESSION['user_id']   = $row['USERID'];
                $_SESSION['email']     = $row['EMAIL'];
                $_SESSION['user_role'] = $row['USERTYPE'];
                $_SESSION['full_name'] = $row['FIRSTNAME'] . ' ' . $row['LASTNAME'];

                // LOG SUCCESS
                logAudit($conn, $row['USERID'], 'LOGIN_SUCCESS', "User logged in as {$row['USERTYPE']}");

                header('Location: ' . ($row['USERTYPE'] === 'admin' ? 'admindashboard.php' : 'dashboard.php'));
                exit();

            } else {
                // Failure
                $attempts = $row['FAILED_ATTEMPTS'] + 1;
                $lockoutUntil = null;
                
                if ($attempts >= 3) {
                    $lockoutUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    $error = 'Too many failed attempts. Account locked.';
                    logAudit($conn, $row['USERID'], 'ACCOUNT_LOCKOUT', 'Account locked due to 3 failed attempts.');
                } else {
                    $error = "Invalid password. Attempt $attempts of 3.";
                    logAudit($conn, $row['USERID'], 'LOGIN_FAILURE', "Failed attempt $attempts.");
                }
                sqlsrv_query($conn, "UPDATE [USER] SET FAILED_ATTEMPTS = ?, LOCKOUT_TIME = ? WHERE USERID = ?", array($attempts, $lockoutUntil, $row['USERID']));
            }
        }
    } else {
        $error = 'Account not found.';
        // Log unauthorized attempt with unknown user
        logAudit($conn, null, 'UNAUTHORIZED_ATTEMPT', "Unknown user tried to login as $role with identifier: $identifier");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UDMap - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #2ECC40; font-family: 'Nunito', sans-serif; padding: 24px 16px; }
    .login-card { background: #fff; border-radius: 24px; padding: 48px 52px 52px; width: 100%; max-width: 520px; box-shadow: 0 8px 40px rgba(0,0,0,0.12); }
    .login-card h1 { font-size: 1.65rem; font-weight: 800; text-align: center; margin-bottom: 24px; color: #111; }
    .error-alert { background: #fff5f5; color: #e74c3c; border-radius: 12px; padding: 12px; font-size: 0.9rem; font-weight: 700; margin-bottom: 20px; text-align: center; border: 1px solid #fed7d7; }
    
    /* Role Selector Styling */
    .role-tabs { display: flex; align-items: center; gap: 8px; margin-bottom: 36px; flex-wrap: wrap; }
    .role-tabs .tab-btn { background: #f0f0f0; border: none; font-family: 'Nunito', sans-serif; font-size: 0.9rem; font-weight: 700; color: #666; padding: 8px 16px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
    .role-tabs .tab-btn.active { background: #2ECC40; color: #fff; }
    .role-tabs .guest-link { margin-left: auto; font-size: 0.9rem; font-weight: 700; color: #2ECC40; text-decoration: none; }
    
    /* Form Input Styling */
    .field-wrap { margin-bottom: 24px; }
    .field-wrap .input-row { display: flex; align-items: center; gap: 10px; border-bottom: 1.5px solid #bbb; padding-bottom: 2px; }
    .field-wrap .input-row:focus-within { border-bottom-color: #2ECC40; }
    .field-wrap .input-row i { font-size: 1.1rem; color: #bbb; }
    .field-wrap .input-row:focus-within i { color: #2ECC40; }
    .field-wrap .input-row input { flex: 1; border: none; outline: none; font-family: 'Nunito', sans-serif; font-size: 1.05rem; color: #333; padding: 10px 0 8px; background: transparent; }
    
    .eye-btn { background: none; border: none; padding: 0; cursor: pointer; color: #bbb; }
    .forgot-link { display: inline-block; margin-bottom: 44px; font-size: 0.9rem; font-weight: 700; color: #2ECC40; text-decoration: none; }
    .btn-login { display: block; margin: 0 auto; background: #2ECC40; color: #fff; border: none; border-radius: 50px; font-weight: 800; padding: 12px 64px; cursor: pointer; width: 100%; transition: background 0.2s; }
    .btn-login:hover { background: #28a745; }
    
    /* SSO Integration Styling */
    .divider { display: flex; align-items: center; text-align: center; margin: 24px 0; color: #aaa; font-size: 0.85rem; font-weight: 700; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #ddd; }
    .divider::before { margin-right: .5em; }
    .divider::after { margin-left: .5em; }
    
    .sso-buttons { display: flex; flex-direction: column; gap: 12px; }
    .btn-sso { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; background: #fff; border: 1.5px solid #ddd; border-radius: 50px; padding: 10px; font-size: 0.95rem; font-weight: 700; color: #444; text-decoration: none; transition: 0.2s; }
    .btn-sso:hover { background: #f8f9fa; border-color: #bbb; }
    .btn-sso .bi-google { color: #DB4437; font-size: 1.1rem; }
    .btn-sso .bi-microsoft { color: #00a4ef; font-size: 1.1rem; }

    .signup-link { text-align: center; margin-top: 24px; font-size: 0.9rem; color: #888; }
    .signup-link a { color: #2ECC40; font-weight: 800; text-decoration: none; }
  </style>
</head>
<body>

<div class="login-card">
  <h1>Welcome to UDMap!</h1>

  <div class="role-tabs" id="roleTabs">
    <button type="button" class="tab-btn active" id="tabStudent" onclick="switchTab('student')">Student</button>
    <button type="button" class="tab-btn" id="tabFaculty" onclick="switchTab('faculty')">Faculty</button>
    <button type="button" class="tab-btn" id="tabAdmin" onclick="switchTab('admin')">Admin</button>
    <a href="guest_dashboard.php" class="guest-link">Guest</a>
  </div>

  <?php if ($error): ?>
    <div class="error-alert">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form id="loginForm" action="login.php" method="POST">
    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? 'student') ?>" />

    <div class="field-wrap">
      <div class="input-row">
        <i class="bi bi-person-fill" id="identifierIcon"></i>
        <input type="text" id="identifier" name="identifier" placeholder="Student ID/Email" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required />
      </div>
    </div>

    <div class="field-wrap">
      <div class="input-row">
        <i class="bi bi-lock-fill"></i>
        <input type="password" id="password" name="password" placeholder="Password" required />
        <button type="button" class="eye-btn" onclick="togglePassword()"><i class="bi bi-eye" id="eyeIcon"></i></button>
      </div>
    </div>

    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
    <button type="submit" class="btn-login" name="login_submit">Login</button>
  </form>

  <div class="divider">OR</div>

  <div class="sso-buttons">
    <a href="googlelogin.php" class="btn-sso">
      <i class="bi bi-google"></i> Continue with Google
    </a>
    <a href="mslogin.php" class="btn-sso">
      <i class="bi bi-microsoft"></i> Continue with Microsoft
    </a>
  </div>

  <p class="signup-link">Don't have an account? <a href="register.php">Sign Up</a></p>
</div>

<script>
  function switchTab(role) {
    const tabs = ['student', 'faculty', 'admin'];
    const roleInput = document.getElementById('roleInput');
    const identifier = document.getElementById('identifier');
    const identifierIcon = document.getElementById('identifierIcon');

    // Update active button state
    tabs.forEach(r => {
        const btn = document.getElementById('tab' + r.charAt(0).toUpperCase() + r.slice(1));
        if (role === r) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Update placeholders and icons based on role
    roleInput.value = role;
    if (role === 'student') {
        identifier.placeholder = 'Student ID/Email';
        identifierIcon.className = 'bi bi-person-fill';
    } else if (role === 'faculty') {
        identifier.placeholder = 'Faculty Email';
        identifierIcon.className = 'bi bi-envelope-fill';
    } else {
        identifier.placeholder = 'Admin Username/Email';
        identifierIcon.className = 'bi bi-shield-lock-fill';
    }
  }

  function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }


  window.onload = function() {
    const currentRole = document.getElementById('roleInput').value;
    switchTab(currentRole);
  };
</script>
</body>
</html>