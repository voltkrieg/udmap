<?php
session_start();

// --- Database Connection ---
$server = "DESKTOP-KILKG9D\SQLEXPRESS";
$opts = ["Database" => "UDMapDB", "Uid" => "", "PWD" => "", "Encrypt" => true, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($server, $opts);

if (!$conn) { die("Database error: " . print_r(sqlsrv_errors(), true)); }

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['first_name'] ?? 'Admin';

// --- Type to Icon Mapping ---
$typeToIcon = [
    'Academic'  => 'bi-book',
    'Services'  => 'bi-activity',
    'Sports'    => 'bi-star-fill',
    'Food Hubs' => 'bi-fork-knife',
    'Offices'   => 'bi-laptop'
];

// --- Handle CRUD Operations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $bldgName = $_POST['name'];
        $longName = $_POST['longname'];
        $bldgType = $_POST['bldgtype'];
        $description = $_POST['description'];
        $floors = (int)$_POST['floors'];
        $lat = (float)$_POST['latitude'];
        $lng = (float)$_POST['longitude'];
        $isActive = 1; 
        $icon = $typeToIcon[$bldgType] ?? 'bi-geo-alt';

        if ($action === 'add') {
            $sql = "INSERT INTO LOCATIONS (NAME, LONGNAME, BLDGTYPE, DESCRIPTION, FLOORS, LATITUDE, LONGITUDE, ISACTIVE, ICON) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [$bldgName, $longName, $bldgType, $description, $floors, $lat, $lng, $isActive, $icon];
            sqlsrv_query($conn, $sql, $params);
        } else if ($action === 'edit') {
            $bldgId = (int)$_POST['bldgid'];
            $sql = "UPDATE LOCATIONS SET NAME=?, LONGNAME=?, BLDGTYPE=?, DESCRIPTION=?, FLOORS=?, LATITUDE=?, LONGITUDE=?, ICON=? 
                    WHERE BLDGID=?";
            $params = [$bldgName, $longName, $bldgType, $description, $floors, $lat, $lng, $icon, $bldgId];
            sqlsrv_query($conn, $sql, $params);
        }
        
        // Refresh page to prevent form resubmission
        header("Location: addlocation.php");
        exit();
    }
    
    if ($action === 'delete') {
        $bldgId = (int)$_POST['bldgid'];
        // Using Soft Delete as per reference pattern (ISACTIVE = 0), or change to real DELETE
        $sql = "UPDATE LOCATIONS SET ISACTIVE = 0 WHERE BLDGID=?";
        sqlsrv_query($conn, $sql, [$bldgId]);
        
        header("Location: addlocation.php");
        exit();
    }
}

// --- Fetch Locations ---
$locationsArr = [];
$locQuery = "SELECT BLDGID, NAME, BLDGTYPE, LONGNAME, LATITUDE, LONGITUDE, DESCRIPTION, ISACTIVE, FLOORS, ICON FROM LOCATIONS WHERE ISACTIVE = 1";
$locRes = sqlsrv_query($conn, $locQuery);

