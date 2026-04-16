<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['curriculum_id'])) {
    $curriculum_id = (int)$_POST['curriculum_id'];
    $curriculum_name = $_POST['curriculum_name'];
    $semester = $_POST['semester'];
    $academic_year = (int)$_POST['academic_year'];
    
    // ตรวจสอบว่าเป็นปี พ.ศ. ที่ถูกต้อง (2500-2599)
    if ($academic_year < 2500 || $academic_year > 2599) {
        die("กรุณากรอกปีการศึกษาเป็น พ.ศ. ระหว่าง 2500-2599");
    }
    
    // แปลง พ.ศ. เป็น ค.ศ.
    $academic_year_ce = $academic_year - 543;

    try {
        // ตรวจสอบว่าเป็นหลักสูตรของผู้ใช้นี้จริง
        $check_sql = "SELECT id FROM curriculums WHERE id = ? AND user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$curriculum_id, $_SESSION['user_id']]);
        
        if ($check_stmt->rowCount() === 0) {
            die("ไม่พบข้อมูลหลักสูตรหรือคุณไม่มีสิทธิ์แก้ไข");
        }

        // อัพเดทข้อมูล
        $sql = "UPDATE curriculums SET curriculum_name = ?, semester = ?, academic_year = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([$curriculum_name, $semester, $academic_year_ce, $curriculum_id, $_SESSION['user_id']])) {
            header("Location: curriculums.php");
        } else {
            echo "Error: " . $stmt->errorInfo()[2];
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    header("Location: curriculums.php");
}
exit;