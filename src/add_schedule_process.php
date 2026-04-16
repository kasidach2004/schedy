<?php
session_start();

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ตรวจสอบข้อมูลที่ส่งมา
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_name'])) {
    $schedule_name = $_POST['schedule_name'];
    $user_id = $_SESSION['user_id'];

    try {
        // เตรียมคำสั่ง SQL เพื่อป้องกัน SQL Injection
        $sql = "INSERT INTO schedules (schedule_name, user_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$schedule_name, $user_id])) {
            header("Location: schedules.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: schedules.php");
}
exit;
?>