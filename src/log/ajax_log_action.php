<?php
// ตำแหน่งไฟล์: log/ajax_log_action.php
session_start();

// แก้ไข Path ถอยหลังออกไป 1 โฟลเดอร์ เพื่อเข้าถึงโฟลเดอร์ config
require_once '../config/db.php';

// รับค่าจาก AJAX
$action = $_POST['action'] ?? 'Unknown Action';
$details = $_POST['details'] ?? '';

// ถ้าล็อกอินอยู่ ให้บันทึก Log
if (isset($_SESSION['user_id']) && function_exists('writeLog')) {
    
    // ไปดึงชื่อมาเก็บไว้
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $name = $stmt->fetchColumn() ?: 'Unknown';

    // บันทึกลงตาราง
    writeLog($conn, $_SESSION['user_id'], $name, $action, $details);
}
?>