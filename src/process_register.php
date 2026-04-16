<?php
session_start();

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register.php");
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$group = trim($_POST['group'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// ตรวจสอบข้อมูล
$errors = [];

if (empty($name)) { $errors[] = 'กรุณากรอกชื่อ - นามสกุล'; }
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'กรุณากรอกอีเมลที่ถูกต้อง'; }
if (empty($group)) { $errors[] = 'กรุณาเลือกกลุ่ม'; }
if (empty($password) || strlen($password) < 6) { $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'; }
if ($password !== $password_confirm) { $errors[] = 'รหัสผ่านไม่ตรงกัน'; }

if (!empty($errors)) {
    $_SESSION['register_errors'] = $errors;
    header("Location: register.php");
    exit;
}

try {
    // 1. ตรวจสอบอีเมลซ้ำ
    $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkEmail->execute([$email]);
    
    if ($checkEmail->rowCount() > 0) {
        $_SESSION['register_errors'] = ['อีเมลนี้มีผู้ใช้อยู่แล้ว'];
        header("Location: register.php");
        exit;
    }

    // 2. เข้ารหัสผ่าน
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // 3. Insert user ใหม่ 
    // สำคัญ: กำหนด role='user' และ status='pending' (รออนุมัติ)
    $sql = "INSERT INTO users (name, email, `group`, password, role, status) VALUES (?, ?, ?, ?, 'user', 'pending')";
    $insert = $conn->prepare($sql);
    
    if ($insert->execute([$name, $email, $group, $hashedPassword])) {
        // ไม่มีการ set $_SESSION['user_id'] เพื่อป้องกันการ Auto-login
        
        // ส่งกลับไปหน้า register พร้อมสถานะ success เพื่อแสดง SweetAlert
        header("Location: register.php?status=success");
        exit;
    } else {
        $_SESSION['register_errors'] = ['เกิดข้อผิดพลาดในการสมัคร กรุณาลองใหม่'];
        header("Location: register.php");
        exit;
    }

} catch (Exception $e) {
    $_SESSION['register_errors'] = ['เกิดข้อผิดพลาด: ' . $e->getMessage()];
    header("Location: register.php");
    exit;
}
?>