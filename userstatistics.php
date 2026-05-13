<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['full_name'] ?? 'Admin';

function getScalar($conn, $sql) {
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_row()) { return $row[0]; }
    return 0;
}

function getChartData($conn, $sql) {
    $data = ['labels' => [], 'values' => []];
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_row()) {
            $data['labels'][] = $row[0] ?: 'Unknown/None';
            $data['values'][] = $row[1];
        }
    }
    return $data;
}

$totalUsers = getScalar($conn, "SELECT COUNT(*) FROM users");
$verifiedUsers = getScalar($conn, "SELECT COUNT(*) FROM users WHERE isverified = '1'");
$unverifiedUsers = $totalUsers - $verifiedUsers;
$activeLast7Days = getScalar($conn, "SELECT COUNT(*) FROM users WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// Updated to use 'role' instead of 'usertype'
$roleData = getChartData($conn, "SELECT role, COUNT(*) FROM users GROUP BY role");
$courseData = getChartData($conn, "SELECT course, COUNT(*) FROM users WHERE course IS NOT NULL AND course != '' GROUP BY course");
$yearData = getChartData($conn, "SELECT year, COUNT(*) FROM users WHERE year IS NOT NULL AND year != '' GROUP BY year ORDER BY year");

$usersList = [];
// Removed 'usertype' from the SELECT list
$listRes = $conn->query("SELECT id, studentid, firstname, lastname, email, role, course, year, isverified, last_activity FROM users ORDER BY last_activity DESC, id DESC");

if ($listRes) {
    while ($row = $listRes->fetch_assoc()) {
        $usersList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - System Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

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
        
        .stat-card, .content-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .stat-card { border-left: 4px solid var(--star-gold); display: flex; flex-direction: column; justify-content: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 25px rgba(182, 255, 146, 0.2); }
        .stat-card.glow-green { border-left-color: var(--firefly-glow); }
        .stat-card.glow-red { border-left-color: #ff6b6b; }
        
        .stat-number { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1.2; text-shadow: 0 0 8px rgba(255,255,255,0.3); }
        .stat-label { color: rgba(255,255,255,0.6); font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        
        .card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; letter-spacing: 1px; }
        .chart-container { height: 300px; }

        /* Table Overrides */
        .table { color: #fff; }
        .table-light { background-color: rgba(255,255,255,0.05) !important; color: var(--star-gold) !important; }
        .table-light th { background-color: transparent !important; color: var(--star-gold) !important; border-bottom: 1px solid var(--star-gold); }
        .table tbody tr { border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .table tbody tr:hover { background-color: rgba(182, 255, 146, 0.05); }
        .table tbody td { background-color: transparent !important; color: #ccc; }
        
        /* DataTables Dark Mode Fixes */
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { color: #ccc !important; margin-bottom: 10px; }
        .dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select { background-color: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; border-radius: 5px; padding: 4px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { color: var(--star-gold) !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: var(--gradient-btn) !important; border-color: var(--firefly-glow) !important; color: #fff !important; }
        
        .btn-outline-custom { color: var(--firefly-glow); border-color: var(--firefly-glow); }
        .btn-outline-custom:hover { background-color: var(--firefly-glow); color: #000; }
        
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
        <span class="small fw-bold d-none d-sm-inline" style="color: rgba(255,255,255,0.6);">Core Status: <i class="bi bi-circle-fill" style="color: var(--firefly-glow); font-size: 10px; box-shadow: 0 0 8px var(--firefly-glow);"></i> Online</span>
        <span class="small fw-bold border-start border-secondary ps-3" style="color: var(--star-gold);">Commander <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fs-4 fw-bold mb-0 ud-title" style="border: none;">System Analytics</h1>
            <button class="btn btn-sm btn-outline-custom" onclick="window.location.reload();"><i class="bi bi-arrow-clockwise me-1"></i>Refresh Data</button>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Total Registered</div>
                    <div class="stat-number"><?= number_format($totalUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glow-green">
                    <div class="stat-label" style="color: var(--firefly-glow);">Verified Citizens</div>
                    <div class="stat-number"><?= number_format($verifiedUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card glow-red">
                    <div class="stat-label" style="color: #ff6b6b;">Unverified Entities</div>
                    <div class="stat-number"><?= number_format($unverifiedUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-label">Active (7 Days)</div>
                    <div class="stat-number"><?= number_format($activeLast7Days) ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="card-title">Demographics (By Role)</h5>
                    <div class="chart-container"><canvas id="roleChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="card-title">By Territory (Course)</h5>
                    <div class="chart-container"><canvas id="courseChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card">
                    <h5 class="card-title">By Experience (Year)</h5>
                    <div class="chart-container"><canvas id="yearChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="card-title">Complete Citizen Directory</h5>
            <div class="table-responsive mt-3">
                <table id="usersTable" class="table table-hover align-middle">
                    <thead class="table-light small">
                        <tr>
                            <th>Entity</th>
                            <th>ID Code</th>
                            <th>Role</th>
                            <th>Academic Sector</th>
                            <th>Status</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php foreach($usersList as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) ?></div>
                                    <div style="font-size: 11px; color: rgba(255,255,255,0.5);"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td style="color: rgba(255,255,255,0.7);"><?= htmlspecialchars($u['studentid'] ?: 'N/A') ?></td>
                                <td>
                                    <span class="badge text-uppercase" style="background: rgba(255, 218, 108, 0.1); color: var(--star-gold); border: 1px solid rgba(255, 218, 108, 0.3);">
                                        <?= htmlspecialchars($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($u['course']): ?>
                                        <div style="color: var(--firefly-glow);"><?= htmlspecialchars($u['course']) ?></div>
                                        <div style="font-size: 11px; color: rgba(255,255,255,0.5);">Year <?= htmlspecialchars($u['year']) ?></div>
                                    <?php else: ?>
                                        <span style="color: rgba(255,255,255,0.3);">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($u['isverified'] == '1'): ?>
                                        <span style="color: var(--firefly-glow);"><i class="bi bi-check-circle-fill me-1"></i>Verified</span>
                                    <?php else: ?>
                                        <span style="color: #ff6b6b;"><i class="bi bi-x-circle-fill me-1"></i>Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: rgba(255,255,255,0.5);">
                                    <?= $u['last_activity'] ? htmlspecialchars($u['last_activity']) : 'Unknown' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            pageLength: 10,
            language: { search: "", searchPlaceholder: "Query Database..." },
            order: [[5, 'desc']]
        });
    });

    const roleData = <?= json_encode($roleData) ?>;
    const courseData = <?= json_encode($courseData) ?>;
    const yearData = <?= json_encode($yearData) ?>;

    Chart.defaults.color = 'rgba(255, 255, 255, 0.6)';
    Chart.defaults.font.family = "'Nunito', sans-serif";
    const chartColors = ['#ffda6c', '#b6ff92', '#ff6b6b', '#4ecdc4', '#c7f464'];

    new Chart(document.getElementById('roleChart'), {
        type: 'doughnut',
        data: { labels: roleData.labels, datasets: [{ data: roleData.values, backgroundColor: chartColors, borderColor: 'rgba(2, 36, 21, 1)', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('courseChart'), {
        type: 'bar',
        data: { labels: courseData.labels, datasets: [{ data: courseData.values, backgroundColor: 'rgba(182, 255, 146, 0.5)', borderColor: '#b6ff92', borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { grid: { color: 'rgba(255,255,255,0.1)' } }, x: { grid: { display: false } } } }
    });

    new Chart(document.getElementById('yearChart'), {
        type: 'bar', 
        data: { labels: yearData.labels.map(y => 'Yr ' + y), datasets: [{ data: yearData.values, backgroundColor: 'rgba(255, 218, 108, 0.5)', borderColor: '#ffda6c', borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { grid: { color: 'rgba(255,255,255,0.1)' } }, y: { grid: { display: false } } } }
    });

    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
</script>
</body>
</html>