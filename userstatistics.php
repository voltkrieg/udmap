<?php
session_start();

// --- Database Connection ---
$server = "DESKTOP-KILKG9D\SQLEXPRESS";
$opts = [
    "Database" => "UDMapDB", 
    "Uid" => "", 
    "PWD" => "", 
    "Encrypt" => true, 
    "TrustServerCertificate" => true
];
$conn = sqlsrv_connect($server, $opts);

if (!$conn) { die("Database error: " . print_r(sqlsrv_errors(), true)); }

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['first_name'] ?? 'Admin';

// ==========================================
// 1. FETCH AGGREGATED STATISTICS
// ==========================================

function getScalar($conn, $sql) {
    $res = sqlsrv_query($conn, $sql);
    if ($res && $row = sqlsrv_fetch_array($res, SQLSRV_FETCH_NUMERIC)) {
        return $row[0];
    }
    return 0;
}

function getChartData($conn, $sql) {
    $data = ['labels' => [], 'values' => []];
    $res = sqlsrv_query($conn, $sql);
    if ($res) {
        while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_NUMERIC)) {
            $data['labels'][] = $row[0] ?: 'Unknown/None';
            $data['values'][] = $row[1];
        }
    }
    return $data;
}

// --- Relevant Quick Stats ---
$totalUsers = getScalar($conn, "SELECT COUNT(*) FROM [USER]");
$verifiedUsers = getScalar($conn, "SELECT COUNT(*) FROM [USER] WHERE IS_VERIFIED = 1");
$unverifiedUsers = $totalUsers - $verifiedUsers; // More relevant: tells admin who needs verification
$activeLast7Days = getScalar($conn, "SELECT COUNT(*) FROM [USER] WHERE LAST_ACTIVITY >= DATEADD(day, -7, GETDATE())");

// --- Chart Data ---
$userTypeData = getChartData($conn, "SELECT USERTYPE, COUNT(*) FROM [USER] GROUP BY USERTYPE");
$roleData = getChartData($conn, "SELECT ROLE, COUNT(*) FROM [USER] GROUP BY ROLE");
$courseData = getChartData($conn, "SELECT COURSE, COUNT(*) FROM [USER] WHERE COURSE IS NOT NULL AND COURSE != '' GROUP BY COURSE");
$yearData = getChartData($conn, "SELECT YEAR, COUNT(*) FROM [USER] WHERE YEAR IS NOT NULL AND YEAR != '' GROUP BY YEAR ORDER BY YEAR");

// ==========================================
// 2. FETCH ALL USERS FOR DIRECTORY
// ==========================================
$usersList = [];
$listSql = "SELECT USERID, STUDENTID, FIRSTNAME, LASTNAME, EMAIL, USERTYPE, ROLE, COURSE, YEAR, IS_VERIFIED, LAST_ACTIVITY 
            FROM [USER] 
            ORDER BY LAST_ACTIVITY DESC, USERID DESC";
$listRes = sqlsrv_query($conn, $listSql);

