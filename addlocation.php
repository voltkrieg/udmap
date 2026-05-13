<?php
session_start();
require_once 'db.php';

// Enforce admin check based on your dashboard logic
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['firstname'] ?? 'Admin';

// --- Handle CRUD Operations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $abbreviation = $_POST['abbreviation'];
        $fullname = $_POST['fullname'];
        $type = strtolower($_POST['type']); // venue, food, class, services, library
        $description = $_POST['description'];
        $floors = (int)$_POST['floors'];
        $lat = (float)$_POST['latitude'];
        $lng = (float)$_POST['longitude'];

        if ($action === 'add') {
            // New database table has 'total_searches', setting default to 0 on creation
            $stmt = $conn->prepare("INSERT INTO location (abbreviation, fullname, description, lattitude, longitude, type, floors, total_searches) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssddsi", $abbreviation, $fullname, $description, $lat, $lng, $type, $floors);
            $stmt->execute();
        } else if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE location SET abbreviation=?, fullname=?, description=?, lattitude=?, longitude=?, type=?, floors=? WHERE id=?");
            $stmt->bind_param("sssddsii", $abbreviation, $fullname, $description, $lat, $lng, $type, $floors, $id);
            $stmt->execute();
        }
        header("Location: addlocation.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM location WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: addlocation.php");
        exit();
    }
}

