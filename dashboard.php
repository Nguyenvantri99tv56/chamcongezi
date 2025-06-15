<?php
session_start();
if (!isset($_SESSION["user"])) {
  header("Location: index.php");
  exit();
}

require_once 'config.php';

$user = $_SESSION["user"];

// Kết nối DB để lấy số lượng chấm công chờ duyệt (status = 0)
// Số liệu chỉ lấy cho cơ sở người dùng thuộc
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
  die("Lỗi kết nối CSDL: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$pendingCount = 0;
if (in_array($user['role'], ['manager', 'ceo', 'accountant', 'it'])) {
  // Với manager thì chỉ lấy chấm công chờ duyệt của cơ sở mình
  if ($user['role'] === 'manager') {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM timekeeping t JOIN users u ON t.user_id = u.id WHERE t.status = 0 AND u.branch_id = ?");
    $stmt->bind_param("i", $user['branch_id']);
  } else {
    // Với ceo, accountant, it lấy tất cả cơ sở
    $stmt = $conn->prepare("SELECT COUNT(*) FROM timekeeping WHERE status = 0");
  }
  $stmt->execute();
  $stmt->bind_result($pendingCount);
  $stmt->fetch();
  $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - EZI COFFEE & TEA</title>
  <style>
    /* Reset */
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f7fa;
      color: #333;
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: #2563eb;
      color: white;
      display: flex;
      flex-direction: column;
      padding-top: 20px;
      flex-shrink: 0;
      transition: width 0.3s ease;
    }
    .sidebar h2 {
      text-align: center;
      margin-bottom: 30px;
      font-weight: 700;
      letter-spacing: 1.2px;
      font-size: 24px;
      white-space: nowrap;
    }
    .nav-link {
      padding: 15px 25px;
      color: white;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      white-space: nowrap;
    }
    .nav-link:hover {
      background-color: #1e40af;
    }
    .nav-link.active {
      background-color: #1e3a8a;
    }

    /* Main content */
    .main-content {
      flex: 1;
      padding: 30px 40px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      transition: padding 0.3s ease;
    }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 10px;
    }
    .header .welcome {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      flex-grow: 1;
      min-width: 200px;
    }
    .header .user-info {
      font-size: 16px;
      color: #555;
    }
    .header .logout-btn {
      background-color: #ef4444;
      color: white;
      border: none;
      padding: 8px 14px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      margin-left: 15px;
      transition: background-color 0.3s ease;
      white-space: nowrap;
    }
    .header .logout-btn:hover {
      background-color: #b91c1c;
    }

    /* Cards container */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(200px,1fr));
      gap: 20px;
    }

    /* Card */
    .card {
      background-color: white;
      border-radius: 12px;
      padding: 25px 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      text-align: center;
      cursor: pointer;
      transition: box-shadow 0.3s ease;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 12px;
      user-select: none;
      position: relative;
    }
    .card:hover {
      box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    }
    .card .icon {
      font-size: 40px;
      color: #2563eb;
    }
    .card .title {
      font-size: 18px;
      font-weight: 700;
      color: #111827;
    }
    .card .badge {
      position: absolute;
      top: 10px;
      right: 15px;
      background: #ef4444;
      color: white;
      font-size: 12px;
      font-weight: 700;
      border-radius: 50%;
      padding: 3px 8px;
      min-width: 20px;
      text-align: center;
      line-height: 18px;
      box-shadow: 0 0 5px rgba(0,0,0,0.2);
      user-select: none;
      pointer-events: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
      /* Thu nhỏ sidebar */
      .sidebar {
        width: 60px;
        padding-top: 15px;
      }
      .sidebar h2 {
        font-size: 0;
        margin-bottom: 15px;
      }
      .nav-link {
        justify-content: center;
        padding: 15px 0;
        gap: 0;
      }
      .nav-link span.title {
        display: none;
      }

      .main-content {
        padding: 20px 15px;
      }
      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      .header .welcome {
        font-size: 20px;
      }
      .header .user-info {
        font-size: 14px;
      }
      .header .logout-btn {
        margin-left: 0;
        width: 100%;
        padding: 10px 0;
        font-size: 16px;
      }

      .cards {
        grid-template-columns: repeat(auto-fit,minmax(140px,1fr));
        gap: 15px;
      }
      .card .icon {
        font-size: 32px;
      }
      .card .title {
        font-size: 16px;
      }
    }
  </style>
