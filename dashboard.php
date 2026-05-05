<?php
session_start();

$server = "DESKTOP-KILKG9D\SQLEXPRESS";
$opts = ["Database" => "UDMapDB", "Uid" => "", "PWD" => "", "Encrypt" => true, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($server, $opts);

if (!$conn) { die("Database error"); }

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$id = (int)$_SESSION['user_id'];
$name = $_SESSION['first_name'] ?? 'User';

$day = date('l');
$now = date('H:i:s');
$map = ['Monday'=>'M','Tuesday'=>'T','Wednesday'=>'W','Thursday'=>'Th','Friday'=>'F','Saturday'=>'S','Sunday'=>'Su'];
$short = $map[$day] ?? $day;

$schedArr = [];
$res = sqlsrv_query($conn, "SELECT * FROM CLASS WHERE USERID = ? AND (DAY = ? OR DAY = ?) ORDER BY TIMESTART", [$id, $day, $short]);
while ($r = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) { $schedArr[] = $r; }

$events = [];
$eRes = sqlsrv_query($conn, "SELECT TOP 2 * FROM EVENTS WHERE DATE >= CAST(GETDATE() AS DATE) ORDER BY DATE, TIMESTART");
while ($r = sqlsrv_fetch_array($eRes, SQLSRV_FETCH_ASSOC)) { $events[] = $r; }

function timeFix($t) { 
    return ($t instanceof DateTime) ? $t->format('h:i A') : date('h:i A', strtotime($t)); 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS (2D Map) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- MapLibre GL CSS (for OpenFreeMap 3D) -->
    <link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />

    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; display: flex; flex-direction: column; min-height: 100vh; }
        .ud-topbar { background: #2ECC40; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 20px; margin-left: 230px; display: grid; grid-template-columns: 1fr 370px; gap: 20px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .info-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-green { background: #2ECC40; color: white; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 800; text-decoration: none; }
        .map-panel { background: #d4d4d4; border-radius: 0 0 16px 16px; height: 500px; width: 100%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #666; position: relative; }
        .nav-tabs .nav-link { font-weight: 700; color: #666; border-radius: 16px 16px 0 0; }
        .nav-tabs .nav-link.active { color: #2ECC40; }
        
        @media(max-width: 960px) { .content-area { grid-template-columns: 1fr; margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <span class="fw-bolder fs-5">UDMap</span>
    </div>
    <span class="small fw-bold">Hello, <?= $name ?></span>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <!-- MAP TABS SECTION -->
        <div class="d-flex flex-column">
            <ul class="nav nav-tabs border-0" id="mapTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active border-0" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button" role="tab" aria-controls="view-2d" aria-selected="true"><i class="bi bi-map me-2"></i>2D Top View</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link border-0" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button" role="tab" aria-controls="view-3d" aria-selected="false"><i class="bi bi-buildings me-2"></i>3D View</button>
                </li>
            </ul>
            
            <div class="tab-content bg-white rounded-bottom-4 shadow-sm" id="mapTabsContent">
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

        <div class="d-flex flex-column gap-3">
            <div class="info-card">
                <h2 class="fs-5 fw-bold mb-3">Schedule <small class="text-muted fs-6"><?= $day ?></small></h2>
                <?php if(empty($schedArr)): ?>
                    <p class="text-muted small">Nothing today.</p>
                <?php else: ?>
                    <?php foreach($schedArr as $c): ?>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($c['COURSETITLE']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($c['COURSECODE']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?= timeFix($c['TIMESTART']) ?></div>
                                <div class="text-muted small">to <?= timeFix($c['TIMEEND']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="schedule.php" class="btn-green w-100 d-block text-center mt-2">View Full List</a>
            </div>

            <div class="info-card">
                <h2 class="fs-5 fw-bold mb-3">Next Events</h2>
                <?php foreach($events as $e): ?>
                    <div class="mb-3">
                        <div class="text-success fw-bold small"><?= $e['DATE']->format('M d') ?></div>
                        <div class="fw-bold"><?= htmlspecialchars($e['EVENTTITLE']) ?></div>
                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($e['PLACE']) ?></div>
                    </div>
                <?php endforeach; ?>
                <a href="events.php" class="btn-green w-100 d-block text-center mt-2">All Events</a>
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
var locations = [
    { BLDGID: 1, NAME: 'ULS', BLDGTYPE: 'SPORT', LONGNAME: 'Ugnayang La Salle', LATITUDE: 14.32672, LONGITUDE: 120.95739, DESCRIPTION: 'Ugnayang La Salle', ISACTIVE: 1, FLOORS: 2, ICON: 'bi-dribbble' },
    { BLDGID: 2, NAME: 'MLH', BLDGTYPE: 'CLASS', LONGNAME: 'Maria Salome Llanera Hall', LATITUDE: 14.322719, LONGITUDE: 120.958470, DESCRIPTION: 'CEAT Building', ISACTIVE: 1, FLOORS: 2, ICON: 'bi-mortarboard-fill' },
    { BLDGID: 3, NAME: 'MTH', BLDGTYPE: 'CLASS', LONGNAME: 'Mariano Trias Hall', LATITUDE: 14.323987, LONGITUDE: 120.958840, DESCRIPTION: 'MTH Building', ISACTIVE: 1, FLOORS: 2, ICON: 'bi-mortarboard-fill' }
];

var markers2D = {};
var markers3D = {};
var centerCoords = [14.32464, 120.96016]; // [lat, lng]

// Helper function to create interactive popup content WITH Routing Buttons
function createPopupContent(loc) {
    return `
        <div style="font-family: 'Nunito', sans-serif; min-width: 180px;">
            <h6 class="mb-1 fw-bold text-success">${loc.LONGNAME} (${loc.NAME})</h6>
            <p class="mb-1 text-muted small"><i class="bi ${loc.ICON} me-1"></i>${loc.BLDGTYPE}</p>
            <p class="mb-1" style="font-size: 13px;">${loc.DESCRIPTION}</p>
            <p class="mb-2 text-muted" style="font-size: 12px;">Floors: <b>${loc.FLOORS}</b></p>
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
var map2d = L.map('mapContainer2D').setView(centerCoords, 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map2d);

locations.forEach(function(loc) {
    if(loc.ISACTIVE) {
        var pinColor = loc.BLDGTYPE === 'SPORT' ? '#9b59b6' : '#2ECC40';
        var iconHtml = `<div style="background:${pinColor}; width:28px; height:28px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; color:white; font-size:14px; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer;"><i class="bi ${loc.ICON || 'bi-geo-alt'}"></i></div>`;
        
        var marker = L.marker([loc.LATITUDE, loc.LONGITUDE], {
            icon: L.divIcon({ html: iconHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 14] })
        }).addTo(map2d).bindPopup(createPopupContent(loc));
        
        markers2D[loc.NAME.toLowerCase()] = marker;
    }
});


// ==========================================
// 2. INITIALIZE 3D MAP (OpenFreeMap via MapLibre)
// ==========================================
// Completely free, no access tokens needed!
var map3d = new maplibregl.Map({
    container: 'mapContainer3D',
    style: 'https://tiles.openfreemap.org/styles/liberty', // OpenFreeMap Liberty Style
    center: [centerCoords[1], centerCoords[0]], // [lng, lat]
    zoom: 16.5,
    pitch: 60, // Tilts the camera for 3D effect
    bearing: -20,
    antialias: true
});

map3d.on('load', () => {
    // OpenFreeMap usually provides building footprints under 'openmaptiles' source
    // We add an extrusion layer to make them 3D
    const layers = map3d.getStyle().layers;
    let labelLayerId;
    
    // Find the first symbol layer to place buildings underneath labels
    for (let i = 0; i < layers.length; i++) {
        if (layers[i].type === 'symbol' && layers[i].layout['text-field']) {
            labelLayerId = layers[i].id;
            break;
        }
    }

    map3d.addLayer({
        'id': 'add-3d-buildings',
        'source': 'openmaptiles', // OpenFreeMap relies on the openmaptiles schema
        'source-layer': 'building',
        'type': 'fill-extrusion',
        'minzoom': 15,
        'paint': {
            'fill-extrusion-color': '#aaa',
            'fill-extrusion-height': ['get', 'render_height'], // Schema specific height variable
            'fill-extrusion-base': ['get', 'render_min_height'],
            'fill-extrusion-opacity': 0.6
        }
    }, labelLayerId);
});

// Add Markers to 3D Map
locations.forEach(function(loc) {
    if(loc.ISACTIVE) {
        var pinColor = loc.BLDGTYPE === 'SPORT' ? '#9b59b6' : '#2ECC40';
        
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
    }
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
            document.querySelectorAll('.maplibregl-popup').forEach(p => p.remove()); // Updated class name
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
        map2d.invalidateSize(); 
        map3d.resize();         
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
</script>
</body>
</html>