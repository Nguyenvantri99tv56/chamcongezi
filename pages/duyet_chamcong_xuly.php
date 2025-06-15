<?php
session_start();
if (!isset($_SESSION['user'])) {
  header('Location: ../index.php');
  exit;
}
$user = $_SESSION['user'];

if (!in_array($user['role'], ['manager', 'ceo', 'accountant', 'it'])) {
  die('Bạn không có quyền thực hiện.');
}

require_once '../config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  die("Lỗi kết nối CSDL");
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = intval($_POST['id']);
  $action = $_POST['action'];
  if (in_array($action, ['approve', 'reject'])) {
    $status = ($action === 'approve') ? 1 : 2;
    $stmt = $conn->prepare("UPDATE timekeeping SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $id);
    $stmt->execute();
    $stmt->close();
  }
}

header("Location: duyet_chamcong.php");
exit;
