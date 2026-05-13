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
$name = 'User'; // Fallback

$userStmt = $conn->prepare("SELECT firstname FROM users WHERE id = ?");
if ($userStmt) {
    $userStmt->bind_param("i", $id);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    if ($uRow = $userRes->fetch_assoc()) {
        $name = htmlspecialchars($uRow['firstname']); 
    }
    $userStmt->close();
}

$day = date('l');
$map = ['Monday'=>'M','Tuesday'=>'T','Wednesday'=>'W','Thursday'=>'H','Friday'=>'F','Saturday'=>'S','Sunday'=>'SU'];
$short = $map[$day] ?? 'M';


$schedArr = [];
$stmt = $conn->prepare("SELECT * FROM class WHERE profid = ? AND day = ? ORDER BY timestart");
if ($stmt) {
    $stmt->bind_param("is", $id, $short);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $schedArr[] = $r; }
    $stmt->close();
}

$events = [];
$eRes = $conn->query("SELECT eventname, description, dtstart, locid FROM event WHERE dtstart >= NOW() ORDER BY dtstart LIMIT 2");
if ($eRes) {
    while ($r = $eRes->fetch_assoc()) { $events[] = $r; }
}

function getIconForType($type) {
    switch(strtolower($type)) {
        case 'food': return 'bi-shop';
        case 'class': return 'bi-book';
        case 'services': return 'bi-info-circle';
        case 'library': return 'bi-journal-bookmark';
        case 'venue': return 'bi-geo-alt-fill';
        default: return 'bi-building';
    }
}

$locationsArr = [];
$locQuery = "SELECT id AS BLDGID, abbreviation AS NAME, type AS BLDGTYPE, fullname AS LONGNAME, 
                    lattitude AS LATITUDE, longitude AS LONGITUDE, description AS DESCRIPTION, floors AS FLOORS 
             FROM location";
$locRes = $conn->query($locQuery);

if ($locRes) {
    while ($row = $locRes->fetch_assoc()) {
        $row['LATITUDE'] = (float)$row['LATITUDE'];
        $row['LONGITUDE'] = (float)$row['LONGITUDE'];
        $row['ICON'] = getIconForType($row['BLDGTYPE']);
        $row['ISACTIVE'] = 1;
        $locationsArr[] = $row;
    }
}

