<?php
session_start();

// Redirect to dashboard if already logged in
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $role       = $_POST['role'];               
    $firstname  = trim($_POST['firstname']);
    $lastname   = trim($_POST['lastname']);
    $email      = trim($_POST['email']);
    $studentid  = trim($_POST['studentid']);  
    $password   = $_POST['password'];
    $cpassword  = $_POST['cpassword'];

    // 1. Validate Passwords Match
    if ($password !== $cpassword) {
        $error = "Passwords do not match.";
    } 
    // 2. Validate Password Length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    else {
        // 3. Check if Email or Student ID already exists
        // If it's a faculty member, they might not have a student ID, so we handle that conditionally.
        $checkSql = "SELECT USERID FROM [USER] WHERE EMAIL = ? OR (STUDENTID = ? AND STUDENTID != '')";
        $checkStmt = sqlsrv_query($conn, $checkSql, array($email, $studentid));

        if ($checkStmt === false) {
            $error = "Database query error.";
        } elseif (sqlsrv_has_rows($checkStmt)) {
            $error = "An account with this Email or Student ID already exists.";
        } else {
            // 4. Hash the password and insert the new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Setting default values for new accounts (0 failed attempts, not active, not verified)
            $insertSql = "INSERT INTO [USER] (FIRSTNAME, LASTNAME, EMAIL, STUDENTID, PASSWORD, USERTYPE, FAILED_ATTEMPTS, ISACTIVE, IS_VERIFIED) 
                          VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0)";
            $params = array($firstname, $lastname, $email, $studentid, $hashedPassword, $role);
            
            $insertStmt = sqlsrv_query($conn, $insertSql, $params);

            if ($insertStmt) {
                // Set a success session variable to display on login page, then redirect
                $_SESSION['success_msg'] = "Registration successful! You may now log in.";
                header('Location: login.php');
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UDMap - Sign Up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    /* Styling matches login.php perfectly */
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #2ECC40; font-family: 'Nunito', sans-serif; padding: 24px 16px; }
    .login-card { background: #fff; border-radius: 24px; padding: 40px 52px 40px; width: 100%; max-width: 520px; box-shadow: 0 8px 40px rgba(0,0,0,0.12); }
    .login-card h1 { font-size: 1.65rem; font-weight: 800; text-align: center; margin-bottom: 24px; color: #111; }
    .error-alert { background: #fff5f5; color: #e74c3c; border-radius: 12px; padding: 12px; font-size: 0.9rem; font-weight: 700; margin-bottom: 20px; text-align: center; border: 1px solid #fed7d7; }
    
    .role-tabs { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; justify-content: center; }
    .role-tabs .tab-btn { background: #f0f0f0; border: none; font-family: 'Nunito', sans-serif; font-size: 0.9rem; font-weight: 700; color: #666; padding: 8px 32px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
    .role-tabs .tab-btn.active { background: #2ECC40; color: #fff; }
    
    .field-wrap { margin-bottom: 20px; }
    .field-wrap .input-row { display: flex; align-items: center; gap: 10px; border-bottom: 1.5px solid #bbb; padding-bottom: 2px; }
    .field-wrap .input-row:focus-within { border-bottom-color: #2ECC40; }
    .field-wrap .input-row i { font-size: 1.1rem; color: #bbb; width: 20px; text-align: center; }
    .field-wrap .input-row:focus-within i { color: #2ECC40; }
    .field-wrap .input-row input { flex: 1; border: none; outline: none; font-family: 'Nunito', sans-serif; font-size: 1.05rem; color: #333; padding: 8px 0; background: transparent; }
    
    .eye-btn { background: none; border: none; padding: 0; cursor: pointer; color: #bbb; }
    .btn-login { display: block; margin: 30px auto 0; background: #2ECC40; color: #fff; border: none; border-radius: 50px; font-weight: 800; padding: 12px 64px; cursor: pointer; width: 100%; transition: background 0.2s; }
    .btn-login:hover { background: #28a745; }
    
    .signup-link { text-align: center; margin-top: 24px; font-size: 0.9rem; color: #888; }
    .signup-link a { color: #2ECC40; font-weight: 800; text-decoration: none; }
  </style>
</head>
<body>

<div class="login-card">
  <h1>Create an Account</h1>

  <div class="role-tabs" id="roleTabs">
    <button type="button" class="tab-btn active" id="tabStudent" onclick="switchTab('student')">Student</button>
    <button type="button" class="tab-btn" id="tabFaculty" onclick="switchTab('faculty')">Faculty</button>
  </div>

  <?php if ($error): ?>
    <div class="error-alert">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form id="registerForm" action="register.php" method="POST">
    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? 'student') ?>" />

    <!-- Name Row -->
    <div class="row">
        <div class="col-6">
            <div class="field-wrap">
              <div class="input-row">
                <i class="bi bi-person-fill"></i>
                <input type="text" name="firstname" placeholder="First Name" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required />
              </div>
            </div>
        </div>
        <div class="col-6">
            <div class="field-wrap">
              <div class="input-row">
                <input type="text" name="lastname" placeholder="Last Name" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required style="padding-left: 5px;" />
              </div>
            </div>
        </div>
    </div>

    <!-- Email -->
    <div class="field-wrap">
      <div class="input-row">
        <i class="bi bi-envelope-fill"></i>
        <input type="email" name="email" placeholder="Campus Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
      </div>
    </div>

    <!-- Student ID (Hides for Faculty) -->
    <div class="field-wrap" id="studentIdWrap">
      <div class="input-row">
        <i class="bi bi-card-heading"></i>
        <input type="text" id="studentid" name="studentid" placeholder="Student ID" value="<?= htmlspecialchars($_POST['studentid'] ?? '') ?>" />
      </div>
    </div>

    <!-- Passwords -->
    <div class="field-wrap">
      <div class="input-row">
        <i class="bi bi-lock-fill"></i>
        <input type="password" id="password" name="password" placeholder="Password (Min 8 chars)" required />
        <button type="button" class="eye-btn" onclick="togglePassword('password', 'eyeIcon1')"><i class="bi bi-eye" id="eyeIcon1"></i></button>
      </div>
    </div>

    <div class="field-wrap">
      <div class="input-row">
        <i class="bi bi-shield-lock-fill"></i>
        <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password" required />
        <button type="button" class="eye-btn" onclick="togglePassword('cpassword', 'eyeIcon2')"><i class="bi bi-eye" id="eyeIcon2"></i></button>
      </div>
    </div>

    <button type="submit" class="btn-login" name="register_submit">Sign Up</button>
  </form>

  <p class="signup-link">Already have an account? <a href="login.php">Log In</a></p>
</div>

<script>
  function switchTab(role) {
    const tabs = ['student', 'faculty'];
    const roleInput = document.getElementById('roleInput');
    const studentIdWrap = document.getElementById('studentIdWrap');
    const studentIdInput = document.getElementById('studentid');

    // Update active button state
    tabs.forEach(r => {
        const btn = document.getElementById('tab' + r.charAt(0).toUpperCase() + r.slice(1));
        if (role === r) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    roleInput.value = role;

    // Show/Hide Student ID based on role
    if (role === 'student') {
        studentIdWrap.style.display = 'block';
        studentIdInput.required = true;
    } else {
        studentIdWrap.style.display = 'none';
        studentIdInput.required = false;
        studentIdInput.value = ''; // Clear value if switching to faculty
    }
  }

  function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
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