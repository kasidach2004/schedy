<?php
/**
 * ไฟล์ดำเนินการการตั้งค่าสิทธิ์
 * ใช้เพื่ออัปเดตฐานข้อมูลให้รองรับระบบสิทธิ์
 * 
 * วิธีใช้: เข้าไป http://localhost/class_schedule/setup_permissions.php
 */

session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินแล้วและเป็น Admin
if (!isset($_SESSION['user_id'])) {
    die("❌ กรุณาเข้าสู่ระบบก่อน");
}

require_once 'config/db.php';

// ตรวจสอบว่าเป็น Admin
$sql_check = "SELECT role FROM users WHERE id = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->execute([$_SESSION['user_id']]);
$user = $stmt_check->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'admin') {
    die("❌ เฉพาะ Admin เท่านั้นที่สามารถเข้าไป");
}

echo "<h2>🔧 ตั้งค่าระบบสิทธิ์</h2>";

// ===== ขั้นตอน 1: ตรวจสอบและเพิ่ม user_id ให้กับ teachers =====
echo "<h3>📋 ขั้นตอน 1: ตรวจสอบและเพิ่มคอลัมน์ user_id</h3>";

try {
    // ตรวจสอบว่าคอลัมน์ user_id มีอยู่หรือไม่
    $sql = "SHOW COLUMNS FROM teachers LIKE 'user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ คอลัมน์ user_id มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่มคอลัมน์ user_id...<br>";
        
        $sql_add = "ALTER TABLE `teachers` ADD COLUMN `user_id` INT(11) NULL DEFAULT NULL COMMENT 'ID ของผู้ใช้ที่สร้างอาจารย์' AFTER `id`";
        $conn->exec($sql_add);
        echo "✅ เพิ่มคอลัมน์ user_id สำเร็จ<br>";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 2: ตรวจสอบและเพิ่ม Foreign Key =====
echo "<h3>📋 ขั้นตอน 2: ตรวจสอบและเพิ่ม Foreign Key</h3>";

try {
    // ตรวจสอบว่า Foreign Key มีอยู่หรือไม่
    $sql_fk = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
               WHERE TABLE_NAME = 'teachers' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL";
    $stmt_fk = $conn->prepare($sql_fk);
    $stmt_fk->execute();
    
    if ($stmt_fk->rowCount() > 0) {
        echo "✅ Foreign Key มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่ม Foreign Key...<br>";
        
        try {
            $sql_add_fk = "ALTER TABLE `teachers` ADD CONSTRAINT `fk_teachers_users` 
                           FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) 
                           ON DELETE SET NULL ON UPDATE CASCADE";
            $conn->exec($sql_add_fk);
            echo "✅ เพิ่ม Foreign Key สำเร็จ<br>";
        } catch (Exception $e) {
            echo "ℹ️ Foreign Key อาจมีอยู่แล้วหรือเกิดข้อผิดพลาด: " . $e->getMessage() . "<br>";
        }
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 3: ตรวจสอบและเพิ่ม role ให้กับ users =====
echo "<h3>📋 ขั้นตอน 3: ตรวจสอบและเพิ่มคอลัมน์ role</h3>";

try {
    $sql = "SHOW COLUMNS FROM users LIKE 'role'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ คอลัมน์ role มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่มคอลัมน์ role...<br>";
        $sql_add_role = "ALTER TABLE `users` ADD COLUMN `role` VARCHAR(50) DEFAULT 'user' AFTER `password`";
        $conn->exec($sql_add_role);
        echo "✅ เพิ่มคอลัมน์ role สำเร็จ<br>";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 4: เพิ่ม user_id ให้กับ courses =====
echo "<h3>📋 ขั้นตอน 4: เพิ่ม user_id ให้กับ courses</h3>";

try {
    $sql = "SHOW COLUMNS FROM courses LIKE 'user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ คอลัมน์ user_id ของ courses มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่มคอลัมน์ user_id ให้ courses...<br>";
        $sql_add = "ALTER TABLE `courses` ADD COLUMN `user_id` INT(11) NULL DEFAULT NULL AFTER `id`";
        $conn->exec($sql_add);
        echo "✅ เพิ่มคอลัมน์ user_id สำเร็จ<br>";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 5: เพิ่ม user_id ให้กับ classrooms =====
echo "<h3>📋 ขั้นตอน 5: เพิ่ม user_id ให้กับ classrooms</h3>";

try {
    $sql = "SHOW COLUMNS FROM classrooms LIKE 'user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ คอลัมน์ user_id ของ classrooms มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่มคอลัมน์ user_id ให้ classrooms...<br>";
        $sql_add = "ALTER TABLE `classrooms` ADD COLUMN `user_id` INT(11) NULL DEFAULT NULL AFTER `id`";
        $conn->exec($sql_add);
        echo "✅ เพิ่มคอลัมน์ user_id สำเร็จ<br>";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 6: เพิ่ม user_id ให้กับ rooms =====
echo "<h3>📋 ขั้นตอน 6: เพิ่ม user_id ให้กับ rooms</h3>";

try {
    $sql = "SHOW COLUMNS FROM rooms LIKE 'user_id'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "✅ คอลัมน์ user_id ของ rooms มีอยู่แล้ว<br>";
    } else {
        echo "⏳ กำลังเพิ่มคอลัมน์ user_id ให้ rooms...<br>";
        $sql_add = "ALTER TABLE `rooms` ADD COLUMN `user_id` INT(11) NULL DEFAULT NULL AFTER `room_id`";
        $conn->exec($sql_add);
        echo "✅ เพิ่มคอลัมน์ user_id สำเร็จ<br>";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

// ===== ขั้นตอน 4: ตรวจสอบสรุป =====
echo "<h3>📊 สรุปการตั้งค่า</h3>";

try {
    // นับจำนวนครูต่อผู้ใช้
    $sql_count = "SELECT user_id, COUNT(*) as count FROM teachers GROUP BY user_id";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute();
    $results = $stmt_count->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>User ID</th><th>จำนวนครู</th></tr>";
    foreach ($results as $row) {
        $user_id_text = $row['user_id'] ?? 'NULL (ทั่วไป - ทุกคนเห็น)';
        echo "<tr><td>" . htmlspecialchars($user_id_text) . "</td><td>" . $row['count'] . "</td></tr>";
    }
    echo "</table>";
    
    echo "<br><strong>📌 หมายเหตุ:</strong><br>";
    echo "- ครูทั่วไป: เห็นเฉพาะข้อมูลของตัวเองเท่านั้น<br>";
    echo "- Admin: เห็นข้อมูลของทุกครู<br>";
    echo "- ข้อมูล NULL: ไม่มี (ระบบไม่สร้างข้อมูลทั่วไป)";
    
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "<br>";
}

echo "<h3>✅ การตั้งค่าเสร็จสิ้น</h3>";
echo "<p><a href='teachers.php'>กลับไปหน้าครู</a></p>";
?>
