<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php'; // file chứa DB_HOST, DB_USER, DB_PASS, DB_NAME

// Kết nối DB
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Kết nối DB thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Đường dẫn file CSV (đặt cùng thư mục hoặc dùng đường dẫn đầy đủ)
$csvFile = 'users.csv';

// Mở file CSV
if (!file_exists($csvFile)) {
    die("File CSV không tồn tại");
}

if (($handle = fopen($csvFile, "r")) === false) {
    die("Không thể mở file CSV");
}

// Đọc dòng đầu tiên (header) để biết các cột
$headers = fgetcsv($handle);

// Kiểm tra các cột cần thiết có đủ không
$requiredColumns = ['username', 'fullname', 'password', 'role', 'branch_id'];
foreach ($requiredColumns as $col) {
    if (!in_array($col, $headers)) {
        die("File CSV thiếu cột: $col");
    }
}

// Lấy index các cột để tiện đọc
$colIndex = array_flip($headers);

$successCount = 0;
$errorCount = 0;

while (($row = fgetcsv($handle)) !== false) {
    // Lấy dữ liệu từng cột
    $username = trim($row[$colIndex['username']]);
    $fullname = trim($row[$colIndex['fullname']]);
    $password_plain = trim($row[$colIndex['password']]);
    $role = trim($row[$colIndex['role']]);
    $branch_id = (int) $row[$colIndex['branch_id']];
    $must_change_password = 1;

    // Kiểm tra username trống hoặc password trống
    if (!$username || !$password_plain) {
        echo "Bỏ qua dòng có username hoặc password trống\n";
        $errorCount++;
        continue;
    }

    // Kiểm tra xem username đã tồn tại chưa
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        echo "Username '$username' đã tồn tại, bỏ qua.\n";
        $errorCount++;
        $stmt_check->close();
        continue;
    }
    $stmt_check->close();

    // Mã hóa mật khẩu bằng bcrypt
    $hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);

    // Chèn vào DB
    $stmt = $conn->prepare("INSERT INTO users (username, fullname, password, role, branch_id, must_change_password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", $username, $fullname, $hashed_password, $role, $branch_id, $must_change_password);

    if ($stmt->execute()) {
        echo "Đã tạo tài khoản: $username\n";
        $successCount++;
    } else {
        echo "Lỗi khi tạo tài khoản $username: " . $stmt->error . "\n";
        $errorCount++;
    }
    $stmt->close();
}

fclose($handle);
$conn->close();

echo "\nHoàn thành. Tạo mới $successCount tài khoản, $errorCount lỗi.\n";
?>
