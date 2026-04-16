<?php
// add_course_process.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['curriculum_id'])) {
    $user_id = $_SESSION['user_id'];
    $curriculum_id = (int)$_POST['curriculum_id'];
    $course_id = (int)$_POST['course_id'];
    $teacher_ids = isset($_POST['teacher_id']) ? (array)$_POST['teacher_id'] : [];
    
    // 🟢 ปรับการรับค่าห้องเรียนเป็น Array
    $classroom_ids = isset($_POST['classroom_id']) ? (array)$_POST['classroom_id'] : [];

    try {
        // --- 1. ตรวจสอบความเป็นเจ้าของหลักสูตร ---
        $stmt_check_curr = $conn->prepare("SELECT id FROM curriculums WHERE id = ? AND user_id = ?");
        $stmt_check_curr->execute([$curriculum_id, $user_id]);
        if ($stmt_check_curr->rowCount() == 0) {
            die("Error: คุณไม่มีสิทธิ์จัดการหลักสูตรนี้");
        }

        // --- 2. ตรวจสอบความเป็นเจ้าของวิชา ---
        $stmt_check_course = $conn->prepare("SELECT id FROM courses WHERE id = ? AND user_id = ?");
        $stmt_check_course->execute([$course_id, $user_id]);
        if ($stmt_check_course->rowCount() == 0) {
            die("Error: คุณไม่มีสิทธิ์ใช้วิชานี้");
        }

        // --- 3. ตรวจสอบความเป็นเจ้าของห้องเรียน (วนลูปตรวจสอบทุกห้อง) ---
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
        
        // 🟢 แปลง Array ของห้องเรียนให้เป็น String คั่นด้วยลูกน้ำ (เช่น "1,2,3")
        $classroom_ids_string = !empty($valid_classroom_ids) ? implode(',', $valid_classroom_ids) : null;

        // --- 4. ตรวจสอบวิชาซ้ำในหลักสูตร ---
        $check_sql = "SELECT curriculum_id FROM curriculum_courses WHERE curriculum_id = ? AND course_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$curriculum_id, $course_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // ส่ง status=duplicate กลับไปที่หน้าจัดการ
            header("Location: manage_curriculum.php?id={$curriculum_id}&status=duplicate");
            exit;
        }

        // --- 5. บันทึกข้อมูล ---
        $sql = "INSERT INTO curriculum_courses (curriculum_id, course_id, classroom_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);

        // 🟢 บันทึกเป็น String แทนไอดีเดียว
        if ($stmt->execute([$curriculum_id, $course_id, $classroom_ids_string])) {
            
            if (!empty($teacher_ids)) {
                $insStmt = $conn->prepare("INSERT INTO curriculum_course_teachers (curriculum_id, course_id, teacher_id) VALUES (?, ?, ?)");
                $check_teacher_stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND user_id = ?");

                foreach ($teacher_ids as $tid) {
                    $check_teacher_stmt->execute([(int)$tid, $user_id]);
                    if ($check_teacher_stmt->rowCount() > 0) {
                        $insStmt->execute([$curriculum_id, $course_id, (int)$tid]);
                    }
                }
            }
            // ส่ง status=success กลับไป
            header("Location: manage_curriculum.php?id={$curriculum_id}&status=success");
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
?>