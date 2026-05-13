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

// ── Handle Profile Updates ──
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $bio = trim($_POST['bio']);
    $picUrl = trim($_POST['profile_pic']);
    
    // Check if profile exists
    $chk = $conn->query("SELECT id FROM user_profiles WHERE userid = $currentUserId");
    if ($chk->num_rows > 0) {
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
        .info-card { background: var(--card-bg); border-radius: 12px; padding: 25px; border: 1px solid var(--border-glow); margin-bottom: 20px; position: relative; }
        
        .cover-photo { height: 150px; background: linear-gradient(45deg, #013220, #011a10); border-radius: 8px; border: 1px solid var(--border-glow); margin-bottom: -50px; }
        .profile-pic-large { width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--bg-dark); object-fit: cover; background: #000; }
        
        .btn-quest { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; transition: 0.3s; }
        .btn-quest:hover { background: #015c3a; color: #fff; box-shadow: 0 0 10px var(--firefly-glow); }
        .form-control { background: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus { background: rgba(0,0,0,0.8); color: #fff; border-color: var(--firefly-glow); box-shadow: none; }
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
            
            <div class="info-card text-center mt-4">
                <div class="cover-photo"></div>
                <?php if ($userData['profile_pic']): ?>
                    <img src="<?= htmlspecialchars($userData['profile_pic']) ?>" class="profile-pic-large mx-auto d-block">
                <?php else: ?>
                    <i class="bi bi-person-circle text-secondary bg-dark rounded-circle" style="font-size: 110px; line-height: 110px; border: 4px solid var(--bg-dark);"></i>
                <?php endif; ?>
                
                <h2 class="mt-2 mb-0" style="color: var(--star-gold); font-family: 'Cinzel', serif;">
                    <?= htmlspecialchars($userData['firstname'] . ' ' . $userData['lastname']) ?>
                </h2>
                <span class="badge bg-success mb-3"><?= strtoupper(htmlspecialchars($userData['role'])) ?></span>
                
                <p class="text-muted mx-auto" style="max-width: 400px;">
                    <?= nl2br(htmlspecialchars($userData['bio'] ?? 'This adventurer is quiet and mysterious.')) ?>
                </p>

                <?php if ($isOwner): ?>
                    <button class="btn btn-sm btn-outline-light mt-2" data-bs-toggle="collapse" data-bs-target="#editProfileForm">Edit Profile</button>
                    
                    <div class="collapse mt-4 text-start" id="editProfileForm">
                        <div class="p-3 border border-secondary rounded" style="background: rgba(0,0,0,0.4);">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="mb-2">
                                    <label class="form-label small text-muted">Profile Picture URL (Cloudinary/Imgur link)</label>
                                    <input type="url" name="profile_pic" class="form-control form-control-sm" value="<?= htmlspecialchars($userData['profile_pic'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small text-muted">Bio</label>
                                    <textarea name="bio" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-quest btn-sm w-100">Save Changes</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <h4 class="mb-3" style="font-family: 'Cinzel', serif; color: var(--firefly-glow);">Quest Log (Recent Posts)</h4>
            
            <?php
            // Fetch User's Posts
            $pStmt = $conn->query("SELECT * FROM posts WHERE userid = $profileId ORDER BY created DESC");
            if ($pStmt->num_rows === 0):
            ?>
                <div class="info-card text-center text-muted py-5">
                    <i class="bi bi-journal-x fs-1"></i>
                    <p class="mt-2 mb-0">No entries in the quest log yet.</p>
                </div>
            <?php else: while ($post = $pStmt->fetch_assoc()): ?>
                <div class="info-card">
                    <div class="small text-muted mb-2"><i class="bi bi-clock me-1"></i><?= date('F j, Y, g:i A', strtotime($post['created'])) ?></div>
                    <p class="mb-2"><?= nl2br(htmlspecialchars($post['caption'])) ?></p>
                    <?php if (!empty($post['image_url'])): ?>
                        <img src="<?= htmlspecialchars($post['image_url']) ?>" style="max-width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    <?php endif; ?>
                </div>
            <?php endwhile; endif; ?>

        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>