<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $branch_id = (int)($_POST['branch_id'] ?? 0);

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Lỗi kết nối CSDL: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("SELECT id, username, fullname, role, branch_id, password, must_change_password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $hashed_password = $user['password'];
        $password_ok = false;

        if (password_get_info($hashed_password)['algo'] !== 0) {
            if (password_verify($password, $hashed_password)) {
                $password_ok = true;
            }
        } else {
            if (strlen($hashed_password) == 40 && sha1($password) === $hashed_password) {
                $password_ok = true;
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $new_hash, $user['id']);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }

        if ($password_ok) {
            if (in_array($user['role'], ['staff', 'manager']) && $user['branch_id'] != $branch_id) {
                $error = "Bạn không thuộc cơ sở này.";
            } else {
                $_SESSION['user'] = $user;
                $_SESSION['user']['branch_id'] = $branch_id;

                $stmtBranch = $conn->prepare("SELECT name FROM branches WHERE id = ?");
                $stmtBranch->bind_param("i", $branch_id);
                $stmtBranch->execute();
                $resBranch = $stmtBranch->get_result();
                if ($branch = $resBranch->fetch_assoc()) {
                    $_SESSION['user']['branch_name'] = $branch['name'];
                } else {
                    $_SESSION['user']['branch_name'] = "Không xác định";
                }
                $stmtBranch->close();

                if ($user['must_change_password'] == 1) {
                    header('Location: pages/change_password.php?first=1');
                    exit();
                } else {
                    header('Location: dashboard.php');
                    exit();
                }
            }
        } else {
            $error = "Sai tên đăng nhập hoặc mật khẩu.";
        }
    } else {
        $error = "Sai tên đăng nhập hoặc mật khẩu.";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Đăng nhập - EZI COFFEE & TEA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      height: 100vh;
      background: linear-gradient(90deg, #0031ab 50%, #fff 50%);
      display: flex;
      justify-content: center;
      align-items: center;
      transition: background 0.3s ease;
    }

    .login-container {
      background: white;
      padding: 40px 50px;
      border-radius: 12px;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      width: 100%;
      box-sizing: border-box;
      transition: padding 0.3s ease;
    }

    h1 {
      color: #000;
      font-weight: 700;
      font-size: 28px;
      margin-bottom: 30px;
      text-align: center;
    }

    label {
      font-weight: 600;
      font-size: 14px;
      color: #2a2a2a;
      display: block;
      margin-bottom: 8px;
    }

    input[type=text], input[type=password], select {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      color: #333;
      margin-bottom: 22px;
      box-sizing: border-box;
      transition: border-color 0.3s ease;
    }

    input[type=text]:focus,
    input[type=password]:focus,
    select:focus {
      outline: none;
      border-color: #fbc02d;
      box-shadow: 0 0 5px #fbc02d;
    }

    button {
      width: 100%;
      padding: 14px 0;
      background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
      border: none;
      border-radius: 10px;
      font-size: 18px;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      transition: background 0.3s ease;
      margin-top: 10px;
    }

    button:hover {
      background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
    }

    p.error {
      color: #d32f2f;
      font-weight: 700;
      margin-bottom: 20px;
      text-align: center;
      font-size: 16px;
    }

    @media (max-width: 768px) {
      body {
        background: #0031ab;
        padding: 20px;
        height: auto;
        min-height: 100vh;
      }
      .login-container {
        box-shadow: none;
        padding: 30px 20px;
        max-width: 100%;
        border-radius: 0;
      }
      h1 {
        font-size: 24px;
        margin-bottom: 20px;
      }
      label {
        font-size: 13px;
        margin-bottom: 6px;
      }
      input[type=text], input[type=password], select {
        padding: 10px 12px;
        font-size: 14px;
        margin-bottom: 16px;
      }
      button {
        font-size: 16px;
        padding: 12px 0;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h1>EZI COFFEE & TEA</h1>
    <?php if ($error): ?>
      <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" novalidate>
      <label for="branch_id">Chọn cơ sở:</label>
      <select id="branch_id" name="branch_id" required>
        <option value="">-- Chọn cơ sở --</option>
        <option value="1">Đống Đa</option>
        <option value="2">Âu Cơ</option>
        <option value="3">Kinh Dương Vương</option>
        <option value="4">Phan Đăng Lưu</option>
      </select>

      <label for="username">Tên đăng nhập:</label>
      <input type="text" id="username" name="username" required autocomplete="username" />

      <label for="password">Mật khẩu:</label>
      <input type="password" id="password" name="password" required autocomplete="current-password" />

      <button type="submit">Đăng nhập</button>
    </form>
  </div>
</body>
</html>