// --- Fetch Locations ---
$locationsArr = [];
$res = $conn->query("SELECT id, abbreviation, fullname, type, lattitude, longitude, description, floors FROM location ORDER BY fullname ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['lattitude'] = (float)$row['lattitude'];
        $row['longitude'] = (float)$row['longitude'];
        $locationsArr[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMap - Manage Locations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            --star-gold: #ffda6c;
            --firefly-glow: #b6ff92;
            --bg-dark: #011a10;
            --card-bg: rgba(2, 36, 21, 0.85);
            --border-glow: rgba(182, 255, 146, 0.3);
            --gradient-btn: linear-gradient(90deg, #014d31, #013220);
        }

        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; display: flex; flex-direction: column; min-height: 100vh; }
        body::before { content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%); z-index: -1; pointer-events: none; }
        
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; text-shadow: 0 0 10px rgba(255, 218, 108, 0.5); }
        
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .content-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); margin-bottom: 20px; }
        .card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; letter-spacing: 1px; }
        
        .btn-green { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; font-weight: 800; transition: all 0.3s ease; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .btn-green:hover { background: #015c3a; color: #fff; box-shadow: 0 0 15px var(--firefly-glow); transform: translateY(-2px); }
        
        .form-label { font-weight: 700; color: rgba(255,255,255,0.7); }
        .form-control, .form-select { background-color: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus, .form-select:focus { background-color: rgba(0,0,0,0.8); border-color: var(--firefly-glow); color: #fff; box-shadow: 0 0 8px rgba(182, 255, 146, 0.4); }
        
        /* Map Adjustments for Dark Mode */
        #mapPicker { height: 450px; border-radius: 8px; border: 1px solid var(--border-glow); width: 100%; cursor: crosshair; filter: brightness(0.9) contrast(1.1); box-shadow: inset 0 0 10px #000; }
        
        .table { color: #fff; }
        .table-light { background-color: rgba(255,255,255,0.05) !important; color: var(--star-gold) !important; }
        .table-light th { background-color: transparent !important; color: var(--star-gold) !important; border-bottom: 1px solid var(--star-gold); }
        .table tbody tr { border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .table tbody tr:hover { background-color: rgba(182, 255, 146, 0.05); }
        .table tbody td { background-color: transparent !important; color: #ccc; }

        @media(max-width: 960px) { .content-area { margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()" style="opacity: 0.8;"><i class="bi bi-list"></i></button>
        <span class="fs-4 ud-title">UDMap <span class="fs-6" style="color: var(--firefly-glow);">Admin</span></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="small fw-bold d-none d-sm-inline" style="color: rgba(255,255,255,0.6);">Core Status: <i class="bi bi-circle-fill" style="color: var(--firefly-glow); font-size: 10px; box-shadow: 0 0 8px var(--firefly-glow); border-radius: 50%;"></i> Online</span>
        <span class="small fw-bold border-start border-secondary ps-3" style="color: var(--star-gold);">Commander <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="row mb-4">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="content-card h-100 p-3">
                    <h5 class="card-title"><i class="bi bi-geo-alt-fill me-2"></i>Cartography (Map Picker)</h5>
                    <div id="mapPicker"></div>
                    <div class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i>Click anywhere on the map to pinpoint a location.</div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="content-card h-100">
                    <h5 class="card-title" id="formTitle">Add New Node</h5>
                    <form method="POST" id="locationForm">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="bldgId" value="">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Short Name / Code</label>
                                <input type="text" name="abbreviation" id="locName" class="form-control" required placeholder="e.g. ULS">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Classification</label>
                                <select name="type" id="locType" class="form-select" required>
                                    <option value="class">Class</option>
                                    <option value="services">Services</option>
                                    <option value="venue">Venue</option>
                                    <option value="food">Food</option>
                                    <option value="library">Library</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Full Designation</label>
                            <input type="text" name="fullname" id="locLongName" class="form-control" required placeholder="e.g. University Library">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Description</label>
                            <textarea name="description" id="locDesc" class="form-control" rows="2" placeholder="Building details..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Floors</label>
                                <input type="number" name="floors" id="locFloors" class="form-control" required min="1" value="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Latitude</label>
                                <input type="text" name="latitude" id="locLat" class="form-control" required readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Longitude</label>
                                <input type="text" name="longitude" id="locLng" class="form-control" required readonly>
                            </div>
                        </div>

                        <div class="d-flex gap-2 pt-2">
                            <button type="submit" class="btn btn-green w-100 py-2" id="submitBtn">Save Node</button>
                            <button type="button" class="btn btn-outline-danger w-100 py-2" onclick="resetForm()" id="cancelBtn" style="display:none;">Abort</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="card-title">Mapped Coordinates</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Code</th>
                            <th>Full Name</th>
                            <th>Type</th>
                            <th>Coordinates (Lat, Lng)</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach($locationsArr as $loc): ?>
                        <tr>
                            <td class="fw-bold" style="color: var(--star-gold);"><?= htmlspecialchars($loc['abbreviation']) ?></td>
                            <td style="color: #fff;"><?= htmlspecialchars($loc['fullname']) ?></td>
                            <td><span class="badge" style="background: rgba(255,255,255,0.1); color: var(--firefly-glow); border: 1px solid var(--border-glow);"><?= htmlspecialchars($loc['type']) ?></span></td>
                            <td style="color: rgba(255,255,255,0.5);"><?= $loc['lattitude'] ?>, <?= $loc['longitude'] ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-light border-0 me-1" onclick='editLocation(<?= json_encode($loc, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Node">
                                    <i class="bi bi-pencil" style="color: var(--star-gold);"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Erase this location from the map?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-light border-0" title="Delete Node">
                                        <i class="bi bi-trash" style="color: #ff6b6b;"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($locationsArr)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">The map is blank.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const defaultCenter = [14.32464, 120.96016];
    let map = L.map('mapPicker').setView(defaultCenter, 17);
    let pinMarker = null;

    // Dark-themed map tiles alternative could be used here, but keeping OSM for reliability
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20 }).addTo(map);

    map.on('click', function(e) { setPin(e.latlng.lat, e.latlng.lng); });

    function setPin(lat, lng) {
        if (!pinMarker) {
            pinMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
            pinMarker.on('dragend', function(e) {
                let pos = e.target.getLatLng();
                document.getElementById('locLat').value = pos.lat.toFixed(6);
                document.getElementById('locLng').value = pos.lng.toFixed(6);
            });
        } else {
            pinMarker.setLatLng([lat, lng]);
        }
        document.getElementById('locLat').value = lat.toFixed(6);
        document.getElementById('locLng').value = lng.toFixed(6);
    }

    function editLocation(loc) {
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Modify Node';
        document.getElementById('formAction').value = "edit";
        document.getElementById('bldgId').value = loc.id;
        
        document.getElementById('locName').value = loc.abbreviation;
        document.getElementById('locLongName').value = loc.fullname;
        document.getElementById('locType').value = loc.type;
        document.getElementById('locDesc').value = loc.description;
        document.getElementById('locFloors').value = loc.floors;
        
        setPin(loc.lattitude, loc.longitude);
        map.flyTo([loc.lattitude, loc.longitude], 18);
        
        document.getElementById('submitBtn').innerText = 'Update Node';
        document.getElementById('cancelBtn').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('locationForm').reset();
        document.getElementById('formTitle').innerText = "Add New Node";
        document.getElementById('formAction').value = "add";
        document.getElementById('bldgId').value = "";
        
        if (pinMarker) { map.removeLayer(pinMarker); pinMarker = null; }
        map.setView(defaultCenter, 17);
        
        document.getElementById('submitBtn').innerText = 'Save Node';
        document.getElementById('cancelBtn').style.display = 'none';
    }

    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
</script>
</body>
</html>