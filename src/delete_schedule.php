<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if (isset($_GET['id'])) {
    $schedule_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // ลบรายการใน schedule_items ที่เชื่อมกับตารางสอนนี้ก่อน
        $sql_items = "DELETE FROM schedule_items WHERE schedule_id = ?";
        $stmt_items = $conn->prepare($sql_items);
        $stmt_items->execute([$schedule_id]);

        // ลบตารางสอน
        $sql_schedule = "DELETE FROM schedules WHERE id = ? AND user_id = ?";
        $stmt_schedule = $conn->prepare($sql_schedule);
        
        if ($stmt_schedule->execute([$schedule_id, $user_id])) {
            header("Location: schedules.php");
        } else {
            echo "Error: " . $stmt_schedule->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: schedules.php");
}
exit;
?>