<?php
// edit_curriculum_course_process.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['course_id']) || !isset($_POST['curriculum_id'])) {
    header("Location: curriculums.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = (int)$_POST['course_id'];
$curriculum_id = (int)$_POST['curriculum_id'];

// 🟢 ปรับการรับค่าให้เป็น Array
$classroom_ids = isset($_POST['classroom_id']) ? (array)$_POST['classroom_id'] : [];
$teacher_ids = isset($_POST['teacher_id']) ? (array)$_POST['teacher_id'] : [];

try {
    // 🟢 ตรวจสอบความเป็นเจ้าของห้องเรียนเหมือนตอนเพิ่ม เพื่อความปลอดภัย
    $valid_classroom_ids = [];
    if (!empty($classroom_ids)) {
        $stmt_check_room = $conn->prepare("SELECT room_id FROM rooms WHERE room_id = ? AND user_id = ?");
        foreach ($classroom_ids as $room_id) {
            $stmt_check_room->execute([(int)$room_id, $user_id]);
            if ($stmt_check_room->rowCount() == 0) {
                die("Error: คุณไม่มีสิทธิ์ใช้ห้องเรียนนี้");
            }
            $valid_classroom_ids[] = (int)$room_id;
        }
    }
    // แปลงเป็น String คั่นด้วยลูกน้ำ
    $classroom_ids_string = !empty($valid_classroom_ids) ? implode(',', $valid_classroom_ids) : null;

    // Update classroom in curriculum_courses
    $updateSql = "UPDATE curriculum_courses SET classroom_id = ? WHERE curriculum_id = ? AND course_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    // 🟢 อัปเดตข้อมูล String ห้องเรียนลงฐานข้อมูล
    $updateStmt->execute([$classroom_ids_string, $curriculum_id, $course_id]);

    // Remove existing teacher assignments for this curriculum+course
    $delSql = "DELETE FROM curriculum_course_teachers WHERE curriculum_id = ? AND course_id = ?";
    $delStmt = $conn->prepare($delSql);
    $delStmt->execute([$curriculum_id, $course_id]);

    // Insert new teacher assignments
    if (!empty($teacher_ids)) {
        $insSql = "INSERT INTO curriculum_course_teachers (curriculum_id, course_id, teacher_id) VALUES (?, ?, ?)";
        $insStmt = $conn->prepare($insSql);
        foreach ($teacher_ids as $tid) {
            $t = (int)$tid;
            $insStmt->execute([$curriculum_id, $course_id, $t]);
        }
    }

    header("Location: manage_curriculum.php?id={$curriculum_id}");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
exit;
?>