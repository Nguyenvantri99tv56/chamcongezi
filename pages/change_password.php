<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (strlen($new_password) < 6) {
        $error = "Mật khẩu mới phải ít nhất 6 ký tự.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu nhập lại không khớp.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $error = "Lỗi kết nối: " . $conn->connect_error;
        } else {
            $conn->set_charset("utf8mb4");
            $stmt = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user['id']);
            if ($stmt->execute()) {
                $success = "Đổi mật khẩu thành công. Bạn sẽ được đăng xuất để đăng nhập lại.";
                session_destroy();
                header("refresh:3;url=../index.php");
                exit;
            } else {
                $error = "Lỗi khi đổi mật khẩu: " . $stmt->error;
            }
            $stmt->close();
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <title>Đổi mật khẩu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="../style.css" />
</head>
<body>
    <div class="container">
        <h2>Đổi mật khẩu</h2>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="new_password">Mật khẩu mới:</label>
            <input type="password" id="new_password" name="new_password" required />

            <label for="confirm_password">Nhập lại mật khẩu mới:</label>
            <input type="password" id="confirm_password" name="confirm_password" required />

            <button type="submit">Đổi mật khẩu</button>
        </form>
    </div>
</body>
</html>
