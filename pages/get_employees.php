<?php
require_once '../config.php';

$branch_id = $_GET['branch_id'] ?? 0;

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$conn->set_charset("utf8mb4");

$stmt = $conn->prepare("SELECT id, fullname FROM users WHERE branch_id = ? ORDER BY fullname");
$stmt->bind_param("i", $branch_id);
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

header('Content-Type: application/json');
echo json_encode($employees);
