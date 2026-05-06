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

$name = $_SESSION['full_name'] ?? 'Admin'; // Changed to full_name based on previous login code
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterAction = isset($_GET['action']) ? trim($_GET['action']) : '';
$limit = 20;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$whereSql = "1=1";
$queryParams = [];

// Search across UserID, Details, or IP
if ($search !== '') {
    $whereSql .= " AND (USERID LIKE ? OR DETAILS LIKE ? OR IPADD LIKE ?)";
    $wildcard = "%" . $search . "%";
    array_push($queryParams, $wildcard, $wildcard, $wildcard);
}

if ($filterAction !== '') {
    $whereSql .= " AND ACTION = ?";
    $queryParams[] = $filterAction;
}

$countQuery = "SELECT COUNT(LOGID) as Total FROM [USERLOGS] WHERE " . $whereSql;
$countStmt = sqlsrv_query($conn, $countQuery, $queryParams);
$totalRecords = 0;
if ($countStmt && $row = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC)) {
    $totalRecords = $row['Total'];
}
$totalPages = ceil($totalRecords / $limit);

$dataQuery = "SELECT * FROM [USERLOGS] WHERE " . $whereSql . " ORDER BY TIMESTMP DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$dataParams = array_merge($queryParams, [$offset, $limit]);
$dataStmt = sqlsrv_query($conn, $dataQuery, $dataParams);

$logs = [];
if ($dataStmt) {
    while ($row = sqlsrv_fetch_array($dataStmt, SQLSRV_FETCH_ASSOC)) {
        $logs[] = $row;
    }
}

$actionsQuery = sqlsrv_query($conn, "SELECT DISTINCT ACTION FROM [USERLOGS] WHERE ACTION IS NOT NULL");
$availableActions = [];
if ($actionsQuery) {
    while ($row = sqlsrv_fetch_array($actionsQuery, SQLSRV_FETCH_ASSOC)) {
        $availableActions[] = $row['ACTION'];
    }
}

function buildUrl($targetPage) {
    global $search, $filterAction;
    $params = ['page' => $targetPage];
    if ($search !== '') $params['search'] = $search;
    if ($filterAction !== '') $params['action'] = $filterAction;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; min-height: 100vh; }
        .ud-topbar { background: #111; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; border-bottom: 3px solid #2ECC40; }
        .page-wrap { display: flex; }
        .content-area { flex: 1; padding: 20px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .content-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .btn-green { background: #2ECC40; color: white; border: none; font-weight: 700; }
        .btn-green:hover { background: #25a834; color: white; }
        .table th { font-weight: 800; color: #666; text-transform: uppercase; font-size: 12px; }
        .badge-action { background: #eafbec; color: #2ECC40; border: 1px solid #d4f5d8; font-weight: 700; }
        @media(max-width: 960px) { .content-area { margin-left: 0; } }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
        <span class="fw-bolder fs-5 text-success">UDMap <span class="text-white">Admin</span></span>
    </div>
    <span class="small fw-bold">Hello, <?= htmlspecialchars($name) ?></span>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <h1 class="fs-4 fw-bold mb-4">User Activity Audit Logs</h1>

        <div class="content-card">
            <!-- Search and Filter Form -->
            <form method="GET" action="" class="row g-3 mb-4">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search UserID, Details, or IP..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php foreach($availableActions as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                                <?= htmlspecialchars($act) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-green px-4">Apply Filters</button>
                    <a href="audit_logs.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No audit records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">#<?= $log['LOGID'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($log['USERID'] ?? 'Guest/System') ?></td>
                                    <td><span class="badge badge-action rounded-pill px-3"><?= htmlspecialchars($log['ACTION']) ?></span></td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($log['DETAILS'] ?? '-') ?>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($log['IPADD'] ?? '0.0.0.0') ?></td>
                                    <td>
                                        <?= ($log['TIMESTMP'] instanceof DateTime) ? $log['TIMESTMP']->format('Y-m-d H:i:s') : $log['TIMESTMP'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-4 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Total: <?= $totalRecords ?> logs</span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildUrl($page - 1) ?>">Prev</a>
                    </li>
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= buildUrl($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= buildUrl($page + 1) ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() { 
        document.getElementById('sidebar').classList.toggle('collapsed'); 
    }
</script>
</body>
</html>