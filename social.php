<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_post') {
        $caption = trim($_POST['caption']);
        $imageUrl = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES["image"]["name"]);
            $targetFilePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath)) {
                $imageUrl = $targetFilePath; 
            }
        }

        if (!empty($caption) || $imageUrl) {
            $stmt = $conn->prepare("INSERT INTO posts (userid, caption, image_url) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $userId, $caption, $imageUrl);
            $stmt->execute();
            $stmt->close();
        }
    } 
    elseif ($action === 'add_comment') {
        $postId = (int)$_POST['post_id'];
        $comment = trim($_POST['comment_text']);
        if (!empty($comment)) {
            $stmt = $conn->prepare("INSERT INTO comments (postid, userid, comment_text) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $postId, $userId, $comment);
            $stmt->execute();
            $stmt->close();
        }
    } 
    elseif ($action === 'react') {
        $postId = (int)$_POST['post_id'];
        $reaction = $_POST['reaction_type'];
        
        // Delete existing reaction if clicked again, otherwise insert/update
        $checkStmt = $conn->prepare("SELECT reaction_type FROM reactions WHERE postid = ? AND userid = ?");
        $checkStmt->bind_param("ii", $postId, $userId);
        $checkStmt->execute();
        $res = $checkStmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            if ($row['reaction_type'] === $reaction) {
                // User clicked the same reaction, so remove it
                $conn->query("DELETE FROM reactions WHERE postid = $postId AND userid = $userId");
            } else {
                // Update to new reaction
                $upStmt = $conn->prepare("UPDATE reactions SET reaction_type = ? WHERE postid = ? AND userid = ?");
                $upStmt->bind_param("sii", $reaction, $postId, $userId);
                $upStmt->execute();
            }
        } else {
            // Insert new reaction
            $inStmt = $conn->prepare("INSERT INTO reactions (postid, userid, reaction_type) VALUES (?, ?, ?)");
            $inStmt->bind_param("iis", $postId, $userId, $reaction);
            $inStmt->execute();
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: social.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Social Realm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
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
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; }
        
        .page-wrap { display: flex; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: flex; flex-direction: column; align-items: center; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .feed-container { width: 100%; max-width: 600px; }
        
        .info-card { background: var(--card-bg); border-radius: 12px; padding: 20px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); margin-bottom: 20px; }
        .btn-quest { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; padding: 8px 16px; font-weight: bold; transition: 0.3s; }
        .btn-quest:hover { background: #015c3a; box-shadow: 0 0 10px var(--firefly-glow); color: #fff;}
        
        .form-control { background: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus { background: rgba(0,0,0,0.8); color: #fff; border-color: var(--firefly-glow); box-shadow: none; }
        
        .post-img { max-width: 100%; border-radius: 8px; margin-top: 10px; border: 1px solid rgba(255,255,255,0.1); }
        .avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--star-gold); }
        
        .reaction-btn { background: none; border: 1px solid rgba(255,255,255,0.2); color: #fff; border-radius: 20px; padding: 4px 12px; font-size: 0.85rem; transition: 0.2s; }
        .reaction-btn:hover { background: rgba(255,255,255,0.1); }
        .reaction-active { background: var(--gradient-btn); border-color: var(--firefly-glow); color: var(--star-gold); }
        
        .comment-box { background: rgba(0,0,0,0.3); border-radius: 8px; padding: 10px; margin-top: 10px; font-size: 0.9rem; border-left: 3px solid var(--star-gold); }
        
        /* --- ENHANCED TEXT CONTRAST --- */
        body { color: #f8fdfa; }
        p { color: #e6f2ec; }
        .text-muted { color: #b0d4c1 !important; }
        .form-control { color: #ffffff !important; font-weight: 600; }
        .form-control::placeholder { color: rgba(255, 255, 255, 0.65) !important; }
        a { color: var(--firefly-glow); }
        a:hover { color: #fff; }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <a href="dashboard.php" class="text-white text-decoration-none me-3"><i class="bi bi-arrow-left fs-4"></i></a>
        <span class="fs-4 ud-title">Community Realm</span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>
    <main class="content-area">
        <div class="feed-container">
            
            <div class="info-card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create_post">
                    <div class="d-flex gap-3 mb-3">
                        <i class="bi bi-person-circle fs-1 text-secondary"></i>
                        <textarea name="caption" class="form-control" rows="2" placeholder="Share your thoughts..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <input type="file" name="image" class="form-control form-control-sm w-50" accept="image/*">
                        <button type="submit" class="btn btn-quest"><i class="bi bi-send me-1"></i> Post</button>
                    </div>
                </form>
            </div>

            <?php
            // Changed JOIN to LEFT JOIN so posts with userid = 0 still appear
            $postQuery = "
                SELECT p.*, u.firstname, u.lastname, up.profile_pic 
                FROM posts p 
                LEFT JOIN users u ON p.userid = u.id 
                LEFT JOIN user_profiles up ON u.id = up.userid 
                ORDER BY p.created DESC LIMIT 50";
            $postRes = $conn->query($postQuery);

            while ($post = $postRes->fetch_assoc()):
                $pId = $post['id'];
                
                // Get Reactions
                $reacts = ['like' => 0, 'heart' => 0, 'lol' => 0];
                $userReact = null;
                $rStmt = $conn->query("SELECT reaction_type, userid FROM reactions WHERE postid = $pId");
                while ($rRow = $rStmt->fetch_assoc()) {
                    if (isset($reacts[$rRow['reaction_type']])) $reacts[$rRow['reaction_type']]++;
                    if ($rRow['userid'] == $userId) $userReact = $rRow['reaction_type'];
                }

                // Get Comments (Changed JOIN to LEFT JOIN)
                $comments = [];
                $cStmt = $conn->query("SELECT c.*, u.firstname FROM comments c LEFT JOIN users u ON c.userid = u.id WHERE c.postid = $pId ORDER BY c.created_at ASC");
                while ($cRow = $cStmt->fetch_assoc()) { $comments[] = $cRow; }
            ?>
            <div class="info-card">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if ($post['profile_pic'] && $post['userid'] != 0): ?>
                        <img src="<?= htmlspecialchars($post['profile_pic']) ?>" class="avatar">
                    <?php else: ?>
                        <i class="bi bi-person-circle fs-1" style="color: var(--star-gold);"></i>
                    <?php endif; ?>
                    <div>
                        <?php if ($post['userid'] == 0 || empty($post['firstname'])): ?>
                            <span class="fw-bold text-white fs-5">Anonymous Adventurer</span>
                        <?php else: ?>
                            <a href="profile.php?id=<?= $post['userid'] ?>" class="fw-bold text-decoration-none text-white fs-5">
                                <?= htmlspecialchars(trim($post['firstname'] . ' ' . $post['lastname'])) ?>
                            </a>
                        <?php endif; ?>
                        <div class="small text-muted" style="font-size: 0.75rem;"><?= date('F j, g:i A', strtotime($post['created'])) ?></div>
                    </div>
                </div>

                <p class="mb-2"><?= nl2br(htmlspecialchars($post['caption'])) ?></p>
                <?php if (!empty($post['image_url'])): ?>
                    <img src="<?= htmlspecialchars($post['image_url']) ?>" class="post-img">
                <?php endif; ?>

                <div class="d-flex gap-2 mt-3 pt-3 border-top border-secondary">
                    <form method="POST" class="m-0 d-inline">
                        <input type="hidden" name="action" value="react">
                        <input type="hidden" name="post_id" value="<?= $pId ?>">
                        <button type="submit" name="reaction_type" value="like" class="reaction-btn <?= $userReact === 'like' ? 'reaction-active' : '' ?>">
                            <i class="bi bi-hand-thumbs-up"></i> <?= $reacts['like'] ?>
                        </button>
                        <button type="submit" name="reaction_type" value="heart" class="reaction-btn <?= $userReact === 'heart' ? 'reaction-active' : '' ?>">
                            <i class="bi bi-heart"></i> <?= $reacts['heart'] ?>
                        </button>
                        <button type="submit" name="reaction_type" value="lol" class="reaction-btn <?= $userReact === 'lol' ? 'reaction-active' : '' ?>">
                            <i class="bi bi-emoji-laughing"></i> <?= $reacts['lol'] ?>
                        </button>
                    </form>
                </div>

                <div class="mt-3">
                    <?php foreach ($comments as $c): 
                        $commentAuthor = ($c['userid'] == 0 || empty($c['firstname'])) ? 'Anonymous' : htmlspecialchars($c['firstname']);
                    ?>
                        <div class="comment-box">
                            <strong style="color: var(--firefly-glow);"><?= $commentAuthor ?>:</strong> 
                            <?= htmlspecialchars($c['comment_text']) ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <form method="POST" class="mt-2 d-flex gap-2">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="post_id" value="<?= $pId ?>">
                        <input type="text" name="comment_text" class="form-control form-control-sm" placeholder="Write a comment..." required>
                        <button type="submit" class="btn btn-quest btn-sm">Reply</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>

        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>