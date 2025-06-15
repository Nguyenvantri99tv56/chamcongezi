<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user'])) {
  header('Location: ../index.php');
  exit;
}
$user = $_SESSION['user'];

if (!in_array($user['role'], ['manager', 'ceo', 'accountant', 'it'])) {
  die('Bạn không có quyền truy cập trang này.');
}

require_once '../config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  die("Lỗi kết nối CSDL: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$branchFilter = '';
if (in_array($user['role'], ['ceo', 'accountant', 'it'])) {
  $branchFilter = $_GET['branch'] ?? '';
} else {
  $branchFilter = $user['branch_id'];
}

$whereClauses = ['t.status = 0'];
$params = [];
$types = '';

if ($branchFilter !== '') {
  $whereClauses[] = 'u.branch_id = ?';
  $params[] = $branchFilter;
  $types .= 'i';
}

$where = 'WHERE ' . implode(' AND ', $whereClauses);

$sql = "SELECT t.id, u.fullname, t.work_date, t.check_in, t.break_start, t.break_end, t.check_out, t.working_hours, b.name as branch_name
        FROM timekeeping t
        JOIN users u ON t.user_id = u.id
        JOIN branches b ON u.branch_id = b.id
        $where
        ORDER BY t.work_date DESC";

$stmt = $conn->prepare($sql);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$branches = [];
if (in_array($user['role'], ['ceo', 'accountant', 'it'])) {
  $resBranches = $conn->query("SELECT id, name FROM branches ORDER BY name");
  while ($row = $resBranches->fetch_assoc()) {
    $branches[] = $row;
  }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <title>Duyệt chấm công</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
      background: #f9fafb;
      margin: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
    }

    .nav-buttons {
      max-width: 1200px;
      width: 100%;
      margin: 15px auto 25px auto;
      display: flex;
      gap: 12px;
      justify-content: flex-start;
    }
    .nav-buttons button {
      padding: 8px 16px;
      background-color: #22c55e;
      border: none;
      border-radius: 6px;
      color: white;
      cursor: pointer;
      font-weight: 600;
      transition: background-color 0.3s;
    }
    .nav-buttons button:hover {
      background-color: #16a34a;
    }

    .content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 15px 40px;
      width: 100%;
    }

    h2 {
      margin-bottom: 20px;
      color: #2563eb;
      text-align: center;
    }

    .filter {
      margin-bottom: 20px;
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      align-items: center;
      justify-content: flex-start;
    }

    .filter label {
      font-weight: bold;
      font-size: 15px;
      margin-right: 6px;
    }

    .filter select {
      padding: 6px 10px;
      font-size: 15px;
      border-radius: 6px;
      border: 1px solid #ccc;
      min-width: 150px;
      cursor: pointer;
    }

    table {
      width: 100%;
      max-width: 1200px;
      border-collapse: collapse;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      overflow-x: auto;
      display: block;
      background-color: white;
    }

    thead th {
      background-color: #2563eb;
      color: white;
      padding: 12px 10px;
      position: sticky;
      top: 0;
      z-index: 10;
      font-weight: 600;
      min-width: 90px;
      text-align: center;
    }

    tbody td {
      border: 1px solid #ddd;
      padding: 10px 8px;
      min-width: 90px;
      text-align: center;
      word-break: break-word;
    }

    button.approve, button.reject {
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      min-width: 80px;
      transition: background-color 0.3s ease;
    }

    button.approve {
      background-color: #22c55e;
      color: white;
      margin-right: 8px;
    }

    button.reject {
      background-color: #ef4444;
      color: white;
    }

    button.approve:hover {
      background-color: #16a34a;
    }

    button.reject:hover {
      background-color: #dc2626;
    }

    /* Responsive cho màn hình nhỏ */
    @media (max-width: 992px) {
      .content {
        max-width: 100%;
        padding: 0 10px 30px;
      }

      .filter {
        justify-content: center;
      }

      table {
        max-width: 100%;
      }

      thead th, tbody td {
        padding: 8px 6px;
        min-width: 70px;
        font-size: 13px;
      }

      button.approve, button.reject {
        padding: 6px 10px;
        font-size: 13px;
        min-width: 70px;
      }
    }

    @media (max-width: 480px) {
      .filter {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
  <script>
    function filterByBranch() {
      const branch = document.getElementById('branchFilter').value;
      const url = new URL(window.location.href);
      if (branch) {
        url.searchParams.set('branch', branch);
      } else {
        url.searchParams.delete('branch');
      }
      window.location.href = url.toString();
    }
  </script>
</head>
<body>
  <div class="nav-buttons">
    <button onclick="window.location.href='../dashboard.php'">🏠 Trang Chủ</button>
    <button onclick="history.back()">🔙 Quay Lại</button>
  </div>

  <div class="content">
    <h2>Duyệt chấm công</h2>

    <?php if (in_array($user['role'], ['ceo', 'accountant', 'it'])): ?>
      <div class="filter">
        <label for="branchFilter">Chọn cơ sở:</label>
        <select id="branchFilter" onchange="filterByBranch()">
          <option value="">--Tất cả cơ sở--</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?php echo htmlspecialchars($b['id']); ?>" <?php if ($branchFilter == $b['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($b['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Họ tên</th>
          <th>Cơ sở</th>
          <th>Ngày làm việc</th>
          <th>Giờ vào</th>
          <th>Giờ nghỉ bắt đầu</th>
          <th>Giờ nghỉ kết thúc</th>
          <th>Giờ ra</th>
          <th>Số giờ công</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr><td colspan="9">Không có bản ghi chờ duyệt.</td></tr>
        <?php else: ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['fullname']); ?></td>
              <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
              <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['work_date']))); ?></td>
              <td><?php echo htmlspecialchars($row['check_in'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['break_start'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['break_end'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['check_out'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['working_hours']); ?></td>
              <td>
                <form method="post" action="duyet_chamcong_xuly.php" style="display:inline-block;">
                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                  <button type="submit" name="action" value="approve" class="approve">Duyệt</button>
                </form>
                <form method="post" action="duyet_chamcong_xuly.php" style="display:inline-block;">
                  <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                  <button type="submit" name="action" value="reject" class="reject">Từ chối</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