function timeFix($t) { return date('h:i A', strtotime($t)); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Explorer Dashboard</title>
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
            overflow-x: hidden;
        }

        /* Ambient Background Glow */
        body::before {
            content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%);
            z-index: -1; pointer-events: none;
        }

        /* Topbar styling */
        .ud-topbar { 
            background: #000; 
            height: 60px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 20px; 
            position: sticky; 
            top: 0; 
            z-index: 1001; 
            border-bottom: 1px solid var(--star-gold);
            box-shadow: 0 0 15px rgba(255, 218, 108, 0.2);
        }
        
        .ud-title {
            font-family: 'Cinzel', serif;
            color: var(--star-gold);
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(255, 218, 108, 0.5);
        }

        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: grid; grid-template-columns: 1fr 370px; gap: 25px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }

        /* Thematic Cards */
        .info-card { 
            background: var(--card-bg); 
            border-radius: 12px; 
            padding: 24px; 
            border: 1px solid var(--border-glow);
            box-shadow: 0 4px 20px rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
        }
        
        .info-card-title {
            font-family: 'Cinzel', serif;
            color: var(--star-gold);
            border-bottom: 1px solid rgba(255,218,108,0.2);
            padding-bottom: 12px;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }

        /* Thematic Buttons */
        .btn-quest { 
            background: var(--gradient-btn); 
            color: white; 
            border: 1px solid var(--firefly-glow); 
            border-radius: 8px; 
            padding: 10px 16px; 
            font-weight: 800; 
            text-transform: uppercase;
            letter-spacing: 1px;
            text-decoration: none; 
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .btn-quest:hover {
            background: #015c3a;
            color: #fff;
            box-shadow: 0 0 15px var(--firefly-glow);
            transform: translateY(-2px);
        }

        /* Map UI Styling */
        .map-panel { 
            background: #0a0a0a; 
            border-radius: 0 0 12px 12px; 
            height: 550px; 
            width: 100%; 
            position: relative; 
            border: 1px solid var(--border-glow);
            border-top: none;
        }
        
        /* Map Tabs */
        .nav-tabs { border-bottom: none; gap: 5px; }
        .nav-tabs .nav-link { 
            font-weight: 700; 
            color: rgba(255,255,255,0.6); 
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--border-glow);
            border-bottom: none;
            border-radius: 12px 12px 0 0; 
            padding: 12px 20px;
            font-family: 'Cinzel', serif;
            letter-spacing: 1px;
            transition: 0.3s;
        }
        .nav-tabs .nav-link:hover { color: #fff; background: rgba(2, 36, 21, 0.6); border-color: var(--firefly-glow); }
        .nav-tabs .nav-link.active { 
            color: var(--star-gold); 
            background: var(--card-bg);
            border-color: var(--border-glow);
            text-shadow: 0 0 8px rgba(255, 218, 108, 0.4);
        }
        
        .tab-content { border-radius: 0 0 12px 12px; }

        /* Dark Theme Overrides for Map Popups */
        .leaflet-popup-content-wrapper, .maplibregl-popup-content {
            background: #012a1a !important;
            color: #fff !important;
            border: 1px solid var(--star-gold);
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.8) !important;
        }
        .leaflet-popup-tip, .maplibregl-popup-tip {
            border-top-color: #012a1a !important;
        }
        .leaflet-popup-close-button, .maplibregl-popup-close-button {
            color: var(--star-gold) !important;
        }

        /* Darken the 2D map slightly to fit the theme */
        .leaflet-layer, .leaflet-control-zoom-in, .leaflet-control-zoom-out, .leaflet-control-attribution {
            filter: brightness(0.8) contrast(1.1) saturate(0.8);
        }

        /* Utility */
        .text-muted { color: rgba(255,255,255,0.5) !important; }
        .border-bottom { border-bottom: 1px dashed rgba(255,255,255,0.1) !important; }
        
        @media(max-width: 960px) { .content-area { grid-template-columns: 1fr; margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()" style="opacity: 0.8;"><i class="bi bi-list"></i></button>
        <span class="fs-4 ud-title">UDMap</span>
    </div>
    <span class="small fw-bold" style="color: var(--firefly-glow);">Explorer: <?= $name ?></span>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex flex-column">
            <ul class="nav nav-tabs" id="mapTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-controls="view-2d" aria-selected="true"><i class="bi bi-map me-2"></i>Atlas View</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-controls="view-3d" aria-selected="false"><i class="bi bi-buildings me-2"></i>Realm View (3D)</button>
                </li>
            </ul>
            
            <div class="tab-content shadow-lg" id="mapTabsContent">
                <div class="tab-pane fade show active" id="view-2d" role="tabpanel" aria-labelledby="view-2d-tab">
                    <div id="mapContainer2D" class="map-panel"></div>
                </div>
                <div class="tab-pane fade" id="view-3d" role="tabpanel" aria-labelledby="view-3d-tab">
                    <div id="mapContainer3D" class="map-panel"></div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column gap-4">
            
            <div class="info-card">
                <h2 class="fs-5 fw-bold info-card-title d-flex justify-content-between align-items-end">
                    <span><i class="bi bi-journal-text me-2"></i>Active Quests</span>
                    <small class="text-muted fs-6" style="font-family: 'Nunito', sans-serif;"><?= $day ?></small>
                </h2>
                <?php if(empty($schedArr)): ?>
                    <p class="text-muted small text-center py-3">No active quests for today.</p>
                <?php else: ?>
                    <?php foreach($schedArr as $c): ?>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <div>
                                <div class="fw-bold" style="color: var(--firefly-glow);"><?= htmlspecialchars($c['coursetitle']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($c['coursecode']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?= timeFix($c['timestart']) ?></div>
                                <div class="text-muted small">to <?= timeFix($c['timeend']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="schedule.php" class="btn btn-quest w-100 d-block text-center mt-3">View Quest Log</a>
            </div>

            <div class="info-card">
                <h2 class="fs-5 fw-bold info-card-title"><i class="bi bi-stars me-2"></i>Upcoming Events</h2>
                <?php if(empty($events)): ?>
                     <p class="text-muted small text-center py-3">The realm is quiet.</p>
                <?php else: ?>
                    <?php foreach($events as $e): ?>
                        <div class="mb-3 border-bottom pb-2">
                            <div class="fw-bold small" style="color: var(--star-gold);">
                            <i class="bi bi-calendar-event me-1"></i> <?= date('M d', strtotime($e['dtstart'])) ?>
                            </div>
                            <div class="fw-bold fs-6 mt-1"><?= htmlspecialchars($e['eventname']) ?></div>
                            <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i>Location ID: <?= htmlspecialchars($e['locid']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="events.php" class="btn btn-quest w-100 d-block text-center mt-3">All Events</a>
            </div>
            
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>

<script>
    const locationsData = <?= json_encode($locationsArr); ?>;
</script>

<script src="js/map-handler.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initMapSystem === 'function') {
            initMapSystem(''); 
        }
    });

    function focusEvent(placeName) {
        const key = placeName.toLowerCase();
        const is3DActive = document.getElementById('view-3d-tab').classList.contains('active');
        if (is3DActive && markers3D[key]) {
            map3d.flyTo({ center: markers3D[key].getLngLat(), zoom: 19 });
            markers3D[key].togglePopup();
        } else if (markers2D[key]) {
            map2d.setView(markers2D[key].getLatLng(), 19);
            markers2D[key].openPopup();
        }
    }
</script>
</body>
</html>