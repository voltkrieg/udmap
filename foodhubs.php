<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit();
}

$id = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id']);
$_SESSION['user_id'] = $id;
$role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if ($role === 'admin') {
    header('Location: admindashboard.php');
    exit();
} elseif ($role === 'faculty') {
    header('Location: facultydashboard.php');
    exit();
}

$id = (int)$_SESSION['user_id'];
$firstName = 'User'; 
$userStmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
if ($userStmt) {
    $userStmt->bind_param("i", $id);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($uRow = $userRes->fetch_assoc()) {
        $firstName = htmlspecialchars($uRow['firstname']); 
    }
    $userStmt->close();
}

$foodHubs = [];
$reviews  = []; // Array to store reviews grouped by location

if ($conn) {
    // 1. Fetch Food Hub Locations
    $sqlFood = "SELECT abbreviation AS NAME, 
                       fullname AS LONGNAME, 
                       description AS DESCRIPTION 
                FROM location 
                WHERE type = 'food' 
                ORDER BY fullname, abbreviation";
                
    $resultFood = $conn->query($sqlFood);
    if ($resultFood) {
        while ($row = $resultFood->fetch_assoc()) { 
            $foodHubs[] = $row; 
        }
    } else {
        error_log("Database error (locations): " . $conn->error);
    }

    // 2. Fetch 5-Star Reviews & Comments
    // Assumes a 'location_reviews' table joined with your 'users' table
    $sqlReviews = "SELECT r.location_abbreviation, r.rating, r.comment, u.firstname
                   FROM location_reviews r
                   LEFT JOIN users u ON r.user_id = u.id
                   WHERE r.rating = 5
                   ORDER BY r.created_at DESC";
                   
    $resultReviews = $conn->query($sqlReviews);
    if ($resultReviews) {
        while ($row = $resultReviews->fetch_assoc()) {
            $loc = $row['location_abbreviation'];
            // Group comments by the location abbreviation
            if (!isset($reviews[$loc])) {
                $reviews[$loc] = [];
            }
            $reviews[$loc][] = $row;
        }
    } else {
        error_log("Database error (reviews): " . $conn->error);
    }
}
logUserAction($conn, $_SESSION['user_id'], 'OPENED_FH', 'User Opened Food Hubs.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Food Hubs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />
    <style>
        :root { --star-gold: #ffda6c; --firefly-glow: #b6ff92; --bg-dark: #011a10; --card-bg: rgba(2, 36, 21, 0.85); --border-glow: rgba(182, 255, 146, 0.3); }
        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        body::before { content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%); z-index: -1; pointer-events: none; }
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; text-shadow: 0 0 10px rgba(255, 218, 108, 0.5); }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: grid; grid-template-columns: 1fr 370px; gap: 25px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .info-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .info-card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; }
        .map-panel { background: #0a0a0a; border-radius: 0 0 12px 12px; height: 550px; width: 100%; border: 1px solid var(--border-glow); border-top: none; }
        .nav-tabs { border-bottom: none; gap: 5px; }
        .nav-tabs .nav-link { font-weight: 700; color: rgba(255,255,255,0.6); background: rgba(0,0,0,0.4); border: 1px solid var(--border-glow); border-bottom: none; border-radius: 12px 12px 0 0; padding: 12px 20px; font-family: 'Cinzel', serif; transition: 0.3s; }
        .nav-tabs .nav-link.active { color: var(--star-gold); background: var(--card-bg); border-color: var(--border-glow); text-shadow: 0 0 8px rgba(255, 218, 108, 0.4); }
        .text-muted { color: rgba(255,255,255,0.5) !important; }
        .border-bottom { border-bottom: 1px dashed rgba(255,255,255,0.1) !important; }
        .event-item { cursor: pointer; transition: 0.2s; padding: 10px; border-radius: 8px; }
        .event-item:hover { background: rgba(182, 255, 146, 0.05); }
        
        /* New review specific styles */
        .review-box { background: rgba(0,0,0,0.3); border-radius: 6px; padding: 8px 10px; margin-top: 6px; border-left: 2px solid var(--star-gold); }
        .review-stars { color: var(--star-gold); font-size: 0.85rem; }

        @media(max-width: 960px) { .content-area { grid-template-columns: 1fr; margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <span class="fs-4 ud-title">UDMap</span>
    </div>
    <span class="small fw-bold" style="color: var(--firefly-glow);">Explorer: <?= $firstName ?></span>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex flex-column">
            <ul class="nav nav-tabs" id="mapTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button"><i class="bi bi-map me-2"></i>Atlas View</button></li>
                <li class="nav-item"><button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button"><i class="bi bi-buildings me-2"></i>Realm View (3D)</button></li>
            </ul>
            <div class="tab-content shadow-lg" id="mapTabsContent">
                <div class="tab-pane fade show active" id="view-2d"><div id="mapContainer2D" class="map-panel"></div></div>
                <div class="tab-pane fade" id="view-3d"><div id="mapContainer3D" class="map-panel"></div></div>
            </div>
        </div>

        <div class="info-card" style="max-height: 600px; overflow-y: auto;">
            <h2 class="fs-5 fw-bold info-card-title d-flex justify-content-between align-items-end">
                <span><i class="bi bi-shop me-2"></i>Taverns & Food</span>
            </h2>

            <?php if (empty($foodHubs)): ?>
                <p class="text-muted small text-center py-5">No food hubs found on campus.</p>
            <?php else: ?>
                <?php foreach ($foodHubs as $fh): ?>
                <div class="event-item border-bottom mb-2 pb-2" onclick="focusLocation('<?= htmlspecialchars($fh['NAME']) ?>')">
                    <div class="fw-bold small" style="color: var(--star-gold);"><i class="bi bi-info-circle-fill me-1"></i>Dining & Snacks</div>
                    <div class="fw-bold fs-6 mt-1 text-white"><?= htmlspecialchars($fh['LONGNAME'] ?: $fh['NAME']) ?></div>
                    <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($fh['NAME']) ?></div>
                    
                    <?php if(!empty($fh['DESCRIPTION'])): ?>
                        <p class="small text-muted mt-2 mb-1" style="line-height: 1.3;"><?= htmlspecialchars($fh['DESCRIPTION']) ?></p>
                    <?php endif; ?>

                    <!-- Reviews Section -->
                    <?php if (isset($reviews[$fh['NAME']]) && count($reviews[$fh['NAME']]) > 0): ?>
                        <div class="mt-2">
                            <div class="review-stars mb-1">
                                <!-- Render 5 stars for this section since we filtered by 5 in SQL -->
                                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                                <span class="ms-1 text-white small opacity-75">Top Rated</span>
                            </div>
                            
                            <?php 
                            // Show up to 2 comments to keep the UI clean
                            $displayReviews = array_slice($reviews[$fh['NAME']], 0, 2);
                            foreach ($displayReviews as $rev): 
                            ?>
                                <div class="review-box">
                                    <div class="small text-white fst-italic">"<?= htmlspecialchars($rev['comment']) ?>"</div>
                                    <div class="small mt-1" style="color: var(--firefly-glow); font-size: 0.75rem;">— <?= htmlspecialchars($rev['firstname'] ?? 'Anonymous Explorer') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>


                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>
<script src="js/map-handler.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initMapSystem === 'function') initMapSystem('food');
    });
</script>
</body>
</html>