<?php
session_start();

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

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId    = (int)$_SESSION['user_id'];
$firstName = htmlspecialchars($_SESSION['first_name'] ?? 'User');

function fmtTime($t): string {
    if ($t instanceof DateTime) {
        return $t->format('h:i A');
    }
    return date('h:i A', strtotime(substr($t, 0, 8)));
}

/* ── Query: All Upcoming Events ── */
$allEvents = [];
if ($conn) {
    $sqlEvents = "SELECT EVENTTITLE, PLACE, DATE, TIMESTART, TIMEEND
                  FROM   EVENTS
                  WHERE  DATE >= CAST(GETDATE() AS DATE)
                  ORDER  BY DATE, TIMESTART";
    $stmtEvents = sqlsrv_query($conn, $sqlEvents);
    if ($stmtEvents) {
        while ($row = sqlsrv_fetch_array($stmtEvents, SQLSRV_FETCH_ASSOC)) {
            $allEvents[] = $row;
        }
    }
}

/* ── Query: Map Locations ── */
$locations = [];
if ($conn) {
    $sqlLoc = "SELECT NAME, LONGNAME, DESCRIPTION, CAST(LATITUDE AS FLOAT) AS LATITUDE, CAST(LONGITUDE AS FLOAT) AS LONGITUDE, ICON, BLDGTYPE 
                FROM LOCATIONS 
                WHERE ISACTIVE = 1 AND UPPER(LTRIM(RTRIM(BLDGTYPE))) = 'Sports'
                ORDER BY LONGNAME, NAME";
    $stmtLoc = sqlsrv_query($conn, $sqlLoc);
    if ($stmtLoc) {
        while ($row = sqlsrv_fetch_array($stmtLoc, SQLSRV_FETCH_ASSOC)) {
            $locations[] = $row;
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>UDMap - Events</title>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>

<!-- Leaflet CSS (2D Map) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<!-- MapLibre GL CSS (for OpenFreeMap 3D) -->
<link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />

<style>
/* ── UI CORE STYLES ── */
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:'Nunito',sans-serif;background:#f4f4f4;color:#111;display:flex;flex-direction:column;min-height:100vh}
.ud-topbar{background:#2ECC40;display:flex;align-items:center;justify-content:space-between;padding:0 16px 0 0;height:54px;flex-shrink:0;position:sticky;top:0;z-index:1001}
.topbar-left{display:flex;align-items:center;height:100%}
.collapse-btn{background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;width:54px;height:54px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s}
.ud-topbar .brand{font-size:1.25rem;font-weight:800;color:#fff;text-decoration:none;padding:0 14px;white-space:nowrap}
.ud-topbar .greeting{font-size:.88rem;font-weight:700;color:#fff;white-space:nowrap}

.page-wrap{display:flex;flex:1;overflow:hidden}

.sidebar{width:230px;background:#fff;border-right:1px solid #e8e8e8;display:flex;flex-direction:column;padding:12px 0 24px;flex-shrink:0;height:calc(100vh - 54px);position:sticky;top:54px;overflow:hidden;transition:width .25s ease;z-index:1000}
.sidebar.collapsed{width:54px}
.sidebar.collapsed .nav-section-label, .sidebar.collapsed .nav-link-text, .sidebar.collapsed .sidebar-footer-label {opacity:0; pointer-events:none; width:0; overflow:hidden;}
.sidebar .nav-section-label{font-size:.68rem;font-weight:800;color:#ccc;letter-spacing:.1em;text-transform:uppercase;padding:14px 18px 5px;margin:0;white-space:nowrap;transition:opacity .2s}
.sidebar .nav-link{display:flex;align-items:center;gap:11px;padding:10px 18px;font-size:.88rem;font-weight:700;color:#444;text-decoration:none;white-space:nowrap;transition:background .15s,color .15s}
.sidebar .nav-link i{font-size:1.05rem;width:20px;text-align:center}
.sidebar .nav-link:hover{background:#f0fdf2;color:#2ECC40}
.sidebar .nav-link.active{background:#eafbec;color:#2ECC40;border-right:3px solid #2ECC40}

.content-area{flex:1;padding:20px;overflow-y:auto; display: grid; grid-template-columns: 1fr 380px; gap: 20px; align-items: start;}
.info-card{background:#fff;border-radius:16px;padding:24px 24px 26px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);}
.info-card h2{font-size:1.22rem;font-weight:800;margin:0 0 16px}

/* MAP TABS & EVENTS */
.map-panel { background: #d4d4d4; border-radius: 0 0 16px 16px; min-height: 560px; height: 100%; width: 100%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #666; position: relative; }
.nav-tabs .nav-link { font-weight: 700; color: #666; border-radius: 16px 16px 0 0; }
.nav-tabs .nav-link.active { color: #2ECC40; }
.tab-content { flex: 1; display: flex; flex-direction: column; }
.tab-pane { flex: 1; height: 100%; width: 100%; }

.event-item { border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 12px; cursor: pointer; transition: 0.2s; }
.event-item:hover { background: #f9f9f9; border-radius: 8px; padding-left: 5px; }
.event-item:last-child { border-bottom: none; }
.btn-green{background:#2ECC40;color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:800;padding:8px 16px;cursor:pointer;text-decoration:none;transition:background .15s}
.btn-green:hover{background:#27ae36;color:#fff}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:150}
.sidebar-overlay.active{display:block}

@media(max-width:960px){ .content-area { grid-template-columns: 1fr; } .map-panel { min-height: 350px; } }
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

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<div class="page-wrap">
  <?php include 'sidebar.php'; ?>
  <main class="content-area">
    
    <!-- MAP TABS SECTION -->
    <div class="d-flex flex-column" style="min-height: 600px;">
        <ul class="nav nav-tabs border-0 bg-white pt-2 px-2 rounded-top-4 shadow-sm" id="mapTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active border-0" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-controls="view-2d" aria-selected="true"><i class="bi bi-map me-2"></i>2D Top View</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link border-0" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-controls="view-3d" aria-selected="false"><i class="bi bi-buildings me-2"></i>3D View</button>
            </li>
        </ul>
        
        <div class="tab-content bg-white rounded-bottom-4 shadow-sm h-100" id="mapTabsContent">
            <!-- 2D Map Container -->
            <div class="tab-pane fade show active" id="view-2d" role="tabpanel" aria-labelledby="view-2d-tab">
                <div id="mapContainer2D" class="map-panel"></div>
            </div>
            <!-- 3D Map Container -->
            <div class="tab-pane fade" id="view-3d" role="tabpanel" aria-labelledby="view-3d-tab">
                <div id="mapContainer3D" class="map-panel"></div>
            </div>
        </div>
    </div>

    <div class="right-col">
        <div class="info-card">
            <h2 class="d-flex justify-content-between align-items-center">
                Campus Events
                <small class="text-muted" style="font-size: 0.7rem;">Upcoming</small>
            </h2>

            <?php if (!empty($eventsError)): ?>
                <div class="alert alert-warning small"><?= htmlspecialchars($eventsError) ?></div>
            <?php endif; ?>

            <?php if (empty($allEvents)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2 small">No upcoming events found.</p>
                </div>
            <?php else: ?>
                <div class="events-list">
                    <?php foreach ($allEvents as $i => $ev): 
                        $evDate = $ev['DATE'] instanceof DateTime ? $ev['DATE'] : new DateTime($ev['DATE']);
                    ?>
                    <div class="event-item" onclick="focusEvent('<?= htmlspecialchars($ev['PLACE']) ?>')">
                        <div class="text-muted fw-bold" style="font-size: 0.65rem;"><?= $evDate->format('l, F j') ?></div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= htmlspecialchars($ev['EVENTTITLE']) ?></div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($ev['PLACE']) ?></span>
                            <span class="small fw-bold text-success"><?= fmtTime($ev['TIMESTART']) ?></span>
                        </div>
                        <form action="event_guide.php" method="POST" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="btn-green w-100 py-1" style="font-size: 0.75rem;">View Details</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
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
// --- Data Setup ---
var locations = <?= json_encode($locations) ?>;
var markers2D = {};
var markers3D = {};
var centerCoords = [14.32464, 120.96016]; // [lat, lng]

// Helper function to create interactive popup content WITH Routing Buttons
function createPopupContent(loc) {
    return `
        <div style="font-family: 'Nunito', sans-serif; min-width: 180px;">
            <h6 class="mb-1 fw-bold text-success">${loc.NAME}</h6>
            <p class="mb-2 text-muted small" style="text-transform: capitalize;"><i class="bi ${loc.ICON || 'bi-geo-alt'} me-1"></i>${loc.BLDGTYPE}</p>
            <div class="d-flex gap-2 border-top pt-2">
                <button class="btn btn-sm btn-outline-primary w-50" onclick="getRoute(${loc.LATITUDE}, ${loc.LONGITUDE}, 'foot')"><i class="bi bi-person-walking"></i> Walk</button>
                <button class="btn btn-sm btn-outline-success w-50" onclick="getRoute(${loc.LATITUDE}, ${loc.LONGITUDE}, 'driving')"><i class="bi bi-car-front"></i> Drive</button>
            </div>
        </div>
    `;
}

// ==========================================
// 1. INITIALIZE 2D MAP (Leaflet)
// ==========================================
var map2d = L.map('mapContainer2D').setView(centerCoords, 18);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map2d);

locations.forEach(function(loc) {
    var pinColor = loc.BLDGTYPE === 'sport' ? '#9b59b6' : '#2ECC40';
    var iconHtml = `<div style="background:${pinColor}; width:28px; height:28px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; color:white; font-size:14px; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer;"><i class="bi ${loc.ICON || 'bi-geo-alt'}"></i></div>`;
    
    var marker = L.marker([loc.LATITUDE, loc.LONGITUDE], {
        icon: L.divIcon({ html: iconHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 14] })
    }).addTo(map2d).bindPopup(createPopupContent(loc));
    
    markers2D[loc.NAME.toLowerCase()] = marker;
});


// ==========================================
// 2. INITIALIZE 3D MAP (OpenFreeMap via MapLibre)
// ==========================================
var map3d = new maplibregl.Map({
    container: 'mapContainer3D',
    style: 'https://tiles.openfreemap.org/styles/liberty', // OpenFreeMap Liberty Style
    center: [centerCoords[1], centerCoords[0]], // [lng, lat]
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

// Add Markers to 3D Map
locations.forEach(function(loc) {
    var pinColor = loc.BLDGTYPE === 'sport' ? '#9b59b6' : '#2ECC40';
    
    var el = document.createElement('div');
    el.innerHTML = `<div style="background:${pinColor}; width:28px; height:28px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; color:white; font-size:14px; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer; transition: transform 0.2s;"><i class="bi ${loc.ICON || 'bi-geo-alt'}"></i></div>`;
    
    el.addEventListener('mouseenter', () => el.style.transform = 'scale(1.2)');
    el.addEventListener('mouseleave', () => el.style.transform = 'scale(1)');

    var popup = new maplibregl.Popup({ offset: 25 }).setHTML(createPopupContent(loc));
    
    var marker3d = new maplibregl.Marker(el)
        .setLngLat([loc.LONGITUDE, loc.LATITUDE])
        .setPopup(popup)
        .addTo(map3d);
        
    markers3D[loc.NAME.toLowerCase()] = marker3d;
});


// ==========================================
// 3. DIRECTIONS / ROUTING LOGIC
// ==========================================
window.routeLine2D = null;
window.userMarker2D = null;
window.userMarker3D = null;

function getRoute(destLat, destLng, profile) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            var userLat = position.coords.latitude;
            var userLng = position.coords.longitude;
            fetchAndDrawRoute(userLng, userLat, destLng, destLat, profile);
        }, function(error) {
            alert("Unable to retrieve your location. Please allow location access in your browser.");
        });
    } else {
        alert("Geolocation is not supported by this browser.");
    }
}

function fetchAndDrawRoute(startLng, startLat, endLng, endLat, profile) {
    var url = `https://router.project-osrm.org/route/v1/${profile}/${startLng},${startLat};${endLng},${endLat}?overview=full&geometries=geojson`;
    
    fetch(url)
    .then(res => res.json())
    .then(data => {
        if(data.routes && data.routes.length > 0) {
            var coords = data.routes[0].geometry.coordinates;
            drawRoute2D(coords, startLat, startLng);
            drawRoute3D(coords, startLat, startLng);
            
            map2d.closePopup();
            document.querySelectorAll('.maplibregl-popup').forEach(p => p.remove());
        } else {
            alert("Could not find a valid route.");
        }
    }).catch(err => {
        console.error("Routing error:", err);
        alert("An error occurred while fetching the route.");
    });
}

function drawRoute2D(coords, userLat, userLng) {
    var latlngs = coords.map(c => [c[1], c[0]]);
    
    if (window.routeLine2D) { map2d.removeLayer(window.routeLine2D); }
    if (window.userMarker2D) { map2d.removeLayer(window.userMarker2D); }
    
    window.routeLine2D = L.polyline(latlngs, {color: '#0d6efd', weight: 6, opacity: 0.8}).addTo(map2d);
    
    window.userMarker2D = L.marker([userLat, userLng], {
        icon: L.divIcon({ html: '<div style="background:#dc3545; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>', className: '' })
    }).addTo(map2d).bindPopup("Your Location");

    map2d.fitBounds(window.routeLine2D.getBounds(), { padding: [50, 50] });
}

function drawRoute3D(coords, userLat, userLng) {
    if (window.userMarker3D) { window.userMarker3D.remove(); }
    
    var userEl = document.createElement('div');
    userEl.style.cssText = 'background:#dc3545; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);';
    window.userMarker3D = new maplibregl.Marker(userEl).setLngLat([userLng, userLat]).addTo(map3d);

    if (map3d.getSource('route')) {
        map3d.getSource('route').setData({
            type: 'Feature',
            properties: {},
            geometry: { type: 'LineString', coordinates: coords }
        });
    } else {
        map3d.addSource('route', {
            type: 'geojson',
            data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: coords } }
        });
        map3d.addLayer({
            id: 'route',
            type: 'line',
            source: 'route',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': '#0d6efd', 'line-width': 6, 'line-opacity': 0.8 }
        });
    }
    
    var bounds = coords.reduce(function(bounds, coord) {
        return bounds.extend(coord);
    }, new maplibregl.LngLatBounds(coords[0], coords[0]));
    map3d.fitBounds(bounds, { padding: 50 });
}

// ==========================================
// 4. UI CONTROLS & TAB RESIZING
// ==========================================
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', event => {
        if (event.target.id === 'view-2d-tab') {
            map2d.invalidateSize(); 
        } else if (event.target.id === 'view-3d-tab') {
            map3d.resize();         
        }
    });
});

function focusEvent(placeName) {
    var key = placeName.toLowerCase();
    var is3DActive = document.getElementById('view-3d-tab').classList.contains('active');
    
    if (is3DActive) {
        if (markers3D[key]) {
            map3d.flyTo({ center: markers3D[key].getLngLat(), zoom: 19 });
            markers3D[key].togglePopup();
        }
    } else {
        if (markers2D[key]) {
            map2d.setView(markers2D[key].getLatLng(), 19);
            markers2D[key].openPopup();
        }
    }
}

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
function closeMobileSidebar() { document.getElementById('sidebar').classList.remove('mobile-open'); }
</script>
</body>
</html>