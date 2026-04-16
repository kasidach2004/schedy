<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $room_name = $_POST['room_name'];
    $notes = $_POST['notes'];

    try {
        $sql = "UPDATE classrooms SET room_name = ?, notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([$room_name, $notes, $id])) {
            $_SESSION['success_message'] = "แก้ไขห้องเรียนสำเร็จ";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการแก้ไขห้องเรียน";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
    
    header("Location: classrooms.php");
    exit;
}
?>
