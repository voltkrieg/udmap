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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMap - Interactive Map</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />

    <style>
        /* Centralized Dark Theme Variables */
        :root {
            --star-gold: #ffda6c; --firefly-glow: #b6ff92; --bg-dark: #011a10;
            --card-bg: rgba(2, 36, 21, 0.85); --border-glow: rgba(182, 255, 146, 0.3);
        }

        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        body::before { content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%); z-index: -1; pointer-events: none; }

        /* Topbar */
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; text-shadow: 0 0 10px rgba(255, 218, 108, 0.5); text-decoration: none; }

        .page-wrap { display: flex; flex: 1; overflow: hidden; }
        
        /* Map specific full-screen container layout */
        .content-area { flex: 1; position: relative; display: flex; flex-direction: column; margin-left: 230px; transition: margin-left 0.25s; padding: 0; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }

        /* Map Tabs */
        .nav-tabs { background: #000; border-bottom: 1px solid var(--border-glow); padding: 10px 20px 0; z-index: 2; position: relative; gap: 5px; }
        .nav-tabs .nav-link { font-weight: 700; color: rgba(255,255,255,0.6); background: rgba(0,0,0,0.4); border: 1px solid var(--border-glow); border-bottom: none; border-radius: 12px 12px 0 0; padding: 10px 20px; font-family: 'Cinzel', serif; transition: 0.3s; }
        .nav-tabs .nav-link:hover { color: #fff; background: rgba(2, 36, 21, 0.6); border-color: var(--firefly-glow); }
        .nav-tabs .nav-link.active { color: var(--star-gold); background: var(--card-bg); border-color: var(--star-gold); text-shadow: 0 0 8px rgba(255, 218, 108, 0.4); }

        .tab-content { flex: 1; display: flex; flex-direction: column; position: relative; }
        .tab-pane { flex: 1; height: 100%; width: 100%; }
        .map-container { height: 100%; width: 100%; position: absolute; top: 0; left: 0; z-index: 1; background: #0a0a0a; }

        /* Dark Theme Legend */
        .map-legend {
            position: absolute; bottom: 30px; left: 30px;
            background: var(--card-bg); padding: 15px; border-radius: 12px;
            border: 1px solid var(--border-glow);
            box-shadow: 0 4px 20px rgba(0,0,0,0.8); z-index: 1000;
            backdrop-filter: blur(5px);
        }
        .map-legend h6 { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 8px; margin-bottom: 12px; }
        .legend-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; margin-bottom: 8px; color: #fff; }
        .legend-color { width: 16px; height: 16px; border-radius: 50%; box-shadow: 0 0 8px rgba(255,255,255,0.3); border: 1px solid #fff; }

        @media(max-width: 960px) { .content-area { margin-left: 0; } .map-legend { bottom: 20px; left: 20px; padding: 10px; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()" style="opacity: 0.8;"><i class="bi bi-list"></i></button>
        <a href="dashboard.php" class="fs-4 ud-title">UDMap</a>
    </div>
    <span class="small fw-bold" style="color: var(--firefly-glow);">Explorer: <?= $firstName ?></span>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <ul class="nav nav-tabs" id="mapTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-controls="view-2d" aria-selected="true">
                    <i class="bi bi-map me-2"></i>Atlas View
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-controls="view-3d" aria-selected="false">
                    <i class="bi bi-buildings me-2"></i>Realm View (3D)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="mapTabsContent">
            <div class="tab-pane fade show active" id="view-2d" role="tabpanel" aria-labelledby="view-2d-tab">
                <div id="mapContainer2D" class="map-container"></div>
            </div>
            <div class="tab-pane fade" id="view-3d" role="tabpanel" aria-labelledby="view-3d-tab">
                <div id="mapContainer3D" class="map-container"></div>
            </div>
        </div>

        <div class="map-legend">
            <h6 class="fw-bold">Map Legend</h6>
            <div class="legend-item"><div class="legend-color" style="background:#f39c12;"></div> Taverns (Food)</div>
            <div class="legend-item"><div class="legend-color" style="background:#3498db;"></div> Outposts (Services)</div>
            <div class="legend-item"><div class="legend-color" style="background:#9b59b6;"></div> Arenas (Sports)</div>
            <div class="legend-item"><div class="legend-color" style="background:#2ecc71;"></div> Academies (Class)</div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>
<script src="js/map-handler.js" defer></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize map with NO filter to load everything
        if(typeof initMapSystem === 'function') {
            initMapSystem('').then(() => {
                // Add Live User Tracking specific to the full map page
                if (map2d) {
                    map2d.locate({setView: false, watch: true, enableHighAccuracy: true});
                    let userMarkerLive, userCircleLive;

                    map2d.on('locationfound', function(e) {
                        let radius = e.accuracy / 2;
                        if (userMarkerLive) {
                            userMarkerLive.setLatLng(e.latlng);
                            userCircleLive.setLatLng(e.latlng).setRadius(radius);
                        } else {
                            userMarkerLive = L.marker(e.latlng, {
                                icon: L.divIcon({
                                    html: '<div style="background:var(--firefly-glow); width:15px; height:15px; border-radius:50%; border:2px solid #000; box-shadow: 0 0 15px var(--firefly-glow);"></div>',
                                    className: '', iconSize: [15, 15]
                                })
                            }).addTo(map2d).bindPopup("<span class='text-dark fw-bold'>You are here</span>");
                            userCircleLive = L.circle(e.latlng, radius, { color: 'var(--firefly-glow)', opacity: 0.4 }).addTo(map2d);
                        }
                    });
                }
            });
        }
    });

    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
</script>
</body>
</html>