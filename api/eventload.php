<?php
session_start();
header('Content-Type: application/json');

require_once '../db.php';

$eventsArr = [];

// Join event table with location table to get map coordinates
$query = "
    SELECT 
        e.id AS event_id, e.eventname, e.description, e.dtstart, e.dtend, e.guestallow, 
        l.abbreviation AS location_name, l.lattitude, l.longitude
    FROM event e
    LEFT JOIN location l ON e.locid = l.id
    WHERE e.dtstart >= NOW() 
    ORDER BY e.dtstart ASC
";

$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['date_formatted'] = date('l, F j', strtotime($row['dtstart']));
        $row['time_formatted'] = date('h:i A', strtotime($row['dtstart']));
        $eventsArr[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $eventsArr]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to fetch events"]);
}
$conn->close();
?>