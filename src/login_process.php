
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {

    // 🔥 เช็คสถานะก่อนให้เข้า
    if ($user['status'] !== 'approved') {
        $_SESSION['login_error'] = "บัญชีของคุณรอการอนุมัติจากผู้ดูแลระบบ";
        header("Location: login.php");
        exit;
    }

    // อนุมัติแล้ว → เข้าใช้งานได้
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    header("Location: dashboard.php");
    exit;

} else {
    $_SESSION['login_error'] = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    header("Location: login.php");
    exit;
}
