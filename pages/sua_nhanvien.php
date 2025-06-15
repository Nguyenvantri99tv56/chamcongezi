<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user'])) {
  header('Location: ../index.php');
  exit;
}

$user = $_SESSION['user'];
$role = $user['role'];
$branch_id = $user['branch_id'] ?? null;

if (!isset($_GET['id'])) {
  die('Thiếu ID nhân viên');
}

$id = intval($_GET['id']);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  die("Lỗi kết nối: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Lấy dữ liệu user cần sửa
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  die("Không tìm thấy nhân viên");
}

$emp = $res->fetch_assoc();

// Kiểm tra quyền manager chỉ sửa nhân viên cùng cơ sở
if ($role === 'manager' && $emp['branch_id'] != $branch_id) {
  die("Bạn không có quyền sửa nhân viên cơ sở khác");
}

// Xử lý POST cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'update') {
    $fullname = trim($_POST['fullname']);
    $salary_per_hour = intval($_POST['salary_per_hour']);

    // Manager không được sửa username, role, branch_id
    if (in_array($role, ['ceo', 'it'])) {
      $username = trim($_POST['username']);
      $role_update = $_POST['role'];
      $branch_update = intval($_POST['branch_id']);
    } else {
      $username = $emp['username'];
      $role_update = $emp['role'];
      $branch_update = $emp['branch_id'];
    }

    // Kiểm tra trùng username nếu có thay đổi
    if ($username !== $emp['username']) {
      $stmtCheck = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
      $stmtCheck->bind_param("si", $username, $id);
      $stmtCheck->execute();
      $stmtCheck->store_result();
      if ($stmtCheck->num_rows > 0) {
        die("Tên đăng nhập đã tồn tại.");
      }
      $stmtCheck->close();
    }

    // Cập nhật
    $stmtUpdate = $conn->prepare("UPDATE users SET username=?, fullname=?, role=?, branch_id=?, salary_per_hour=? WHERE id=?");
    $stmtUpdate->bind_param("sssiii", $username, $fullname, $role_update, $branch_update, $salary_per_hour, $id);
    if ($stmtUpdate->execute()) {
      header("Location: nhanvien.php");
      exit;
    } else {
      die("Lỗi cập nhật: " . $stmtUpdate->error);
    }
  } elseif ($_POST['action'] === 'delete') {
    // Xóa nhân viên
    $stmtDelete = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmtDelete->bind_param("i", $id);
    if ($stmtDelete->execute()) {
      header("Location: nhanvien.php");
      exit;
    } else {
      die("Lỗi xóa nhân viên: " . $stmtDelete->error);
    }
  }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Sửa nhân viên</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="../style.css" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f9fafb;
      margin: 0;
      padding: 20px;
      box-sizing: border-box;
      min-height: 100vh;
    }
    .container {
      max-width: 600px;
      margin: 30px auto;
      background: #fff;
      padding: 20px 25px;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    h1 {
      text-align: center;
      margin-bottom: 25px;
      color: #111827;
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: 700;
      font-size: 15px;
    }
    input, select {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #ccc;
      box-sizing: border-box;
    }
    button {
      margin-top: 25px;
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      border: none;
      transition: background-color 0.3s ease;
      user-select: none;
    }
    button.update-btn {
      background-color: #2563eb;
      color: white;
      margin-right: 10px;
    }
    button.update-btn:hover {
      background-color: #1d4ed8;
    }
    button.delete-btn {
      background-color: #ef4444;
      color: white;
    }
    button.delete-btn:hover {
      background-color: #b91c1c;
    }
    .nav-buttons {
      max-width: 600px;
      margin: 0 auto 20px auto;
      display: flex;
      gap: 15px;
      justify-content: flex-start;
    }
    .nav-buttons button {
      background-color: #2563eb;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      transition: background-color 0.3s ease;
      user-select: none;
    }
    .nav-buttons button:hover {
      background-color: #1d4ed8;
    }
    @media (max-width: 768px) {
      .container {
        margin: 15px 10px;
        padding: 15px 20px;
      }
      button.update-btn, button.delete-btn {
        width: 100%;
        margin: 10px 0 0 0;
      }
      .nav-buttons {
        flex-direction: column;
        gap: 10px;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>

  <div class="nav-buttons">
    <button onclick="window.location.href='../dashboard.php'">🏠 Trang Chủ</button>
    <button onclick="window.location.href='nhanvien.php'">🔙 Quay Lại</button>
  </div>

  <div class="container">
    <h1>Sửa nhân viên</h1>
    <form method="POST" novalidate onsubmit="return confirm('Bạn có chắc muốn cập nhật nhân viên này?');">
      <?php if (in_array($role, ['ceo', 'it'])): ?>
        <label for="username">Tên đăng nhập:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($emp['username']); ?>" required>
        
        <label for="role">Vai trò:</label>
        <select id="role" name="role" required>
          <option value="staff" <?php if ($emp['role'] === 'staff') echo 'selected'; ?>>Nhân viên</option>
          <option value="manager" <?php if ($emp['role'] === 'manager') echo 'selected'; ?>>Quản lý</option>
          <option value="ceo" <?php if ($emp['role'] === 'ceo') echo 'selected'; ?>>CEO</option>
          <option value="accountant" <?php if ($emp['role'] === 'accountant') echo 'selected'; ?>>Kế toán</option>
          <option value="it" <?php if ($emp['role'] === 'it') echo 'selected'; ?>>IT</option>
        </select>

        <label for="branch_id">Cơ sở:</label>
        <select id="branch_id" name="branch_id" required>
          <?php
          $branches = $conn->query("SELECT id, name FROM branches ORDER BY name");
          while ($b = $branches->fetch_assoc()) {
            $sel = ($b['id'] == $emp['branch_id']) ? 'selected' : '';
            echo '<option value="'.$b['id'].'" '.$sel.'>'.htmlspecialchars($b['name']).'</option>';
          }
          ?>
        </select>
      <?php else: ?>
        <p><strong>Tên đăng nhập:</strong> <?php echo htmlspecialchars($emp['username']); ?></p>
        <p><strong>Vai trò:</strong> <?php echo htmlspecialchars($emp['role']); ?></p>
        <p><strong>Cơ sở:</strong> <?php
          $res = $conn->query("SELECT name FROM branches WHERE id = ".$emp['branch_id']);
          $branch = $res->fetch_assoc();
          echo htmlspecialchars($branch['name'] ?? 'Không xác định');
        ?></p>
      <?php endif; ?>

      <label for="fullname">Họ và tên:</label>
      <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($emp['fullname']); ?>" required>

      <label for="salary_per_hour">Lương giờ (đồng):</label>
      <input type="number" id="salary_per_hour" name="salary_per_hour" value="<?php echo (int)$emp['salary_per_hour']; ?>" required>

      <div style="margin-top: 30px; display: flex; flex-wrap: wrap; gap: 10px;">
        <button type="submit" name="action" value="update" class="update-btn">Cập nhật</button>
      </form>

      <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa nhân viên này?');" style="margin:0;">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <button type="submit" name="action" value="delete" class="delete-btn">Xóa nhân viên</button>
      </form>
      </div>
  </div>

</body>
</html>
