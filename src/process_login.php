<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
  if (password_verify($password, $user['password'])) {
    session_start();
    $_SESSION['user_id'] = $user['id'];
    header("Location: dashboard.php");
  } else {
    echo "รหัสผ่านไม่ถูกต้อง";
  }
} else {
  echo "ไม่พบผู้ใช้งาน";
}// หลังจากตรวจสอบรหัสผ่านผ่านแล้ว
if ($user['status'] !== 'approved') {
    header("Location: login.php?error=รอการอนุมัติจากผู้ดูแลระบบ");
    exit();
}
// ... ค่อยสร้าง $_SESSION['user_id'] ...
?>
