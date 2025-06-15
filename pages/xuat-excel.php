<?php
// Bật hiển thị lỗi
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kiểm tra quyền người dùng (CEO, IT, Kế toán có quyền xuất)
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: ../index.php");
    exit();
}

$user = $_SESSION["user"];
$role = $user["role"];

if (!in_array($role, ['ceo', 'it', 'accountant'])) {
    die("Bạn không có quyền xuất bảng công.");
}

// Kết nối cơ sở dữ liệu
$conn = new mysqli("sql307.infinityfree.com", "if0_37824703", "Dmx123457", "if0_37824703_ezicore");
$conn->set_charset("utf8mb4");

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->connect_error);
}

// Lấy tham số tháng và năm từ URL (hoặc mặc định là tháng và năm hiện tại)
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Truy vấn dữ liệu
$sql = "
  SELECT tk.id, tk.user_id, tk.work_date, tk.check_in, tk.break_start, tk.break_end, tk.check_out, tk.working_hours,
         u.fullname, u.salary_per_hour, u.branch_id
  FROM timekeeping tk
  JOIN users u ON tk.user_id = u.id
  WHERE MONTH(tk.work_date) = ? AND YEAR(tk.work_date) = ?
";

$params = [$month, $year];
$types = "ii";

// Lọc theo cơ sở nếu là CEO, IT hoặc Kế toán
$branch_id = $_GET['branch_id'] ?? null;
if ($branch_id) {
    $sql .= " AND u.branch_id = ?";
    $params[] = $branch_id;
    $types .= "i";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Thiết lập tiêu đề cho file CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="Bang_cong_' . $month . '_' . $year . '.csv"');
header('Cache-Control: max-age=0');

// Mở file CSV và ghi dữ liệu
$output = fopen('php://output', 'w');

// Ghi tiêu đề cột
fputcsv($output, ['Họ tên', 'Ngày làm', 'Check In', 'Nghỉ giữa ca', 'Check Out', 'Giờ công', 'Lương', 'Thành tiền']);

// Ghi dữ liệu vào CSV
while ($row = $result->fetch_assoc()) {
    $data = [
        $row['fullname'],
        date('d/m/Y', strtotime($row['work_date'])),
        $row['check_in'],
        ($row['break_start'] && $row['break_end']) ? $row['break_start'] . ' - ' . $row['break_end'] : '-',
        $row['check_out'],
        $row['working_hours'],
        number_format($row['salary_per_hour']),
        number_format($row['salary_per_hour'] * $row['working_hours'])
    ];
    fputcsv($output, $data);
}

// Đóng file CSV
fclose($output);
exit;
?>
