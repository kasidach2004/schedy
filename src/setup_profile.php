<?php
// setup_profile.php
session_start();
// หน้านี้มีไว้เพื่อแสดงผลลัพธ์จาก SweetAlert เท่านั้น ไม่จำเป็นต้องมี Logic PHP ซับซ้อน
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Operation Status</title>
    <link rel="icon" type="image/png" href="img/FTE.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            /* พื้นหลังแบบไล่สีเหมือนหน้า Login ให้ความรู้สึกต่อเนื่อง */
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Kanit', sans-serif;
            overflow: hidden;
        }

        /* ปรับแต่ง SweetAlert2 ให้ดู Modern */
        div:where(.swal2-container) div:where(.swal2-popup) {
            border-radius: 20px !important;
            background: rgba(30, 35, 45, 0.95) !important; /* สีพื้นหลังเข้มโปร่งแสงนิดๆ */
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
            padding: 2em;
        }

        div:where(.swal2-container) h2:where(.swal2-title) {
            color: #fff !important;
            font-weight: 500;
            font-size: 1.6rem;
        }

        div:where(.swal2-container) div:where(.swal2-html-container) {
            color: #b0b3b8 !important;
            font-weight: 300;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        /* ปรับแต่งปุ่มกดใน SweetAlert */
        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            background: linear-gradient(90deg, #0d6efd, #0a58ca) !important;
            border-radius: 50px !important;
            padding: 12px 30px !important;
            font-size: 1rem !important;
            font-weight: 500 !important;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
            transition: transform 0.2s;
        }

        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');

        // การตั้งค่าพื้นฐานของ SweetAlert
        const commonConfig = {
            allowOutsideClick: false,
            backdrop: `rgba(0,0,0,0.6) backdrop-filter: blur(5px)`, // เบลอฉากหลังเพื่อให้ Pop-up เด่นขึ้น
            showClass: {
                popup: 'animate__animated animate__fadeInDown animate__faster'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp animate__faster'
            }
        };

        if (status === 'success_pending') {
            Swal.fire({
                ...commonConfig,
                icon: 'success',
                title: 'บันทึกข้อมูลสำเร็จ!',
                html: 'บัญชีของคุณอยู่ระหว่างรอการอนุมัติจาก Admin<br><span style="font-size: 0.9rem; color: #6c757d;">กรุณารอการตรวจสอบสักครู่...</span>',
                confirmButtonText: 'รับทราบ และกลับสู่หน้าล็อกอิน',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php'; 
                }
            });

        } else if (status === 'password_error') {
            Swal.fire({
                ...commonConfig,
                icon: 'error',
                title: 'รหัสผ่านไม่ถูกต้อง',
                text: 'รหัสผ่านทั้งสองช่องต้องตรงกัน และมีความยาวอย่างน้อย 6 ตัวอักษร',
                confirmButtonText: 'ลองใหม่อีกครั้ง',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                // ส่งกลับไปหน้า select_faculty.php ถ้าเกิด error
                window.history.back();
            });

        } else if (status === 'db_error' || status === 'missing_fields') {
            Swal.fire({
                ...commonConfig,
                icon: 'warning',
                title: 'เกิดข้อผิดพลาด',
                text: 'ข้อมูลไม่ครบถ้วน หรือระบบมีปัญหา กรุณาลองใหม่อีกครั้ง',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#ffc107'
            }).then(() => {
                window.history.back();
            });
        } else {
            // กรณีเข้ามาหน้านี้โดยไม่มี status ให้เด้งกลับ login
            window.location.href = 'login.php';
        }
    });
    </script>
</body>
</html>