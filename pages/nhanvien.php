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

// Phân quyền
if ($role === 'staff') {
    die("Bạn không có quyền truy cập trang này.");
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Lỗi kết nối: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Lấy bộ lọc
$filter_branch = '';
$filter_name = '';

if (in_array($role, ['ceo', 'it', 'accountant'])) {
    $filter_branch = $_GET['branch'] ?? '';
    $filter_name = $_GET['fullname'] ?? '';
} elseif ($role === 'manager') {
    $filter_branch = $branch_id; // manager chỉ xem nhân viên cùng cơ sở
}

// Build điều kiện truy vấn
$whereClauses = [];
$params = [];
$types = '';

if ($filter_branch !== '') {
    $whereClauses[] = 'branch_id = ?';
    $params[] = $filter_branch;
    $types .= 'i';
}

if ($filter_name !== '') {
    $whereClauses[] = 'fullname LIKE ?';
    $params[] = "%$filter_name%";
    $types .= 's';
}

$where = '';
if (count($whereClauses) > 0) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Lấy danh sách nhân viên
$sql = "SELECT id, username, fullname, role, branch_id, salary_per_hour FROM users $where ORDER BY fullname ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Lấy danh sách cơ sở cho filter
$branches = [];
if (in_array($role, ['ceo', 'it', 'accountant'])) {
    $resBranches = $conn->query("SELECT id, name FROM branches ORDER BY name");
    while ($b = $resBranches->fetch_assoc()) {
        $branches[] = $b;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Quản lý nhân viên - EZI COFFEE & TEA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f9fafb;
      margin: 0; padding: 20px;
      min-height: 100vh;
      box-sizing: border-box;
    }
    h1 {
      text-align: center;
      margin-bottom: 20px;
      color: #111827;
    }
    form.filter-form {
      max-width: 900px;
      margin: 0 auto 20px auto;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: flex-start;
    }
    form.filter-form label {
      font-weight: 700;
      margin-right: 6px;
      align-self: center;
    }
    form.filter-form select,
    form.filter-form input[type="text"] {
      padding: 8px 12px;
      font-size: 16px;
      border-radius: 6px;
      border: 1px solid #ccc;
      min-width: 180px;
      box-sizing: border-box;
    }
    form.filter-form button {
      background-color: #2563eb;
      border: none;
      color: white;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 700;
      font-size: 16px;
      transition: background-color 0.3s ease;
      align-self: center;
      white-space: nowrap;
    }
    form.filter-form button:hover {
      background-color: #1d4ed8;
    }
    .table-wrapper {
      max-width: 900px;
      margin: 0 auto;
      overflow-x: auto;
      background: white;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
    }
    th, td {
      padding: 12px 10px;
      border-bottom: 1px solid #ddd;
      text-align: left;
      font-size: 15px;
    }
    th {
      background-color: #2563eb;
      color: white;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    tr:hover {
      background-color: #f1f5f9;
    }
    td.actions a {
      background-color: #2563eb;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
      transition: background-color 0.3s ease;
    }
    td.actions a:hover {
      background-color: #1d4ed8;
    }

    /* Responsive */
    @media (max-width: 768px) {
      form.filter-form {
        flex-direction: column;
        max-width: 100%;
      }
      form.filter-form select,
      form.filter-form input[type="text"],
      form.filter-form button {
        min-width: 100%;
      }
      table {
        min-width: 100%;
        font-size: 14px;
      }
      th, td {
        padding: 10px 8px;
      }
      td.actions {
        flex-direction: column;
        gap: 6px;
      }
      td.actions a {
        width: 100%;
        padding: 8px 0;
        font-size: 16px;
      }
    }
  </style>
</head>
<body>

  <h1>Quản lý nhân viên</h1>

  <?php if (in_array($role, ['ceo', 'it', 'accountant'])): ?>
  <form method="GET" class="filter-form" novalidate>
    <label for="branch">Cơ sở:</label>
    <select name="branch" id="branch">
      <option value="">-- Tất cả --</option>
      <?php foreach ($branches as $b): ?>
        <option value="<?php echo htmlspecialchars($b['id']); ?>" <?php if ($filter_branch == $b['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($b['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="fullname">Tên nhân viên:</label>
    <input type="text" name="fullname" id="fullname" placeholder="Tìm theo tên" value="<?php echo htmlspecialchars($filter_name); ?>" />

    <button type="submit">Tìm kiếm</button>
  </form>
  <?php endif; ?>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Họ tên</th>
          <th>Tên đăng nhập</th>
          <th>Vai trò</th>
          <th>Cơ sở</th>
          <th>Lương giờ (đồng)</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="6" style="text-align:center;">Không có nhân viên nào.</td></tr>
      <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['username']); ?></td>
            <td><?php echo htmlspecialchars($row['role']); ?></td>
            <td>
              <?php
                $resBranchName = $conn->prepare("SELECT name FROM branches WHERE id = ?");
                $resBranchName->bind_param("i", $row['branch_id']);
                $resBranchName->execute();
                $resBranchName->bind_result($branchName);
                $resBranchName->fetch();
                echo htmlspecialchars($branchName);
                $resBranchName->close();
              ?>
            </td>
            <td><?php echo number_format($row['salary_per_hour']); ?></td>
            <td class="actions">
              <a href="sua_nhanvien.php?id=<?php echo $row['id']; ?>">Sửa</a>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

</body>
</html>
