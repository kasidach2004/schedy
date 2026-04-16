<?php
// save_schedule_process.php
session_start();
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

// 1. ตรวจสอบการ Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// 2. รับข้อมูล JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['schedule_id']) || !isset($input['items'])) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$schedule_id = $input['schedule_id'];
$items = $input['items'];
$user_id = $_SESSION['user_id'];

try {
    // 3. SECURITY CHECK 1: ตรวจสอบความเป็นเจ้าของ Schedule
    $stmt_check = $conn->prepare("SELECT id FROM schedules WHERE id = ? AND user_id = ?");
    $stmt_check->execute([$schedule_id, $user_id]);
    
    if ($stmt_check->rowCount() == 0) {
        echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์แก้ไขตารางนี้']);
        exit;
    }

    // --- SECURITY CHECK 2 (ตรวจสอบ Curriculum) ---
    $stmt_valid_curr = $conn->prepare("SELECT id FROM curriculums WHERE user_id = ?");
    $stmt_valid_curr->execute([$user_id]);
    $valid_curriculum_ids = $stmt_valid_curr->fetchAll(PDO::FETCH_COLUMN);
    // -----------------------------------------------------

    $conn->beginTransaction();

    // 4. ล้างข้อมูลเก่า
    $stmt_del = $conn->prepare("DELETE FROM schedule_items WHERE schedule_id = ?");
    $stmt_del->execute([$schedule_id]);

    // 5. บันทึกข้อมูลใหม่
    if (!empty($items)) {
        // 🟢 เพิ่ม start_date และ end_date เข้าไปในคำสั่ง INSERT
        $sql_insert = "INSERT INTO schedule_items 
                       (schedule_id, room_id, day_of_week, timeslot_id, course_id, curriculum_id, teacher_ids, duration, credits, chunk_id, physical_room, start_date, end_date) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        foreach ($items as $item) {
            $curr_id = $item['curriculum_id'] ?? null;
            
            if ($curr_id && !in_array($curr_id, $valid_curriculum_ids)) {
                $conn->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'ตรวจพบการพยายามบันทึกหลักสูตรที่ไม่ใช่ของคุณ (Security Violation)']);
                exit;
            }

            $teachers_json = json_encode($item['teacher_ids'], JSON_UNESCAPED_UNICODE);
            
            $credits = $item['credits'] ?? 0;
            if ($credits == 0) {
                $stmt_credits = $conn->prepare("SELECT credits FROM courses WHERE id = ?");
                $stmt_credits->execute([$item['course_id']]);
                $course_data = $stmt_credits->fetch(PDO::FETCH_ASSOC);
                $credits = $course_data['credits'] ?? 0;
            }

            // รับค่าเพิ่มเติมจาก JS (ถ้าไม่มีให้เป็นค่าว่าง)
            $chunk_id = $item['chunk_id'] ?? '';
            $physical_room = $item['physical_room'] ?? '';
            
            // 🟢 รับค่าวันที่ ถ้าไม่มีให้เป็น null เพื่อบันทึกลง Database ได้ถูกต้อง
            $start_date = !empty($item['start_date']) ? $item['start_date'] : null;
            $end_date = !empty($item['end_date']) ? $item['end_date'] : null;
            
            $stmt_insert->execute([
                $schedule_id,
                $item['room_id'],
                $item['day_of_week'],
                $item['timeslot_id'],
                $item['course_id'],
                $curr_id, 
                $teachers_json,
                $item['duration'],
                $credits,
                $chunk_id,        // บันทึกรหัสการหั่นบล็อก
                $physical_room,   // บันทึกหมายเลขห้องเรียน
                $start_date,      // บันทึกวันที่เริ่มต้น
                $end_date         // บันทึกวันที่สิ้นสุด
            ]);
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>