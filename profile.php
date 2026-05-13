<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $currentUserId;
$isOwner = ($currentUserId === $profileId);

// Fetch existing profile pic to prevent overwriting it with a blank value
$existingPic = '';
$chkProfile = $conn->query("SELECT profile_pic FROM user_profiles WHERE userid = $currentUserId");
if ($chkProfile && $chkProfile->num_rows > 0) {
    $existingPic = $chkProfile->fetch_assoc()['profile_pic'];
}

// ── Handle Profile Updates ──
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $bio = trim($_POST['bio']);
    $picUrl = $existingPic; // Default to existing image
    
    // Handle File Upload (Priority 1)
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES["profile_image"]["name"]);
        $targetFilePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
            $picUrl = $targetFilePath;
        }
    } 
    // Handle URL Fallback (Priority 2)
    elseif (!empty(trim($_POST['profile_pic_url']))) {
        $picUrl = trim($_POST['profile_pic_url']);
    }
    
    // Check if profile exists to either UPDATE or INSERT
    if ($chkProfile->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE user_profiles SET bio = ?, profile_pic = ? WHERE userid = ?");
        $stmt->bind_param("ssi", $bio, $picUrl, $currentUserId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO user_profiles (userid, bio, profile_pic) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $currentUserId, $bio, $picUrl);
        $stmt->execute();
    }
    header("Location: profile.php");
    exit();
}

