<?php
session_start();
require_once '../db.php'; 
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if (isset($input['lat']) && isset($input['lng'])) {
    $lat = $input['lat'];
    $lng = $input['lng'];
    $profile = $input['profile'] ?? 'unknown mode';

    $details = "Requested route to coordinates ($lat, $lng) via $profile.";

    if (function_exists('logUserAction')) {
        logUserAction($conn, $userId, 'ROUTE_SEARCH', $details);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'logUserAction function not found.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
}
?>