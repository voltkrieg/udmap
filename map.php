<?php
session_start();

/* ── Database Connection (MSSQL) ── */
$serverName = "DESKTOP-KILKG9D\SQLEXPRESS";
$connectionOptions = array(
    "Database"               => "UDMapDB",
    "Uid"                    => "",
    "PWD"                    => "",
    "Encrypt"                => true,
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    $dbError = "Connection failed: " . print_r(sqlsrv_errors(), true);
}

/* ── Auth guard ── */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$firstName = htmlspecialchars($_SESSION['first_name'] ?? 'User');

/* ── Fetch All Map Locations ── */
$locations = [];
if ($conn) {
    $sql = "SELECT BLDGID, NAME, BLDGTYPE, LONGNAME,
                   CAST(LATITUDE AS FLOAT) AS LATITUDE,
                   CAST(LONGITUDE AS FLOAT) AS LONGITUDE,
                   DESCRIPTION, ICON
            FROM LOCATIONS WHERE ISACTIVE = 1";
    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $locations[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UDMap - Interactive Map</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>

<!-- Leaflet CSS (2D Map) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<!-- MapLibre GL CSS (3D Map) -->
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

.content-area{flex:1; position:relative; display: flex; flex-direction: column;}
.nav-tabs { background: #fff; border-bottom: 1px solid #ddd; padding: 10px 20px 0; z-index: 2; position: relative;}
.nav-tabs .nav-link { font-weight: 700; color: #666; border: none; border-radius: 12px 12px 0 0; padding: 10px 20px; }
.nav-tabs .nav-link.active { color: #2ECC40; background: #f4f4f4; border-bottom: 3px solid #2ECC40; }
.tab-content { flex: 1; display: flex; flex-direction: column; position: relative; }
.tab-pane { flex: 1; height: 100%; width: 100%; }
.map-container { height: 100%; width: 100%; position: absolute; top: 0; left: 0; z-index: 1; }

.map-legend {
    position: absolute; bottom: 20px; left: 20px;
    background: white; padding: 15px; border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 1000;
}
.legend-item { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; font-weight: 700; margin-bottom: 5px; }
.legend-color { width: 15px; height: 15px; border-radius: 50%; }

.popup-btn {
    background: #2ECC40; color: white; border: none;
    border-radius: 6px; padding: 5px 12px; font-weight: 800;
    font-size: 0.75rem; margin-top: 10px; width: 100%; cursor: pointer;
}

@media(max-width:640px){
  .sidebar{position:fixed;left:0;top:54px;height:calc(100vh - 54px);transform:translateX(-100%);z-index:2000}
  .sidebar.mobile-open{transform:translateX(0)}
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
    <!-- MAP TABS -->
    <ul class="nav nav-tabs" id="mapTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-controls="view-2d" aria-selected="true">
                <i class="bi bi-map me-2"></i>2D Top View
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-controls="view-3d" aria-selected="false">
                <i class="bi bi-buildings me-2"></i>3D View
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
        <h6 class="fw-bold mb-3" style="font-size: 0.9rem;">Map Legend</h6>
        <div class="legend-item"><div class="legend-color" style="background:#f39c12;"></div> Food Hubs</div>
        <div class="legend-item"><div class="legend-color" style="background:#3498db;"></div> Services</div>
        <div class="legend-item"><div class="legend-color" style="background:#9b59b6;"></div> Sports</div>
        <div class="legend-item"><div class="legend-color" style="background:#2ecc71;"></div> Academic</div>
    </div>
  </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS (2D) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- MapLibre GL JS (3D OpenFreeMap) -->
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>

<script>
/* ── Sidebar Toggle ── */
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

/* ── Campus Center Coordinates ── */
var campusLat = 14.32464; 
var campusLng = 120.96016;

/* ── Color Map for Building Types ── */
const typeColors = {
    'food': '#f39c12',
    'service': '#3498db',
    'sport': '#9b59b6',
    'academic': '#2ecc71'
};

/* ── Helper: Generate Popup Content ── */
function createPopupContent(loc) {
    return `
        <div style="font-family:'Nunito', sans-serif;">
            <strong style="font-size:1rem;">${loc.NAME}</strong><br>
            <span style="color:#666; font-size:0.8rem;">${loc.DESCRIPTION || 'University Building'}</span>
            <form action="directions.php" method="POST">
                <input type="hidden" name="room" value="${loc.NAME}">
                <button type="submit" class="popup-btn">
                    <i class="bi bi-signpost-split-fill me-1"></i> Show Direction
                </button>
            </form>
        </div>
    `;
}

/* ── Load Locations from PHP ── */
var locations = <?= json_encode($locations) ?>;

// ==========================================
// 1. INITIALIZE 2D MAP (Leaflet)
// ==========================================
var map2d = L.map('mapContainer2D').setView([campusLat, campusLng], 18);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '© OpenStreetMap contributors'
}).addTo(map2d);

// ==========================================
// 2. INITIALIZE 3D MAP (MapLibre GL)
// ==========================================
var map3d = new maplibregl.Map({
    container: 'mapContainer3D',
    style: 'https://tiles.openfreemap.org/styles/liberty', // OpenFreeMap Liberty Style
    center: [campusLng, campusLat], // [lng, lat]
    zoom: 17,
    pitch: 60, // Tilts the camera for 3D effect
    bearing: -20,
    antialias: true
});

map3d.on('load', () => {
    // Add 3D Extrusion Layer for Buildings
    const layers = map3d.getStyle().layers;
    let labelLayerId;
    
    for (let i = 0; i < layers.length; i++) {
        if (layers[i].type === 'symbol' && layers[i].layout['text-field']) {
            labelLayerId = layers[i].id;
            break;
        }
    }

    map3d.addLayer({
        'id': 'add-3d-buildings',
        'source': 'openmaptiles',
        'source-layer': 'building',
        'type': 'fill-extrusion',
        'minzoom': 15,
        'paint': {
            'fill-extrusion-color': '#ccc',
            'fill-extrusion-height': ['get', 'render_height'],
            'fill-extrusion-base': ['get', 'render_min_height'],
            'fill-extrusion-opacity': 0.7
        }
    }, labelLayerId);
});

// ==========================================
// 3. POPULATE MARKERS FOR BOTH MAPS
// ==========================================
locations.forEach(function(loc) {
    var color = typeColors[loc.BLDGTYPE.toLowerCase()] || '#999';
    var iconClass = loc.ICON || 'bi-geo-alt-fill';
    
    // -- 2D Leaflet Marker --
    var pinHtml2D = `<div style="background:${color}; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); color:white;">
                        <i class="bi ${iconClass}"></i>
                     </div>`;
                     
    var customIcon2D = L.divIcon({
        html: pinHtml2D,
        className: '',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });

    L.marker([loc.LATITUDE, loc.LONGITUDE], { icon: customIcon2D })
     .addTo(map2d)
     .bindPopup(createPopupContent(loc));

    // -- 3D MapLibre Marker --
    var el3D = document.createElement('div');
    el3D.innerHTML = pinHtml2D; // Reuse the same HTML styling
    el3D.style.cursor = 'pointer';
    el3D.style.transition = 'transform 0.2s';
    
    el3D.addEventListener('mouseenter', () => el3D.style.transform = 'scale(1.2)');
    el3D.addEventListener('mouseleave', () => el3D.style.transform = 'scale(1)');

    var popup3D = new maplibregl.Popup({ offset: 25 }).setHTML(createPopupContent(loc));
    
    new maplibregl.Marker(el3D)
        .setLngLat([loc.LONGITUDE, loc.LATITUDE])
        .setPopup(popup3D)
        .addTo(map3d);
});

// ==========================================
// 4. USER GPS LOCATION (Applied to 2D Map)
// ==========================================
map2d.locate({setView: false, watch: true, enableHighAccuracy: true});
var userMarker, userCircle;

map2d.on('locationfound', function(e) {
    var radius = e.accuracy / 2;
    if (userMarker) {
        userMarker.setLatLng(e.latlng);
        userCircle.setLatLng(e.latlng).setRadius(radius);
    } else {
        userMarker = L.marker(e.latlng, {
            icon: L.divIcon({
                html: '<div style="background:#2ECC40; width:15px; height:15px; border-radius:50%; border:2px solid white; box-shadow: 0 0 10px rgba(46,204,64,0.6);"></div>',
                className: '', iconSize: [15, 15]
            })
        }).addTo(map2d).bindPopup("You are here");
        userCircle = L.circle(e.latlng, radius).addTo(map2d);
    }
});

// ==========================================
// 5. FIX TAB RESIZING ISSUES
// ==========================================
// Maps need to recalculate their dimensions when a hidden tab becomes visible
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', event => {
        if (event.target.id === 'view-2d-tab') {
            map2d.invalidateSize(); 
        } else if (event.target.id === 'view-3d-tab') {
            map3d.resize();         
        }
    });
});
</script>
</body>
</html>