<?php
session_start();
header('Content-Type: application/json');
require_once '../config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
  echo json_encode(['status' => 'error', 'message' => 'Bạn chưa đăng nhập hoặc không có quyền']);
  exit;
}

$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ']);
  exit;
}

$work_date = $_POST['work_date'] ?? '';
$check_in = $_POST['check_in'] ?? '';
$break_start = $_POST['break_start'] ?? null;
$break_end = $_POST['break_end'] ?? null;
$check_out = $_POST['check_out'] ?? '';

if (!$work_date || !$check_in || !$check_out) {
  echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ trường bắt buộc']);
  exit;
}

function calcWorkingHours($check_in, $check_out, $break_start = null, $break_end = null) {
  $start = strtotime($check_in);
  $end = strtotime($check_out);
  if ($end < $start) {
    $end += 24 * 3600;
  }
  $breakDuration = 0;
  if ($break_start && $break_end) {
    $bstart = strtotime($break_start);
    $bend = strtotime($break_end);
    if ($bend < $bstart) {
      $bend += 24 * 3600;
    }
    $breakDuration = $bend - $bstart;
  }
  $total = ($end - $start) - $breakDuration;
  return round($total / 3600, 2);
}

$working_hours = calcWorkingHours($check_in, $check_out, $break_start, $break_end);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối CSDL']);
  exit;
}
$conn->set_charset('utf8mb4');

// Kiểm tra xem user đã chấm công ngày đó chưa
$stmt_check = $conn->prepare("SELECT id FROM timekeeping WHERE user_id = ? AND work_date = ?");
$stmt_check->bind_param("is", $user['id'], $work_date);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
  echo json_encode(['status' => 'error', 'message' => 'Bạn đã chấm công ngày này rồi']);
  exit;
}

$stmt_check->close();

$status = 0; // trạng thái chờ duyệt

$stmt = $conn->prepare("INSERT INTO timekeeping (user_id, work_date, check_in, break_start, break_end, check_out, working_hours, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssdi", $user['id'], $work_date, $check_in, $break_start, $break_end, $check_out, $working_hours, $status);

if ($stmt->execute()) {
  echo json_encode(['status' => 'ok', 'hours' => $working_hours]);
} else {
  echo json_encode(['status' => 'error', 'message' => 'Lỗi lưu dữ liệu chấm công']);
}

$stmt->close();
$conn->close();
