<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['full_name'] ?? 'Admin';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterAction = isset($_GET['action']) ? trim($_GET['action']) : '';
$limit = 20;
$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$whereSql = "1=1";
$queryParams = [];
$queryTypes = "";

if ($search !== '') {
    $whereSql .= " AND (userid LIKE ? OR details LIKE ? OR ipaddress LIKE ?)";
    $wildcard = "%" . $search . "%";
    array_push($queryParams, $wildcard, $wildcard, $wildcard);
    $queryTypes .= "sss";
}

if ($filterAction !== '') {
    $whereSql .= " AND action = ?";
    $queryParams[] = $filterAction;
    $queryTypes .= "s";
}

$countQuery = $conn->prepare("SELECT COUNT(id) as Total FROM logs WHERE " . $whereSql);
if (!empty($queryParams)) { $countQuery->bind_param($queryTypes, ...$queryParams); }
$countQuery->execute();
$totalRecords = $countQuery->get_result()->fetch_assoc()['Total'] ?? 0;
$totalPages = ceil($totalRecords / $limit);

$dataQuery = $conn->prepare("SELECT * FROM logs WHERE " . $whereSql . " ORDER BY timestmp DESC LIMIT ? OFFSET ?");
$dataParams = $queryParams; $dataParams[] = $limit; $dataParams[] = $offset; $dataTypes = $queryTypes . "ii";

if (!empty($dataParams)) { $dataQuery->bind_param($dataTypes, ...$dataParams); }
$dataQuery->execute();
$dataResult = $dataQuery->get_result();

$logs = [];
while ($row = $dataResult->fetch_assoc()) { $logs[] = $row; }

$actionsQuery = $conn->query("SELECT DISTINCT action FROM logs WHERE action IS NOT NULL AND action != ''");
$availableActions = [];
while ($row = $actionsQuery->fetch_assoc()) { $availableActions[] = $row['action']; }

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

        body { font-family: 'Nunito', sans-serif; background: var(--bg-dark); color: #fff; display: flex; flex-direction: column; min-height: 100vh; }
        body::before { content: ""; position: fixed; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 50% 50%, rgba(107, 255, 216, 0.03) 0%, transparent 60%); z-index: -1; pointer-events: none; }
        
        .ud-topbar { background: #000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 1001; border-bottom: 1px solid var(--star-gold); box-shadow: 0 0 15px rgba(255, 218, 108, 0.2); }
        .ud-title { font-family: 'Cinzel', serif; color: var(--star-gold); letter-spacing: 2px; text-shadow: 0 0 10px rgba(255, 218, 108, 0.5); }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 25px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        
        .content-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; letter-spacing: 1px; }

        /* Forms / Inputs Dark Mode */
        .form-control, .form-select { background-color: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus, .form-select:focus { background-color: rgba(0,0,0,0.8); border-color: var(--star-gold); color: #fff; box-shadow: 0 0 8px rgba(255, 218, 108, 0.3); }
        .form-control::placeholder { color: rgba(255,255,255,0.4); }
        option { background-color: #011a10; color: #fff; }

        /* Buttons */
        .btn-outline-custom { color: var(--firefly-glow); border-color: var(--firefly-glow); }
        .btn-outline-custom:hover { background-color: var(--firefly-glow); color: #000; }
        .btn-outline-danger-custom { color: #ff6b6b; border-color: #ff6b6b; }
        .btn-outline-danger-custom:hover { background-color: #ff6b6b; color: #000; }

        /* Table Overrides */
        .table { color: #fff; }
        .table-light { background-color: rgba(255,255,255,0.05) !important; color: var(--star-gold) !important; }
        .table-light th { background-color: transparent !important; color: var(--star-gold) !important; border-bottom: 1px solid var(--star-gold); }
        .table tbody tr { border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .table tbody tr:hover { background-color: rgba(182, 255, 146, 0.05); }
        .table tbody td { background-color: transparent !important; color: #ccc; }

        /* Pagination Overrides */
        .pagination { --bs-pagination-bg: transparent; --bs-pagination-border-color: var(--border-glow); --bs-pagination-color: var(--star-gold); --bs-pagination-hover-bg: rgba(255,218,108,0.1); --bs-pagination-hover-color: var(--star-gold); }
        .page-item.active .page-link { background-color: var(--firefly-glow); border-color: var(--firefly-glow); color: #000; }
        .page-item.disabled .page-link { color: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.1); }

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
            <h1 class="fs-4 fw-bold mb-0 ud-title" style="border: none;">Realm Audit Logs</h1>
            <button class="btn btn-sm btn-outline-danger-custom" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf-fill me-1"></i> Extract PDF</button>
        </div>

        <div class="content-card">
            <form method="GET" action="" class="row g-3 mb-4">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search Entity, Details, or IP..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="action" class="form-select">
                        <option value="">All Directives</option>
                        <?php foreach($availableActions as $act): ?>
                            <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-outline-custom px-4">Execute Filter</button>
                    <a href="auditlog.php" class="btn btn-outline-secondary ms-2" style="color: rgba(255,255,255,0.5); border-color: rgba(255,255,255,0.3);">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table id="auditTable" class="table table-hover align-middle">
                    <thead class="table-light small">
                        <tr>
                            <th>Log Hash</th>
                            <th>Entity ID</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Network Source</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center py-4" style="color: rgba(255,255,255,0.3);">Archives are empty.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="color: rgba(255,255,255,0.3);">#<?= $log['id'] ?></td>
                                    <td class="fw-bold text-white"><?= htmlspecialchars($log['userid'] ?? 'System') ?></td>
                                    <td><span class="badge" style="background: rgba(182, 255, 146, 0.1); color: var(--firefly-glow); border: 1px solid var(--border-glow);"><?= htmlspecialchars($log['action']) ?></span></td>
                                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #ccc;">
                                        <?= htmlspecialchars($log['details'] ?? '-') ?>
                                    </td>
                                    <td style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($log['ipaddress'] ?? 'Unknown') ?></td>
                                    <td style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($log['timestmp']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav class="mt-4 d-flex justify-content-between align-items-center">
                <span class="small" style="color: rgba(255,255,255,0.5);">Total: <?= $totalRecords ?> logs stored</span>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="<?= buildUrl($page - 1) ?>">Prev</a></li>
                    <?php 
                    $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>"><a class="page-link" href="<?= buildUrl($i) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>"><a class="page-link" href="<?= buildUrl($page + 1) ?>">Next</a></li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
    function exportToPDF() {
        const { jsPDF } = window.jspdf; const doc = new jsPDF('l', 'mm', 'a4');
        doc.setFontSize(18); doc.text("UDMap - Command Center Audit", 14, 22);
        doc.setFontSize(10); doc.setTextColor(100); doc.text("Extracted: " + new Date().toLocaleString(), 14, 30);
        doc.autoTable({ html: '#auditTable', startY: 35, theme: 'grid', styles: { fontSize: 9, cellPadding: 4 }, headStyles: { fillColor: [1, 77, 49], textColor: 255 }, columnStyles: { 3: { cellWidth: 100 } } });
        doc.save('UDMap_AuditLogs_Page' + <?= $page ?> + '.pdf');
    }
</script>
</body>
</html>