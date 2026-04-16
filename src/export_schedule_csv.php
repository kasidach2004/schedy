<?php
// export_schedule_excel.php
session_start();
require_once 'config/db.php';

// ฟังก์ชันแปลงวันที่แบบไทยใน PHP
function formatThaiDatePHP($dateStr) {
    if (empty($dateStr)) return '';
    $parts = explode('-', $dateStr);
    if (count($parts) !== 3) return '';
    $months = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $d = intval($parts[2]);
    $m = intval($parts[1]);
    $y = intval($parts[0]) + 543; // ปี พ.ศ.
    return "{$d} {$months[$m]} {$y}";
}

// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die("Access Denied");
}

$schedule_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// 2. ตรวจสอบความเป็นเจ้าของ
$stmt_check = $conn->prepare("SELECT schedule_name FROM schedules WHERE id = ? AND user_id = ?");
$stmt_check->execute([$schedule_id, $user_id]);
$schedule = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    die("ไม่พบข้อมูลตารางสอน");
}

// 3. เตรียมข้อมูล Master Data (Teachers & Timeslots)
$teachers_map = [];
$t_query = $conn->query("SELECT id, initials FROM teachers");
while ($row = $t_query->fetch(PDO::FETCH_ASSOC)) {
    $teachers_map[$row['id']] = $row['initials'];
}

$timeslots_map = [];
$ts_query = $conn->query("SELECT id, start_time, end_time FROM timeslots ORDER BY start_time");
$all_timeslots = $ts_query->fetchAll(PDO::FETCH_ASSOC);
foreach ($all_timeslots as $row) {
    $timeslots_map[$row['id']] = $row;
}

$days_map = [
    1 => 'วันจันทร์', 2 => 'วันอังคาร', 3 => 'วันพุธ',
    4 => 'วันพฤหัสบดี', 5 => 'วันศุกร์', 6 => 'วันเสาร์', 7 => 'วันอาทิตย์'
];

// 4. ดึงข้อมูล Schedule Items พร้อมกับดึงหมายเลขห้องเรียน (Physical Room) เข้ามาด้วย
$sql = "SELECT 
            si.*, 
            c.room_name, 
            co.course_code, 
            co.course_name, 
            co.credits,
            (
                SELECT GROUP_CONCAT(DISTINCT r.room_number ORDER BY r.room_id ASC SEPARATOR ', ')
                FROM curriculum_courses cc
                LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
                WHERE cc.curriculum_id = si.curriculum_id AND cc.course_id = si.course_id
            ) AS assigned_room
        FROM schedule_items si
        JOIN classrooms c ON si.room_id = c.id
        JOIN courses co ON si.course_id = co.id
        WHERE si.schedule_id = ?
        ORDER BY si.day_of_week ASC, si.room_id ASC, si.timeslot_id ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([$schedule_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. ตั้งค่า Header เพื่อดาวน์โหลด (ปรับชื่อไฟล์ตามต้องการ)
$filename = "schedule_ced_fte_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 6. สร้างเนื้อหาไฟล์ในรูปแบบ HTML Table
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-type" content="text/html;charset=utf-8" />
    <style>
        .table-header { background-color: #0d6efd; color: #ffffff; font-weight: bold; }
        td, th { border: 0.5pt solid #000000; vertical-align: middle; }
    </style>
</head>
<body>
    <strong>ตารางสอน: <?php echo htmlspecialchars($schedule['schedule_name']); ?></strong><br>
    <small>ภาควิชาคอมพิวเตอร์ศึกษา (CED) คณะครุศาสตร์อุตสาหกรรม (FTE)</small><br><br>
    
    <table>
        <thead>
            <tr class="table-header">
                <th width="120">วัน</th>
                <th width="100">ห้องเรียน (กลุ่ม)</th>
                <th width="150">เวลาเรียน</th>
                <th width="100">รหัสวิชา</th>
                <th width="300">ชื่อวิชา</th>
                <th width="80">หน่วยกิต</th>
                <th width="200">อาจารย์ผู้สอน</th>
                <th width="120">หมายเลขห้องเรียน</th>
                <th width="240">วันที่เรียน</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                // คำนวณช่วงเวลา
                $start_time = ""; $end_time = "";
                $start_index = -1;
                foreach ($all_timeslots as $idx => $ts) {
                    if ($ts['id'] == $item['timeslot_id']) {
                        $start_index = $idx;
                        $start_time = substr($ts['start_time'], 0, 5);
                        break;
                    }
                }
                if ($start_index != -1) {
                    $end_index = $start_index + $item['duration'] - 1;
                    if (isset($all_timeslots[$end_index])) {
                        $end_time = substr($all_timeslots[$end_index]['end_time'], 0, 5);
                    }
                }
                
                // จัดการชื่อย่ออาจารย์จาก JSON
                $t_ids = json_decode($item['teacher_ids'], true);
                $teacher_names = [];
                if (is_array($t_ids)) {
                    foreach ($t_ids as $tid) {
                        if (isset($teachers_map[$tid])) $teacher_names[] = $teachers_map[$tid];
                    }
                }

                // จัดการรูปแบบวันที่
                $dateText = "-";
                if (!empty($item['start_date']) && !empty($item['end_date'])) {
                    $dateText = "Start " . formatThaiDatePHP($item['start_date']) . " - End " . formatThaiDatePHP($item['end_date']);
                }

                // ใช้ physical_room หากมีการระบุห้องเองตอนลากวาง (ถ้าไม่มีค่อยใช้ default จาก assigned_room)
                $display_room = !empty($item['physical_room']) ? $item['physical_room'] : ($item['assigned_room'] ?? '-');
            ?>
                <tr>
                    <td style="text-align: center;"><?php echo $days_map[$item['day_of_week']] ?? 'N/A'; ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($item['room_name']); ?></td>
                    <td style="text-align: center;"><?php echo "$start_time - $end_time"; ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($item['course_code']); ?></td>
                    <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                    <td style="text-align: center;"><?php echo (float)$item['credits']; ?></td>
                    <td><?php echo htmlspecialchars(implode(', ', $teacher_names)); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($display_room); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($dateText); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>