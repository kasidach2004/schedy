<?php
// fix_copy_course_display.php
// ไฟล์นี้ช่วยแก้ไขปัญหา "สำเนาแล้วข้อมูลไม่โชว์"

session_start();

if (!isset($_SESSION['user_id'])) {
    die("<p style='color:red;'>❌ กรุณา login ก่อน</p>");
}

require_once 'config/db.php';

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Fix Copy Course Display</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #dc3545; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; border: none; border-radius: 5px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        button:hover { opacity: 0.9; }
        pre { background: #f9f9f9; border: 1px solid #ddd; padding: 10px; overflow-x: auto; border-radius: 5px; }
        .alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f9f9f9; }
        .code { background: #f4f4f4; padding: 10px; border-left: 3px solid #007bff; margin: 10px 0; }
        input[type="number"] { padding: 8px; width: 200px; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔧 แก้ไขปัญหา: สำเนาแล้วข้อมูลไม่โชว์</h1>
    
    <div class="alert alert-info">
        <strong>ℹ️ วิธีใช้:</strong> เลือกฟังก์ชันแก้ไขข้างล่างตามปัญหาที่เจอ
    </div>

    <div class="section">
        <h2>🔍 ขั้นตอนที่ 1: ตรวจสอบไฟล์</h2>
        <p>ก่อนอื่น ต้องตรวจสอบว่าไฟล์ <code>copy_curriculum_course.php</code> อยู่ที่ถูกต้อง</p>
        
        <form method="GET">
            <input type="hidden" name="action" value="check_files">
            <button type="submit" class="btn-primary">✓ ตรวจสอบไฟล์</button>
        </form>

        <?php
        if ($action == 'check_files') {
            echo "<h3>📋 ผลการตรวจสอบ:</h3>";
            
            // ตรวจสอบไฟล์ที่สำคัญ
            $files_to_check = [
                'manage_curriculum.php' => 'ไฟล์จัดการหลักสูตร',
                'copy_curriculum_course.php' => 'ไฟล์ประมวลผลการสำเนา',
            ];
            
            foreach ($files_to_check as $file => $description) {
                $exists = file_exists($file);
                $status = $exists ? '✅ มีอยู่' : '❌ ไม่มี';
                echo "<p>$status - <strong>$file</strong> ($description)</p>";
                
                if ($exists) {
                    $size = filesize($file);
                    echo "<small>ขนาด: " . round($size / 1024, 2) . " KB</small><br>";
                }
            }
            
            // ตรวจสอบ form action ใน manage_curriculum.php
            echo "<h3>🔎 ตรวจสอบ Form Action:</h3>";
            $manage_content = file_get_contents('manage_curriculum.php');
            if (strpos($manage_content, 'copy_curriculum_course.php') !== false) {
                echo "<p style='color: green;'>✅ พบ copy_curriculum_course.php ในไฟล์</p>";
            } else {
                echo "<p style='color: red;'>❌ ไม่พบ copy_curriculum_course.php ในไฟล์</p>";
                echo "<p>⚠️ ต้องตรวจสอบว่า manage_curriculum.php ถูกอัปเดตแล้ว</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>🗄️ ขั้นตอนที่ 2: ตรวจสอบ Database</h2>
        <p>ดูว่าข้อมูลถูกบันทึกลงฐานข้อมูลหรือไม่</p>
        
        <form method="GET">
            <div>
                <label>Curriculum ID: </label>
                <input type="number" name="curriculum_id" placeholder="เช่น 1" required>
                
                <label style="margin-left: 20px;">Course ID: </label>
                <input type="number" name="course_id" placeholder="เช่น 2" required>
                
                <input type="hidden" name="action" value="check_database">
                <button type="submit" class="btn-primary">✓ ตรวจสอบ Database</button>
            </div>
        </form>

        <?php
        if ($action == 'check_database') {
            $curriculum_id = isset($_GET['curriculum_id']) ? (int)$_GET['curriculum_id'] : 0;
            $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
            
            if ($curriculum_id > 0 && $course_id > 0) {
                echo "<h3>📊 ผลการตรวจสอบ Database:</h3>";
                
                // ตรวจสอบ curriculum_courses
                echo "<h4>1. ตรวจสอบตาราง curriculum_courses:</h4>";
                $stmt = $conn->prepare("SELECT * FROM curriculum_courses WHERE curriculum_id = ? AND course_id = ?");
                $stmt->execute([$curriculum_id, $course_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo "<p style='color: green;'>✅ พบข้อมูล</p>";
                    echo "<pre>";
                    print_r($data);
                    echo "</pre>";
                } else {
                    echo "<p style='color: red;'>❌ ไม่พบข้อมูล</p>";
                    echo "<p>⚠️ <strong>นี่คือสาเหตุที่ข้อมูลไม่โชว์</strong> - ข้อมูลไม่ถูกบันทึก</p>";
                    echo "<a href='#fix_insert'><strong>→ ไปแก้ไข INSERT Issue</strong></a>";
                }
                
                // ตรวจสอบ curriculum_course_teachers
                echo "<h4>2. ตรวจสอบตาราง curriculum_course_teachers:</h4>";
                $stmt = $conn->prepare("SELECT * FROM curriculum_course_teachers WHERE curriculum_id = ? AND course_id = ?");
                $stmt->execute([$curriculum_id, $course_id]);
                $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($teachers) > 0) {
                    echo "<p style='color: green;'>✅ พบอาจารย์ " . count($teachers) . " คน</p>";
                    echo "<table>";
                    echo "<tr><th>Teacher ID</th><th>Created At</th></tr>";
                    foreach ($teachers as $t) {
                        echo "<tr><td>" . $t['teacher_id'] . "</td><td>" . $t['created_at'] . "</td></tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p style='color: orange;'>⚠️ ไม่พบอาจารย์</p>";
                }
                
                // ดึง Query จาก manage_curriculum.php ทดสอบ
                echo "<h4>3. ทดสอบ Query จาก manage_curriculum.php:</h4>";
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
                $display = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($display) {
                    echo "<p style='color: green;'>✅ Query ได้ผลลัพธ์ - ข้อมูลควรโชว์</p>";
                    echo "<pre>";
                    print_r($display);
                    echo "</pre>";
                } else {
                    echo "<p style='color: red;'>❌ Query ไม่ได้ผลลัพธ์</p>";
                }
            }
        }
        ?>
    </div>

    <div class="section" id="fix_insert">
        <h2>🔨 ขั้นตอนที่ 3: แก้ไขปัญหา INSERT</h2>
        <p>หากข้อมูลไม่ถูกบันทึกลง Database</p>
        
        <h3>❌ ปัญหา: copy_curriculum_course.php ไม่ทำงาน</h3>
        
        <p><strong>สาเหตุที่เป็นไปได้:</strong></p>
        <ul>
            <li>❌ ไฟล์ไม่อยู่ในรูท directory</li>
            <li>❌ Form action URL ผิด</li>
            <li>❌ Database connection error</li>
            <li>❌ SQL query มีปัญหา</li>
        </ul>

        <h3>✅ วิธีแก้ไข:</h3>
        
        <div class="code">
            <strong>Step 1:</strong> ตรวจสอบไฟล์ copy_curriculum_course.php<br>
            <code>ls -la copy_curriculum_course.php</code>
        </div>

        <div class="code">
            <strong>Step 2:</strong> เพิ่ม Debug ลงในไฟล์<br>
            <code>
            // ที่บรรทัดแรกของ copy_curriculum_course.php<br>
            error_log("DEBUG: copy_curriculum_course.php started");<br>
            error_log("POST: " . print_r($_POST, true));
            </code>
        </div>

        <div class="code">
            <strong>Step 3:</strong> ดู Error Log<br>
            <code>tail -f /var/log/apache2/error.log</code>
        </div>

        <div class="code">
            <strong>Step 4:</strong> ทดสอบ Database Connection<br>
            <code>
            // เพิ่มลงในไฟล์ copy_curriculum_course.php<br>
            if (!$conn) { die("Database connection failed"); }
            </code>
        </div>

        <button class="btn-danger" onclick="showFixCode()">📋 ดูโค้ด Fix ฉบับเต็ม</button>
        
        <div id="fix_code" style="display:none; margin-top: 20px;">
            <h3>🔧 โค้ด Fix (วาง่ว่านี้ใน copy_curriculum_course.php):</h3>
            <pre><?php echo htmlspecialchars('<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("log_errors", 1);

session_start();

// DEBUG
error_log("START copy_curriculum_course.php");
error_log("POST: " . json_encode($_POST));
error_log("USER_ID: " . ($_SESSION["user_id"] ?? "NOT SET"));

if (!isset($_SESSION["user_id"])) {
    error_log("ERROR: No user_id in session");
    header("Location: login.php");
    exit;
}

require_once "config/db.php";

if (!$conn) {
    error_log("ERROR: Database connection failed");
    die("Database connection failed");
}

$user_id = $_SESSION["user_id"];
$curriculum_id = isset($_POST["curriculum_id"]) ? (int)$_POST["curriculum_id"] : 0;
$source_course_id = isset($_POST["source_course_id"]) ? (int)$_POST["source_course_id"] : 0;
$teacher_ids = isset($_POST["teacher_id"]) ? $_POST["teacher_id"] : [];
$classroom_ids = isset($_POST["classroom_id"]) ? $_POST["classroom_id"] : [];

error_log("curriculum_id: $curriculum_id");
error_log("source_course_id: $source_course_id");
error_log("teacher_ids: " . json_encode($teacher_ids));
error_log("classroom_ids: " . json_encode($classroom_ids));

// ตรวจสอบ Curriculum
$stmt = $conn->prepare("SELECT id FROM curriculums WHERE id = ? AND user_id = ?");
$stmt->execute([$curriculum_id, $user_id]);
if (!$stmt->fetch()) {
    error_log("ERROR: Curriculum not found");
    header("Location: manage_curriculum.php?id=$curriculum_id&status=error");
    exit;
}

// ตรวจสอบ Course
$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND user_id = ?");
$stmt->execute([$source_course_id, $user_id]);
if (!$stmt->fetch()) {
    error_log("ERROR: Course not found");
    header("Location: manage_curriculum.php?id=$curriculum_id&status=error");
    exit;
}

error_log("All checks passed. Starting transaction.");

try {
    $conn->beginTransaction();
    
    // ตรวจสอบ Course มีอยู่หรือไม่
    $stmt = $conn->prepare("SELECT COUNT(*) FROM curriculum_courses WHERE curriculum_id = ? AND course_id = ?");
    $stmt->execute([$curriculum_id, $source_course_id]);
    $count = $stmt->fetchColumn();
    
    error_log("Course exists count: $count");
    
    if ($count > 0) {
        error_log("Updating existing course");
        // ลบอาจารย์เก่า
        $stmt = $conn->prepare("DELETE FROM curriculum_course_teachers WHERE curriculum_id = ? AND course_id = ?");
        $stmt->execute([$curriculum_id, $source_course_id]);
        error_log("Deleted old teachers");
        
        // อัปเดต classroom_id
        $classroom_str = !empty($classroom_ids) ? implode(",", $classroom_ids) : NULL;
        $stmt = $conn->prepare("UPDATE curriculum_courses SET classroom_id = ? WHERE curriculum_id = ? AND course_id = ?");
        $stmt->execute([$classroom_str, $curriculum_id, $source_course_id]);
        error_log("Updated classroom_id: $classroom_str");
    } else {
        error_log("Inserting new course");
        // เพิ่มใหม่
        $classroom_str = !empty($classroom_ids) ? implode(",", $classroom_ids) : NULL;
        $stmt = $conn->prepare("INSERT INTO curriculum_courses (curriculum_id, course_id, classroom_id) VALUES (?, ?, ?)");
        $stmt->execute([$curriculum_id, $source_course_id, $classroom_str]);
        error_log("Inserted new course");
    }
    
    // เพิ่มอาจารย์
    if (!empty($teacher_ids)) {
        $stmt = $conn->prepare("INSERT INTO curriculum_course_teachers (curriculum_id, course_id, teacher_id) VALUES (?, ?, ?)");
        foreach ($teacher_ids as $teacher_id) {
            $teacher_id = (int)$teacher_id;
            $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$teacher_id, $user_id]);
            if ($check_stmt->fetch()) {
                $stmt->execute([$curriculum_id, $source_course_id, $teacher_id]);
                error_log("Inserted teacher: $teacher_id");
            }
        }
    }
    
    $conn->commit();
    error_log("Transaction committed successfully");
    
    header("Location: manage_curriculum.php?id=$curriculum_id&status=copy_success");
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("ERROR: " . $e->getMessage());
    header("Location: manage_curriculum.php?id=$curriculum_id&status=error");
    exit;
}
?>'); ?></pre>
        </div>
    </div>

    <div class="section">
        <h2>📺 ขั้นตอนที่ 4: ตรวจสอบ UI/Network</h2>
        <p>ใช้ Browser Developer Tools ตรวจสอบ</p>
        
        <div class="alert alert-info">
            <strong>1. เปิด F12 (Developer Tools)</strong><br>
            2. ไปที่ <code>Network</code> tab<br>
            3. ทำการสำเนาวิชา<br>
            4. ดูว่า request ไปถึง <code>copy_curriculum_course.php</code> หรือไม่<br>
            5. ดูว่า Response status code คือ 200 หรือไม่<br>
            6. ดูข้อมูลใน Response
        </div>

        <div class="alert alert-warning">
            <strong>ถ้าเห็น 404 Error:</strong><br>
            ค้นหาไฟล์ <code>copy_curriculum_course.php</code> ที่ถูกต้อง<br>
            หรือตรวจสอบ form action URL
        </div>

        <div class="alert alert-warning">
            <strong>ถ้าเห็น 500 Error:</strong><br>
            ตรวจสอบ error log ของ Server<br>
            ปกติจะเป็น PHP/Database error
        </div>
    </div>

    <div class="section">
        <h2>📋 Quick Fix Checklist</h2>
        
        <form method="POST" action="">
            <div style="line-height: 2;">
                <label><input type="checkbox"> ✅ ไฟล์ copy_curriculum_course.php อยู่ในรูท</label><br>
                <label><input type="checkbox"> ✅ form action = "copy_curriculum_course.php"</label><br>
                <label><input type="checkbox"> ✅ ค่า curriculum_id ส่งไปถูกต้อง</label><br>
                <label><input type="checkbox"> ✅ ค่า course_id ส่งไปถูกต้อง</label><br>
                <label><input type="checkbox"> ✅ Teacher/Room checkboxes มี name ถูกต้อง</label><br>
                <label><input type="checkbox"> ✅ Database connection ทำงาน</label><br>
                <label><input type="checkbox"> ✅ Error log ไม่เห็น PHP errors</label><br>
                <label><input type="checkbox"> ✅ Network tab เห็น 200 status</label><br>
                <label><input type="checkbox"> ✅ Database มี rows ใหม่</label><br>
                <label><input type="checkbox"> ✅ manage_curriculum.php แสดงข้อมูล</label><br>
            </div>
        </form>
    </div>

</div>

<script>
function showFixCode() {
    const elem = document.getElementById('fix_code');
    elem.style.display = elem.style.display === 'none' ? 'block' : 'none';
}
</script>

</body>
</html>