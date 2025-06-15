<?php
$conn = new mysqli("sql307.infinityfree.com", "if0_37824703", "Dmx123457", "if0_37824703_ezicore");

if ($conn->connect_error) {
  die("Kết nối thất bại: " . $conn->connect_error);
}

echo "✅ Kết nối thành công!";
$conn->close();
?>
