<?php
session_start();

$server = "DESKTOP-KILKG9D\SQLEXPRESS";
$opts = ["Database" => "UDMapDB", "Uid" => "", "PWD" => "", "Encrypt" => true, "TrustServerCertificate" => true];
$conn = sqlsrv_connect($server, $opts);

if (!$conn) { die("Database error"); }

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['full_name'] ?? 'Admin'; // Adjusted to use full_name

// Overview Stats
$userQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Total FROM [USER]");
$totalUsers = $userQuery ? sqlsrv_fetch_array($userQuery)['Total'] : 0;

$activeQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Active FROM [USER] WHERE ISACTIVE = 1");
$activeUsers = $activeQuery ? sqlsrv_fetch_array($activeQuery)['Active'] : 0;

$nodeQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Nodes FROM LOCATIONS");
$totalNodes = $nodeQuery ? sqlsrv_fetch_array($nodeQuery)['Nodes'] : 0;

$eventQuery = sqlsrv_query($conn, "SELECT COUNT(*) as TodayEvents FROM EVENTS WHERE CAST(DATE AS DATE) = CAST(GETDATE() AS DATE)");
$totalEvents = $eventQuery ? sqlsrv_fetch_array($eventQuery)['TodayEvents'] : 0;


// --- NEW: Scrollable List Queries ---

// 1. Most Visited Locations
$visitQuery = sqlsrv_query($conn, "SELECT TOP 50 NAME, LONGNAME, TOTAL_SEARCHES FROM LOCATIONS ORDER BY TOTAL_SEARCHES DESC");
$visitedLocations = [];
if ($visitQuery) {
    while ($row = sqlsrv_fetch_array($visitQuery, SQLSRV_FETCH_ASSOC)) {
        $visitedLocations[] = $row;
    }
}

// 2. Latest Logins (Using the USERLOGS audit table)
$loginQuery = sqlsrv_query($conn, "SELECT TOP 50 USERID, DETAILS, TIMESTMP FROM [USERLOGS] WHERE ACTION = 'LOGIN_SUCCESS' ORDER BY TIMESTMP DESC");
$recentLogins = [];
if ($loginQuery) {
    while ($row = sqlsrv_fetch_array($loginQuery, SQLSRV_FETCH_ASSOC)) {
        $recentLogins[] = $row;
    }
}

// 3. Routing Activity
$activityQuery = sqlsrv_query($conn, "SELECT TOP 5 USER_ID, ACTION, DESTINATION, METHOD, TIMESTAMP FROM ACTIVITY_LOGS ORDER BY TIMESTAMP DESC");
$recentActivities = [];
if ($activityQuery) {
    while ($row = sqlsrv_fetch_array($activityQuery, SQLSRV_FETCH_ASSOC)) {
        $recentActivities[] = $row;
    }
}

function timeAgo($datetime) {
    if (!$datetime) return '';
    $time = is_string($datetime) ? strtotime($datetime) : $datetime->getTimestamp();
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
    <title>UDMap - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; display: flex; flex-direction: column; min-height: 100vh; }
        .ud-topbar { background: #111; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; border-bottom: 3px solid #2ECC40; }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 20px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .stat-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; }
        .stat-card .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; }
        .bg-green { background: #2ECC40; }
        .bg-blue { background: #007bff; }
        .bg-purple { background: #6f42c1; }
        .bg-orange { background: #fd7e14; }
        
        .content-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: 100%; }
        
        /* Custom Scrollable List Styles */
        .scrollable-list { max-height: 350px; overflow-y: auto; padding-right: 8px; }
        .scrollable-list::-webkit-scrollbar { width: 6px; }
        .scrollable-list::-webkit-scrollbar-thumb { background-color: #ddd; border-radius: 10px; }
        .scrollable-list::-webkit-scrollbar-track { background: transparent; }
        
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f0f0f0; }
        .list-item:last-child { border-bottom: none; }
        
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
        <span class="small fw-bold d-none d-sm-inline">System Status: <i class="bi bi-circle-fill text-success" style="font-size: 10px;"></i> Online</span>
        <span class="small fw-bold border-start ps-3">Hello, <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fs-4 fw-bold mb-0">Overview Statistics</h1>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i> Export Report</button>
        </div>

        <!-- 1. Top Stat Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="text-muted small fw-bold mb-1">Total Users</p>
                        <h3 class="fw-bolder mb-0"><?= number_format($totalUsers) ?></h3>
                    </div>
                    <div class="icon-box bg-blue"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card border-bottom border-success border-4">
                    <div>
                        <p class="text-muted small fw-bold mb-1">Active Now</p>
                        <h3 class="fw-bolder mb-0 text-success"><?= number_format($activeUsers) ?></h3>
                    </div>
                    <div class="icon-box bg-green"><i class="bi bi-activity"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="text-muted small fw-bold mb-1">Registered Nodes</p>
                        <h3 class="fw-bolder mb-0"><?= number_format($totalNodes) ?></h3>
                    </div>
                    <div class="icon-box bg-purple"><i class="bi bi-geo-alt-fill"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div>
                        <p class="text-muted small fw-bold mb-1">Events Today</p>
                        <h3 class="fw-bolder mb-0"><?= number_format($totalEvents) ?></h3>
                    </div>
                    <div class="icon-box bg-orange"><i class="bi bi-calendar-event-fill"></i></div>
                </div>
            </div>
        </div>

        <!-- 2. Scrollable Lists Row -->
        <div class="row g-4 mb-4">
            <!-- Most Visited Locations -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                        Most Visited Buildings
                        <i class="bi bi-geo-fill text-muted fs-5"></i>
                    </h5>
                    <div class="scrollable-list">
                        <?php if (empty($visitedLocations)): ?>
                            <p class="text-muted small py-3">No location data available.</p>
                        <?php else: ?>
                            <?php foreach($visitedLocations as $index => $loc): ?>
                                <div class="list-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="fw-bolder text-muted">#<?= $index + 1 ?></span>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($loc['NAME']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?= htmlspecialchars($loc['LONGNAME'] ?? 'Campus Location') ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2">
                                        <?= number_format($loc['TOTAL_SEARCHES'] ?? 0) ?> visits
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Latest Logins -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                        Recent User Logins
                        <i class="bi bi-person-check-fill text-muted fs-5"></i>
                    </h5>
                    <div class="scrollable-list">
                        <?php if (empty($recentLogins)): ?>
                            <p class="text-muted small py-3">No recent logins found.</p>
                        <?php else: ?>
                            <?php foreach($recentLogins as $login): ?>
                                <div class="list-item">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold">User #<?= htmlspecialchars($login['USERID']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?= htmlspecialchars($login['DETAILS']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="text-muted small fw-bold">
                                        <?= timeAgo($login['TIMESTMP']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Recent Activity Table -->
        <div class="content-card">
            <h5 class="fw-bold mb-3">Recent Routing Activity</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light text-muted small">
                        <tr>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Destination</th>
                            <th>Method</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($recentActivities)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">No recent activity found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentActivities as $log): ?>
                                <tr>
                                    <td class="fw-bold">#<?= htmlspecialchars($log['USER_ID']) ?></td>
                                    <td><?= htmlspecialchars($log['ACTION']) ?></td>
                                    <td><?= htmlspecialchars($log['DESTINATION']) ?></td>
                                    <td>
                                        <?php if(strtolower($log['METHOD']) == 'driving'): ?>
                                            <span class="badge bg-success">Driving</span>
                                        <?php elseif(strtolower($log['METHOD']) == 'walking'): ?>
                                            <span class="badge bg-primary">Walking</span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= timeAgo($log['TIMESTAMP']) ?></td>
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