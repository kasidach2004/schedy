// debug_copy_course.php
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$curriculum_id = isset($_GET['curriculum_id']) ? (int)$_GET['curriculum_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// 🔴 DEBUG: แสดงข้อมูล Parameters
echo "<h2>📊 DEBUG: Copy Course Information</h2>";
echo "<pre>";
echo "User ID: " . $user_id . "\n";
echo "Curriculum ID: " . $curriculum_id . "\n";
echo "Course ID: " . $course_id . "\n";
echo "</pre>";

if ($curriculum_id == 0 || $course_id == 0) {
    echo "<p style='color: red;'>⚠️ ข้อมูลไม่สมบูรณ์ - กรุณาส่ง curriculum_id และ course_id</p>";
    exit;
}

// 🔴 DEBUG 1: ตรวจสอบ Curriculum เป็นของ User หรือไม่
echo "<h3>🔍 DEBUG 1: ตรวจสอบ Curriculum Ownership</h3>";
$stmt = $conn->prepare("SELECT id, curriculum_name, user_id FROM curriculums WHERE id = ?");
$stmt->execute([$curriculum_id]);
$curriculum = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curriculum) {
    echo "<p style='color: red;'>❌ ไม่พบ Curriculum ID: $curriculum_id</p>";
} else {
    echo "<pre>";
    print_r($curriculum);
    echo "</pre>";
    if ($curriculum['user_id'] != $user_id) {
        echo "<p style='color: red;'>❌ Curriculum นี้ไม่ใช่ของผู้ใช้</p>";
        exit;
    } else {
        echo "<p style='color: green;'>✅ Curriculum ถูก Ownership OK</p>";
    }
}

// 🔴 DEBUG 2: ตรวจสอบ Course ต้นฉบับ
echo "<h3>🔍 DEBUG 2: ตรวจสอบ Source Course</h3>";
$stmt = $conn->prepare("SELECT id, course_code, course_name, credits, teaching_hours, user_id FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$source_course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source_course) {
    echo "<p style='color: red;'>❌ ไม่พบ Course ID: $course_id</p>";
} else {
    echo "<pre>";
    print_r($source_course);
    echo "</pre>";
    if ($source_course['user_id'] != $user_id) {
        echo "<p style='color: red;'>❌ Course นี้ไม่ใช่ของผู้ใช้</p>";
        exit;
    } else {
        echo "<p style='color: green;'>✅ Course Ownership OK</p>";
    }
}

// 🔴 DEBUG 3: ตรวจสอบว่า Course มีอยู่ใน Curriculum หรือไม่
echo "<h3>🔍 DEBUG 3: ตรวจสอบ Course ใน Curriculum</h3>";
$stmt = $conn->prepare(
    "SELECT cc.*, 
            GROUP_CONCAT(DISTINCT t.id) AS teacher_ids,
            GROUP_CONCAT(DISTINCT t.name) AS teacher_names,
            GROUP_CONCAT(DISTINCT r.room_id) as room_ids,
            GROUP_CONCAT(DISTINCT r.room_number) as room_numbers
    FROM curriculum_courses cc
    LEFT JOIN curriculum_course_teachers cct ON cc.curriculum_id = cct.curriculum_id AND cc.course_id = cct.course_id
    LEFT JOIN teachers t ON cct.teacher_id = t.id
    LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
    WHERE cc.curriculum_id = ? AND cc.course_id = ?
    GROUP BY cc.id"
);
$stmt->execute([$curriculum_id, $course_id]);
$curriculum_course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$curriculum_course) {
    echo "<p style='color: orange;'>⚠️ Course ยังไม่มีใน Curriculum นี้</p>";
} else {
    echo "<p style='color: green;'>✅ Course มีอยู่ใน Curriculum</p>";
    echo "<pre>";
    print_r($curriculum_course);
    echo "</pre>";
}

// 🔴 DEBUG 4: ตรวจสอบตาราง curriculum_courses Structure
echo "<h3>🔍 DEBUG 4: ตรวจสอบ Database Table Structure</h3>";
echo "<h4>curriculum_courses Columns:</h4>";
$stmt = $conn->prepare("DESCRIBE curriculum_courses");
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach ($columns as $col) {
    echo $col['Field'] . " (" . $col['Type'] . ")" . "\n";
}
echo "</pre>";

