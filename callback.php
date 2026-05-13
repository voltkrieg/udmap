<?php
session_start();

require 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();

$client_id = $_ENV['gauthkey'];
$client_secret = $_ENV['gauthsec'];
$redirect_uri = 'https://coral-newt-292863.hostingersite.com/callback.php';

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $token_url = 'https://oauth2.googleapis.com/token';
    $post_fields = [
        'code' => $code,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Curl error (token request): " . curl_error($ch));
    }
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (!isset($token_data['access_token'])) {
        die("No access token received:<br><pre>$response</pre>");
    }

    $access_token = $token_data['access_token'];

    // ---------------------------
    // 2. Fetch user info
    // ---------------------------
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // TEMPORARY SSL FIX
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $user_info = curl_exec($ch);
    if (curl_errno($ch)) {
        die("Curl error (user info request): " . curl_error($ch));
    }
    curl_close($ch);

    $user = json_decode($user_info, true);

}

$google_id = $user['id'] ?? '';
$given     = $user['given_name'] ?? '';
$surname   = $user['family_name'] ?? '';
$email     = $user['email'] ?? '';
$pic       = $user['picture'] ?? '';

// SECURITY FIX: Prevent querying the database if Google failed to return data
if (empty($google_id) || empty($email)) {
    die("Authentication failed: No valid data received from Google.");
}

$_SESSION['oauth_data'] = [
    'google_id' => $google_id,
    'given'     => $given,
    'surname'   => $surname,
    'email'     => $email,
    'picture'   => $pic
];

require_once 'db.php';

// FIXED: Safely check if the user is actually a logged-in member (ID > 0) and not a guest (ID = 0)
$target_user_id = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id'] ?? 0);
$is_logged_in = ($target_user_id > 0);

if ($is_logged_in) {

    $updateq = $conn->prepare("UPDATE users SET googleid = ? WHERE id = ?");
    $updateq->bind_param("si", $google_id, $target_user_id);
    $updateq->execute();
    
    $return_target = 'dashboard.php';
    if (isset($_GET['state']) && $_GET['state'] === 'settings') {
        $return_target = 'dashboard.php?showSettings=1';
    }
    
    unset($_SESSION['oauth_data']);
    session_write_close();
    header("Location: $return_target");
    exit;

} else {
    // ----------------------------------------------------
    // LOGIN / REGISTER FLOW (Guest or New User)
    // ----------------------------------------------------
    $check = $conn->prepare("SELECT id, firstname, lastname, role FROM users WHERE googleid = ?");
    $check->bind_param("s", $google_id);
    $check->execute();
    $result = $check->get_result();

    if ($row = $result->fetch_assoc()) {
        // --- ACCOUNT FOUND -> Log them in ---
        $userId = $row['id'];
        
        $updateq = $conn->prepare("UPDATE users SET googleid = ? WHERE id = ?");
        $updateq->bind_param("si", $google_id, $userId);
        $updateq->execute();

        $cleanRole = strtolower(trim($row['role'] ?? 'student'));
        
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $cleanRole;
        $_SESSION['full_name'] = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

        $_SESSION['user'] = [
            'id'        => $userId,
            'firstname' => $row['firstname'],
            'lastname'  => $row['lastname'],
        ];
        
        if (function_exists('logUserAction')) {
            logUserAction($conn, $userId, 'GOOGLE_LOGIN', 'User verified login through Google.');
        }

        switch ($cleanRole) {
            case 'admin':
                $return_target = 'admindashboard.php';
                break;
            case 'faculty':
            case 'professor': 
                $return_target = 'facultydashboard.php';
                break;
            default:
                $return_target = 'dashboard.php';
                break;
        }

        unset($_SESSION['oauth_data']);
        session_write_close();
        header("Location: $return_target");
        exit;
        
    } else {
        session_write_close();
        header('Location: googleregister.php');
        exit;
    }
}
?>