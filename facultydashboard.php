<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header('Location: login.php');
    exit();
}

$facultyId = (int)$_SESSION['user_id'];
$firstName = htmlspecialchars($_SESSION['firstname'] ?? 'Faculty');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_schedule') {
        $subject   = trim($_POST['subject']);
        $fullsub   = trim($_POST['fullsub']);
        $day       = $_POST['day']; 
        $timeStart = $_POST['timestart'];
        $timeEnd   = $_POST['timeend'];
        $room      = trim($_POST['room']);

        // Updated query and bind_param to include 'room'
        $stmt = $conn->prepare("INSERT INTO class (subject, fullsub, timestart, timeend, profid, day, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiss", $subject, $fullsub, $timeStart, $timeEnd, $facultyId, $day, $room);
        
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success small py-2">Schedule added successfully.</div>';
        } else {
            $msg = '<div class="alert alert-danger small py-2">Error adding schedule.</div>';
        }
    } 
    elseif ($action === 'add_event') {
        $eventname = trim($_POST['eventtitle']);
        $description = trim($_POST['description']);
        $dtstart   = $_POST['date'] . ' ' . $_POST['timestart'] . ':00';
        $dtend     = $_POST['date'] . ' ' . $_POST['timeend'] . ':00';
        $locid     = (int)$_POST['locid']; 
        $guestallow = 1;

        $stmt = $conn->prepare("INSERT INTO event (eventname, description, dtstart, dtend, locid, guestallow) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssii", $eventname, $description, $dtstart, $dtend, $locid, $guestallow);
        
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success small py-2">Event published successfully.</div>';
        } else {
            $msg = '<div class="alert alert-danger small py-2">Error publishing event.</div>';
        }
    }
    elseif ($action === 'edit_schedule') {
        $classId   = (int)$_POST['class_id'];
        $subject   = trim($_POST['subject']);
        $fullsub   = trim($_POST['fullsub']);
        $day       = $_POST['day'];
        $timeStart = $_POST['timestart'];
        $timeEnd   = $_POST['timeend'];
        $room      = trim($_POST['room']);

        // Updated query and bind_param to include 'room'
        $stmt = $conn->prepare("UPDATE class SET subject=?, fullsub=?, day=?, timestart=?, timeend=?, room=? WHERE id=? AND profid=?");
        $stmt->bind_param("ssssssii", $subject, $fullsub, $day, $timeStart, $timeEnd, $room, $classId, $facultyId);
        $msg = $stmt->execute() ? '<div class="alert alert-success py-2">Class updated.</div>' : '<div class="alert alert-danger">Update failed.</div>';
    }
    elseif ($action === 'assign_student') {
        $classId   = (int)$_POST['class_id'];
        $studentId = (int)$_POST['student_id']; 

        $check = $conn->query("SELECT id FROM students WHERE courseid = $classId AND studentid = $studentId");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO students (courseid, studentid, profid) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $classId, $studentId, $facultyId);
            $msg = $stmt->execute() ? '<div class="alert alert-success py-2">Student assigned.</div>' : '<div class="alert alert-danger">Assignment failed.</div>';
        } else {
            $msg = '<div class="alert alert-warning py-2">Student already in this class.</div>';
        }
    }
}

$allStudents = [];
$sRes = $conn->query("SELECT id, firstname, lastname FROM users WHERE role = 'student' ORDER BY lastname");
if ($sRes) { while ($row = $sRes->fetch_assoc()) { $allStudents[] = $row; } }

$myClasses = [];
$cRes = $conn->query("SELECT * FROM class WHERE profid = $facultyId ORDER BY FIELD(day, 'M','T','W','H','F','S','SU'), timestart");
if ($cRes) { while ($row = $cRes->fetch_assoc()) { $myClasses[] = $row; } }

$locations = [];
$lRes = $conn->query("SELECT id, abbreviation, fullname FROM location ORDER BY fullname");
if ($lRes) { while ($row = $lRes->fetch_assoc()) { $locations[] = $row; } }