</head>
<body>

  <aside class="sidebar">
    <h2>EZI Coffee</h2>
    <a href="dashboard.php" class="nav-link active"><span class="icon">🏠</span><span class="title">Trang Chủ</span></a>
    <?php if ($user['role'] === 'staff'): ?>
      <a href="pages/chamcong.php" class="nav-link"><span class="icon">🕘</span><span class="title">Chấm công</span></a>
      <a href="pages/bangcong.php" class="nav-link"><span class="icon">📅</span><span class="title">Xem bảng công</span></a>
    <?php elseif ($user['role'] === 'manager'): ?>
      <a href="pages/bangcong.php" class="nav-link"><span class="icon">📋</span><span class="title">Quản lý bảng công</span></a>
      <a href="pages/duyet_chamcong.php" class="nav-link"><span class="icon">✅</span><span class="title">Duyệt chấm công</span></a>
      <a href="pages/create_account.php" class="nav-link"><span class="icon">➕</span><span class="title">Tạo tài khoản</span></a>
    <?php elseif (in_array($user['role'], ['ceo', 'accountant', 'it'])): ?>
      <a href="pages/bangcong.php" class="nav-link"><span class="icon">📊</span><span class="title">Bảng công</span></a>
      <a href="pages/nhanvien.php" class="nav-link"><span class="icon">👥</span><span class="title">Quản lý nhân viên</span></a>
      <a href="pages/duyet_chamcong.php" class="nav-link"><span class="icon">✅</span><span class="title">Duyệt chấm công</span></a>
      <a href="pages/create_account.php" class="nav-link"><span class="icon">➕</span><span class="title">Tạo tài khoản</span></a>
    <?php endif; ?>
  </aside>

  <main class="main-content">
    <div class="header">
      <div class="welcome">Xin chào, <?php echo htmlspecialchars($user['fullname']); ?>!</div>
      <div>
        <span class="user-info"><?php echo htmlspecialchars(ucfirst($user['role'])); ?> | Cơ sở: <?php echo htmlspecialchars($user['branch_name'] ?? 'Tất cả'); ?></span>
        <button class="logout-btn" onclick="window.location.href='logout.php'">Đăng xuất</button>
      </div>
    </div>

    <div class="cards">
      <?php if ($user['role'] === 'staff'): ?>
        <div class="card" onclick="window.location.href='pages/chamcong.php'">
          <div class="icon">🕘</div>
          <div class="title">Chấm công</div>
        </div>
        <div class="card" onclick="window.location.href='pages/bangcong.php'">
          <div class="icon">📅</div>
          <div class="title">Xem bảng công</div>
        </div>
      <?php elseif ($user['role'] === 'manager'): ?>
        <div class="card" onclick="window.location.href='pages/bangcong.php'">
          <div class="icon">📋</div>
          <div class="title">Quản lý bảng công</div>
        </div>
        <div class="card" onclick="window.location.href='pages/duyet_chamcong.php'" style="position: relative;">
          <div class="icon">✅</div>
          <?php if ($pendingCount > 0): ?>
            <span class="badge"><?php echo $pendingCount; ?></span>
          <?php endif; ?>
          <div class="title">Duyệt chấm công</div>
        </div>
        <div class="card" onclick="window.location.href='pages/create_account.php'">
          <div class="icon">➕</div>
          <div class="title">Tạo tài khoản</div>
        </div>
      <?php elseif (in_array($user['role'], ['ceo', 'accountant', 'it'])): ?>
        <div class="card" onclick="window.location.href='pages/bangcong.php'">
          <div class="icon">📊</div>
          <div class="title">Bảng công</div>
        </div>
        <div class="card" onclick="window.location.href='pages/nhanvien.php'">
          <div class="icon">👥</div>
          <div class="title">Quản lý nhân viên</div>
        </div>
        <div class="card" onclick="window.location.href='pages/duyet_chamcong.php'" style="position: relative;">
          <div class="icon">✅</div>
          <?php if ($pendingCount > 0): ?>
            <span class="badge"><?php echo $pendingCount; ?></span>
          <?php endif; ?>
          <div class="title">Duyệt chấm công</div>
        </div>
        <div class="card" onclick="window.location.href='pages/create_account.php'">
          <div class="icon">➕</div>
          <div class="title">Tạo tài khoản</div>
        </div>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
