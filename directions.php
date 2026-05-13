<?php
session_start();

require_once 'db.php';


/* ── Auth guard ── */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$firstName = htmlspecialchars($_SESSION['first_name'] ?? 'User');

/* ── Check if a Route was Requested ── */
$targetLocation = null;
$targetRoom = $_POST['room'] ?? $_GET['room'] ?? null;

if ($targetRoom && $conn) {
    $sqlTarget = "SELECT NAME, LONGNAME, CAST(LATITUDE AS FLOAT) AS LATITUDE, CAST(LONGITUDE AS FLOAT) AS LONGITUDE 
                  FROM LOCATIONS WHERE NAME = ? AND ISACTIVE = 1";
    $stmtTarget = sqlsrv_query($conn, $sqlTarget, array($targetRoom));
    if ($stmtTarget && $row = sqlsrv_fetch_array($stmtTarget, SQLSRV_FETCH_ASSOC)) {
        $targetLocation = $row;
    }
}
// Notice: We deleted the massive "Fetch All Map Locations" query here! map-handler.js does this for us now.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UDMap - Interactive Map & Directions</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />

<style>
/* ── UI CORE STYLES ── */
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:'Nunito',sans-serif;background:#f4f4f4;color:#111;display:flex;flex-direction:column;min-height:100vh}
.ud-topbar{background:#2ECC40;display:flex;align-items:center;justify-content:space-between;padding:0 16px 0 0;height:54px;flex-shrink:0;position:sticky;top:0;z-index:1001}
.topbar-left{display:flex;align-items:center;height:100%}
.collapse-btn{background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;width:54px;height:54px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ud-topbar .brand{font-size:1.25rem;font-weight:800;color:#fff;text-decoration:none;padding:0 14px;white-space:nowrap}
.ud-topbar .greeting{font-size:.88rem;font-weight:700;color:#fff}

.page-wrap{display:flex;flex:1;overflow:hidden}

/* SIDEBAR */
.sidebar{width:230px;background:#fff;border-right:1px solid #e8e8e8;display:flex;flex-direction:column;padding:12px 0 24px;flex-shrink:0;height:calc(100vh - 54px);position:sticky;top:54px;overflow:hidden;transition:width .25s ease;z-index:1000}
.sidebar.collapsed{width:54px}
.sidebar.collapsed .nav-link-text, .sidebar.collapsed .nav-section-label, .sidebar.collapsed .sidebar-footer-label {display:none}
.sidebar .nav-section-label{font-size:.68rem;font-weight:800;color:#ccc;letter-spacing:.1em;text-transform:uppercase;padding:14px 18px 5px;margin:0;white-space:nowrap}
.sidebar .nav-link{display:flex;align-items:center;gap:11px;padding:10px 18px;font-size:.88rem;font-weight:700;color:#444;text-decoration:none;white-space:nowrap}
.sidebar .nav-link:hover, .sidebar .nav-link.active{background:#eafbec;color:#2ECC40}

/* MAP AREA */
.content-area{flex:1; position:relative; display: flex; flex-direction: column;}
.nav-tabs { background: #fff; border-bottom: 1px solid #ddd; padding: 10px 20px 0; z-index: 2; position: relative;}
.nav-tabs .nav-link { font-weight: 700; color: #666; border: none; border-radius: 12px 12px 0 0; padding: 10px 20px; }
.nav-tabs .nav-link.active { color: #2ECC40; background: #f4f4f4; border-bottom: 3px solid #2ECC40; }
.tab-content { flex: 1; display: flex; flex-direction: column; position: relative; }
.tab-pane { flex: 1; height: 100%; width: 100%; }
.map-container { height: 100%; width: 100%; position: absolute; top: 0; left: 0; z-index: 1; }

/* LEGEND & ROUTING PANEL */
.map-legend { position: absolute; bottom: 20px; left: 20px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000; }
.legend-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; margin-bottom: 5px; }
.legend-color { width: 15px; height: 15px; border-radius: 50%; }

.routing-panel { position: absolute; top: 20px; right: 20px; background: white; padding: 20px; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.15); z-index: 1000; width: 320px; border-left: 5px solid #2ECC40; }
.routing-panel h5 { font-weight: 800; font-size: 1.1rem; margin-bottom: 5px; color: #111; }
.routing-panel p { font-size: 0.85rem; color: #666; margin-bottom: 15px; }

.popup-btn { background: #2ECC40; color: white; border: none; border-radius: 6px; padding: 5px 12px; font-weight: 800; font-size: 0.75rem; margin-top: 10px; width: 100%; cursor: pointer; }

@media(max-width:640px){
  .sidebar{position:fixed;left:0;top:54px;height:calc(100vh - 54px);transform:translateX(-100%);z-index:2000}
  .sidebar.mobile-open{transform:translateX(0)}
  .routing-panel { top: auto; bottom: 180px; right: 20px; left: 20px; width: auto; }
}
</style>
</head>
<body>

<header class="ud-topbar">
  <div class="topbar-left">
    <button class="collapse-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="dashboard.php" class="brand">UDMap</a>
  </div>
  <span class="greeting pe-3">Hello! <?= $firstName ?></span>
</header>

<div class="page-wrap">
  <?php include 'sidebar.php'; ?>

  <main class="content-area">
    <ul class="nav nav-tabs" id="mapTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-selected="true">
                <i class="bi bi-map me-2"></i>2D Top View
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-selected="false">
                <i class="bi bi-buildings me-2"></i>3D View
            </button>
        </li>
    </ul>

    <div class="tab-content" id="mapTabsContent">
        <div class="tab-pane fade show active" id="view-2d" role="tabpanel">
            <div id="mapContainer2D" class="map-container"></div>
        </div>
        <div class="tab-pane fade" id="view-3d" role="tabpanel">
            <div id="mapContainer3D" class="map-container"></div>
        </div>
    </div>

    <?php if ($targetLocation): ?>
    <div class="routing-panel">
        <h5>Navigating to <?= htmlspecialchars($targetLocation['NAME']) ?></h5>
        <p><?= htmlspecialchars($targetLocation['LONGNAME'] ?: 'Campus Location') ?></p>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm flex-fill fw-bold" onclick="updateRoute('foot')">
                <i class="bi bi-person-walking me-1"></i> Walk
            </button>
            <button class="btn btn-outline-success btn-sm flex-fill fw-bold" onclick="updateRoute('driving')">
                <i class="bi bi-car-front me-1"></i> Drive
            </button>
        </div>
        <a href="directions.php" class="btn btn-light btn-sm w-100 mt-2 text-muted fw-bold">Cancel Route</a>
    </div>
    <?php endif; ?>

    <div class="map-legend">
        <h6 class="fw-bold mb-3" style="font-size: 0.9rem;">Map Legend</h6>
        <div class="legend-item"><div class="legend-color" style="background:#f39c12;"></div> Food Hubs</div>
        <div class="legend-item"><div class="legend-color" style="background:#3498db;"></div> Services</div>
        <div class="legend-item"><div class="legend-color" style="background:#9b59b6;"></div> Sports</div>
        <div class="legend-item"><div class="legend-color" style="background:#2ecc71;"></div> Academic</div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>

<script src="js/map-handler.js"></script>

<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

    // Safely pass PHP coordinates to JS if a route was requested
    const targetLat = <?= $targetLocation ? $targetLocation['LATITUDE'] : 'null' ?>;
    const targetLng = <?= $targetLocation ? $targetLocation['LONGITUDE'] : 'null' ?>;

    document.addEventListener('DOMContentLoaded', async () => {
        // 1. Await the map initialization from our handler script
        if (typeof initMapSystem === 'function') {
            await initMapSystem(''); 
            
            // 2. If a destination was posted, immediately start routing
            if (targetLat && targetLng) {
                window.getRoute(targetLat, targetLng, 'foot'); // Default to walking
            }
        }
    });

    // Helper function for the routing panel buttons
    function updateRoute(profile) {
        if (targetLat && targetLng && typeof window.getRoute === 'function') {
            window.getRoute(targetLat, targetLng, profile);
        }
    }
</script>
</body>
</html>