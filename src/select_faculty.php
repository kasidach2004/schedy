<?php
// setup_profile.php (หน้าตั้งค่ารหัสผ่านและเลือกคณะ)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ในสถานการณ์จริง ควรมีการดึงข้อมูลผู้ใช้เพื่อตรวจสอบสถานะการตั้งค่าโปรไฟล์
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Default Profile Settings</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 450px; 
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .form-label {
            color: #ccc;
            float: left;
            margin-bottom: 5px;
            height: 25px;
        }
        .alert-info {
            background-color: rgba(23, 162, 184, 0.2);
            border-color: #17a2b8;
            color: #fff;
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .form-control::placeholder {
            color: #aaa;
        }
        /* เพิ่มสไตล์แจ้งเตือน Error */
        .error-message {
            color: #dc3545;
            text-align: left;
            font-size: 0.9em;
            margin-top: -10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">ตั้งค่าโปรไฟล์เริ่มต้น</h2>
        <div class="alert alert-info mb-4" role="alert">
            โปรดตั้งรหัสผ่านสำรอง (สำหรับล็อกอินโดยตรง) และเลือกคณะของคุณ
        </div>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger mb-4">
                <?php
                $error = $_GET['error'];
                if ($error === 'password_mismatch') {
                    echo "รหัสผ่านไม่ตรงกัน หรือสั้นกว่า 6 ตัวอักษร โปรดตรวจสอบ";
                } elseif ($error === 'missing_fields') {
                    echo "กรุณากรอกข้อมูลให้ครบถ้วน";
                } else {
                    echo "เกิดข้อผิดพลาดในการตั้งค่าโปรไฟล์";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <form action="setup_profile_process.php" method="POST">
            
            <div class="mb-3 text-start">
                <label for="new_password" class="form-label">ตั้งรหัสผ่านใหม่</label>
                <input type="password" class="form-control form-control-lg" id="new_password" name="new_password" required minlength="6" placeholder="รหัสผ่านอย่างน้อย 6 ตัวอักษร">
            </div>

            <div class="mb-4 text-start">
                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน</label>
                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password"  placeholder="กรอกรหัสผ่านอีกครั้ง" required>
            </div>
            
            <div class="mb-4 text-start">
                <label for="faculty_select" class="form-label">เลือกคณะ/กลุ่มงานของคุณ</label>
                <select class="form-select form-select-lg" id="faculty_select" name="faculty" aria-label="เลือกคณะ" required>
                    <option  class="text-black" value="" selected disabled>--- กรุณาเลือกคณะ/กลุ่มงาน ---</option>
                    <option  class="text-black" value="ครุศาสตร์เครื่องกล">ครุศาสตร์เครื่องกล</option>
                    <option  class="text-black" value="ครุศาสตร์ไฟฟ้า">ครุศาสตร์ไฟฟ้า</option>
                    <option  class="text-black" value="ครุศาสตร์โยธา">ครุศาสตร์โยธา</option>
                    <option  class="text-black" value="คอมพิวเตอร์ศึกษา">คอมพิวเตอร์ศึกษา</option>
                    <option  class="text-black" value="เทคโนโลยีและสารสนเทศ">เทคโนโลยีและสารสนเทศ</option>
                    <option  class="text-black" value="บริหารเทคนิคศึกษา">บริหารเทคนิคศึกษา</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 btn-lg">บันทึกและเข้าสู่ระบบ</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            var password = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('รหัสผ่านไม่ตรงกัน โปรดตรวจสอบอีกครั้ง');
            } else if (password.length < 6) {
                e.preventDefault();
                alert('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
            }
        });
    </script>
</body>
</html>