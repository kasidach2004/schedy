<?php
session_start();
require_once 'config/db.php';

// ตรวจสอบว่าเป็น Admin หรือไม่
$stmt_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt_check->execute([$_SESSION['user_id']]);
$user = $stmt_check->fetch();

if (!$user || $user['role'] !== 'admin') {
    die("Access Denied: คุณไม่มีสิทธิ์จัดการส่วนนี้");
}

$id = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;

if ($id && in_array($status, ['approved', 'rejected', 'pending'])) {
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

header("Location: admin_users_report.php");
exit;