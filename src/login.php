<?php
session_start();

// เรียกใช้การเชื่อมต่อฐานข้อมูล เพื่อดึงฟังก์ชัน writeLog มาใช้
require_once 'config/db.php';

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (isset($_SESSION['user_id'])) {
    header("Location: home.php"); 
    exit;
}

// 🟢 บันทึก Log: มีผู้เข้าชมหน้า Login (สถานะเป็น Guest)
writeLog($conn, null, 'Guest', 'View Page', 'เปิดหน้าเข้าสู่ระบบ (login.php)');

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri('http://localhost:8000/src/callback.php');
$client->addScope("email");
$client->addScope("profile");

$loginUrl = $client->createAuthUrl();

$login_errors = $_SESSION['login_errors'] ?? [];

// 🟢 บันทึก Log: กรณีมี Error แจ้งเตือนจากการพยายามล็อกอิน
if (!empty($login_errors)) {
    $error_details = implode(", ", $login_errors);
    writeLog($conn, null, 'Guest', 'Login Error', 'พบข้อผิดพลาด: ' . $error_details);
}
unset($_SESSION['login_errors']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(to right, #0f2027, #203a43, #2c5364); 
            color: white; 
            font-family: "Prompt", sans-serif;
        }
        .login-box { 
            max-width: 410px; 
            margin: 20px auto; 
            padding: 35px; 
            background: rgba(255,255,255,0.08); 
            border-radius: 20px; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.6); 
            backdrop-filter: blur(6px);
        }
        h1 {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        h2 {
            font-size: 22px;
            margin-bottom: 25px;
        }
        .welcome {
            font-size: 14px;
            margin-bottom: 30px;
            color: #ddd;
        }
        .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 15px;
        }
        .form-control::placeholder {
            color: rgba(255,255,255,0.6);
        }
        .form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.5);
            color: white;
            box-shadow: none;
        }
        .btn-login {
            background: #2196F3;
            color: white;
            font-weight: 500;
            padding: 12px;
            border-radius: 10px;
            transition: 0.3s;
            border: none;
        }
        .btn-login:hover {
            background: #0b7dda;
            transform: translateY(-2px);
            color: white;
        }
        .divider {
            margin: 25px 0;
            text-align: center;
            color: #aaa;
        }
        .divider::before,
        .divider::after {
            content: '';
            display: inline-block;
            width: 40%;
            height: 1px;
            background: rgba(255,255,255,0.3);
            vertical-align: middle;
            margin: 0 10px;
        }
        .btn-google {
            background: #fff;
            color: #444;
            font-weight: 500;
            padding: 10px;
            border-radius: 50px;
            transition: 0.3s;
            border: none;
        }
        .btn-google:hover {
            background: #f1f1f1;
            transform: translateY(-2px);
            color: #444;
        }
        .btn-google img {
            width: 22px; 
            margin-right: 8px;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #ddd;
        }
        .register-link a {
            color: #4CAF50;
            text-decoration: none;
            font-weight: 500;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid #ff6b6b;
            color: #ff8787;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .dept-logo {
            display: block;
            margin: 0 auto 20px;
            max-width: 90px;
            transition: transform 0.3s ease;
        }
        .dept-logo:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="login-box">
        <?php if (!empty($login_errors)): ?>
            <div class="alert-error">
                <?php foreach ($login_errors as $error): ?>
                    <div>• <?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="https://ced.kmutnb.ac.th/" target="_blank" title="ไปที่เว็บไซต์ภาควิชา">
            <img src="img/comedu.png" alt="ภาควิชาคอมพิวเตอร์ศึกษา" class="dept-logo">
        </a>

        <h1 class="text-center">ระบบจัดการตารางเรียน</h1>
        <h2 class="text-center">ภาควิชาคอมพิวเตอร์ศึกษา</h2>
        <p class="welcome text-center">ยินดีต้อนรับเข้าสู่เว็บไซต์จัดตารางเรียน</p>
        
        <form action="process_login_manual.php" method="POST">
            <input type="email" name="email" class="form-control" placeholder="อีเมล" required>
            <input type="password" name="password" class="form-control" placeholder="รหัสผ่าน" required>
            <button type="submit" class="btn btn-login w-100">เข้าสู่ระบบ</button>
        </form>

        <div class="divider">หรือ</div>

        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-google w-100 d-flex align-items-center justify-content-center">
            <img src="img/google-icon.png" alt="Google Logo">
            Sign in with Google
        </a> 

        <div class="register-link">
            ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิกใหม่</a>
        </div>
        <div class="register-link"><a href="index.php">ย้อนกลับ</a></div>
    </div>
</body>
</html>