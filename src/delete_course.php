<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if (isset($_GET['id'])) {
    $course_id = $_GET['id'];

    try {
        // ตรวจสอบสิทธิ์
        $sql_check = "SELECT user_id FROM courses WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$course_id]);
        $course = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $sql_user = "SELECT role FROM users WHERE id = ?";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->execute([$_SESSION['user_id']]);
        $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

        // Admin หรือ เจ้าของข้อมูล
        if ($course && ($user['role'] === 'admin' || $course['user_id'] == $_SESSION['user_id'])) {
            $sql = "DELETE FROM courses WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt->execute([$course_id])) {
                header("Location: courses.php");
            } else {
                echo "Error: " . $stmt->errorInfo()[2];
            }
        } else {
            echo "ไม่มีสิทธิ์ลบข้อมูลนี้";
            header("Location: courses.php");
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: courses.php");
}
exit;
?>