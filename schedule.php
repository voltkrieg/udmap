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

function fmtTime($t): string {
    return date('h:i A', strtotime($t));
}

$weeklySchedule = [];

// Added c.room to the SELECT statement
$sqlSched = "SELECT c.subject, c.fullsub, c.timestart, c.timeend, c.day, c.room 
             FROM class c
             INNER JOIN students s ON s.courseid = c.id 
             WHERE s.studentid = ? 
             ORDER BY 
                CASE 
                    WHEN c.day = 'M' THEN 1
                    WHEN c.day = 'T' THEN 2
                    WHEN c.day = 'W' THEN 3
                    WHEN c.day = 'H' THEN 4
                    WHEN c.day = 'F' THEN 5
                    WHEN c.day = 'S' THEN 6
                    WHEN c.day = 'SU' THEN 7 
                END, c.timestart";

$stmtSched = $conn->prepare($sqlSched);
$stmtSched->bind_param("i", $userId);
$stmtSched->execute();
$result = $stmtSched->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $weeklySchedule[] = $row;
    }
}

// Ensure your logging function handles it correctly, commented out if it throws undefined function error
// logUserAction($conn, $userId, 'OPENED_SCH', 'User Opened Schedules.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - My Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.css" rel="stylesheet" />
    
    <style>
        :root {
            --star-gold: #ffda6c; --firefly-glow: #b6ff92; --bg-dark: #011a10;
            --card-bg: rgba(2, 36, 21, 0.85); --border-glow: rgba(182, 255, 146, 0.3);
            --gradient-btn: linear-gradient(90deg, #014d31, #013220);
        }
        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; display: flex; flex-direction: column; min-height: 100vh; overflow-x: hidden; }
        body::before { content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%); z-index: -1; pointer-events: none; }
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; text-shadow: 0 0 10px rgba(255, 218, 108, 0.5); }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; display: grid; grid-template-columns: 1fr 370px; gap: 25px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .info-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .info-card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; }
        .map-panel { background: #0a0a0a; border-radius: 0 0 12px 12px; height: 550px; width: 100%; border: 1px solid var(--border-glow); border-top: none; }
        .nav-tabs { border-bottom: none; gap: 5px; }
        .nav-tabs .nav-link { font-weight: 700; color: rgba(255,255,255,0.6); background: rgba(0,0,0,0.4); border: 1px solid var(--border-glow); border-bottom: none; border-radius: 12px 12px 0 0; padding: 12px 20px; font-family: 'Cinzel', serif; transition: 0.3s; }
        .nav-tabs .nav-link.active { color: var(--star-gold); background: var(--card-bg); border-color: var(--border-glow); text-shadow: 0 0 8px rgba(255, 218, 108, 0.4); }
        
        /* Schedule Specific Overrides */
        .day-header { font-size: 0.85rem; font-weight: 800; color: var(--firefly-glow); text-transform: uppercase; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 5px; margin-top: 15px; margin-bottom: 10px; }
        .class-item { display: flex; justify-content: space-between; padding: 12px; border-radius: 8px; transition: 0.2s; cursor: pointer; border: 1px solid transparent; }
        .class-item:hover { background: rgba(182, 255, 146, 0.05); border-color: var(--firefly-glow); box-shadow: 0 0 10px rgba(182, 255, 146, 0.1); }
        .text-muted { color: rgba(255,255,255,0.5) !important; }
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
            <ul class="nav nav-tabs" id="mapTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="view-2d-tab" data-bs-toggle="tab" data-bs-target="#view-2d" type="button"><i class="bi bi-map me-2"></i>Atlas View</button></li>
                <li class="nav-item"><button class="nav-link" id="view-3d-tab" data-bs-toggle="tab" data-bs-target="#view-3d" type="button"><i class="bi bi-buildings me-2"></i>Realm View (3D)</button></li>
            </ul>
            <div class="tab-content shadow-lg" id="mapTabsContent">
                <div class="tab-pane fade show active" id="view-2d"><div id="mapContainer2D" class="map-panel"></div></div>
                <div class="tab-pane fade" id="view-3d"><div id="mapContainer3D" class="map-panel"></div></div>
            </div>
        </div>

        <div class="info-card" style="max-height: 600px; overflow-y: auto;">
            <h2 class="fs-5 fw-bold info-card-title"><i class="bi bi-journal-text me-2"></i>Quest Log (Schedule)</h2>
        
            <?php if (empty($weeklySchedule)): ?>
                <p class="text-muted small text-center py-5">No classes found in your record.</p>
            <?php else: 
                $currentDay = "";
                $dayNames = ['M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday', 'H' => 'Thursday', 'F' => 'Friday', 'S' => 'Saturday', 'SU' => 'Sunday'];
                
                foreach ($weeklySchedule as $item): 
                    if ($currentDay !== $item['day']): 
                        $currentDay = $item['day'];
            ?>
                <div class="day-header"><?= $dayNames[$currentDay] ?? $currentDay ?></div>
            <?php endif; 
                
                // This strips out any non-alphabetical characters (so "ULS 101" or "ULS-101" becomes just "ULS")
                $buildingCode = preg_replace('/[^a-zA-Z]/', '', $item['room']);
            ?>
                
                <div class="class-item" onclick="focusLocation('<?= htmlspecialchars($buildingCode) ?>')">
                    <div>
                        <div class="fw-bold" style="color: var(--star-gold);"><?= htmlspecialchars($item['fullsub']) ?></div>
                        <div class="small mt-1">
                            <span class="fw-bold" style="color: var(--firefly-glow);"><?= htmlspecialchars($item['subject']) ?></span> 
                            <?php if(!empty($item['room'])): ?>
                                <span class="text-muted ms-1">• Room: <?= htmlspecialchars($item['room']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold fs-6"><?= fmtTime($item['timestart']) ?></div>
                        <div class="text-muted small">to <?= fmtTime($item['timeend']) ?></div>
                    </div>
                </div>
        
            <?php endforeach; endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.5.0/dist/maplibre-gl.js"></script>
<script src="js/map-handler.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if(typeof initMapSystem === 'function') initMapSystem('Class'); // Or 'Academic' depending on your DB
    });
</script>
</body>
</html>