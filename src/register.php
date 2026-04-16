<?php
// register.php
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

// --- ส่วนเพิ่ม: เตรียมลิงก์ Google Login ---
require_once 'vendor/autoload.php';
require_once 'config/db.php'; 

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri('http://localhost:8000/src/callback.php');
$client->addScope("email");
$client->addScope("profile");

$loginUrl = $client->createAuthUrl();
// ----------------------------------------
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for membership</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: "Prompt", sans-serif;
            padding: 20px; /* ป้องกันขอบติดจอเวลาจอย่อ */
        }

        .container {
            width: 100%;
            max-width: 420px; /* ลดความกว้างลงเพื่อให้ดูสมส่วน */
            padding: 30px 35px; /* ปรับ Padding ให้กระชับ */
            background: rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            text-align: center;
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        h2 { font-size: 1.6rem; font-weight: 600; margin-bottom: 5px; }
        .sub-title { font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 25px; }

        .form-label {
            color: #e0e0e0;
            font-size: 0.9rem;
            margin-bottom: 6px;
            font-weight: 400;
        }

        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            border-radius: 10px;
            padding: 10px 15px; /* ลดความสูง Input ลงเล็กน้อย */
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255,255,255,0.12);
            border-color: #4CAF50;
            color: white;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
        }

        .form-control::placeholder { color: rgba(255,255,255,0.3); font-size: 0.9rem; }
        
        /* Dropdown options color */
        option { color: #333; }

        .btn-register {
            background: linear-gradient(90deg, #4CAF50, #43a047);
            color: white;
            font-weight: 500;
            padding: 10px;
            border-radius: 10px;
            transition: 0.3s;
            border: none;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
            font-size: 1rem;
            margin-top: 10px;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(76, 175, 80, 0.4);
            background: linear-gradient(90deg, #43a047, #388e3c);
        }
        
        .btn-google {
            background: white;
            color: #444;
            font-weight: 500;
            padding: 10px;
            border-radius: 50px;
            transition: 0.2s;
            border: none;
            font-size: 0.95rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .btn-google:hover {
            background: #f1f3f4;
            transform: translateY(-1px);
            color: #222;
        }
        .btn-google img { width: 20px; margin-right: 10px; }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: rgba(255,255,255,0.5);
            font-size: 0.85rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }
        .divider:not(:empty)::before { margin-right: .8em; }
        .divider:not(:empty)::after { margin-left: .8em; }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff8fa3;
            font-size: 0.85rem;
            text-align: left;
            border-radius: 8px;
            padding: 10px 15px;
        }

        .login-link { margin-top: 25px; color: rgba(255,255,255,0.6); font-size: 0.9em; }
        .login-link a { color: #66bb6a; text-decoration: none; font-weight: 500; transition: 0.2s; }
        .login-link a:hover { color: #81c784; text-decoration: underline; }

        /* SweetAlert Customization */
        div:where(.swal2-container) div:where(.swal2-popup) {
            background: rgba(30, 35, 45, 0.95) !important;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px !important;
        }
        div:where(.swal2-container) h2:where(.swal2-title) { color: #fff !important; }
        div:where(.swal2-container) div:where(.swal2-html-container) { color: #ccc !important; }
    </style>
</head>
<body>
    <div class="container">
        <h2>สมัครสมาชิกใหม่</h2>
        <p class="sub-title">ระบบจัดตารางเรียน ภาควิชาคอมพิวเตอร์ศึกษา</p>
        
        <?php if (isset($_SESSION['register_errors'])): ?>
            <div class="alert alert-danger mb-4" role="alert">
                <ul class="mb-0 ps-3">
                    <?php 
                    foreach ($_SESSION['register_errors'] as $error) {
                        echo "<li>$error</li>";
                    }
                    unset($_SESSION['register_errors']);
                    ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-google w-100 d-flex align-items-center justify-content-center">
            <img src="img/google-icon.png" alt="Google Logo">
            สมัครด้วยบัญชี Google
        </a>

        <div class="divider">หรือ สมัครด้วยอีเมลทั่วไป</div>

        <form action="process_register.php" method="POST" id="registerForm">
            
            <div class="mb-3 text-start">
                <label for="name" class="form-label">ชื่อ - นามสกุล</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="ชื่อจริงและนามสกุล" required>
            </div>
            
            <div class="mb-3 text-start">
                <label for="email" class="form-label">อีเมล</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="example@email.com" required>
            </div>
            
            <div class="mb-3 text-start">
                <label for="group" class="form-label">กลุ่มเรียน / สาขา</label>
                <select id="group" name="group" class="form-select" required>
                    <option value="" selected disabled>-- เลือกกลุ่ม --</option>
                    <option value="ครุศาสตร์ไฟฟ้า">ครุศาสตร์ไฟฟ้า</option>
                    <option value="ครุศาสตร์โยธา">ครุศาสตร์โยธา</option>
                    <option value="คอมพิวเตอร์ศึกษา">คอมพิวเตอร์ศึกษา</option>
                    <option value="บริหารเทคนิคศึกษา">บริหารเทคนิคศึกษา</option>
                    <option value="เทคโนโลยีและสารสนเทศ">เทคโนโลยีและสารสนเทศ</option>
                    <option value="ครุศาสตร์เครื่องกล">ครุศาสตร์เครื่องกล</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3 text-start">
                    <label for="password" class="form-label">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="6+ ตัวอักษร" required minlength="6">
                </div>
                <div class="col-md-6 mb-3 text-start">
                    <label for="password_confirm" class="form-label">ยืนยันรหัสผ่าน</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="กรอกอีกครั้ง" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-register w-100 btn-lg">ลงทะเบียน</button>
        </form>

        <div class="login-link">
            มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
        </div>
        <div class="login-link">
            <a href="index.php">ย้อนกลับ</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const pass = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (pass !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'รหัสผ่านไม่ตรงกัน',
                    text: 'กรุณากรอกรหัสผ่านให้ตรงกันทั้งสองช่อง',
                    background: '#1e1e2d',
                    color: '#fff',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'แก้ไขข้อมูล'
                });
            }
        });

        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');

        if (status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'ลงทะเบียนสำเร็จ!',
                html: `
                    <div style="font-size: 1rem; line-height: 1.6; color: #d1d5db;">
                        ข้อมูลของคุณถูกบันทึกเรียบร้อยแล้ว<br>
                        <span style="color: #4CAF50; font-weight: 500;">กรุณารอการอนุมัติสิทธิ์</span> จากแอดมิน<br>
                        ก่อนเริ่มใช้งานระบบ
                    </div>
                `,
                background: '#1e1e2d',
                color: '#fff',
                confirmButtonColor: '#4CAF50',
                confirmButtonText: 'ตกลง',
                backdrop: `rgba(0,0,0,0.7) backdrop-filter: blur(5px)`,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'login.php';
                }
            });
        }
    </script>
</body>
</html>