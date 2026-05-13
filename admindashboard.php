<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$name = $_SESSION['full_name'] ?? 'Admin';

$userQuery = $conn->query("SELECT COUNT(*) as Total FROM users");
$totalUsers = $userQuery ? $userQuery->fetch_assoc()['Total'] : 0;

$activeQuery = $conn->query("SELECT COUNT(*) as Active FROM users WHERE isactive = '1'");
$activeUsers = $activeQuery ? $activeQuery->fetch_assoc()['Active'] : 0;

$nodeQuery = $conn->query("SELECT COUNT(*) as Nodes FROM location");
$totalNodes = $nodeQuery ? $nodeQuery->fetch_assoc()['Nodes'] : 0;

$eventQuery = $conn->query("SELECT COUNT(*) as TodayEvents FROM event WHERE DATE(dtstart) = CURDATE()");
$totalEvents = $eventQuery ? $eventQuery->fetch_assoc()['TodayEvents'] : 0;

$visitQuery = $conn->query("SELECT fullname, description, total_searches FROM location ORDER BY total_searches DESC LIMIT 50");
$visitedLocations = [];
if ($visitQuery) {
    while ($row = $visitQuery->fetch_assoc()) {
        $visitedLocations[] = $row;
    }
}

$loginQuery = $conn->query("SELECT userid, details, timestmp FROM logs WHERE action = 'LOGIN_SUCCESS' ORDER BY timestmp DESC LIMIT 50");
$recentLogins = [];
if ($loginQuery) {
    while ($row = $loginQuery->fetch_assoc()) {
        $recentLogins[] = $row;
    }
}

