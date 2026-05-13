<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: home.html');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$firstName = 'User'; // Fallback

// Fetch firstname directly from the users table
$userStmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
if ($userStmt) {
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($uRow = $userRes->fetch_assoc()) {
        $firstName = htmlspecialchars($uRow['firstname']); 
    }
    $userStmt->close();
}
// ── Handle Comment & Reaction Submissions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $eventId = (int)$_POST['event_id'];
    $eventTitleForm = $_POST['eventtitle'];

    if ($action === 'add_comment' && !empty(trim($_POST['comment']))) {
        $comment = trim($_POST['comment']);
        $stmt = $conn->prepare("INSERT INTO event_comments (event_id, user_id, first_name, comment) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iiss", $eventId, $userId, $firstName, $comment);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'react' && !empty($_POST['reaction_type'])) {
        $reaction = $_POST['reaction_type'];
        $stmt = $conn->prepare("INSERT INTO event_reactions (event_id, user_id, reaction_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)");
        if ($stmt) {
            $stmt->bind_param("iis", $eventId, $userId, $reaction);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: eventguide.php?eventtitle=" . urlencode($eventTitleForm));
    exit();
}

function fmtTime($t): string {
    if (!$t) return '';
    return date('h:i A', strtotime($t));
}

// ── Get Requested Event ──
$eventTitle = $_POST['eventtitle'] ?? $_GET['eventtitle'] ?? null;
$eventData = null;
$mapLocation = null;
$comments = [];
$reactionCounts = ['like' => 0, 'love' => 0, 'excited' => 0];
$userReaction = null;

if ($eventTitle && $conn) {
    $sql = "SELECT e.id AS EVENTID, e.eventname AS EVENTTITLE, l.abbreviation AS PLACE, 
                   DATE(e.dtstart) AS DATE, TIME(e.dtstart) AS TIMESTART, TIME(e.dtend) AS TIMEEND, 
                   e.description AS DESCRIPTION, l.lattitude AS LATITUDE, l.longitude AS LONGITUDE, 
                   l.type AS BLDGTYPE, l.fullname AS LONGNAME
            FROM event e
            LEFT JOIN location l ON e.locid = l.id
            WHERE e.eventname = ?";
            
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $eventTitle);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $now = new DateTime();
            $start = new DateTime($row['DATE'] . ' ' . $row['TIMESTART']);
            $end = new DateTime($row['DATE'] . ' ' . $row['TIMEEND']);
            
            if ($now < $start) $row['STATUS'] = 'Upcoming';
            elseif ($now >= $start && $now <= $end) $row['STATUS'] = 'Ongoing';
            else $row['STATUS'] = 'Completed';

            $eventData = $row;
            if ($row['LATITUDE'] && $row['LONGITUDE']) {
                $mapLocation = [
                    'NAME' => $row['PLACE'],
                    'LATITUDE' => (float)$row['LATITUDE'],
                    'LONGITUDE' => (float)$row['LONGITUDE'],
                    'BLDGTYPE' => $row['BLDGTYPE']
                ];
            }

            // Fetch Comments
            $cStmt = $conn->prepare("SELECT first_name, comment, created_at FROM event_comments WHERE event_id = ? ORDER BY created_at DESC");
            if ($cStmt) {
                $cStmt->bind_param("i", $row['EVENTID']);
                $cStmt->execute();
                $cRes = $cStmt->get_result();
                while ($cRow = $cRes->fetch_assoc()) { $comments[] = $cRow; }
            }

            // Fetch Reactions
            $rStmt = $conn->prepare("SELECT reaction_type, COUNT(*) as cnt FROM event_reactions WHERE event_id = ? GROUP BY reaction_type");
            if ($rStmt) {
                $rStmt->bind_param("i", $row['EVENTID']);
                $rStmt->execute();
                $rRes = $rStmt->get_result();
                while ($rRow = $rRes->fetch_assoc()) { $reactionCounts[$rRow['reaction_type']] = $rRow['cnt']; }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Event Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />

    <style>
        :root {
            --star-gold: #ffda6c;
            --firefly-glow: #b6ff92;
            --bg-dark: #011a10;
            --card-bg: rgba(2, 36, 21, 0.85);
            --border-glow: rgba(182, 255, 146, 0.3);
            --gradient-btn: linear-gradient(90deg, #014d31, #013220);
        }

        body { 
            font-family: 'Nunito', sans-serif; 
            background: var(--bg-dark); 
            color: #fff; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }

        /* Topbar styling */
        .ud-topbar { 
            background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; 
            padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold);
        }
        
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; }

        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: grid; grid-template-columns: 1fr 400px; gap: 25px; transition: margin 0.25s; }

        .info-card { 
            background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow);
            box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px);
        }
        
        .info-card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; }

        .btn-quest { 
            background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; 
            padding: 8px 16px; font-weight: 800; text-transform: uppercase; transition: 0.3s; text-decoration: none;
        }
        .btn-quest:hover { background: #015c3a; color: #fff; box-shadow: 0 0 15px var(--firefly-glow); }

        .map-panel { background: #0a0a0a; border-radius: 0 0 12px 12px; height: 550px; width: 100%; border: 1px solid var(--border-glow); border-top: none; }
        
        .nav-tabs .nav-link { 
            font-weight: 700; color: rgba(255,255,255,0.6); background: rgba(0,0,0,0.4);
            border: 1px solid var(--border-glow); border-bottom: none; border-radius: 12px 12px 0 0; font-family: 'Cinzel', serif;
        }
        .nav-tabs .nav-link.active { color: var(--star-gold); background: var(--card-bg); border-color: var(--border-glow); }

        .comment-box { background: rgba(255,255,255,0.05); border: 1px solid var(--border-glow); border-radius: 8px; padding: 12px; margin-bottom: 10px; }
        .text-muted { color: rgba(255,255,255,0.5) !important; }
        
        /* Form styling */
        .form-control { background: rgba(0,0,0,0.3); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus { background: rgba(0,0,0,0.5); color: #fff; border-color: var(--firefly-glow); box-shadow: none; }

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
            <ul class="nav nav-tabs" id="mapTabs">
                <li class="nav-item">
                    <button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d"><i class="bi bi-map me-2"></i>2D Path</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d"><i class="bi bi-buildings me-2"></i>3D Path</button>
                </li>
            </ul>
            <div class="tab-content" id="mapTabsContent">
                <div class="tab-pane fade show active" id="view-2d">
                    <div id="mapContainer2D" class="map-panel"></div>
                </div>
                <div class="tab-pane fade" id="view-3d">
                    <div id="mapContainer3D" class="map-panel"></div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column gap-4">
            <div class="info-card">
                <a href="events.php" class="text-decoration-none small fw-bold mb-3 d-inline-block" style="color: var(--star-gold);"><i class="bi bi-arrow-left me-1"></i>Back to Events</a>
                
                <?php if (!$eventData): ?>
                    <h5 class="text-center">Event Not Found</h5>
                <?php else: ?>
                    <span class="badge bg-dark border border-warning text-warning mb-2"><?= $eventData['STATUS'] ?></span>
                    <h2 class="fs-4 fw-bold info-card-title"><?= htmlspecialchars($eventData['EVENTTITLE']) ?></h2>
                    
                    <div class="mb-3">
                        <div class="fw-bold" style="color: var(--firefly-glow);"><i class="bi bi-calendar-event me-2"></i><?= date('l, F j, Y', strtotime($eventData['DATE'])) ?></div>
                        <div class="text-muted small ms-4"><?= fmtTime($eventData['TIMESTART']) ?> - <?= fmtTime($eventData['TIMEEND']) ?></div>
                    </div>

                    <div class="mb-3">
                        <div class="fw-bold" style="color: var(--star-gold);"><i class="bi bi-geo-alt me-2"></i><?= htmlspecialchars($eventData['PLACE'] ?? 'TBA') ?></div>
                        <div class="text-muted small ms-4"><?= htmlspecialchars($eventData['LONGNAME'] ?? '') ?></div>
                    </div>

                    <p class="small border-top border-secondary pt-3"><?= nl2br(htmlspecialchars($eventData['DESCRIPTION'])) ?></p>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-quest flex-grow-1" onclick="getRoute('foot')"><i class="bi bi-person-walking me-1"></i> Walk</button>
                        <button class="btn btn-quest flex-grow-1" onclick="getRoute('driving')"><i class="bi bi-car-front me-1"></i> Drive</button>
                    </div>

                    <div class="mt-4 pt-3 border-top border-secondary">
                        <h6 class="fw-bold mb-2 text-warning">Reactions</h6>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="action" value="react">
                            <input type="hidden" name="event_id" value="<?= $eventData['EVENTID'] ?>">
                            <input type="hidden" name="eventtitle" value="<?= htmlspecialchars($eventData['EVENTTITLE']) ?>">
                            <button type="submit" name="reaction_type" value="like" class="btn btn-sm btn-outline-primary border-primary text-white">👍 <?= $reactionCounts['like'] ?></button>
                            <button type="submit" name="reaction_type" value="love" class="btn btn-sm btn-outline-danger border-danger text-white">❤️ <?= $reactionCounts['love'] ?></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h6 class="info-card-title"><i class="bi bi-chat-left-text me-2"></i>Scroll of Comments</h6>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="event_id" value="<?= $eventData['EVENTID'] ?>">
                    <input type="hidden" name="eventtitle" value="<?= htmlspecialchars($eventData['EVENTTITLE']) ?>">
                    <div class="input-group">
                        <textarea name="comment" class="form-control" rows="1" placeholder="Add your message..." required></textarea>
                        <button class="btn btn-quest" type="submit">Post</button>
                    </div>
                </form>

                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($comments as $c): ?>
                        <div class="comment-box">
                            <div class="d-flex justify-content-between mb-1">
                                <strong style="color: var(--firefly-glow); font-size: 0.8rem;"><?= htmlspecialchars($c['first_name']) ?></strong>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($c['comment'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>

<script>
    const locData = <?= json_encode($mapLocation) ?>;
    const centerCoords = locData ? [locData.LATITUDE, locData.LONGITUDE] : [14.32464, 120.96016]; 

    // Dark Map 2D
    var map2d = L.map('mapContainer2D').setView(centerCoords, 18);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
        maxZoom: 20,
        className: 'map-dark-filter' 
    }).addTo(map2d);

    // 3D Map
    var map3d = new maplibregl.Map({
        container: 'mapContainer3D',
        style: 'https://tiles.openfreemap.org/styles/liberty',
        center: [centerCoords[1], centerCoords[0]], 
        zoom: 17, pitch: 60
    });

    // Handle tab resize
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            if (e.target.id === 'view-2d-tab') map2d.invalidateSize();
            else map3d.resize();
        });
    });

    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
    // ... Routing functions (getRoute, fetchAndDrawRoute) from your original script ...
</script>
</body>
</html>