<?php
session_start();
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

try {
    // เพิ่มการดึงฟิลด์ status ใน SQL
    $stmt = $conn->prepare("SELECT id, `group`, password, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        
        // --- ตรวจสอบสถานะการอนุมัติที่นี่ ---
        if ($user['status'] !== 'approved') {
            $_SESSION['login_errors'] = ['บัญชีของคุณรอการอนุมัติ หรือถูกปฏิเสธจากผู้ดูแลระบบ'];
            header("Location: login.php");
            exit;
        }

        // หากอนุมัติแล้ว ค่อยสร้าง Session และไปหน้าหลัก
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_group'] = $user['group'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['success_message'] = 'เข้าสู่ระบบสำเร็จ';
        
        header("Location: home.php");
        exit;

    } else {
        $_SESSION['login_errors'] = ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'];
        header("Location: login.php");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['login_errors'] = ['เกิดข้อผิดพลาด: ' . $e->getMessage()];
    header("Location: login.php");
    exit;
}