<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['course_code'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credits = (int)$_POST['credits'];
    $teaching_hours = (int)$_POST['teaching_hours'];
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
    $user_id = $_SESSION['user_id'];

    try {
        // ตรวจสอบว่า "ผู้ใช้นี้" เคยเพิ่ม "รหัสวิชานี้" ไปแล้วหรือยัง
        $check_sql = "SELECT id FROM courses WHERE course_code = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$course_code, $user_id]);

        if ($check_stmt->rowCount() > 0) {
            echo "<script>alert('คุณมีรหัสวิชานี้อยู่ในระบบของคุณแล้ว'); window.history.back();</script>";
            exit;
        }

        // เพิ่มวิชาพร้อมผูก user_id
        $sql = "INSERT INTO courses (course_code, course_name, credits, teaching_hours, teacher_id, curriculum_id, user_id) VALUES (?, ?, ?, ?, ?, 0, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$course_code, $course_name, $credits, $teaching_hours, $teacher_id, $user_id])) {
            header("Location: courses.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: courses.php");
}
exit;