// 🔴 DEBUG 5: ดึงข้อมูล Raw จาก curriculum_courses
echo "<h3>🔍 DEBUG 5: ข้อมูล Raw จาก curriculum_courses</h3>";
$stmt = $conn->prepare("SELECT * FROM curriculum_courses WHERE curriculum_id = ? AND course_id = ?");
$stmt->execute([$curriculum_id, $course_id]);
$raw_curriculum_course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$raw_curriculum_course) {
    echo "<p style='color: red;'>❌ ไม่พบข้อมูลใน curriculum_courses</p>";
} else {
    echo "<p style='color: green;'>✅ พบข้อมูลใน curriculum_courses</p>";
    echo "<pre>";
    print_r($raw_curriculum_course);
    echo "</pre>";
}

// 🔴 DEBUG 6: ตรวจสอบ curriculum_course_teachers
echo "<h3>🔍 DEBUG 6: ข้อมูลใน curriculum_course_teachers</h3>";
$stmt = $conn->prepare(
    "SELECT cct.*, t.id, t.name, t.initials 
    FROM curriculum_course_teachers cct
    LEFT JOIN teachers t ON cct.teacher_id = t.id
    WHERE cct.curriculum_id = ? AND cct.course_id = ?"
);
$stmt->execute([$curriculum_id, $course_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($teachers)) {
    echo "<p style='color: orange;'>⚠️ ไม่พบอาจารย์ใน curriculum_course_teachers</p>";
} else {
    echo "<p style='color: green;'>✅ พบอาจารย์ " . count($teachers) . " คน</p>";
    echo "<pre>";
    print_r($teachers);
    echo "</pre>";
}

// 🔴 DEBUG 7: ตรวจสอบ Rooms
echo "<h3>🔍 DEBUG 7: ข้อมูลห้องเรียน</h3>";
if (!$raw_curriculum_course) {
    echo "<p style='color: red;'>ไม่มีข้อมูล classroom_id</p>";
} else {
    $classroom_ids = $raw_curriculum_course['classroom_id'] ?? '';
    echo "Classroom ID String: " . $classroom_ids . "<br>";
    
    if (empty($classroom_ids)) {
        echo "<p style='color: orange;'>⚠️ ไม่มีห้องเรียนกำหนด</p>";
    } else {
        $stmt = $conn->prepare("SELECT room_id, room_number FROM rooms WHERE FIND_IN_SET(room_id, ?)");
        $stmt->execute([$classroom_ids]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rooms)) {
            echo "<p style='color: orange;'>⚠️ ไม่พบห้องเรียน</p>";
        } else {
            echo "<p style='color: green;'>✅ พบห้องเรียน " . count($rooms) . " ห้อง</p>";
            echo "<pre>";
            print_r($rooms);
            echo "</pre>";
        }
    }
}

// 🔴 DEBUG 8: ทดสอบ Query แบบเดียวกับ manage_curriculum.php
echo "<h3>🔍 DEBUG 8: ทดสอบ Query เดิม (manage_curriculum.php)</h3>";
$stmt = $conn->prepare("
    SELECT c.*, 
        GROUP_CONCAT(DISTINCT CONCAT(t.initials, ' (', t.name, ')') SEPARATOR '; ') AS teacher_list,
        GROUP_CONCAT(DISTINCT t.id) AS teacher_ids,
        GROUP_CONCAT(DISTINCT r.room_number SEPARATOR ', ') as room_number,
        GROUP_CONCAT(DISTINCT r.room_id) as assigned_room_ids
    FROM courses c
    INNER JOIN curriculum_courses cc ON c.id = cc.course_id
    LEFT JOIN curriculum_course_teachers cct ON cc.curriculum_id = cct.curriculum_id AND cc.course_id = cct.course_id
    LEFT JOIN teachers t ON cct.teacher_id = t.id
    LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
    WHERE cc.curriculum_id = ? AND c.id = ?
    GROUP BY c.id
");
$stmt->execute([$curriculum_id, $course_id]);
$test_result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test_result) {
    echo "<p style='color: red;'>❌ Query ไม่ได้ผลลัพธ์</p>";
} else {
    echo "<p style='color: green;'>✅ Query ได้ผลลัพธ์</p>";
    echo "<pre>";
    print_r($test_result);
    echo "</pre>";
}