// ── Fetch Profile Data ──
$userStmt = $conn->prepare("
    SELECT u.firstname, u.lastname, u.role, up.bio, up.profile_pic 
    FROM users u 
    LEFT JOIN user_profiles up ON u.id = up.userid 
    WHERE u.id = ?");
$userStmt->bind_param("i", $profileId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();

if (!$userData) {
    die("User not found in the realm.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($userData['firstname']) ?>'s Profile - UDMap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --star-gold: #ffda6c;
            --firefly-glow: #b6ff92;
            --bg-dark: #011a10;
            --card-bg: rgba(2, 36, 21, 0.85);
            --border-glow: rgba(182, 255, 146, 0.3);
            --gradient-btn: linear-gradient(90deg, #014d31, #013220);
        }
        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; min-height: 100vh; }
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; border-bottom: 1px solid var(--star-gold); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); }
        
        .page-wrap { display: flex; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: flex; flex-direction: column; align-items: center; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .profile-container { width: 100%; max-width: 700px; }
        .info-card { background: var(--card-bg); border-radius: 12px; padding: 25px; border: 1px solid var(--border-glow); margin-bottom: 20px; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.6); }
        
        /* Enhanced Cover Photo */
        .cover-photo { 
            height: 160px; 
            background: linear-gradient(135deg, #014d31 0%, #000 50%, #011a10 100%); 
            border-radius: 8px; 
            border: 1px solid var(--border-glow); 
            margin-bottom: -60px; 
            box-shadow: inset 0 0 50px rgba(0,0,0,0.8);
        }
        
        /* Glowing Profile Picture */
        .profile-pic-large { 
            width: 130px; 
            height: 130px; 
            border-radius: 50%; 
            border: 4px solid var(--bg-dark); 
            object-fit: cover; 
            background: #000;
            box-shadow: 0 0 20px rgba(255, 218, 108, 0.2);
            position: relative;
            z-index: 2;
        }
        
        .btn-quest { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; font-weight: 700; transition: 0.3s; }
        .btn-quest:hover { background: #015c3a; color: #fff; box-shadow: 0 0 10px var(--firefly-glow); }
        
        /* Form Inputs */
        .form-control { background: rgba(0,0,0,0.6); border: 1px solid var(--border-glow); color: #fff; border-radius: 6px; }
        .form-control:focus { background: rgba(0,0,0,0.9); color: #fff; border-color: var(--firefly-glow); box-shadow: 0 0 8px rgba(182, 255, 146, 0.2); }
        .form-control::file-selector-button { background: #013220; color: #fff; border: none; padding: 5px 10px; border-right: 1px solid var(--border-glow); cursor: pointer; transition: 0.2s;}
        .form-control::file-selector-button:hover { background: #014d31; }

        /* Quest Log Entry (Posts) */
        .quest-entry { border-left: 3px solid var(--star-gold); padding-left: 15px; }
                /* --- ENHANCED TEXT CONTRAST --- */
        body { 
            color: #f8fdfa; /* Brighter, crisper base white */
        }
        
        p {
            color: #e6f2ec; /* Slightly softened white for easy reading on paragraphs */
        }
        
        .text-muted { 
            color: #b0d4c1 !important; 
        }
        
        .form-control { 
            color: #ffffff !important; 
            font-weight: 600;
        }
        
        .form-control::placeholder { 
            color: rgba(255, 255, 255, 0.65) !important; 
        }
        
        a {
            color: var(--firefly-glow);
        }
        a:hover {
            color: #fff;
        }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <a href="social.php" class="text-white text-decoration-none me-3"><i class="bi bi-arrow-left fs-4"></i></a>
        <span class="fs-4 ud-title">Adventurer Profile</span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>
    <main class="content-area">
        <div class="profile-container">
            
            <div class="info-card text-center mt-4 pb-4">
                <div class="cover-photo"></div>
                <?php if ($userData['profile_pic']): ?>
                    <img src="<?= htmlspecialchars($userData['profile_pic']) ?>" class="profile-pic-large mx-auto d-block">
                <?php else: ?>
                    <i class="bi bi-person-circle text-secondary rounded-circle" style="font-size: 130px; line-height: 130px; border: 4px solid var(--bg-dark); background: #000; box-shadow: 0 0 20px rgba(255, 218, 108, 0.2); position: relative; z-index: 2;"></i>
                <?php endif; ?>
                
                <h2 class="mt-3 mb-1" style="color: var(--star-gold); font-family: 'Cinzel', serif;">
                    <?= htmlspecialchars($userData['firstname'] . ' ' . $userData['lastname']) ?>
                </h2>
                <span class="badge bg-success mb-3 px-3 py-2 border border-light border-opacity-25" style="letter-spacing: 1px;"><?= strtoupper(htmlspecialchars($userData['role'])) ?></span>
                
                <p class="text-muted mx-auto fs-6" style="max-width: 450px; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($userData['bio'] ?? 'This adventurer is quiet and mysterious.')) ?>
                </p>

                <?php if ($isOwner): ?>
                    <button class="btn btn-sm btn-outline-secondary text-light mt-3 px-4 rounded-pill" data-bs-toggle="collapse" data-bs-target="#editProfileForm">
                        <i class="bi bi-pencil me-1"></i> Edit Profile
                    </button>
                    
                    <div class="collapse mt-4 text-start" id="editProfileForm">
                        <div class="p-4 rounded" style="background: rgba(0,0,0,0.5); border: 1px dashed var(--border-glow);">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-light" style="color: var(--firefly-glow) !important;"><i class="bi bi-camera me-1"></i> Update Avatar</label>
                                    <input type="file" name="profile_image" class="form-control form-control-sm mb-2" accept="image/*">
                                    
                                    <div class="d-flex align-items-center gap-2">
                                        <hr class="flex-grow-1 border-secondary m-0">
                                        <span class="text-muted small">OR PASTE URL</span>
                                        <hr class="flex-grow-1 border-secondary m-0">
                                    </div>
                                    
                                    <input type="url" name="profile_pic_url" class="form-control form-control-sm mt-2" placeholder="https://example.com/image.jpg">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label small fw-bold" style="color: var(--firefly-glow) !important;"><i class="bi bi-card-text me-1"></i> Biography</label>
                                    <textarea name="bio" class="form-control form-control-sm" rows="3" placeholder="Tell the realm about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-quest btn-sm w-100 py-2">Save Attributes</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h4 class="mb-3 ps-2" style="font-family: 'Cinzel', serif; color: var(--firefly-glow); border-bottom: 1px solid var(--border-glow); padding-bottom: 10px;">
                <i class="bi bi-journal-bookmark-fill me-2"></i>Quest Log
            </h4>
            
            <?php
            // Fetch User's Posts
            $pStmt = $conn->query("SELECT * FROM posts WHERE userid = $profileId ORDER BY created DESC");
            if ($pStmt->num_rows === 0):
            ?>
                <div class="info-card text-center text-muted py-5">
                    <i class="bi bi-journal-x fs-1 opacity-50 mb-2 d-block"></i>
                    <p class="mb-0">No entries recorded in the log yet.</p>
                </div>
            <?php else: while ($post = $pStmt->fetch_assoc()): ?>
                <div class="info-card quest-entry">
                    <div class="small text-muted mb-2"><i class="bi bi-clock me-1"></i><?= date('F j, Y, g:i A', strtotime($post['created'])) ?></div>
                    <p class="mb-2 fs-6 text-light"><?= nl2br(htmlspecialchars($post['caption'])) ?></p>
                    <?php if (!empty($post['image_url'])): ?>
                        <div class="mt-3">
                            <img src="<?= htmlspecialchars($post['image_url']) ?>" class="shadow" style="max-width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; endif; ?>

        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>