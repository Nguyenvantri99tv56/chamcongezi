<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}

$user = $_SESSION['user'];
$role = $user['role'];

// Chỉ CEO và IT mới có quyền xóa nhân viên
if (!in_array($role, ['ceo', 'it'])) {
    die("Bạn không có quyền xóa nhân viên.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        die("ID không hợp lệ.");
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Lỗi kết nối: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header('Location: nhanvien.php');
        exit();
    } else {
        echo "Lỗi xóa nhân viên: " . $stmt->error;
    }
} else {
    die("Phương thức không hợp lệ.");
}
