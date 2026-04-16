<?php
// copy_curriculum_process.php
session_start();
require_once 'config/db.php';

// ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบว่ามี ID ส่งมาไหม
if (isset($_GET['id'])) {
    $old_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // 1. ดึงข้อมูล User Role จาก Database โดยตรง (ชัวร์กว่า Session)
        // เพื่อให้แน่ใจว่าถ้าเป็น Admin จะสามารถก๊อปปี้ของใครก็ได้
        $stmt_u = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_u->execute([$user_id]);
        $u_data = $stmt_u->fetch(PDO::FETCH_ASSOC);
        $role = $u_data['role'] ?? 'user';

        // 2. ดึงข้อมูลหลักสูตรต้นฉบับ
        if ($role === 'admin') {
            // Admin เห็นและก๊อปได้ทุกอัน
            $stmt = $conn->prepare("SELECT * FROM curriculums WHERE id = ?");
            $stmt->execute([$old_id]);
        } else {
            // User ทั่วไป ก๊อปได้เฉพาะของตัวเอง
            $stmt = $conn->prepare("SELECT * FROM curriculums WHERE id = ? AND user_id = ?");
            $stmt->execute([$old_id, $user_id]);
        }
        
        $original_curriculum = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($original_curriculum) {
            $conn->beginTransaction(); // เริ่ม Transaction

            // 3. สร้างชื่อใหม่ (ป้องกันชื่อยาวเกิน DB รับไหว)
            $new_name = $original_curriculum['curriculum_name'] . " (สำเนา)";
            if (mb_strlen($new_name) > 255) {
                $new_name = mb_substr($original_curriculum['curriculum_name'], 0, 240) . "...(สำเนา)";
            }
            
            $semester = $original_curriculum['semester'];
            $academic_year = $original_curriculum['academic_year'];
            
            // 4. เพิ่มหลักสูตรใหม่ (ใช้ user_id ของคนที่กดทำสำเนา)
            $sql_insert = "INSERT INTO curriculums (user_id, curriculum_name, semester, academic_year) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->execute([$user_id, $new_name, $semester, $academic_year]);
            
            $new_curriculum_id = $conn->lastInsertId(); // ได้ ID ของหลักสูตรใหม่

            // 5. คัดลอกรายวิชา (curriculum_courses)
            $sql_copy_courses = "INSERT INTO curriculum_courses (curriculum_id, course_id)
                                 SELECT ?, course_id 
                                 FROM curriculum_courses 
                                 WHERE curriculum_id = ?";
            $stmt_copy = $conn->prepare($sql_copy_courses);
            $stmt_copy->execute([$new_curriculum_id, $old_id]);
            
            // 6. คัดลอกอาจารย์ผู้สอน (curriculum_course_teachers) *** สำคัญมาก ต้องเปิดใช้งาน ***
            // เพื่อให้ตารางใหม่มีข้อมูลอาจารย์ติดมาด้วย
            $sql_copy_teachers = "INSERT INTO curriculum_course_teachers (curriculum_id, course_id, teacher_id) 
                                  SELECT ?, course_id, teacher_id 
                                  FROM curriculum_course_teachers 
                                  WHERE curriculum_id = ?";
            $stmt_copy_t = $conn->prepare($sql_copy_teachers);
            $stmt_copy_t->execute([$new_curriculum_id, $old_id]);

            $conn->commit();
            $_SESSION['success'] = "ทำสำเนาหลักสูตรเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "ไม่พบหลักสูตร หรือคุณไม่มีสิทธิ์เข้าถึงหลักสูตรนี้";
        }

    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "รหัสหลักสูตรไม่ถูกต้อง";
}

// กลับไปหน้าหลักสูตร
header("Location: curriculums.php");
exit;
?>