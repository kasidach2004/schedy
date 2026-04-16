<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาต']);
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csvFile'])) {
    echo json_encode(['success' => false, 'message' => 'ไม่มีไฟล์ CSV']);
    exit;
}

// ตรวจสอบนามสกุลไฟล์
$ext = strtolower(pathinfo($_FILES['csvFile']['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'รองรับเฉพาะไฟล์ CSV']);
    exit;
}

$file = $_FILES['csvFile']['tmp_name'];
$handle = fopen($file, 'r');
if (!$handle) {
    echo json_encode(['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์']);
    exit;
}

// อ่าน header + แก้ BOM
$header = fgetcsv($handle, 1000, ',');
if (isset($header[0])) {
    $header[0] = preg_replace('/\x{FEFF}/u', '', $header[0]);
}

$row = 1;
$success = 0;
$errors = [];

while (($data = fgetcsv($handle, 1000, ',')) !== false) {
    $row++;

    if (count($data) < 4) {
        $errors[] = "แถว $row: ข้อมูลไม่ครบ";
        continue;
    }

    $course_code     = trim($data[0]);
    $course_name     = trim($data[1]);
    $credits         = (int) trim($data[2]);
    $teaching_hours  = (int) trim($data[3]);
    $teacher_id      = isset($data[4]) && is_numeric($data[4]) ? (int)$data[4] : null;

    if ($course_code === '' || $course_name === '' || $credits <= 0 || $teaching_hours <= 0) {
        $errors[] = "แถว $row: ข้อมูลไม่ถูกต้อง";
        continue;
    }

    // ตรวจสอบซ้ำ (เฉพาะ user เดียวกัน)
    $check = $conn->prepare(
        "SELECT id FROM courses WHERE course_code = ? AND user_id = ?"
    );
    $check->execute([$course_code, $_SESSION['user_id']]);
    if ($check->rowCount() > 0) {
        $errors[] = "แถว $row: รหัสวิชา '$course_code' ซ้ำ";
        continue;
    }

    try {
        $stmt = $conn->prepare(
            "INSERT INTO courses
            (course_code, course_name, credits, teaching_hours, teacher_id, user_id)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $course_code,
            $course_name,
            $credits,
            $teaching_hours,
            $teacher_id,
            $_SESSION['user_id']
        ]);

        $success++;
    } catch (Exception $e) {
        $errors[] = "แถว $row: " . $e->getMessage();
    }
}

fclose($handle);

echo json_encode([
    'success' => $success > 0,
    'imported' => $success,
    'total' => $row - 1,
    'errors' => $errors
]);
