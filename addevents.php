<?php
session_start();
require_once 'db.php';

// Enforce admin check based on your dashboard logic
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$name = $_SESSION['firstname'] ?? 'Admin';

// --- Fetch Locations for Dropdown ---
$locations = [];
$locRes = $conn->query("SELECT id, fullname FROM location ORDER BY fullname ASC");
if ($locRes) {
    while ($row = $locRes->fetch_assoc()) {
        $locations[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $eventname   = $_POST['eventname'];
        $description = $_POST['description'];
        $dtstart     = $_POST['dtstart'];
        $dtend       = $_POST['dtend'];
        $locid       = (int)$_POST['locid'];
        $guestallow  = isset($_POST['guestallow']) ? 1 : 0;

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO event (eventname, description, dtstart, dtend, locid, guestallow) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $eventname, $description, $dtstart, $dtend, $locid, $guestallow);
            $stmt->execute();
        } else if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE event SET eventname=?, description=?, dtstart=?, dtend=?, locid=?, guestallow=? WHERE id=?");
            $stmt->bind_param("ssssiii", $eventname, $description, $dtstart, $dtend, $locid, $guestallow, $id);
            $stmt->execute();
        }
        
        header("Location: addevents.php");
        exit();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM event WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        header("Location: addevents.php");
        exit();
    }
}

// --- Fetch Events ---
$eventsArr = [];
// Join with location to get the venue name
$eventQuery = "SELECT e.id, e.eventname, e.description, e.dtstart, e.dtend, e.locid, e.guestallow, l.fullname as venue_name 
               FROM event e 
               LEFT JOIN location l ON e.locid = l.id 
               ORDER BY e.dtstart DESC";
               
$eventRes = $conn->query($eventQuery);

