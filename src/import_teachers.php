<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

$errors = [];
$success_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {

    $file = $_FILES['csv_file'];

    // ตรวจสอบนามสกุลไฟล์
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        $errors[] = 'กรุณาอัปโหลดไฟล์ CSV เท่านั้น';
    }

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'ขนาดไฟล์ต้องไม่เกิน 2MB';
    }

    if (empty($errors)) {

        if (($handle = fopen($file['tmp_name'], 'r')) !== false) {

            // อ่าน header
            $header = fgetcsv($handle, 1000, ',');
            // แก้ UTF-8 BOM
            if (isset($header[0])) {
                $header[0] = preg_replace('/\x{FEFF}/u', '', $header[0]);
            }

            $row = 2;

            while (($data = fgetcsv($handle, 1000, ',')) !== false) {

                if (count($data) < 3) {
                    $errors[] = "แถว $row: ข้อมูลไม่ครบ";
                    $row++;
                    continue;
                }

                $name = trim(preg_replace('/\x{FEFF}/u', '', $data[0]));
                $initials = trim($data[1]);
                $max_load = (int)trim($data[2]);
                $notes = isset($data[3]) ? trim($data[3]) : '';

                if ($name === '' || $initials === '' || $max_load <= 0) {
                    $errors[] = "แถว $row: ข้อมูลไม่ถูกต้อง";
                    $row++;
                    continue;
                }

                try {
                    // เช็คชื่อย่อซ้ำ
                    $check = $conn->prepare(
                        "SELECT id FROM teachers WHERE initials = ? AND user_id = ?"
                    );
                    $check->execute([$initials, $_SESSION['user_id']]);

                    if ($check->rowCount() > 0) {
                        $errors[] = "แถว $row: ชื่อย่อ '$initials' ซ้ำ";
                        $row++;
                        continue;
                    }

                    // Insert
                    $stmt = $conn->prepare(
                        "INSERT INTO teachers (name, initials, max_load, notes, user_id)
                         VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $name,
                        $initials,
                        $max_load,
                        $notes,
                        $_SESSION['user_id']
                    ]);

                    $success_count++;

                } catch (Exception $e) {
                    $errors[] = "แถว $row: " . $e->getMessage();
                }

                $row++;
            }

            fclose($handle);
        } else {
            $errors[] = 'ไม่สามารถอ่านไฟล์ได้';
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success_count > 0,
        'success_count' => $success_count,
        'errors' => $errors,
        'message' => $success_count > 0
            ? "นำเข้าข้อมูลสำเร็จ $success_count รายการ"
            : "ไม่สามารถนำเข้าข้อมูลได้"
    ]);
    exit;
}
