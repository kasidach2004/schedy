<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {
    $name = $_POST['name'];
    $initials = $_POST['initials'];
    $max_load = $_POST['max_load'];
    $notes = $_POST['notes'];
    $user_id = $_SESSION['user_id']; // บันทึก ID ของผู้ใช้ที่กรอกข้อมูล

    try {

        $sql = "INSERT INTO teachers (name, initials, max_load, notes, user_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt->execute([$name, $initials, $max_load, $notes, $user_id])) {
            header("Location: teachers.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: teachers.php");
}
exit;
?>