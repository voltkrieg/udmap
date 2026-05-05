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
    $sqlLoc = "SELECT NAME, CAST(LATITUDE AS FLOAT) AS LATITUDE, CAST(LONGITUDE AS FLOAT) AS LONGITUDE, ICON, BLDGTYPE FROM LOCATIONS WHERE ISACTIVE = 1";
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
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
.info-card{background:#fff;border-radius:16px;padding:24px 24px 26px}
.info-card h2{font-size:1.22rem;font-weight:800;margin:0 0 16px}

/* MAP & EVENTS */
#mapContainer { background:#d4d4d4; border-radius:16px; min-height:560px; z-index: 1; }
.event-item { border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 12px; cursor: pointer; transition: 0.2s; }
.event-item:hover { background: #f9f9f9; border-radius: 8px; padding-left: 5px; }
.event-item:last-child { border-bottom: none; }
.btn-green{background:#2ECC40;color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:800;padding:8px 16px;cursor:pointer;text-decoration:none;transition:background .15s}
.btn-green:hover{background:#27ae36;color:#fff}

.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:150}
.sidebar-overlay.active{display:block}

@media(max-width:960px){ .content-area { grid-template-columns: 1fr; } #mapContainer { min-height: 350px; } }
</style>
</head>
<body>

<header class="ud-topbar">
  <div class="topbar-left">
    <button class="collapse-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <a href="dashboard.php" class="brand">UDMap</a>
  </div>
  <span class="greeting">Hello! <?= $firstName ?></span>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<div class="page-wrap">
  <?php include 'sidebar.php'; ?>
  <main class="content-area">
    <div id="mapContainer"></div>

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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map = L.map('mapContainer').setView([14.32464, 120.96016], 18);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);

var locations = <?= json_encode($locations) ?>;
var markers = {};

locations.forEach(function(loc) {
    var pinColor = loc.BLDGTYPE === 'sport' ? '#9b59b6' : '#2ECC40';
    var iconHtml = `<div style="background:${pinColor}; width:24px; height:24px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; color:white; font-size:12px;"><i class="bi ${loc.ICON || 'bi-geo-alt'}"></i></div>`;
    
    var marker = L.marker([loc.LATITUDE, loc.LONGITUDE], {
        icon: L.divIcon({ html: iconHtml, className: '', iconSize: [24, 24], iconAnchor: [12, 12] })
    }).addTo(map).bindPopup(`<b>${loc.NAME}</b>`);
    
    markers[loc.NAME.toLowerCase()] = marker;
});

function focusEvent(placeName) {
    var marker = markers[placeName.toLowerCase()];
    if (marker) {
        map.setView(marker.getLatLng(), 19);
        marker.openPopup();
    }
}


function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
function closeMobileSidebar() { document.getElementById('sidebar').classList.remove('mobile-open'); }
</script>
</body>
</html>