$activityQuery = $conn->query("SELECT userid, action, details, ipaddress, timestmp FROM logs ORDER BY timestmp DESC LIMIT 5");
$recentActivities = [];
if ($activityQuery) {
    while ($row = $activityQuery->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}

function timeAgo($datetime) {
    if (!$datetime) return '';
    $time = is_string($datetime) ? strtotime($datetime) : $datetime;
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . " mins ago";
    if ($diff < 86400) return floor($diff / 3600) . " hours ago";
    return floor($diff / 86400) . " days ago";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMap - Command Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
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
        }

        /* Ambient Background Glow */
        body::before {
            content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%);
            z-index: -1; pointer-events: none;
        }

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
        .content-area { flex: 1; padding: 25px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .stat-card, .content-card { 
            background: var(--card-bg); 
            border-radius: 12px; 
            padding: 24px; 
            border: 1px solid var(--border-glow);
            box-shadow: 0 4px 20px rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
        }

        .stat-card { display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 25px rgba(182, 255, 146, 0.2); }
        
        .stat-card .icon-box { 
            width: 55px; height: 55px; 
            border-radius: 12px; 
            display: flex; align-items: center; justify-content: center; 
            font-size: 26px; 
            color: #fff;
            background: var(--gradient-btn);
            border: 1px solid var(--firefly-glow);
        }

        .card-title {
            font-family: 'Cinzel', serif;
            color: var(--star-gold);
            border-bottom: 1px solid rgba(255,218,108,0.2);
            padding-bottom: 12px;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        /* Custom Scrollable List Styles */
        .scrollable-list { max-height: 350px; overflow-y: auto; padding-right: 8px; }
        .scrollable-list::-webkit-scrollbar { width: 6px; }
        .scrollable-list::-webkit-scrollbar-thumb { background-color: var(--star-gold); border-radius: 10px; }
        .scrollable-list::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
        
        .list-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 14px 0; 
            border-bottom: 1px dashed rgba(255,255,255,0.1); 
        }
        .list-item:last-child { border-bottom: none; }

        /* Table Styling Overrides */
        .table { color: #fff; }
        .table-light { background-color: rgba(255,255,255,0.05) !important; color: var(--star-gold) !important; }
        .table-light th { background-color: transparent !important; color: var(--star-gold) !important; border-bottom: 1px solid var(--star-gold); }
        .table tbody tr { border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .table tbody tr:hover { background-color: rgba(182, 255, 146, 0.05); }
        .table tbody td { background-color: transparent !important; color: #ccc; }

        .btn-outline-custom {
            color: var(--firefly-glow);
            border-color: var(--firefly-glow);
        }
        .btn-outline-custom:hover {
            background-color: var(--firefly-glow);
            color: #000;
        }

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
        <span class="small fw-bold d-none d-sm-inline" style="color: rgba(255,255,255,0.6);">
            Core Status: <i class="bi bi-circle-fill" style="color: var(--firefly-glow); font-size: 10px; box-shadow: 0 0 8px var(--firefly-glow); border-radius: 50%;"></i> Online
        </span>
        <span class="small fw-bold border-start border-secondary ps-3" style="color: var(--star-gold);">Commander <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fs-4 fw-bold mb-0 ud-title" style="border: none;">Realm Statistics</h1>
            <button class="btn btn-sm btn-outline-custom"><i class="bi bi-download me-1"></i> Extract Data</button>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="small fw-bold mb-1" style="color: rgba(255,255,255,0.5);">Total Citizens</p>
                        <h3 class="fw-bolder mb-0" style="color: #fff;"><?= number_format($totalUsers) ?></h3>
                    </div>
                    <div class="icon-box"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-bottom: 3px solid var(--firefly-glow);">
                    <div>
                        <p class="small fw-bold mb-1" style="color: rgba(255,255,255,0.5);">Active Explorers</p>
                        <h3 class="fw-bolder mb-0" style="color: var(--firefly-glow); text-shadow: 0 0 10px rgba(182, 255, 146, 0.4);"><?= number_format($activeUsers) ?></h3>
                    </div>
                    <div class="icon-box"><i class="bi bi-activity"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="small fw-bold mb-1" style="color: rgba(255,255,255,0.5);">Mapped Nodes</p>
                        <h3 class="fw-bolder mb-0" style="color: #fff;"><?= number_format($totalNodes) ?></h3>
                    </div>
                    <div class="icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="small fw-bold mb-1" style="color: rgba(255,255,255,0.5);">Events Today</p>
                        <h3 class="fw-bolder mb-0" style="color: #fff;"><?= number_format($totalEvents) ?></h3>
                    </div>
                    <div class="icon-box"><i class="bi bi-calendar-event-fill"></i></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="content-card h-100">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        Most Traveled Territories
                        <i class="bi bi-map-fill fs-5" style="color: rgba(255,255,255,0.3);"></i>
                    </h5>
                    <div class="scrollable-list mt-3">
                        <?php if (empty($visitedLocations)): ?>
                            <p class="small text-center py-3" style="color: rgba(255,255,255,0.5);">No location data available in the archives.</p>
                        <?php else: ?>
                            <?php foreach($visitedLocations as $index => $loc): ?>
                                <div class="list-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bolder" style="color: rgba(255,255,255,0.3);">#<?= $index + 1 ?></span>
                                        <div>
                                            <div class="fw-bold" style="color: var(--firefly-glow);"><?= htmlspecialchars($loc['fullname']) ?></div>
                                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">
                                                <?= htmlspecialchars($loc['description'] ?? 'Unknown Region') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge" style="background: rgba(182, 255, 146, 0.1); color: var(--firefly-glow); border: 1px solid var(--border-glow);">
                                        <?= number_format($loc['total_searches'] ?? 0) ?> visits
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-card h-100">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        Recent Arrivals
                        <i class="bi bi-person-check-fill fs-5" style="color: rgba(255,255,255,0.3);"></i>
                    </h5>
                    <div class="scrollable-list mt-3">
                        <?php if (empty($recentLogins)): ?>
                            <p class="small text-center py-3" style="color: rgba(255,255,255,0.5);">The gates have been quiet.</p>
                        <?php else: ?>
                            <?php foreach($recentLogins as $login): ?>
                                <div class="list-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background: rgba(255, 218, 108, 0.1); color: var(--star-gold); border: 1px solid rgba(255, 218, 108, 0.3);">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-white">Entity #<?= htmlspecialchars($login['userid']) ?></div>
                                            <div style="font-size: 11px; color: rgba(255,255,255,0.5);">
                                                <?= htmlspecialchars($login['details']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="small fw-bold" style="color: rgba(255,255,255,0.4);">
                                        <?= timeAgo($login['timestmp']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="card-title">Activity Logs</h5>
            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Entity ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Network (IP)</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($recentActivities)): ?>
                            <tr><td colspan="5" class="text-center py-4" style="color: rgba(255,255,255,0.5);">No recent activities forged.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $log): ?>
                                <tr>
                                    <td class="fw-bold" style="color: var(--star-gold);">#<?= htmlspecialchars($log['userid']) ?></td>
                                    <td><span class="badge" style="background: rgba(182, 255, 146, 0.2); color: var(--firefly-glow); border: 1px solid var(--border-glow);"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td style="color: #fff;"><?= htmlspecialchars($log['details']) ?></td>
                                    <td style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($log['ipaddress']) ?></td>
                                    <td style="color: rgba(255,255,255,0.5);"><?= timeAgo($log['timestmp']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
</script>
</body>
</html>