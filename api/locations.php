<?php
header('Content-Type: application/json');

require_once '../db.php';

$type = $_GET['type'] ?? '';


$sql = "SELECT 
            abbreviation AS NAME, 
            fullname AS LONGNAME, 
            description AS DESCRIPTION, 
            lattitude AS LATITUDE, 
            longitude AS LONGITUDE, 
            type AS BLDGTYPE 
        FROM location 
        WHERE 1=1"; 

if (!empty($type)) {
    $sql .= " AND UPPER(TRIM(type)) = UPPER(?)";
}

$sql .= " ORDER BY fullname, abbreviation";

$locations = [];


$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($type)) {
        $stmt->bind_param("s", $type);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['LATITUDE'] = (float) $row['LATITUDE'];
        $row['LONGITUDE'] = (float) $row['LONGITUDE'];
    
        
        $locations[] = $row;
    }
    
    $stmt->close();
} else {
    error_log("Database error: " . $conn->error);
}

echo json_encode($locations);
?>