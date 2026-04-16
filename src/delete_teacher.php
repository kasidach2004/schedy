<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if (isset($_GET['id'])) {
    $teacher_id = $_GET['id'];

    // ตรวจสอบสิทธิ์ - ต้องเป็นคนเดียวกันหรือ Admin
    $sql_check = "SELECT user_id FROM teachers WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$teacher_id]);
    $teacher = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // ตรวจสอบบทบาท
    $sql_user = "SELECT role FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->execute([$_SESSION['user_id']]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // อนุญาตให้ลบได้ถ้า: เป็น Admin หรือ เป็นคนกรอกข้อมูลนั้นเอง
    if ($teacher && ($user['role'] === 'admin' || $teacher['user_id'] == $_SESSION['user_id'])) {
        // ลบข้อมูลอาจารย์
        $sql = "DELETE FROM teachers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$teacher_id])) {
            header("Location: teachers.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } else {
        echo "ไม่มีสิทธิ์ลบข้อมูลนี้";
        header("Location: teachers.php");
    }
} else {
    header("Location: teachers.php");
}
exit;
?>