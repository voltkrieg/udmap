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

// --- Helper function for SQL Server Time/Date Objects ---
function formatSqlDate($dateObj, $format = 'Y-m-d') {
    if ($dateObj instanceof DateTime) return $dateObj->format($format);
    return $dateObj ? date($format, strtotime($dateObj)) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $eventTitle = $_POST['eventtitle'];
        $place      = $_POST['place'];
        $date       = $_POST['date'];
        $timeStart  = $_POST['timestart'];
        $timeEnd    = $_POST['timeend'];
        $status     = $_POST['status'];

        if ($action === 'add') {
            $sql = "INSERT INTO EVENTS (EVENTTITLE, PLACE, DATE, TIMESTART, TIMEEND, STATUS, CREATED_AT) 
                    VALUES ('$eventTitle', '$place', '$date', '$timeStart', '$timeEnd', '$status', GETDATE())";
            sqlsrv_query($conn, $sql);
        } else if ($action === 'edit') {
            $eventId = (int)$_POST['eventid'];
            $sql = "UPDATE EVENTS SET EVENTTITLE='$eventTitle', PLACE='$place', DATE='$date', TIMESTART='$timeStart', TIMEEND='$timeEnd', STATUS='$status' 
                    WHERE EVENTID=?";
            sqlsrv_query($conn, $sql, );
        }
        
        // Refresh page to prevent form resubmission
        header("Location: addevents.php");
        exit();
    }
    
    if ($action === 'delete') {
        $eventId = (int)$_POST['eventid'];
        $sql = "DELETE FROM EVENTS WHERE EVENTID=?";
        sqlsrv_query($conn, $sql, [$eventId]);
        
        header("Location: addevents.php");
        exit();
    }
}

// --- Fetch Events ---
$eventsArr = [];
// Assuming EVENTID is your primary key. Change if necessary.
$eventQuery = "SELECT EVENTID, EVENTTITLE, PLACE, DATE, TIMESTART, TIMEEND, STATUS FROM EVENTS ORDER BY DATE DESC, TIMESTART DESC";
$eventRes = sqlsrv_query($conn, $eventQuery);