if ($listRes) {
    while ($row = sqlsrv_fetch_array($listRes, SQLSRV_FETCH_ASSOC)) {
        $usersList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - User Statistics & Directory</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS for the interactive table -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; display: flex; flex-direction: column; min-height: 100vh; }
        .ud-topbar { background: #111; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; border-bottom: 3px solid #2ECC40; }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 24px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .stat-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); height: 100%; border-left: 5px solid #2ECC40; display: flex; flex-direction: column; justify-content: center; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
        .stat-number { font-size: 2rem; font-weight: 800; color: #333; line-height: 1.2; }
        .stat-label { color: #6c757d; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .content-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px; position: relative; }
        .chart-container { height: 350px; }
        .card-title { font-weight: 800; color: #333; margin-bottom: 20px; font-size: 1.15rem; }

        .table-avatar { width: 35px; height: 35px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #555; }
        
        @media(max-width: 960px) { .content-area { margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <span class="fw-bolder fs-5">UDMap Admin</span>
    </div>
    <span class="small fw-bold">Hello, <?= htmlspecialchars($name) ?></span>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fs-4 fw-bold mb-0">System Analytics & Users</h1>
            <button class="btn btn-sm btn-outline-success" onclick="window.location.reload();"><i class="bi bi-arrow-clockwise me-1"></i>Refresh Data</button>
        </div>

        <!-- 1. Quick Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-label text-success">Total Registered</div>
                    <div class="stat-number"><?= number_format($totalUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card info">
                    <div class="stat-label text-info">Verified Accounts</div>
                    <div class="stat-number"><?= number_format($verifiedUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card danger">
                    <div class="stat-label text-danger">Unverified / Pending</div>
                    <div class="stat-number"><?= number_format($unverifiedUsers) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card warning">
                    <div class="stat-label text-warning">Active (Last 7 Days)</div>
                    <div class="stat-number"><?= number_format($activeLast7Days) ?></div>
                </div>
            </div>
        </div>

        <!-- 2. Charts Row 1 -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="content-card chart-container">
                    <div class="card-title"><i class="bi bi-people-fill text-primary me-2"></i>User Demographics</div>
                    <canvas id="userTypeChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="content-card chart-container">
                    <div class="card-title"><i class="bi bi-shield-lock-fill text-success me-2"></i>Account Roles</div>
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 3. Charts Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="content-card chart-container">
                    <div class="card-title"><i class="bi bi-book-fill text-warning me-2"></i>Students by Course</div>
                    <canvas id="courseChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="content-card chart-container">
                    <div class="card-title"><i class="bi bi-mortarboard-fill text-danger me-2"></i>Students by Year</div>
                    <canvas id="yearChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 4. ALL USERS DIRECTORY TABLE -->
        <div class="content-card">
            <div class="card-title"><i class="bi bi-person-lines-fill text-secondary me-2"></i>Complete User Directory</div>
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover align-middle border-top" style="width:100%">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>User</th>
                            <th>Student ID</th>
                            <th>Role / Type</th>
                            <th>Academic Info</th>
                            <th>Status</th>
                            <th>Last Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usersList as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="table-avatar">
                                            <?= strtoupper(substr($u['FIRSTNAME'], 0, 1) . substr($u['LASTNAME'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($u['FIRSTNAME'] . ' ' . $u['LASTNAME']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($u['EMAIL']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($u['STUDENTID'] ?: 'N/A') ?></td>
                                <td>
                                    <span class="badge bg-<?= strtolower($u['ROLE']) == 'admin' ? 'danger' : 'primary' ?> mb-1">
                                        <?= htmlspecialchars($u['ROLE']) ?>
                                    </span>
                                    <div class="small text-muted"><?= htmlspecialchars($u['USERTYPE'] ?: 'Unknown') ?></div>
                                </td>
                                <td>
                                    <?php if($u['COURSE']): ?>
                                        <div class="fw-bold"><?= htmlspecialchars($u['COURSE']) ?></div>
                                        <div class="text-muted small">Year <?= htmlspecialchars($u['YEAR']) ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($u['IS_VERIFIED']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">Verified</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php 
                                        if($u['LAST_ACTIVITY'] instanceof DateTime) {
                                            echo $u['LAST_ACTIVITY']->format('M d, Y h:i A');
                                        } else {
                                            echo 'Never';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    // Initialize DataTables (Fixed Syntax)
    $(document).ready(function() {
        $('#usersTable').DataTable({
            pageLength: 10,
            language: { search: "", searchPlaceholder: "Search users..." },
            order: [[5, 'desc']] // Order by Last Active by default
        });
    });

    // Chart Data Parsing
    const userTypeData = <?= json_encode($userTypeData) ?>;
    const roleData = <?= json_encode($roleData) ?>;
    const courseData = <?= json_encode($courseData) ?>;
    const yearData = <?= json_encode($yearData) ?>;

    Chart.defaults.font.family = "'Nunito', sans-serif";
    Chart.defaults.color = '#666';

    const defaultColors = [
        'rgba(46, 204, 64, 0.8)',  // Green
        'rgba(13, 110, 253, 0.8)', // Blue
        'rgba(253, 126, 20, 0.8)', // Orange
        'rgba(111, 66, 193, 0.8)', // Purple
        'rgba(220, 53, 69, 0.8)'   // Red
    ];

    // User Type Chart
    new Chart(document.getElementById('userTypeChart'), {
        type: 'doughnut',
        data: {
            labels: userTypeData.labels,
            datasets: [{ data: userTypeData.values, backgroundColor: defaultColors, borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    // Role Chart
    new Chart(document.getElementById('roleChart'), {
        type: 'pie',
        data: {
            labels: roleData.labels,
            datasets: [{ data: roleData.values, backgroundColor: ['rgba(13, 110, 253, 0.8)', 'rgba(220, 53, 69, 0.8)'], borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });

    // Course Chart
    new Chart(document.getElementById('courseChart'), {
        type: 'bar',
        data: {
            labels: courseData.labels,
            datasets: [{
                label: 'Students',
                data: courseData.values,
                backgroundColor: 'rgba(253, 126, 20, 0.7)',
                borderColor: 'rgba(253, 126, 20, 1)',
                borderWidth: 1, borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
    });

    // Year Chart
    new Chart(document.getElementById('yearChart'), {
        type: 'bar', 
        data: {
            labels: yearData.labels.map(y => 'Year ' + y),
            datasets: [{
                label: 'Students',
                data: yearData.values,
                backgroundColor: 'rgba(111, 66, 193, 0.7)',
                borderColor: 'rgba(111, 66, 193, 1)',
                borderWidth: 1, borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
    });

    function toggleSidebar() { 
        let sb = document.getElementById('sidebar');
        if(sb) sb.classList.toggle('collapsed'); 
    }
</script>
</body>
</html>