if ($locRes) {
    while ($row = sqlsrv_fetch_array($locRes, SQLSRV_FETCH_ASSOC)) {
        $row['LATITUDE'] = (float)$row['LATITUDE'];
        $row['LONGITUDE'] = (float)$row['LONGITUDE'];
        $locationsArr[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Manage Locations</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; display: flex; flex-direction: column; min-height: 100vh; }
        .ud-topbar { background: #111; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; border-bottom: 3px solid #2ECC40; }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 20px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .info-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn-green { background: #2ECC40; color: white; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 700; }
        .btn-green:hover { background: #28a745; color: white; }
        
        #mapPicker { height: 450px; border-radius: 12px; border: 2px solid #ddd; width: 100%; cursor: crosshair; }
        .form-label { font-weight: 700; color: #555; }
        .table-wrap { overflow-x: auto; }
        .custom-icon { display:flex; align-items:center; justify-content:center; color:white; font-size:14px; border-radius:50%; border:2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); }

        @media(max-width: 960px) { .content-area { margin-left: 0; } }
        
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <span class="fw-bolder fs-5 text-success">UDMap <span class="text-white">Admin</span></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="small fw-bold">System Status: <i class="bi bi-circle-fill text-success" style="font-size: 10px;"></i> Online</span>
        <span class="small fw-bold border-start ps-3">Hello, <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="row mb-4">
            <!-- MAP SECTION -->
            <div class="col-lg-7">
                <div class="info-card h-100">
                    <h2 class="fs-5 fw-bold mb-3"><i class="bi bi-geo-alt-fill text-success me-2"></i>Location Picker</h2>
                    <p class="text-muted small mb-2">Click anywhere on the map to pin a new location, or drag the existing pin to adjust coordinates.</p>
                    <div id="mapPicker"></div>
                </div>
            </div>

            <!-- FORM SECTION -->
            <div class="col-lg-5">
                <div class="info-card h-100">
                    <h2 class="fs-5 fw-bold mb-3" id="formTitle">Add New Location</h2>
                    <form method="POST" id="locationForm">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="bldgid" id="bldgId" value="">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Short Name / Code</label>
                                <input type="text" name="name" id="locName" class="form-control form-control-sm" required placeholder="e.g. BLDG-A">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small">Building Type</label>
                                <select name="bldgtype" id="locType" class="form-select form-select-sm" required>
                                    <option value="" disabled selected>Select Type...</option>
                                    <option value="Academic">Academic</option>
                                    <option value="Services">Services</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Food Hubs">Food Hubs</option>
                                    <option value="Offices">Offices</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Full Building Name</label>
                            <input type="text" name="longname" id="locLongName" class="form-control form-control-sm" required placeholder="e.g. Main Academic Building">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Description</label>
                            <textarea name="description" id="locDesc" class="form-control form-control-sm" rows="2" placeholder="Brief description of the building..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Floors</label>
                                <input type="number" name="floors" id="locFloors" class="form-control form-control-sm" required min="1" value="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Latitude</label>
                                <input type="text" name="latitude" id="locLat" class="form-control form-control-sm" required readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label small">Longitude</label>
                                <input type="text" name="longitude" id="locLng" class="form-control form-control-sm" required readonly>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-green w-100" id="submitBtn"><i class="bi bi-save me-2"></i>Save Location</button>
                            <button type="button" class="btn btn-secondary w-100" onclick="resetForm()" id="cancelBtn" style="display:none;">Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="info-card">
            <h2 class="fs-5 fw-bold mb-3">Existing Locations</h2>
            <div class="table-wrap">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Icon</th>
                            <th>Short Name</th>
                            <th>Full Name</th>
                            <th>Type</th>
                            <th>Coordinates</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($locationsArr as $loc): ?>
                        <tr>
                            <td>
                                <?php 
                                    $colorMap = [
                                        'Academic' => '#2ECC40', 
                                        'Services' => '#dc3545', 
                                        'Sports' => '#6f42c1', 
                                        'Food Hubs' => '#fd7e14', 
                                        'Offices' => '#0d6efd'
                                    ];
                                    $bgColor = $colorMap[$loc['BLDGTYPE']] ?? '#6c757d';
                                ?>
                                <div class="custom-icon" style="background: <?= $bgColor ?>; width:32px; height:32px;">
                                    <i class="bi <?= htmlspecialchars($loc['ICON']) ?>"></i>
                                </div>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($loc['NAME']) ?></td>
                            <td><?= htmlspecialchars($loc['LONGNAME']) ?></td>
                            <td><span class="badge" style="background-color: <?= $bgColor ?>;"><?= htmlspecialchars($loc['BLDGTYPE']) ?></span></td>
                            <td class="small text-muted"><?= $loc['LATITUDE'] ?>, <?= $loc['LONGITUDE'] ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick='editLocation(<?= json_encode($loc, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this location?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="bldgid" value="<?= $loc['BLDGID'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($locationsArr)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No active locations found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // Configuration & State
    const defaultCenter = [14.32464, 120.96016]; // Update to your campus default coordinates
    let map = L.map('mapPicker').setView(defaultCenter, 17);
    let pinMarker = null;

    // Load Tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
        maxZoom: 20,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Click on map to drop/move pin
    map.on('click', function(e) {
        setPin(e.latlng.lat, e.latlng.lng);
    });

    // Helper: Set pin and update form coordinates
    function setPin(lat, lng) {
        if (!pinMarker) {
            // Create draggable marker
            pinMarker = L.marker([lat, lng], { draggable: true }).addTo(map);
            
            // Update coordinates when finished dragging
            pinMarker.on('dragend', function(e) {
                let pos = e.target.getLatLng();
                document.getElementById('locLat').value = pos.lat;
                document.getElementById('locLng').value = pos.lng;
            });
        } else {
            // Move existing marker
            pinMarker.setLatLng([lat, lng]);
        }
        
        // Update form fields immediately
        document.getElementById('locLat').value = lat;
        document.getElementById('locLng').value = lng;
    }

    // Function triggered when "Edit" button in table is clicked
    function editLocation(loc) {
        document.getElementById('formTitle').innerText = "Edit Location";
        document.getElementById('formAction').value = "edit";
        document.getElementById('bldgId').value = loc.BLDGID;
        
        document.getElementById('locName').value = loc.NAME;
        document.getElementById('locLongName').value = loc.LONGNAME;
        document.getElementById('locType').value = loc.BLDGTYPE;
        document.getElementById('locDesc').value = loc.DESCRIPTION;
        document.getElementById('locFloors').value = loc.FLOORS;
        
        setPin(loc.LATITUDE, loc.LONGITUDE);
        map.flyTo([loc.LATITUDE, loc.LONGITUDE], 18);
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Update Location';
        document.getElementById('cancelBtn').style.display = 'block';

        // Scroll up to form smoothly
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Function to clear form and revert to "Add" mode
    function resetForm() {
        document.getElementById('locationForm').reset();
        document.getElementById('formTitle').innerText = "Add New Location";
        document.getElementById('formAction').value = "add";
        document.getElementById('bldgId').value = "";
        
        if (pinMarker) {
            map.removeLayer(pinMarker);
            pinMarker = null;
        }
        map.setView(defaultCenter, 17);
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Save Location';
        document.getElementById('cancelBtn').style.display = 'none';
    }

    function toggleSidebar() { 
        // Assuming sidebar id is 'sidebar' from sidebar.php inclusion
        let sb = document.getElementById('sidebar');
        if(sb) sb.classList.toggle('collapsed'); 
    }
</script>
</body>
</html>