<?php
// copy_curriculum_course.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$curriculum_id = isset($_POST['curriculum_id']) ? (int)$_POST['curriculum_id'] : 0;
$source_course_id = isset($_POST['source_course_id']) ? (int)$_POST['source_course_id'] : 0;
$teacher_ids = isset($_POST['teacher_id']) ? $_POST['teacher_id'] : [];
$classroom_ids = isset($_POST['classroom_id']) ? $_POST['classroom_id'] : [];

// ตรวจสอบความเป็นเจ้าของหลักสูตร
$stmt = $conn->prepare("SELECT id FROM curriculums WHERE id = ? AND user_id = ?");
$stmt->execute([$curriculum_id, $user_id]);
if (!$stmt->fetch()) {
    header("Location: curriculums.php");
    exit;
}

// ตรวจสอบวิชาต้นฉบับ
$stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE id = ? AND user_id = ?");
$stmt->execute([$source_course_id, $user_id]);
$source_course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source_course) {
    header("Location: manage_curriculum.php?id=$curriculum_id&status=error");
    exit;
}

try {
    $conn->beginTransaction();

    // 1. ตรวจสอบว่าวิชาอยู่ในหลักสูตรแล้วหรือไม่
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM curriculum_courses 
         WHERE curriculum_id = ? AND course_id = ?"
    );
    $stmt->execute([$curriculum_id, $source_course_id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        // วิชามีอยู่แล้ว ให้ลบข้อมูลเก่าและเพิ่มใหม่
        // ลบข้อมูลอาจารย์
        $stmt = $conn->prepare(
            "DELETE FROM curriculum_course_teachers 
             WHERE curriculum_id = ? AND course_id = ?"
        );
        $stmt->execute([$curriculum_id, $source_course_id]);

        // อัปเดต classroom_id
        if (!empty($classroom_ids)) {
            $classroom_str = implode(',', $classroom_ids);
        } else {
            $classroom_str = NULL;
        }

        $stmt = $conn->prepare(
            "UPDATE curriculum_courses 
             SET classroom_id = ? 
             WHERE curriculum_id = ? AND course_id = ?"
        );
        $stmt->execute([$classroom_str, $curriculum_id, $source_course_id]);
    } else {
        // วิชายังไม่มี ให้เพิ่มใหม่
        if (!empty($classroom_ids)) {
            $classroom_str = implode(',', $classroom_ids);
        } else {
            $classroom_str = NULL;
        }

        $stmt = $conn->prepare(
            "INSERT INTO curriculum_courses (curriculum_id, course_id, classroom_id) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$curriculum_id, $source_course_id, $classroom_str]);
    }

    // 2. เพิ่มอาจารย์ผู้สอน
    if (!empty($teacher_ids)) {
        $stmt = $conn->prepare(
            "INSERT INTO curriculum_course_teachers (curriculum_id, course_id, teacher_id) 
             VALUES (?, ?, ?)"
        );

        foreach ($teacher_ids as $teacher_id) {
            $teacher_id = (int)$teacher_id;
            // ตรวจสอบว่าอาจารย์เป็นของผู้ใช้หรือไม่
            $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$teacher_id, $user_id]);
            if ($check_stmt->fetch()) {
                $stmt->execute([$curriculum_id, $source_course_id, $teacher_id]);
            }
        }
    }

    // 3. บันทึก Log
    if (function_exists('writeLog')) {
        $stmt_user = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user_name = $stmt_user->fetchColumn() ?: 'Unknown';

        writeLog(
            $conn,
            $user_id,
            $user_name,
            'Copy Course',
            "สำเนาวิชา: {$source_course['course_code']} - {$source_course['course_name']} ในหลักสูตร ID: $curriculum_id"
        );
    }

    $conn->commit();

    // redirect กลับพร้อม status
    header("Location: manage_curriculum.php?id=$curriculum_id&status=copy_success");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    // Log error
    error_log("Copy Course Error: " . $e->getMessage());
    
    header("Location: manage_curriculum.php?id=$curriculum_id&status=error");
    exit;
}
?>