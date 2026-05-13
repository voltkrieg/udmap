<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['oauth_data'])) {
    header('Location: index.php');
    exit();
}

$oauth = $_SESSION['oauth_data'];
$google_id = $oauth['google_id'];
$google_email = $oauth['email'];
$google_given = $oauth['given'];
$google_surname = $oauth['surname'];
$google_pic = $oauth['picture'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $role = $_POST['role']; // 'student' or 'faculty'
    $studentid = ($role === 'student') ? trim($_POST['studentid']) : null;
    
    // Create a dummy password for Google-only accounts
    $dummyPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    
    // FIXED: Changed 'google_id, google_email' to just 'googleid' to match your schema
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, studentid, email, password, isverified, isactive, role, googleid) VALUES (?, ?, ?, ?, ?, '1', '1', ?, ?)");
    
    if ($stmt) {
        // FIXED: 7 parameters to match the 7 question marks
        $stmt->bind_param("sssssss", $firstname, $lastname, $studentid, $google_email, $dummyPassword, $role, $google_id);
        
        if ($stmt->execute()) {
            $newUserId = $stmt->insert_id;
            
            $picStmt = $conn->prepare("INSERT INTO user_profiles (userid, profile_pic) VALUES (?, ?)");
            if ($picStmt) {
                $picStmt->bind_param("is", $newUserId, $google_pic);
                $picStmt->execute();
            }

            // FIXED: Standardize session variables to perfectly match callback.php
            $cleanRole = strtolower(trim($role));
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = $cleanRole;
            $_SESSION['full_name'] = trim($firstname . ' ' . $lastname);
            
            $_SESSION['user'] = [
                'id'        => $newUserId,
                'firstname' => $firstname,
                'lastname'  => $lastname,
            ];
            
            // Optional: Log the registration action if your function exists
            if (function_exists('logUserAction')) {
                logUserAction($conn, $newUserId, 'REGISTER_GOOGLE', 'User forged a new realm account via Google.');
            }

            // Clean up the temporary OAuth data
            unset($_SESSION['oauth_data']);
            session_write_close();
            
            // Route them to the correct dashboard based on their new role
            switch ($cleanRole) {
                case 'admin':
                    $targetPage = 'admindashboard.php';
                    break;
                case 'faculty':
                case 'professor': 
                    $targetPage = 'facultydashboard.php';
                    break;
                default:
                    $targetPage = 'dashboard.php';
                    break;
            }
            
            header("Location: " . $targetPage);
            exit();
        } else {
            $error = "Failed to forge account in the database.";
        }
    } else {
        $error = "Database preparation error.";
    }
}
?>

<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UDMap - Bind Your Realm Account</title>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;700;900&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --star-gold: #ffda6c;
      --firefly-glow: #b6ff92;
      --bg-dark: #011a10;
    }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg-dark); font-family: 'Nunito', sans-serif; color: white; overflow: hidden; }
    .bg-glow { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(255, 218, 108, 0.1) 0%, transparent 70%); pointer-events: none; }
    
    .login-card { background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(15px); border: 1px solid rgba(255, 218, 108, 0.4); border-radius: 30px; padding: 40px; width: 100%; max-width: 450px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.5); z-index: 10; max-height: 90vh; overflow-y: auto; }
    
    /* Scrollbar for the card if it gets too tall on mobile */
    .login-card::-webkit-scrollbar { width: 5px; }
    .login-card::-webkit-scrollbar-thumb { background: var(--star-gold); border-radius: 5px; }

    .card-header h2 { font-family: 'Cinzel', serif; font-size: 1.6rem; letter-spacing: 1px; color: var(--star-gold); margin-bottom: 5px; }
    .card-header p { font-size: 13px; opacity: 0.8; margin-top: 0; margin-bottom: 25px; color: #e6f2ec; }
    
    .input-group { margin-bottom: 18px; text-align: left; }
    .input-group label { display: block; font-size: 11px; text-transform: uppercase; margin-bottom: 6px; color: rgba(255,255,255,0.7); font-weight: 700; letter-spacing: 1px; }
    
    .input-control { width: 100%; padding: 12px 14px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; color: white; font-size: 1rem; outline: none; transition: 0.3s; box-sizing: border-box; font-family: 'Nunito', sans-serif;}
    .input-control:focus { border-color: var(--star-gold); background: rgba(255,255,255,0.1); box-shadow: 0 0 8px rgba(255, 218, 108, 0.2); }
    
    select.input-control { appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23FFFFFF%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 1rem top 50%; background-size: .65rem auto; }
    select.input-control option { background: var(--bg-dark); color: white; }

    .btn-submit { width: 100%; padding: 14px; background: linear-gradient(90deg, #014d31, #013220); color: white; border: 1px solid var(--star-gold); border-radius: 50px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; transition: 0.3s; margin-top: 15px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 218, 108, 0.3); }
    
    .google-badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 20px; font-size: 12px; margin-bottom: 20px; color: #b0d4c1; border: 1px solid rgba(255,255,255,0.1); }
    .google-badge img { width: 16px; height: 16px; border-radius: 50%; }

    .error-msg { color: #ff9292; font-size: 13px; margin-bottom: 15px; background: rgba(255,0,0,0.1); padding: 8px; border-radius: 8px; border: 1px solid rgba(255,0,0,0.2); }
  </style>
</head>
<body>
  <div class="bg-glow"></div>
  <div class="login-card">
    <div class="card-header">
      <h2>Forge Your Identity</h2>
      <p>Complete your profile to enter the campus realm.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <div class="google-badge">
        <img src="<?= htmlspecialchars($google_pic) ?>" alt="Google Avatar">
        Linking as <?= htmlspecialchars($google_email) ?>
    </div>
    
    <form action="googleregister.php" method="POST">
      
      <div style="display: flex; gap: 10px;">
          <div class="input-group" style="flex: 1;">
            <label>Given Name</label>
            <input type="text" name="firstname" class="input-control" value="<?= htmlspecialchars($google_given) ?>" required>
          </div>
          <div class="input-group" style="flex: 1;">
            <label>Surname</label>
            <input type="text" name="lastname" class="input-control" value="<?= htmlspecialchars($google_surname) ?>" required>
          </div>
      </div>

      <div class="input-group">
        <label>Your Role in the Realm</label>
        <select name="role" id="roleSelect" class="input-control" onchange="toggleStudentId()" required>
            <option value="student">Student</option>
            <option value="faculty">Faculty</option>
        </select>
      </div>

      <div class="input-group" id="studentIdGroup">
        <label>Student ID Number</label>
        <input type="text" name="studentid" id="studentIdInput" class="input-control" placeholder="e.g. 2023xxxxx">
      </div>

      <button type="submit" class="btn-submit">Complete Registration</button>
    </form>
  </div>

  <script>
      // Function to hide the Student ID field if Faculty is selected
      function toggleStudentId() {
          const role = document.getElementById('roleSelect').value;
          const studentIdGroup = document.getElementById('studentIdGroup');
          const studentIdInput = document.getElementById('studentIdInput');
          
          if (role === 'faculty') {
              studentIdGroup.style.display = 'none';
              studentIdInput.removeAttribute('required');
              studentIdInput.value = ''; // Clear value if hidden
          } else {
              studentIdGroup.style.display = 'block';
              studentIdInput.setAttribute('required', 'required');
          }
      }
      
      // Run once on page load to ensure correct state
      window.onload = toggleStudentId;
  </script>
</body>
</html>