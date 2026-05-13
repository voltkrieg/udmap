<?php
require_once '../db.php'; // Adjust this path if your db.php is located elsewhere

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $q = $_GET['q'] ?? '';
    if (empty($q) || strlen($q) < 2) { 
        echo json_encode([]); 
        exit; 
    }
    
    // Search by abbreviation or fullname
    $stmt = $conn->prepare("SELECT id, abbreviation, fullname, type, lattitude, longitude FROM location WHERE abbreviation LIKE ? OR fullname LIKE ? LIMIT 10");
    $like = "%$q%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $data = [];
    while($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE location SET total_searches = total_searches + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        if($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
    }
}
?>