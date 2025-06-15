<?php
session_start();
if (!isset($_SESSION["user"])) {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION["user"];
$role = $user["role"];
$branch_id = $user["branch_id"] ?? null;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  die("Truy cập không hợp lệ");
}

$id = (int) $_POST["id"];
$check_in = $_POST["check_in"];
$break_start = $_POST["break_start"] ?: null;
$break_end = $_POST["break_end"] ?: null;
$check_out = $_POST["check_out"];

$conn = new mysqli("sql307.infinityfree.com", "if0_37824703", "Dmx123457", "if0_37824703_ezicore");
$conn->set_charset("utf8mb4");

// Lấy dữ liệu hiện tại để kiểm tra quyền
$stmt = $conn->prepare("
  SELECT tk.*, u.branch_id AS user_branch
  FROM timekeeping tk
  JOIN users u ON tk.user_id = u.id
  WHERE tk.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Không tìm thấy bản ghi cần sửa");
}

$row = $result->fetch_assoc();

if ($role === "manager" && $row["user_branch"] != $branch_id) {
  die("Bạn không có quyền sửa công của nhân viên cơ sở khác");
}

// Tính lại working_hours
$checkInDT = new DateTime($check_in);
$checkOutDT = new DateTime($check_out);
if ($check_out < $check_in) {
  $checkOutDT->modify('+1 day');
}
$interval = $checkInDT->diff($checkOutDT);
$totalMinutes = ($interval->h * 60) + $interval->i;

// Trừ thời gian nghỉ giữa ca nếu có
if ($break_start && $break_end) {
  $breakStartDT = new DateTime($break_start);
  $breakEndDT = new DateTime($break_end);
  if ($break_end < $break_start) {
    $breakEndDT->modify('+1 day');
  }
  $breakInterval = $breakStartDT->diff($breakEndDT);
  $breakMinutes = ($breakInterval->h * 60) + $breakInterval->i;
  $totalMinutes -= $breakMinutes;
}

$working_hours = round($totalMinutes / 60, 2);

// Cập nhật vào DB
$stmt = $conn->prepare("
  UPDATE timekeeping SET 
    check_in = ?, 
    break_start = ?, 
    break_end = ?, 
    check_out = ?, 
    working_hours = ?
  WHERE id = ?
");
$stmt->bind_param("ssssdi", $check_in, $break_start, $break_end, $check_out, $working_hours, $id);

if ($stmt->execute()) {
  header("Location: bangcong.php");
  exit();
} else {
  echo "Cập nhật thất bại: " . $conn->error;
}