if ($eventRes) {
    while ($row = $eventRes->fetch_assoc()) {
        // Calculate Dynamic Status based on current time
        $now = new DateTime();
        $start = new DateTime($row['dtstart']);
        $end = new DateTime($row['dtend']);
        
        if ($now < $start) {
            $row['status'] = 'Upcoming';
        } elseif ($now >= $start && $now <= $end) {
            $row['status'] = 'Ongoing';
        } else {
            $row['status'] = 'Completed';
        }
        
        // Format for display
        $row['disp_start'] = $start->format('M d, Y h:i A');
        $row['disp_end'] = $end->format('M d, Y h:i A');
        
        $eventsArr[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMap - Manage Events</title>
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
        
        .content-card { background: var(--card-bg); border-radius: 12px; padding: 24px; border: 1px solid var(--border-glow); box-shadow: 0 4px 20px rgba(0,0,0,0.6); backdrop-filter: blur(5px); margin-bottom: 20px; }
        .card-title { font-family: 'Cinzel', serif; color: var(--star-gold); border-bottom: 1px solid rgba(255,218,108,0.2); padding-bottom: 12px; margin-bottom: 15px; letter-spacing: 1px; }
        
        .btn-green { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); border-radius: 8px; font-weight: 800; transition: all 0.3s ease; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .btn-green:hover { background: #015c3a; color: #fff; box-shadow: 0 0 15px var(--firefly-glow); transform: translateY(-2px); }
        
        .form-label { font-weight: 700; color: rgba(255,255,255,0.7); }
        .form-control, .form-select { background-color: rgba(0,0,0,0.5); border: 1px solid var(--border-glow); color: #fff; }
        .form-control:focus, .form-select:focus { background-color: rgba(0,0,0,0.8); border-color: var(--firefly-glow); color: #fff; box-shadow: 0 0 8px rgba(182, 255, 146, 0.4); }
        ::-webkit-calendar-picker-indicator { filter: invert(1) hue-rotate(100deg); cursor: pointer; }
        
        .table { color: #fff; }
        .table-light { background-color: rgba(255,255,255,0.05) !important; color: var(--star-gold) !important; }
        .table-light th { background-color: transparent !important; color: var(--star-gold) !important; border-bottom: 1px solid var(--star-gold); }
        .table tbody tr { border-bottom: 1px dashed rgba(255,255,255,0.1); }
        .table tbody tr:hover { background-color: rgba(182, 255, 146, 0.05); }
        .table tbody td { background-color: transparent !important; color: #ccc; }

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
        <span class="small fw-bold d-none d-sm-inline" style="color: rgba(255,255,255,0.6);">Core Status: <i class="bi bi-circle-fill" style="color: var(--firefly-glow); font-size: 10px; box-shadow: 0 0 8px var(--firefly-glow); border-radius: 50%;"></i> Online</span>
        <span class="small fw-bold border-start border-secondary ps-3" style="color: var(--star-gold);">Commander <?= htmlspecialchars($name) ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'adminsidebar.php'; ?>

    <main class="content-area">
        <div class="row mb-4">
            <div class="col-lg-4">
                <div class="content-card h-100">
                    <h5 class="card-title" id="formTitle"><i class="bi bi-calendar-plus me-2"></i>Add New Event</h5>
                    <form method="POST" id="eventForm">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="eventId" value="">

                        <div class="mb-3">
                            <label class="form-label small">Event Name</label>
                            <input type="text" name="eventname" id="evName" class="form-control" required placeholder="e.g. University Week">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small">Description</label>
                            <textarea name="description" id="evDesc" class="form-control" rows="2" required placeholder="Event details..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Venue / Location</label>
                            <select name="locid" id="evLoc" class="form-select" required>
                                <option value="" disabled selected>Select a mapped node...</option>
                                <?php foreach($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label small">Start Time</label>
                                <input type="datetime-local" name="dtstart" id="evStart" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label small">End Time</label>
                                <input type="datetime-local" name="dtend" id="evEnd" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-4 form-check">
                            <input class="form-check-input" type="checkbox" name="guestallow" id="evGuest" value="1" style="background-color: transparent; border-color: var(--firefly-glow);">
                            <label class="form-check-label small" for="evGuest" style="color: rgba(255,255,255,0.7);">Allow Guests (External Visitors)</label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-green w-100 py-2" id="submitBtn"><i class="bi bi-save me-2"></i>Forge Event</button>
                            <button type="button" class="btn btn-outline-danger w-100 py-2" onclick="resetForm()" id="cancelBtn" style="display:none;">Abort</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="content-card h-100">
                    <h5 class="card-title"><i class="bi bi-calendar-event me-2"></i>Scheduled Quests (Events)</h5>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Title</th>
                                    <th>Venue</th>
                                    <th>Timeline</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                <?php foreach($eventsArr as $ev): ?>
                                <tr>
                                    <td class="fw-bold" style="color: #fff;"><?= htmlspecialchars($ev['eventname']) ?></td>
                                    <td style="color: rgba(255,255,255,0.6);"><i class="bi bi-geo-alt-fill me-1" style="color: var(--star-gold);"></i><?= htmlspecialchars($ev['venue_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <div class="fw-bold" style="color: var(--firefly-glow);"><?= $ev['disp_start'] ?></div>
                                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.4);">Until <?= $ev['disp_end'] ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                            $bColor = 'rgba(255,255,255,0.2)'; $tColor = '#fff';
                                            if ($ev['status'] === 'Upcoming') { $bColor = 'rgba(182, 255, 146, 0.2)'; $tColor = 'var(--firefly-glow)'; }
                                            if ($ev['status'] === 'Ongoing') { $bColor = 'rgba(255, 218, 108, 0.2)'; $tColor = 'var(--star-gold)'; }
                                        ?>
                                        <span class="badge" style="background: <?= $bColor ?>; color: <?= $tColor ?>; border: 1px solid <?= $tColor ?>;"><?= $ev['status'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-light me-1 border-0" onclick='editEvent(<?= json_encode($ev, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit">
                                            <i class="bi bi-pencil" style="color: var(--star-gold);"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Obliterate this event from the timeline?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-light border-0" title="Delete">
                                                <i class="bi bi-trash" style="color: #ff6b6b;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($eventsArr)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No events found in the database.</td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function editEvent(ev) {
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Modify Event';
        document.getElementById('formAction').value = "edit";
        document.getElementById('eventId').value = ev.id;
        
        document.getElementById('evName').value = ev.eventname;
        document.getElementById('evDesc').value = ev.description;
        document.getElementById('evLoc').value = ev.locid;
        document.getElementById('evStart').value = ev.dtstart;
        document.getElementById('evEnd').value = ev.dtend;
        document.getElementById('evGuest').checked = (ev.guestallow == 1);
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Update Node';
        document.getElementById('cancelBtn').style.display = 'block';

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('eventForm').reset();
        document.getElementById('formTitle').innerHTML = '<i class="bi bi-calendar-plus me-2"></i>Add New Event';
        document.getElementById('formAction').value = "add";
        document.getElementById('eventId').value = "";
        
        document.getElementById('submitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Forge Event';
        document.getElementById('cancelBtn').style.display = 'none';
    }

    function toggleSidebar() { 
        let sb = document.getElementById('sidebar');
        if(sb) sb.classList.toggle('collapsed'); 
    }
</script>
</body>
</html>