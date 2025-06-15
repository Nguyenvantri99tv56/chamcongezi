<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

// Kiểm tra user đã đăng nhập chưa
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}

// Kiểm tra quyền tạo tài khoản
$allowed_roles = ['ceo', 'it', 'accountant', 'manager'];
if (!in_array($_SESSION['user']['role'], $allowed_roles)) {
    die("Bạn không có quyền tạo tài khoản");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $password_plain = trim($_POST['password']);
    $role = $_POST['role'] ?? 'staff';
    $branch_id = (int)$_POST['branch_id'];
    $salary_per_hour = (int)$_POST['salary_per_hour'];

    // Với quản lý chỉ được tạo tài khoản nhân viên cùng cơ sở
    if ($_SESSION['user']['role'] === 'manager') {
        $role = 'staff';
        $branch_id = $_SESSION['user']['branch_id'];
    }

    $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);
    $must_change_password = 1;

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Lỗi kết nối: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    // Kiểm tra username tồn tại
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        $message = "Tài khoản đã tồn tại.";
    } else {
        $stmt_check->close();

        $stmt = $conn->prepare("INSERT INTO users (username, fullname, password, role, branch_id, salary_per_hour, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiii", $username, $fullname, $hashed_password, $role, $branch_id, $salary_per_hour, $must_change_password);

        if ($stmt->execute()) {
            $message = "Tạo tài khoản thành công";
        } else {
            $message = "Lỗi tạo tài khoản: " . $stmt->error;
        }

        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Tạo tài khoản nhân viên</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
          background: #f9fafb;
        }
        .container {
          max-width: 600px;
          margin: auto;
          background: white;
          padding: 20px;
          border-radius: 10px;
          box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        label {
          display: block;
          margin-bottom: 5px;
          font-weight: bold;
        }
        input, select {
          width: 100%;
          padding: 10px;
          margin-bottom: 15px;
          border: 1px solid #ccc;
          border-radius: 6px;
          font-size: 14px;
          box-sizing: border-box;
        }
        button {
          width: 100%;
          padding: 12px;
          background-color: #2563eb;
          color: white;
          font-size: 16px;
          font-weight: bold;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          transition: background-color 0.3s ease;
        }
        button:hover {
          background-color: #1d4ed8;
        }
        .message {
          margin-bottom: 15px;
          padding: 10px;
          background-color: #fef3c7;
          border: 1px solid #fde68a;
          border-radius: 6px;
          color: #92400e;
          font-weight: 600;
        }
        .nav-buttons {
          margin-bottom: 20px;
          display: flex;
          gap: 10px;
        }
        .nav-buttons button {
          padding: 8px 16px;
          background-color: #22c55e;
          border: none;
          border-radius: 6px;
          color: white;
          font-weight: 600;
          cursor: pointer;
          transition: background-color 0.3s ease;
        }
        .nav-buttons button:hover {
          background-color: #16a34a;
        }
    </style>
</head>
<body>
    <div class="container">
      <div class="nav-buttons">
        <button onclick="window.location.href='../dashboard.php'">🏠 Trang Chủ</button>
        <button onclick="history.back()">🔙 Quay Lại</button>
      </div>
      <h2 style="text-align: center;">Tạo tài khoản nhân viên mới</h2>

      <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <form action="" method="POST" novalidate>
          <label for="username">Tên đăng nhập:</label>
          <input type="text" id="username" name="username" required>

          <label for="fullname">Họ và tên:</label>
          <input type="text" id="fullname" name="fullname" required>

          <label for="password">Mật khẩu:</label>
          <input type="password" id="password" name="password" value="1" readonly>

          <?php if ($_SESSION['user']['role'] !== 'manager'): ?>
              <label for="role">Vai trò:</label>
              <select id="role" name="role" required>
                <option value="staff" selected>Nhân viên</option>
                <option value="manager">Quản lý</option>
                <option value="ceo">CEO</option>
                <option value="accountant">Kế toán</option>
                <option value="it">IT</option>
              </select>
          <?php else: ?>
              <!-- Manager chỉ được tạo tài khoản nhân viên, vai trò cố định -->
              <input type="hidden" name="role" value="staff" />
          <?php endif; ?>

          <?php if ($_SESSION['user']['role'] === 'manager'): ?>
              <label for="branch_id">Cơ sở:</label>
              <input type="text" value="<?php echo htmlspecialchars($_SESSION['user']['branch_name']); ?>" disabled>
              <input type="hidden" name="branch_id" value="<?php echo (int)$_SESSION['user']['branch_id']; ?>">
          <?php else: ?>
              <label for="branch_id">Cơ sở:</label>
              <select id="branch_id" name="branch_id" required>
                <option value="">-- Chọn cơ sở --</option>
                <option value="1">Đống Đa</option>
                <option value="2">Âu Cơ</option>
                <option value="3">Kinh Dương Vương</option>
                <option value="4">Phan Đăng Lưu</option>
              </select>
          <?php endif; ?>

          <label for="salary_per_hour">Lương giờ:</label>
          <input type="number" id="salary_per_hour" name="salary_per_hour" required min="0" step="1000" value="15000">

          <button type="submit">Tạo tài khoản</button>
      </form>
    </div>
</body>
</html>