// 🔴 DEBUG 9: ตรวจสอบ Classrooms IDs Parsing
echo "<h3>🔍 DEBUG 9: ตรวจสอบการแยก classroom_id</h3>";
if (!empty($raw_curriculum_course['classroom_id'])) {
    $classroom_ids = $raw_curriculum_course['classroom_id'];
    $ids_array = explode(',', $classroom_ids);
    echo "Classroom IDs String: " . $classroom_ids . "<br>";
    echo "Exploded Array:<br>";
    echo "<pre>";
    print_r($ids_array);
    echo "</pre>";
}

// 🔴 DEBUG 10: ตรวจสอบ All Courses
echo "<h3>🔍 DEBUG 10: ตรวจสอบทั้งหมด Courses ในหลักสูตร</h3>";
$stmt = $conn->prepare("
    SELECT c.*, 
        GROUP_CONCAT(DISTINCT CONCAT(t.initials, ' (', t.name, ')') SEPARATOR '; ') AS teacher_list,
        GROUP_CONCAT(DISTINCT t.id) AS teacher_ids,
        GROUP_CONCAT(DISTINCT r.room_number SEPARATOR ', ') as room_number,
        GROUP_CONCAT(DISTINCT r.room_id) as assigned_room_ids
    FROM courses c
    INNER JOIN curriculum_courses cc ON c.id = cc.course_id
    LEFT JOIN curriculum_course_teachers cct ON cc.curriculum_id = cct.curriculum_id AND cc.course_id = cct.course_id
    LEFT JOIN teachers t ON cct.teacher_id = t.id
    LEFT JOIN rooms r ON FIND_IN_SET(r.room_id, cc.classroom_id) > 0
    WHERE cc.curriculum_id = ?
    GROUP BY c.id
");
$stmt->execute([$curriculum_id]);
$all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "จำนวน Courses: " . count($all_courses) . "<br>";
echo "<pre>";
print_r($all_courses);
echo "</pre>";

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Debug Copy Course</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        h2 { color: #333; border-bottom: 3px solid #0066cc; }
        h3 { color: #0066cc; margin-top: 20px; }
        h4 { color: #666; }
        pre { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; overflow-x: auto; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<h1>🔧 DEBUG: Copy Course Feature</h1>
<p><a href="javascript:history.back()">← กลับไป</a></p>

<hr>

<h2>✅ วิธีการใช้ Debug Page นี้</h2>
<pre>
1. URL ตัวอย่าง:
   debug_copy_course.php?curriculum_id=1&course_id=2

2. ข้อมูลที่แสดง:
   - ✅ DEBUG 1: ตรวจสอบความเป็นเจ้าของ Curriculum
   - ✅ DEBUG 2: ตรวจสอบความเป็นเจ้าของ Source Course
   - ✅ DEBUG 3: ตรวจสอบว่า Course มีใน Curriculum
   - ✅ DEBUG 4: ตรวจสอบ Database Table Structure
   - ✅ DEBUG 5: ดึงข้อมูล Raw จาก curriculum_courses
   - ✅ DEBUG 6: ตรวจสอบ curriculum_course_teachers
   - ✅ DEBUG 7: ตรวจสอบห้องเรียน
   - ✅ DEBUG 8: ทดสอบ Query เดิม
   - ✅ DEBUG 9: ตรวจสอบการแยก classroom_id
   - ✅ DEBUG 10: ตรวจสอบทั้งหมด Courses

3. ติดต่อการแก้ไข:
   - อ่านข้อความแดง (❌) = มีปัญหา
   - อ่านข้อความเหลือง (⚠️) = ข้อมูลอาจไม่สมบูรณ์
   - อ่านข้อความเขียว (✅) = ตกลง
</pre>

<hr>

</body>
</html>