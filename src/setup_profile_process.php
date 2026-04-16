<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $faculty = $_POST['faculty'] ?? '';

    if (empty($new_password) || empty($confirm_password) || empty($faculty)) {
        header("Location: setup_profile.php?status=missing_fields");
        exit;
    }

    if ($new_password !== $confirm_password || strlen($new_password) < 6) {
        header("Location: setup_profile.php?status=password_error");
        exit;
    }

    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    try {
        // อัปเดตข้อมูลและตั้งสถานะเป็น pending
        $sql_update = "UPDATE users SET password = ?, `group` = ?, status = 'pending' WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->execute([$hashed_password, $faculty, $user_id]);

        // ส่งกลับไปหน้าเดิมเพื่อแสดง Pop-up สวยๆ ก่อนค่อย Redirect ไป login
        header("Location: setup_profile.php?status=success_pending");
        exit;

    } catch (PDOException $e) {
        header("Location: setup_profile.php?status=db_error");
        exit;
    }
}