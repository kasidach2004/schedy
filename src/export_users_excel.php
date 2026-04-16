<?php
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$sql_user = "SELECT role FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->execute([$_SESSION['user_id']]);
$user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าเป็น admin หรือไม่
if ($user_info['role'] !== 'admin') {
    header("Location: home.php");
    exit;
}

// ดึงข้อมูล user ทั้งหมด พร้อมจำนวนตารางสอน
$sql_users = "SELECT 
                u.id,
                u.name,
                u.email,
                u.created_at,
                u.`group`,
                u.password,
                u.role,
                COUNT(s.id) as schedule_count
              FROM users u
              LEFT JOIN schedules s ON u.id = s.user_id
              GROUP BY u.id
              ORDER BY u.created_at DESC";

$users = $conn->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบว่ามี PhpSpreadsheet หรือไม่
$spreadsheetPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($spreadsheetPath)) {
    die('PhpSpreadsheet ไม่ได้ติดตั้ง');
}

require_once $spreadsheetPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// สร้าง Spreadsheet ใหม่
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ตั้งชื่อ Sheet
$sheet->setTitle('User Report');

// ตั้งหัวตาราง
$headers = ['ID', 'ชื่อ', 'อีเมล', 'วันสมัคร', 'กลุ่ม', 'Role', 'จำนวนตารางสอน'];
$sheet->fromArray([$headers], null, 'A1');

// ตั้งสไตล์หัวตาราง
$sheet->getStyle('A1:G1')->getFont()->setBold(true);
$sheet->getStyle('A1:G1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
$sheet->getStyle('A1:G1')->getFill()->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_BLUE);
$sheet->getStyle('A1:G1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

// เติมข้อมูล
$rowNum = 2;
foreach ($users as $user) {
    $sheet->setCellValue('A' . $rowNum, $user['id']);
    $sheet->setCellValue('B' . $rowNum, $user['name']);
    $sheet->setCellValue('C' . $rowNum, $user['email']);
    
    // จัดรูปแบบวันที่
    $dateTime = new DateTime($user['created_at']);
    $sheet->setCellValue('D' . $rowNum, $dateTime->format('d/m/Y H:i'));
    
    $sheet->setCellValue('E' . $rowNum, $user['group']);
    $sheet->setCellValue('F' . $rowNum, $user['role']);
    $sheet->setCellValue('G' . $rowNum, $user['schedule_count']);
    
    $rowNum++;
}

// ตั้งความกว้างของคอลัมน์
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(18);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(15);

// สร้างไฟล์ Excel
$fileName = 'user_report_' . date('Y-m-d_H-i-s') . '.xlsx';
$writer = new Xlsx($spreadsheet);

// ส่งไฟล์ให้ดาวน์โหลด
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
