<?php
session_start();

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบว่ามีการส่งค่า faculty มาหรือไม่
if (isset($_GET['faculty'])) {
    $faculty = $_GET['faculty'];
    $user_id = $_SESSION['user_id'];

    require_once 'config/db.php';

    // 1. ดึงข้อมูลชื่อและอีเมลของผู้ใช้
    $sql_fetch = "SELECT name, email FROM users WHERE id = ?";
    $stmt_fetch = $conn->prepare($sql_fetch);
    $stmt_fetch->execute([$user_id]);
    $user_info = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    $name = $user_info['name'];
    $email = $user_info['email'];

    // 2. เตรียมคำสั่ง SQL เพื่ออัปเดตข้อมูล group
    $sql_update = "UPDATE users SET `group` = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->execute([$faculty, $user_id]);

    // เพิ่มบรรทัดนี้เพื่ออัปเดต Session
    $_SESSION['user_group'] = $faculty;

    // 3. แสดงผลข้อมูลผู้ใช้และคณะที่เลือก
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลผู้ใช้</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #0f2027, #203a43, #2c5364);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 600px;
            padding: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="container text-center">
        <h2 class="mb-4">ข้อมูลผู้ใช้</h2>
        <div class="card p-4 bg-light text-dark">
            <p><strong>ชื่อ:</strong> <?= htmlspecialchars($name) ?></p>
            <p><strong>อีเมล:</strong> <?= htmlspecialchars($email) ?></p>
            <p><strong>คณะที่เลือก:</strong> <?= htmlspecialchars($faculty) ?></p>
        </div>
        <div class="mt-4">
            <a href="home.php" class="btn btn-secondary">ไปยังหน้าหลัก</a>
        </div>
    </div>
</body>
</html>
<?php
} else {
    echo "ไม่มีการระบุคณะที่เลือก";
    // ถ้าไม่มีการเลือกคณะ ให้ redirect กลับไปหน้าเลือกคณะ (สมมติว่ามีไฟล์นี้)
    header("Location: select_faculty.php");
    exit;
}
?>