if ($eventRes) {
    while ($row = sqlsrv_fetch_array($eventRes, SQLSRV_FETCH_ASSOC)) {
        // Format dates and times so they inject correctly into HTML5 inputs
        $row['RAW_DATE']  = formatSqlDate($row['DATE'], 'Y-m-d');
        $row['DISP_DATE'] = formatSqlDate($row['DATE'], 'M d, Y');
        
        $row['RAW_START'] = formatSqlDate($row['TIMESTART'], 'H:i');
        $row['DISP_START']= formatSqlDate($row['TIMESTART'], 'h:i A');
        
        $row['RAW_END']   = formatSqlDate($row['TIMEEND'], 'H:i');
        $row['DISP_END']  = formatSqlDate($row['TIMEEND'], 'h:i A');

        $eventsArr[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UDMap - Manage Events</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Nunito', sans-serif; background: #f4f4f4; color: #111; display: flex; flex-direction: column; min-height: 100vh; }
        .ud-topbar { background: #111; height: 54px; display: flex; align-items: center; justify-content: space-between; padding: 0 16px; position: sticky; top: 0; z-index: 1001; color: white; border-bottom: 3px solid #2ECC40; }
        .page-wrap { display: flex; flex: 1; }
        .content-area { flex: 1; padding: 20px; margin-left: 230px; transition: margin 0.25s; }
        .sidebar.collapsed + .content-area { margin-left: 54px; }
        .info-card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn-green { background: #2ECC40; color: white; border: none; border-radius: 8px; padding: 8px 16px; font-weight: 700; }
        .btn-green:hover { background: #28a745; color: white; }
        
        .form-label { font-weight: 700; color: #555; }
        .table-wrap { overflow-x: auto; }

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
        <span class="small fw-bold border-start ps-3">Hello, <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="row mb-4">
            <!-- FORM SECTION (Left Side) -->
            <div class="col-lg-4">
                <div class="info-card h-100">
                    <h2 class="fs-5 fw-bold mb-4" id="formTitle"><i class="bi bi-calendar-plus text-success me-2"></i>Add New Event</h2>
                    <form method="POST" id="eventForm">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="eventid" id="eventId" value="">

                        <div class="mb-3">
                            <label class="form-label small">Event Title</label>
                            <input type="text" name="eventtitle" id="evTitle" class="form-control" required placeholder="e.g. University Week">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Place / Venue</label>
                            <input type="text" name="place" id="evPlace" class="form-control" required placeholder="e.g. ULS">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Date</label>
                            <input type="date" name="date" id="evDate" class="form-control" required>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small">Time Start</label>
                                <input type="time" name="timestart" id="evStart" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small">Time End</label>
                                <input type="time" name="timeend" id="evEnd" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small">Status</label>
                            <select name="status" id="evStatus" class="form-select" required>
                                <option value="Upcoming" selected>Upcoming</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-green w-100" id="submitBtn"><i class="bi bi-save me-2"></i>Save Event</button>
                            <button type="button" class="btn btn-secondary w-100" onclick="resetForm()" id="cancelBtn" style="display:none;">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TABLE SECTION (Right Side) -->
            <div class="col-lg-8">
                <div class="info-card h-100">
                    <h2 class="fs-5 fw-bold mb-3"><i class="bi bi-calendar-event text-primary me-2"></i>Scheduled Events</h2>
                    <div class="table-wrap">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Event Title</th>
                                    <th>Venue</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($eventsArr as $ev): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($ev['EVENTTITLE']) ?></td>
                                    <td><i class="bi bi-geo-alt-fill text-muted me-1"></i><?= htmlspecialchars($ev['PLACE']) ?></td>
                                    <td>
                                        <div class="fw-bold text-success small"><?= $ev['DISP_DATE'] ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            <?= $ev['DISP_START'] ?> - <?= $ev['DISP_END'] ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            if ($ev['STATUS'] === 'Upcoming') $badgeClass = 'bg-primary';
                                            if ($ev['STATUS'] === 'Ongoing') $badgeClass = 'bg-success';
                                            if ($ev['STATUS'] === 'Cancelled') $badgeClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($ev['STATUS']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <!-- Pass the JSON encoded event data to JS -->
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editEvent(<?= json_encode($ev, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="eventid" value="<?= $ev['EVENTID'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($eventsArr)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No events found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Function triggered when "Edit" button in table is clicked
    function editEvent(ev) {
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square text-primary me-2"></i>Edit Event';
        document.getElementById('formAction').value = "edit";
        document.getElementById('eventId').value = ev.EVENTID;
        
        document.getElementById('evTitle').value = ev.EVENTTITLE;
        document.getElementById('evPlace').value = ev.PLACE;
        document.getElementById('evDate').value = ev.RAW_DATE;
        document.getElementById('evStart').value = ev.RAW_START;
        document.getElementById('evEnd').value = ev.RAW_END;
        document.getElementById('evStatus').value = ev.STATUS;
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Update Event';
        document.getElementById('submitBtn').classList.replace('btn-green', 'btn-primary');
        document.getElementById('cancelBtn').style.display = 'block';

        // Scroll up to form smoothly (helpful on mobile)
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Function to clear form and revert to "Add" mode
    function resetForm() {
        document.getElementById('eventForm').reset();
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-calendar-plus text-success me-2"></i>Add New Event';
        document.getElementById('formAction').value = "add";
        document.getElementById('eventId').value = "";
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Save Event';
        document.getElementById('submitBtn').classList.replace('btn-primary', 'btn-green');
        document.getElementById('cancelBtn').style.display = 'none';
    }

    function toggleSidebar() { 
        let sb = document.getElementById('sidebar');
        if(sb) sb.classList.toggle('collapsed'); 
    }
</script>
</body>
</html>