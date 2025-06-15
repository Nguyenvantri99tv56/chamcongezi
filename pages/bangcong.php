<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../index.php');
    exit;
}
require_once '../config.php';

$user = $_SESSION['user'];

$fullnameFilter = $_GET['fullname'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$branchFilter = $_GET['branch'] ?? '';

if ($user['role'] === 'staff') {
    $branchFilter = $user['branch_id'];
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Kết nối DB thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

function safeHtml($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$whereClauses = [];
$params = [];
$types = '';

if ($user['role'] == 'staff') {
    $whereClauses[] = 't.user_id = ?';
    $params[] = $user['id'];
    $types .= 'i';
} elseif ($user['role'] == 'manager') {
    $whereClauses[] = 'u.branch_id = ?';
    $params[] = $user['branch_id'];
    $types .= 'i';
} else {
    if (!empty($fullnameFilter)) {
        $whereClauses[] = "u.fullname LIKE ?";
        $params[] = "%$fullnameFilter%";
        $types .= 's';
    }
    if (!empty($branchFilter)) {
        $whereClauses[] = "u.branch_id = ?";
        $params[] = $branchFilter;
        $types .= 'i';
    }
}

if ($startDate && $endDate) {
    $whereClauses[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
} else {
    $whereClauses[] = "t.work_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()";
}

$where = '';
if (count($whereClauses) > 0) {
    $where = 'WHERE ' . implode(' AND ', $whereClauses);
}

$sql = "SELECT 
    t.work_date, 
    u.fullname, 
    t.check_in, t.break_start, t.break_end, t.check_out, t.working_hours,
    b.name AS branch_name,
    u.salary_per_hour,
    t.status
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

$rows = [];
$total_hours = 0;
$total_wage = 0;
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
    $total_hours += floatval($row['working_hours']);
    if ($row['status'] == 1) {
        $total_wage += floatval($row['working_hours']) * floatval($row['salary_per_hour']);
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bảng công</title>
  <link rel="stylesheet" href="../assets/bangcong.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
  <style>
    /* Nav buttons đồng bộ các trang */
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
    @media (max-width: 768px) {
      .nav-buttons {
        justify-content: center;
      }
      .nav-buttons button {
        padding: 8px 14px;
        font-size: 14px;
      }
    }
  </style>
</head>
<body>
  <div class="nav-buttons">
    <button onclick="window.location.href='../dashboard.php'">🏠 Trang Chủ</button>
    <button onclick="history.back()">🔙 Quay Lại</button>
  </div>

  <div class="content">
    <h1>Bảng công</h1>

    <form method="get" class="filter-inline" id="filterForm" style="align-items: flex-start;">
      <?php if (in_array($user['role'], ['ceo', 'it', 'accountant', 'manager'])): ?>
        <label for="branch">Cơ sở:</label>
        <select name="branch" id="branch" style="min-width: 180px;" required>
          <option value="">--Chọn cơ sở--</option>
          <?php
            $resBranches = $conn->query("SELECT id, name FROM branches");
            while ($branch = $resBranches->fetch_assoc()) {
              $selected = ($branchFilter == $branch['id']) ? 'selected' : '';
              echo '<option value="'.safeHtml($branch['id']).'" '.$selected.'>'.safeHtml($branch['name']).'</option>';
            }
          ?>
        </select>
      <?php else: ?>
        <input type="hidden" name="branch" value="<?php echo safeHtml($branchFilter); ?>" />
      <?php endif; ?>

      <?php if (in_array($user['role'], ['ceo', 'it', 'accountant'])): ?>
        <label for="fullname" id="labelFullname">Tên nhân viên:</label>
        <select name="fullname" id="fullname" style="min-width: 180px;">
          <option value="">--Chọn nhân viên--</option>
        </select>
      <?php endif; ?>

      <label for="start_date">Từ ngày:</label>
      <input type="date" name="start_date" id="start_date" value="<?php echo safeHtml($startDate); ?>" required />

      <label for="end_date">Đến ngày:</label>
      <input type="date" name="end_date" id="end_date" value="<?php echo safeHtml($endDate); ?>" required />

      <button type="submit">Lọc</button>
      <div class="export-button-wrapper">
        <?php if (!in_array($user['role'], ['staff'])): ?>
          <button type="button" id="exportButton">Xuất Excel</button>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-wrapper">
      <table id="timekeepingTable">
        <thead>
          <tr>
            <th>Ngày</th>
            <th>Họ tên</th>
            <th>Giờ vào</th>
            <th>Giờ nghỉ bắt đầu</th>
            <th>Giờ nghỉ kết thúc</th>
            <th>Giờ ra</th>
            <th>Số giờ công</th>
            <th>Lương giờ (đồng)</th>
            <th>Lương (đồng)</th>
            <th>Cơ sở</th>
            <th>Trạng thái</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row):
            $wage = ($row['status'] == 1) ? $row['working_hours'] * $row['salary_per_hour'] : 0;
          ?>
            <tr>
              <td><?php echo safeHtml(date('d/m/Y', strtotime($row['work_date']))); ?></td>
              <td><?php echo safeHtml($row['fullname']); ?></td>
              <td><?php echo safeHtml($row['check_in']); ?></td>
              <td><?php echo safeHtml($row['break_start'] ?? ''); ?></td>
              <td><?php echo safeHtml($row['break_end'] ?? ''); ?></td>
              <td><?php echo safeHtml($row['check_out'] ?? ''); ?></td>
              <td><?php echo safeHtml($row['working_hours']); ?></td>
              <td><?php echo number_format($row['salary_per_hour']); ?></td>
              <td><?php echo number_format($wage); ?></td>
              <td><?php echo safeHtml($row['branch_name']); ?></td>
              <td>
                <?php
                  if ($row['status'] == 0) echo '<span style="color:orange; font-weight:bold;">Chờ duyệt</span>';
                  elseif ($row['status'] == 1) echo '<span style="color:green; font-weight:bold;">Đã duyệt</span>';
                  elseif ($row['status'] == 2) echo '<span style="color:red; font-weight:bold;">Từ chối</span>';
                  else echo '<span>Không rõ</span>';
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6" style="text-align: right; font-weight: bold;">Tổng</td>
            <td><?php echo number_format($total_hours, 2); ?></td>
            <td></td>
            <td><?php echo number_format($total_wage); ?></td>
            <td></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <script>
    document.getElementById('branch').addEventListener('change', function() {
      const branchId = this.value;
      const fullnameSelect = document.getElementById('fullname');
      fullnameSelect.innerHTML = '<option value="">--Chọn nhân viên--</option>';
      if (!branchId) return;
      fetch('get_employees.php?branch_id=' + branchId)
        .then(res => res.json())
        .then(data => {
          if (data.length > 0) {
            data.forEach(emp => {
              const option = document.createElement('option');
              option.value = emp.fullname;
              option.textContent = emp.fullname;
              fullnameSelect.appendChild(option);
            });
          }
        })
        .catch(err => console.error('Lỗi tải danh sách nhân viên:', err));
    });

    window.addEventListener('load', function() {
      const branchSelect = document.getElementById('branch');
      if (branchSelect.value) {
        branchSelect.dispatchEvent(new Event('change'));
      }
    });

    document.getElementById('exportButton')?.addEventListener('click', function() {
      var wb = XLSX.utils.table_to_book(document.getElementById('timekeepingTable'), { sheet: "Bảng công" });
      XLSX.writeFile(wb, "bangcong.xlsx");
    });
  </script>
</body>
</html>
