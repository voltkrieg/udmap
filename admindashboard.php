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

$name = $_SESSION['first_name'] ?? 'Admin';

$userQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Total FROM [USER]");
$totalUsers = $userQuery ? sqlsrv_fetch_array($userQuery)['Total'] : 0;

$activeQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Active FROM [USER] WHERE LAST_ACTIVITY >= DATEADD(minute, -30, GETDATE())");
$activeUsers = $activeQuery ? sqlsrv_fetch_array($activeQuery)['Active'] : 0;

$nodeQuery = sqlsrv_query($conn, "SELECT COUNT(*) as Nodes FROM LOCATIONS");
$totalNodes = $nodeQuery ? sqlsrv_fetch_array($nodeQuery)['Nodes'] : 0;

$eventQuery = sqlsrv_query($conn, "SELECT COUNT(*) as TodayEvents FROM EVENTS WHERE CAST(DATE AS DATE) = CAST(GETDATE() AS DATE)");
$totalEvents = $eventQuery ? sqlsrv_fetch_array($eventQuery)['TodayEvents'] : 0;


$visitQuery = sqlsrv_query($conn, "SELECT TOP 5 NAME, TOTAL_SEARCHES FROM LOCATIONS ORDER BY TOTAL_SEARCHES DESC");
$vLabels = [];
$vData = [];
if ($visitQuery) {
    while ($row = sqlsrv_fetch_array($visitQuery, SQLSRV_FETCH_ASSOC)) {
        $vLabels[] = $row['NAME'];
        $vData[] = $row['TOTAL_SEARCHES'];
    }
}
$visitLabels = json_encode($vLabels);
$visitData = json_encode($vData);

$loginQuery = sqlsrv_query($conn, "
    SELECT CAST(LOGIN_TIME AS DATE) as LogDate, COUNT(*) as DailyCount 
    FROM USER_LOGINS 
    WHERE LOGIN_TIME >= DATEADD(day, -7, GETDATE()) 
    GROUP BY CAST(LOGIN_TIME AS DATE) 
    ORDER BY LogDate ASC
");

$lLabels = [];
$lData = [];
if ($loginQuery) {
    while ($row = sqlsrv_fetch_array($loginQuery, SQLSRV_FETCH_ASSOC)) {
        $lLabels[] = $row['LogDate']->format('D'); // e.g., 'Mon', 'Tue'
        $lData[] = $row['DailyCount'];
    }
}
$loginsLabels = json_encode(empty($lLabels) ? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] : $lLabels);
$loginsData = json_encode(empty($lData) ? [0, 0, 0, 0, 0, 0, 0] : $lData);

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
        
        .chart-container { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: 100%; }
        
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
                        <p class="text-muted small fw-bold mb-1">Registered Map Nodes</p>
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

        <!-- 2. Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-md-7">
                <div class="chart-container">
                    <h5 class="fw-bold mb-4">Most Visited / Searched Buildings</h5>
                    <canvas id="visitsChart" height="100"></canvas>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="chart-container">
                    <h5 class="fw-bold mb-4">User Logins (Last 7 Days)</h5>
                    <canvas id="loginsChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- 3. Recent Activity Table -->
        <div class="chart-container">
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js for Admin Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }

    const visitsLabels = <?= $visitLabels ?>;
    const visitsData = <?= $visitData ?>;

    const ctxVisits = document.getElementById('visitsChart').getContext('2d');
    new Chart(ctxVisits, {
        type: 'bar',
        data: {
            labels: visitsLabels,
            datasets: [{
                label: 'Total Visits/Routes',
                data: visitsData,
                backgroundColor: '#2ECC40',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    const loginsLabels = <?= $loginsLabels ?>;
    const loginsData = <?= $loginsData ?>;

    const ctxLogins = document.getElementById('loginsChart').getContext('2d');
    new Chart(ctxLogins, {
        type: 'line',
        data: {
            labels: loginsLabels,
            datasets: [{
                label: 'Active Logins',
                data: loginsData,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
</body>
</html>