<?php
// callback.php
session_start();
require_once 'vendor/autoload.php';
require_once 'config/db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$client = new Google_Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri('http://localhost:8000/src/callback.php');

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token);

        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;

        // 1. ตรวจสอบว่ามีอีเมลนี้ในระบบหรือไม่
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // ====================================================
            // กรณี 1: มีบัญชีอยู่แล้ว (Existing User)
            // ====================================================

            // 1.1 เช็คว่าเคยเลือกคณะหรือยัง? (ถ้า status เป็น 'new' หรือไม่มีกลุ่ม แสดงว่าสมัครค้างไว้)
            if ($user['status'] === 'new' || empty($user['group'])) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: select_faculty.php"); // ส่งไปทำรายการต่อให้จบ
                exit;
            }

            // 1.2 เช็คสถานะการอนุมัติ (Approved / Pending / Rejected)
            if ($user['status'] !== 'approved') {
                $msg = ($user['status'] === 'pending') 
                        ? "บัญชีของคุณอยู่ระหว่างรอการอนุมัติจากผู้ดูแลระบบ" 
                        : "บัญชีของคุณถูกระงับการใช้งาน";
                
                $_SESSION['login_errors'] = [$msg];
                header("Location: login.php");
                exit;
            }

            // 1.3 ผ่านทุกเงื่อนไข -> เข้าสู่ระบบ
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            // อัปเดต Google ID ล่าสุดเผื่อมีการเปลี่ยนแปลง (Optional)
            $update = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $update->execute([$google_id, $user['id']]);

            header("Location: home.php");
            exit;

        } else {
            // ====================================================
            // กรณี 2: ผู้ใช้งานใหม่ (New User / Registration)
            // ====================================================
            
            // เพิ่มลงฐานข้อมูลโดยให้ status = 'new' เพื่อระบุว่ายังเป็นสมาชิกใหม่ที่ยังไม่ได้เลือกคณะ
            $sql = "INSERT INTO users (name, email, role, status, google_id) VALUES (?, ?, 'user', 'new', ?)";
            $stmt_insert = $conn->prepare($sql);
            
            if ($stmt_insert->execute([$name, $email, $google_id])) {
                // ดึง ID ที่เพิ่งสร้าง
                $new_user_id = $conn->lastInsertId();
                
                // สร้าง Session
                $_SESSION['user_id'] = $new_user_id;
                
                // 🔥 ส่งไปหน้าเลือกคณะและตั้งรหัสผ่านทันที 🔥
                header("Location: select_faculty.php"); 
                exit;
            } else {
                $_SESSION['login_errors'] = ["เกิดข้อผิดพลาดในการสร้างบัญชี"];
                header("Location: login.php");
                exit;
            }
        }

    } catch (Exception $e) {
        $_SESSION['login_errors'] = ["Google Login Error: " . $e->getMessage()];
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>