function fmtTime($t) { return date('h:i A', strtotime($t)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDMap - Faculty Sanctum</title>
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
    
        
        .action-card {
            background: var(--card-bg); 
            border-radius: 12px; 
            padding: 24px; 
            border: 1px solid var(--border-glow);
            box-shadow: 0 4px 20px rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(182, 255, 146, 0.2);
            border-color: var(--firefly-glow);
        }
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 15px;
            background: var(--gradient-btn);
            border: 1px solid var(--firefly-glow);
            color: #fff;
        }
        
        /* Modal Theme Overrides */
        .modal-content {
            background: var(--bg-dark);
            border: 1px solid var(--star-gold);
            color: #fff;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,218,108,0.2);
        }
        .form-control, .form-select {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(182, 255, 146, 0.3);
            color: #fff;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.1);
            border-color: var(--firefly-glow);
            color: #fff;
            box-shadow: 0 0 10px rgba(182, 255, 146, 0.2);
        }
        label { color: var(--star-gold); }
        .btn-green { background: var(--gradient-btn); color: white; border: 1px solid var(--firefly-glow); font-weight: 700; }
        .btn-green:hover { background: #014d31; color: var(--firefly-glow); border-color: var(--firefly-glow); }
    </style>
</head>
<body>

<header class="ud-topbar">
    <div class="d-flex align-items-center">
        <button class="btn text-white fs-4 p-0 me-3" onclick="toggleSidebar()" style="opacity: 0.8;"><i class="bi bi-list"></i></button>
        <span class="fs-4 ud-title">UDMap <span class="fs-6" style="color: var(--firefly-glow);">Faculty</span></span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="small fw-bold d-none d-sm-inline" style="color: rgba(255,255,255,0.6);">
            Sanctum Status: <i class="bi bi-circle-fill" style="color: var(--firefly-glow); font-size: 10px; box-shadow: 0 0 8px var(--firefly-glow); border-radius: 50%;"></i> Active
        </span>
        <span class="small fw-bold border-start border-secondary ps-3" style="color: var(--star-gold);">Professor <?= $firstName ?></span>
    </div>
</header>

<div class="page-wrap">
    <?php include 'sidebar.php'; ?>

    <main class="content-area">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fs-4 fw-bold mb-0 ud-title" style="border: none;">Faculty Dashboard</h1>
            <div class="text-end">
                <p class="small mb-0" style="color: rgba(255,255,255,0.5);">Current Cycle</p>
                <span class="fw-bold" style="color: var(--star-gold);"><?= date('F d, Y') ?></span>
            </div>
        </div>

        <?= $msg ?>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="action-card" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <div class="action-icon"><i class="bi bi-calendar-plus-fill"></i></div>
                    <h5 class="fw-bold mb-1" style="color: var(--star-gold); font-family: 'Cinzel', serif;">Add Class Schedule</h5>
                    <p class="small mb-0" style="color: rgba(255,255,255,0.5);">Manifest a new knowledge session in the archives.</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="action-card" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <div class="action-icon" style="border-color: var(--star-gold); color: var(--star-gold);"><i class="bi bi-megaphone-fill"></i></div>
                    <h5 class="fw-bold mb-1" style="color: var(--star-gold); font-family: 'Cinzel', serif;">Publish Event</h5>
                    <p class="small mb-0" style="color: rgba(255,255,255,0.5);">Broadcast a grand gathering to all seekers.</p>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="card-title d-flex justify-content-between align-items-center">
                Teaching Ledger
                <i class="bi bi-journal-bookmark-fill fs-5" style="color: rgba(255,255,255,0.3);"></i>
            </h5>
            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Course Code</th>
                            <th>Full Subject Name</th>
                            <th>Cycle Day</th>
                            <th>Room</th>
                            <th>Temporal Range</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($myClasses)): ?>
                            <tr><td colspan="6" class="text-center py-4" style="color: rgba(255,255,255,0.5);">No teaching scrolls found.</td></tr>
                        <?php else: ?>
                        <tbody class="small">
                            <?php foreach($myClasses as $cls): ?>
                            <tr>
                                <td class="fw-bold" style="color: var(--star-gold);"><?= htmlspecialchars($cls['subject']) ?></td>
                                <td style="color: #fff;"><?= htmlspecialchars($cls['fullsub']) ?></td>
                                <td><span class="badge" style="background: rgba(182, 255, 146, 0.1); color: var(--firefly-glow);"><?= htmlspecialchars($cls['day']) ?></span></td>
                                <td style="color: #fff;"><?= htmlspecialchars($cls['room']) ?></td>
                                <td style="color: rgba(255,255,255,0.7);"><?= fmtTime($cls['timestart']) ?> - <?= fmtTime($cls['timeend']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-custom me-1" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($cls)) ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" 
                                            onclick="openAssignModal(<?= $cls['id'] ?>, '<?= htmlspecialchars($cls['subject']) ?>')">
                                        <i class="bi bi-person-plus-fill"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="addScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="fw-bold ud-title" style="font-size: 1.2rem;">Add Schedule</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_schedule">
            <div class="mb-3">
                <label class="small fw-bold">Course Code (Subject)</label>
                <input type="text" name="subject" class="form-control" required placeholder="e.g. CPE101">
            </div>
            <div class="mb-3">
                <label class="small fw-bold">Full Subject Name</label>
                <input type="text" name="fullsub" class="form-control" required placeholder="e.g. Intro to Logic Circuits">
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="small fw-bold">Day</label>
                    <select name="day" class="form-select" required>
                        <option value="M">Monday</option><option value="T">Tuesday</option><option value="W">Wednesday</option>
                        <option value="H">Thursday</option><option value="F">Friday</option><option value="S">Saturday</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="small fw-bold">Room</label>
                    <input type="text" name="room" class="form-control" required placeholder="e.g. ULS 101">
                </div>
            </div>
            <div class="row g-2 mb-4">
                <div class="col-6"><label class="small fw-bold">Start Time</label><input type="time" name="timestart" class="form-control" required></div>
                <div class="col-6"><label class="small fw-bold">End Time</label><input type="time" name="timeend" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-green w-100">Save Schedule</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addEventModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="fw-bold ud-title" style="font-size: 1.2rem;">Publish Event</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="action" value="add_event">
            <div class="mb-3"><label class="small fw-bold">Event Title</label><input type="text" name="eventtitle" class="form-control" required></div>
            <div class="mb-3"><label class="small fw-bold">Description</label><textarea name="description" class="form-control"></textarea></div>
            <div class="mb-3">
                <label class="small fw-bold">Location</label>
                <select name="locid" class="form-select" required>
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['fullname'] . ' (' . $loc['abbreviation'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="small fw-bold">Date</label><input type="date" name="date" class="form-control" required></div>
            <div class="row g-2 mb-4">
                <div class="col-6"><label class="small fw-bold">Start Time</label><input type="time" name="timestart" class="form-control" required></div>
                <div class="col-6"><label class="small fw-bold">End Time</label><input type="time" name="timeend" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-green w-100 fw-bold">Publish Event</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="assignStudentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="ud-title">Assign to <span id="assignClassName"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="assign_student">
            <input type="hidden" name="class_id" id="assignClassId">
            <div class="mb-3">
                <label class="small fw-bold">Select Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">-- Select Registered Citizen --</option>
                    <?php foreach($allStudents as $student): ?>
                        <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['lastname']) ?>, <?= htmlspecialchars($student['firstname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer border-0">
            <button type="submit" class="btn btn-green w-100">Confirm Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(data) {
    const modal = new bootstrap.Modal(document.getElementById('addScheduleModal'));
    const form = document.querySelector('#addScheduleModal form');
    
    // Change form to Edit mode
    form.querySelector('[name="action"]').value = 'edit_schedule';
    // Remove old hidden input if it exists
    let oldInput = form.querySelector('input[name="class_id"]');
    if(oldInput) oldInput.remove();
    
    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="class_id" value="${data.id}">`);
    document.querySelector('#addScheduleModal .ud-title').innerText = "Edit Schedule";
    
    // Fill data
    form.querySelector('[name="subject"]').value = data.subject;
    form.querySelector('[name="fullsub"]').value = data.fullsub;
    form.querySelector('[name="day"]').value = data.day;
    form.querySelector('[name="room"]').value = data.room || ''; // Populate room
    form.querySelector('[name="timestart"]').value = data.timestart;
    form.querySelector('[name="timeend"]').value = data.timeend;
    
    modal.show();
}

function openAssignModal(id, name) {
    document.getElementById('assignClassId').value = id;
    document.getElementById('assignClassName').innerText = name;
    new bootstrap.Modal(document.getElementById('assignStudentModal')).show();
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('collapsed'); }
</script>
</body>
</html>