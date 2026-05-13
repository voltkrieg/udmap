<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in."]);
    exit();
}

$profid = (int)$_SESSION['user_id'];
$schedArr = [];

if (isset($_GET['day'])) {
    $day = $_GET['day'];
    $stmt = $conn->prepare("SELECT id, subject, fullsub, timestart, timeend, day FROM class WHERE profid = ? AND day = ? ORDER BY timestart");
    $stmt->bind_param("is", $profid, $day);
} else {
    // Otherwise load the whole schedule
    $stmt = $conn->prepare("SELECT id, subject, fullsub, timestart, timeend, day FROM class WHERE profid = ? ORDER BY FIELD(day, 'M','T','W','H','F','S','SU'), timestart");
    $stmt->bind_param("i", $profid);
}

if ($stmt->execute()) {
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Format times to 12-hour AM/PM for easier frontend display
        $row['timestart_formatted'] = date('h:i A', strtotime($row['timestart']));
        $row['timeend_formatted'] = date('h:i A', strtotime($row['timeend']));
        $schedArr[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $schedArr]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to fetch schedule"]);
}

$stmt->close();
$conn->close();
?>