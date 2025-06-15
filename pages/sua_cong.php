<?php
session_start();
if (!isset($_SESSION["user"])) {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION["user"];
$role = $user["role"];
$branch_id = $user["branch_id"] ?? null;

if (!isset($_GET["id"])) {
  echo "Thiếu ID";
  exit();
}

$id = (int) $_GET["id"];

$conn = new mysqli("sql307.infinityfree.com", "if0_37824703", "Dmx123457", "if0_37824703_ezicore");
$conn->set_charset("utf8mb4");

// Lấy thông tin dòng chấm công cần sửa
$stmt = $conn->prepare("
  SELECT tk.*, u.fullname, u.branch_id AS user_branch
  FROM timekeeping tk
  JOIN users u ON tk.user_id = u.id
  WHERE tk.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  echo "Không tìm thấy dữ liệu";
  exit();
}

$row = $result->fetch_assoc();

// Kiểm tra quyền: nếu là manager thì chỉ được sửa nhân viên cùng cơ sở
if ($role === "manager" && $row["user_branch"] != $branch_id) {
  echo "Bạn không có quyền sửa dữ liệu của cơ sở khác.";
  exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Sửa bảng công</title>
  <link rel="stylesheet" href="../assets/forms.css" />
</head>
<body>

  <div class="nav-buttons" style="margin-bottom: 20px;">
    <button onclick="window.location.href='../dashboard.php'" style="margin-right:10px; padding:8px 16px;">Trang Chủ</button>
    <button onclick="history.back()" style="padding:8px 16px;">Quay Lại</button>
  </div>

  <div class="header">
    <h1>Sửa bảng công</h1>
  </div>

  <div class="form-container">
    <form action="xuly_sua_cong.php" method="POST">
      <input type="hidden" name="id" value="<?php echo $row['id']; ?>">

      <label>Họ tên:</label>
      <input type="text" value="<?php echo htmlspecialchars($row['fullname']); ?>" readonly>

      <label>Ngày làm việc:</label>
      <input type="text" value="<?php echo htmlspecialchars($row['work_date']); ?>" readonly>

      <label>Giờ vào ca:</label>
      <input type="time" name="check_in" value="<?php echo htmlspecialchars($row['check_in']); ?>" required>

      <label>Nghỉ giữa ca:</label>
      <div class="break-time-group">
        <input type="time" name="break_start" value="<?php echo htmlspecialchars($row['break_start']); ?>">
        <input type="time" name="break_end" value="<?php echo htmlspecialchars($row['break_end']); ?>">
      </div>

      <label>Giờ ra ca:</label>
      <input type="time" name="check_out" value="<?php echo htmlspecialchars($row['check_out']); ?>" required>

      <button type="submit">Cập nhật</button>
    </form>
  </div>
</body>
</html>
