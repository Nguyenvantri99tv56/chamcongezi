<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION["user"]["role"] !== "staff") {
  header("Location: ../index.php");
  exit();
}

$user = $_SESSION["user"];
date_default_timezone_set('Asia/Ho_Chi_Minh');

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$now = date('H:i');

// Kiểm tra nếu chọn hôm qua mà đã quá 12h trưa hôm nay
$allow_yesterday = (intval(date('H')) < 12);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Chấm công - EZI COFFEE & TEA</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f9fafb;
      margin: 0;
      padding: 20px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center; /* căn giữa theo chiều ngang */
    }

    .header {
      text-align: center;
      margin-bottom: 20px;
      width: 100%;
      max-width: 500px;
    }

    h1 {
      margin: 0 auto;
      font-size: 28px;
      color: #2563eb;
    }

    .login-container {
      background: white;
      padding: 30px 25px;
      border-radius: 12px;
      box-shadow: 0 0 25px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 500px;
      box-sizing: border-box;
    }

    form label {
      font-weight: 600;
      font-size: 14px;
      color: #2a2a2a;
      margin-top: 15px;
      display: block;
    }

    form input[type="time"],
    form select,
    form input[type="text"] {
      width: 100%;
      padding: 12px 15px;
      font-size: 16px;
      margin-top: 5px;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-sizing: border-box;
      color: #333;
      transition: border-color 0.3s ease;
    }

    form input[type="time"]:focus,
    form select:focus,
    form input[type="text"]:focus {
      outline: none;
      border-color: #fbc02d;
      box-shadow: 0 0 5px #fbc02d;
    }

    form button {
      margin-top: 25px;
      width: 100%;
      padding: 14px 0;
      font-size: 18px;
      font-weight: 700;
      color: white;
      background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    form button:hover {
      background: linear-gradient(90deg, #1d4ed8 0%, #2563eb 100%);
    }

    .loading {
      text-align: center;
      margin-top: 20px;
      display: none;
    }

    .popup {
      display: none;
      position: fixed;
      top: 30%;
      left: 50%;
      transform: translate(-50%, -30%);
      background: #fff;
      border: 1px solid #ccc;
      padding: 20px 30px;
      box-shadow: 0 0 10px #888;
      z-index: 9999;
      text-align: center;
    }

    .popup button {
      margin-top: 10px;
    }

    /* Responsive */
    @media (max-width: 600px) {
      body {
        padding: 15px 10px;
      }
      .login-container {
        padding: 25px 20px;
        width: 100%;
        max-width: 100%;
      }
      h1 {
        font-size: 26px;
      }
      form label {
        font-size: 15px;
        margin-top: 12px;
      }
      form input[type="time"],
      form select,
      form input[type="text"] {
        font-size: 17px;
        padding: 14px 16px;
        margin-bottom: 20px;
      }
      form button {
        font-size: 18px;
        padding: 16px 0;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>Chấm công - EZI COFFEE & TEA</h1>
  </div>

  <div class="login-container">
    <form id="chamcongForm" method="POST">
      <label>Họ và tên:</label>
      <input type="text" value="<?php echo htmlspecialchars($user['fullname']); ?>" readonly>

      <label>Ngày chấm công:</label>
      <select name="work_date" id="work_date" required>
        <option value="<?php echo $today; ?>">Hôm nay (<?php echo $today; ?>)</option>
        <?php if ($allow_yesterday): ?>
          <option value="<?php echo $yesterday; ?>">Hôm qua (<?php echo $yesterday; ?>)</option>
        <?php endif; ?>
      </select>

      <label>Giờ vào ca:</label>
      <input type="time" name="check_in" required>

      <label>Nghỉ giữa ca (không bắt buộc):</label>
      <div style="display: flex; gap: 10px;">
        <input type="time" name="break_start">
        <span>→</span>
        <input type="time" name="break_end">
      </div>

      <label>Giờ ra ca:</label>
      <input type="time" name="check_out" required>

      <button type="submit">Chấm công</button>

      <div class="loading" id="loading">
        <p>Đang xử lý... vui lòng chờ</p>
        <img src="https://i.imgur.com/llF5iyg.gif" width="40" alt="loading...">
      </div>
    </form>
  </div>

  <div class="popup" id="popup">
    <p id="popupText"></p>
    <button onclick="window.location.href='../dashboard.php'">Xác nhận</button>
  </div>

  <script>
    const form = document.getElementById('chamcongForm');
    const loading = document.getElementById('loading');
    const popup = document.getElementById('popup');
    const popupText = document.getElementById('popupText');

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      loading.style.display = 'block';

      const formData = new FormData(form);
      fetch('xuly_chamcong.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        loading.style.display = 'none';
        if (data.status === 'ok') {
          popupText.innerHTML = `<strong><?php echo $user['fullname']; ?></strong><br>✅ Hoàn thành chấm công<br>Tổng số giờ công: ${data.hours} giờ`;
          popup.style.display = 'block';
          setTimeout(() => {
            window.location.href = '../dashboard.php';
          }, 4000);
        } else {
          alert(data.message);
        }
      });
    });
  </script>
</body>
</html>
